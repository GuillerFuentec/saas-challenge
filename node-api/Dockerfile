FROM node:20-alpine AS base
WORKDIR /usr/src/app

COPY package*.json ./
COPY prisma ./prisma
RUN npm ci --omit=dev

COPY src ./src
COPY docker-entrypoint.sh ./docker-entrypoint.sh

RUN chmod +x docker-entrypoint.sh

EXPOSE 3000
ENTRYPOINT ["./docker-entrypoint.sh"]
