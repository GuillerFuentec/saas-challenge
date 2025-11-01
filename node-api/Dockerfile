FROM node:20-alpine AS base
WORKDIR /usr/src/app

# Copiamos lo minimo para que npm ci funcione y quede cacheado
COPY package*.json ./
COPY raccoonstudiosllc-scopekit-0.1.0.tgz ./raccoonstudiosllc-scopekit-0.1.0.tgz
COPY prisma ./prisma

# Instala sin dev (si necesitas dev en build, quita --omit=dev)
RUN npm ci --omit=dev

# Copiamos el codigo fuente
COPY src ./src
COPY docker-entrypoint.sh ./docker-entrypoint.sh

RUN chmod +x docker-entrypoint.sh

EXPOSE 3000
ENTRYPOINT ["./docker-entrypoint.sh"]
