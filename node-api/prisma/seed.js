const { PrismaClient } = require('@prisma/client');

const prisma = new PrismaClient();

const clients = [
  {
    name: 'Client One',
    email: 'client1@example.com',
    dbHost: 'mysql',
    dbUser: 'client1_user',
    dbPassword: 'client1_pass',
    dbName: 'client1_db',
    storageEndpoint: 'http://minio:9000',
    storageAccessKey: 'client1_access',
    storageSecretKey: 'client1_secret',
    storageBucket: 'client1-bucket'
  },
  {
    name: 'Client Two',
    email: 'client2@example.com',
    dbHost: 'mysql',
    dbUser: 'client2_user',
    dbPassword: 'client2_pass',
    dbName: 'client2_db',
    storageEndpoint: 'http://minio:9000',
    storageAccessKey: 'client2_access',
    storageSecretKey: 'client2_secret',
    storageBucket: 'client2-bucket'
  }
];

async function main() {
  for (const client of clients) {
    await prisma.clientCredential.upsert({
      where: { email: client.email },
      create: client,
      update: client
    });
  }
  console.log('Client credentials synced');
}

main()
  .catch((error) => {
    console.error(error);
    process.exit(1);
  })
  .finally(async () => {
    await prisma.$disconnect();
  });
