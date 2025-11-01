require('dotenv').config();

const express = require('express');
const helmet = require('helmet');
const cors = require('cors');
const { PrismaClient } = require('@prisma/client');

const {
  adapters: { express: { principalMiddleware } },
  core: { isBypassed /*, applyScope */ },
} = require('@raccoonstudiosllc/scopekit');

const prisma = new PrismaClient();
const app = express();
const PORT = process.env.PORT || 3000;

app.use(helmet());
app.use(cors());
app.use(express.json());

// ScopeKit middleware builds req.principal from headers:
//   X-Entity-Id: numeric tenant id
//   X-Roles: comma separated roles (super-admin,authenticated)
//   X-Admin: true|false
app.use(principalMiddleware({
  extractor: (req) => ({
    entityId: Number(req.header('x-entity-id')) || null,
    roles: (req.header('x-roles') || '').split(',').map(s => s.trim()).filter(Boolean),
    isAdmin: String(req.header('x-admin')).toLowerCase() === 'true',
  }),
  superRoles: ['super-admin'],
}));

app.get('/', (_req, res) => {
  res.json({
    name: 'credential-orchestrator',
    status: 'online',
    healthy: '/health',
    credentialsEndpoint: '/client/:email'
  });
});

app.get('/health', async (_req, res, next) => {
  try {
    await prisma.$queryRaw`SELECT 1`;
    res.json({ status: 'ok' });
  } catch (error) {
    next(error);
  }
});

// Optional guard: enforce admin or super-admin when CREDENTIALS_REQUIRE_ADMIN=true
// Toggle the behaviour with the CREDENTIALS_REQUIRE_ADMIN env var (default: false)
app.post('/client/:email', async (req, res, next) => {
  try {
    if (process.env.CREDENTIALS_REQUIRE_ADMIN === 'true') {
      const canBypass = isBypassed({
        roles: req.principal?.roles || [],
        isAdmin: !!req.principal?.isAdmin,
        superRoles: ['super-admin'],
      });
      if (!canBypass) {
        return res.status(403).json({ message: 'Forbidden: admin only' });
      }
    }

    const email = decodeURIComponent(req.params.email).toLowerCase();

    // Current behaviour: look up credentials by email only (entityId pending)
    const client = await prisma.clientCredential.findUnique({ where: { email } });

    // Future behaviour: once entityId is available apply automatic scoping
    // const where = applyScope({
    //   principal: req.principal,
    //   filters: { email },          // Prisma where
    //   entityField: 'entityId',
    //   bypassRoles: ['super-admin'],
    //   isAdmin: req.principal?.isAdmin,
    // });
    // const client = await prisma.clientCredential.findFirst({ where });

    if (!client) {
      return res.status(404).json({ message: `Client with email ${email} was not found` });
    }

    res.json({
      tenant: { name: client.name, email: client.email },
      db: {
        host: client.dbHost,
        user: client.dbUser,
        password: client.dbPassword,
        database: client.dbName
      },
      storage: {
        endpoint: client.storageEndpoint,
        accessKey: client.storageAccessKey,
        secretKey: client.storageSecretKey,
        bucket: client.storageBucket
      }
    });
  } catch (error) {
    next(error);
  }
});

app.use((error, _req, res, _next) => {
  console.error(error);
  res.status(500).json({
    message: 'Unexpected server error',
    detail: process.env.NODE_ENV === 'production' ? undefined : error.message
  });
});

const server = app.listen(PORT, () => {
  console.log(`Credential service listening on port ${PORT}`);
});

const shutDown = () => {
  server.close(async () => {
    await prisma.$disconnect();
    process.exit(0);
  });
};
process.on('SIGTERM', shutDown);
process.on('SIGINT', shutDown);
