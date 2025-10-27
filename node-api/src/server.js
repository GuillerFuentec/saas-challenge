require('dotenv').config();

const express = require('express');
const helmet = require('helmet');
const cors = require('cors');
const { PrismaClient } = require('@prisma/client');

const prisma = new PrismaClient();
const app = express();
const PORT = process.env.PORT || 3000;

app.use(helmet());
app.use(cors());
app.use(express.json());

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

app.post('/client/:email', async (req, res, next) => {
  try {
    const email = decodeURIComponent(req.params.email).toLowerCase();
    const client = await prisma.clientCredential.findUnique({
      where: { email }
    });

    if (!client) {
      return res.status(404).json({
        message: `Client with email ${email} was not found`
      });
    }

    res.json({
      tenant: {
        name: client.name,
        email: client.email
      },
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
