# ScopeKit Integration Guide

The `@raccoonstudiosllc/scopekit` package centralises the tenant‑scoping logic used in this repository so that any Express or Knex based service can enforce multi‑tenant boundaries with the same primitives.

## Package layout

- `core` helpers: `applyScope`, `injectOnCreate`, `ensureOwnership`, `buildPrincipal`, `defaultExtractor`, and `isBypassed`.
- `adapters.express.principalMiddleware`: builds a `req.principal` from headers or any other extractor.
- `adapters.knex.applyScopeToKnex`: applies the entity restriction to a Knex query builder.

## Installation

If you are working from this repo you can install the bundled tarball directly:

```bash
npm install ./node-api/raccoonstudiosllc-scopekit-0.1.0.tgz
# or
pnpm add ./node-api/raccoonstudiosllc-scopekit-0.1.0.tgz
```

When the package is published to the npm registry simply use:

```bash
npm install @raccoonstudiosllc/scopekit
```

The library targets Node.js ≥ 18 and ships as CommonJS for compatibility with existing Express apps.

## Establishing the principal

Use the Express adapter to normalise the incoming headers (or any other auth payload) into a shared `principal` object.

```js
const express = require('express');
const { adapters: { express: { principalMiddleware } } } = require('@raccoonstudiosllc/scopekit');

const app = express();
app.use(express.json());

app.use(principalMiddleware({
  extractor: req => ({
    entityId: Number(req.header('x-entity-id')) || null,
    roles: (req.header('x-roles') || '').split(',').map(r => r.trim()).filter(Boolean),
    isAdmin: String(req.header('x-admin')).toLowerCase() === 'true',
  }),
  superRoles: ['super-admin'],          // bypass roles
  adminFlagPath: undefined,             // disable default Strapi-style admin detector
}));
```

Requests should include:

- `X-Entity-Id`: numeric tenant identifier.
- `X-Roles`: comma separated list of roles (e.g. `super-admin,authenticated`).
- `X-Admin`: optional flag that marks the principal as an administrator.

The middleware stores the parsed context in `req.principal`, so all downstream handlers can access it.

## Protecting sensitive routes

To require elevated roles for a route, use `core.isBypassed` (already available via the Express adapter). Example from `POST /client/:email`:

```js
const { core: { isBypassed } } = require('@raccoonstudiosllc/scopekit');

app.post('/client/:email', (req, res, next) => {
  const bypass = isBypassed({
    roles: req.principal?.roles || [],
    isAdmin: Boolean(req.principal?.isAdmin),
    superRoles: ['super-admin'],
  });
  if (!bypass) return res.status(403).json({ message: 'Forbidden: admin only' });
  // ...
});
```

Toggling the `CREDENTIALS_REQUIRE_ADMIN=true` environment variable in this project activates that guard.

## Scoping database queries

ScopeKit can inject the entity condition into ORM queries. With Prisma:

```js
const { core: { applyScope } } = require('@raccoonstudiosllc/scopekit');

const where = applyScope({
  principal: req.principal,
  filters: { status: 'active' },
  entityField: 'entityId',
  bypassRoles: ['super-admin'],
  isAdmin: req.principal?.isAdmin,
});

const rows = await prisma.project.findMany({ where });
```

With Knex use the dedicated adapter:

```js
const { adapters: { knex: { applyScopeToKnex } } } = require('@raccoonstudiosllc/scopekit');

const qb = knex('projects').select('*');
applyScopeToKnex(qb, {
  principal: req.principal,
  entityColumn: 'entity_id',
  bypassRoles: ['super-admin'],
});
const rows = await qb;
```

Both helpers throw a `NO_ENTITY` error when the request lacks an entity context and the user cannot bypass the restriction.

## Writing data safely

When creating or relating records, inject the entity automatically:

```js
const { core: { injectOnCreate } } = require('@raccoonstudiosllc/scopekit');

const data = injectOnCreate({
  principal: req.principal,
  data: req.body,
  entityField: 'entityId',
  relationType: 'belongsTo', // or 'manyToMany' / 'oneToMany'
});

await prisma.project.create({ data });
```

For update/read checks, `ensureOwnership` compares the current record with the principal:

```js
const { core: { ensureOwnership } } = require('@raccoonstudiosllc/scopekit');

const record = await prisma.project.findUnique({ where: { id } });
ensureOwnership({ principal: req.principal, record, entityField: 'entityId' });
```

## Testing and troubleshooting

- Log `req.principal` in development to verify headers are parsed correctly.
- Missing headers raise `NO_ENTITY`; supply `X-Entity-Id` or allow bypass roles.
- Combine with request recording (e.g. `morgan`, `winston`) so tenants are evident in logs.
- In automated tests, stub `principalMiddleware` by setting `req.principal` manually.

With these patterns in place you can drop ScopeKit into any service that needs to enforce tenant boundaries, while keeping the implementation consistent with this demo project.
