# 📘 Technical Challenge – ERP SaaS

## 1. Project Overview
We are building an **ERP SaaS** using **PHP**, **Node.js**, **MySQL**, **Prisma**, and **Docker**.  
The system is **multi-tenant**: each client has its own database and storage, and a central service is responsible for managing and providing credentials.

### 1.1 Objective
- Build a scalable, modular, and secure system.  
- Provide core ERP features (CRUD, authentication, product management).  
- Integrate with external APIs.  
- Use Docker to orchestrate all services.

---

## 2. Node.js Role – Client & Credential Manager
The **Node.js server** acts as the brain of the SaaS.  
Its responsibilities include:
- Storing and serving client credentials (database, storage, etc.).  
- Exposing an API to deliver client configuration to the PHP ERP (/cliente/:email) POST.  
- In production, ensuring tenant separation and secure handling of credentials.

### Example API Response
**Endpoint**: `POST /client/:email`  

```json
{
  "db": {
    "host": "mysql-client1",
    "user": "client1",
    "password": "123456",
    "database": "client1_db"
  },
  "storage": {
    "endpoint": "http://minio:9000",
    "accessKey": "client1",
    "secretKey": "client1_secret",
    "bucket": "client1-bucket"
  }
}
```

For this challenge, credentials can be mocked (JSON file or Prisma Model).

---

## 3. Challenge Requirements

**Note:** Both PHP and Node.js will access MySQL databases using **Prisma**.

### 3.1 Node.js API
- Use **Express.js**.  
- Must provide at least one endpoint:  
  - `POST /client/:email` → returns mocked client credentials.  
- Credentials must include DB and storage information (MinIO can be mocked).

### 3.2 PHP Backend (Simplified ERP)
- Must consume the Node.js API to fetch client credentials.  
- Use these credentials to connect to the correct **MySQL** database.  
- Required features:  
  - `POST /login` → simple authentication (fixed user/password).  
  - CRUD for **Colors** (`id`, `name`, `hexadecimal`).  
  - Display client data (colors) after fetching credentials from Node.js.

### 3.3 MySQL
- Must contain at least **2 client databases** (`client1_db`, `client2_db`).  
- Node.js must provide credentials for each database.

### 3.4 Docker
- All services must run in containers using `docker-compose`.  
- Required services:  
  - PHP + Apache/Nginx  
  - Node.js  
  - MySQL (with multiple client DBs)  
  - MinIO (optional, can be mocked)  
- Sensitive values must be stored in `.env` (not hardcoded).  
- Containers must communicate through Docker network.

---

## 4. Expected Workflow
1. PHP makes a request to Node.js → `/client/:email@email.com`.  
2. Node.js returns the credentials for that client.  
3. PHP connects to the correct MySQL database using the credentials.  
4. User logs in and interacts with the ERP (CRUD for Colors).  
5. PHP displays the list of colors for the selected client.  

---

## 5. Example Project Structure
```
project-root/
├── php-app/
│   ├── index.php
│   ├── core/
│   ├── Dockerfile
│   └── ...
├── node-api/
│   ├── server.js
│   ├── clients.json
│   ├── package.json
│   └── Dockerfile
├── mysql/
│   ├── init.sql
│   └── ...
├── docker-compose.yml
└── README.md
```

---

## 6. Evaluation Criteria
- **Functionality**: PHP connects to the correct DB using credentials from Node.js.  
- **Code organization**: clear, modular, and maintainable.  
- **Docker usage**: all services must run with `docker-compose up`.  
- **Documentation**: clear README with installation and usage steps.  
- **Security**: sensitive credentials must not be hardcoded.  

---

## 7. Deliverables
- Git repository (GitHub).  
- Instructions to run:  
  - `docker-compose up`  
  - Access credentials (username/password)  
  - Main endpoints.  

---

## 8. Bonus (Not Mandatory but Valued)
- Using **JWT** authentication between PHP ↔ Node.js.  
- Adding basic unit or integration tests.  

---

👉 This challenge simulates the **real architecture of our ERP SaaS**, where Node.js acts as the **credential orchestrator**.  
It tests your ability to handle **multi-tenant design, service integration, and containerized environments**.
