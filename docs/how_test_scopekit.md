# Cómo probar ScopeKit

Esta guía complementa el README del monorepo y explica, con el mismo nivel de detalle, cómo validar que ScopeKit esté aplicando el scope multi‑tenant en el servicio Node y que el ERP PHP respete ese aislamiento.

## Prerrequisitos

- Docker Desktop (o Docker Engine + Compose plugin) en ejecución.
- Archivo `.env` en la raíz del repositorio con los valores deseados (`MYSQL_PORT`, `NODE_API_PORT`, `PHP_HTTP_PORT`, `LOGIN_USERNAME`, `LOGIN_PASSWORD`).
- Imagenes locales construidas con `docker compose up --build` al menos una vez.

> Todos los comandos se ejecutan desde `Technical Challenge ERP SaaS/challenge/saas-challenge`.

## 1. Arrancar el stack

1. Levanta los contenedores (reconstruye si cambiaste configuraciones, como los puertos):

   ```powershell
   docker compose up --build
   ```

2. Espera a ver en los logs:
   - `Credential service listening on port 3000`
   - `Apache/2.4... (Unix) ... configured -- resuming normal operations`

3. Confirma que los servicios están arriba:

   ```powershell
   docker compose ps
   ```

   Debes ver `erp-node-api` y `erp-php` con los puertos publicados (`3000->3000`, `8080->80`, salvo que hayas cambiado el `.env`).

## 2. Validar la salud de los servicios

1. Node API:

   ```powershell
   Invoke-RestMethod http://localhost:3000/health
   ```

   Respuesta esperada:

   ```powershell
   status
   ------
   ok
   ```

2. ERP PHP (login con las credenciales configuradas en `.env`):

   ```powershell
   Invoke-RestMethod -Uri http://localhost:8080/login -Method POST `
     -Headers @{ "Content-Type" = "application/json" } `
     -Body '{"username":"isa_6464","password":"6464","clientEmail":"client1@example.com"}'
   ```

   Ajusta `username` y `password` si cambiaste los valores. Debes recibir `Authentication successful` con `tenant` y `storage`.

## 3. Probar el guardado de ScopeKit en Node

1. Exporta la variable para exigir rol admin/super‑admin (solo afecta al contenedor Node):

   ```powershell
   $env:CREDENTIALS_REQUIRE_ADMIN = "true"
   docker compose up --build node-api
   ```

   > Si prefieres editar el `docker-compose.yml`, añade la variable en `services.node-api.environment` y vuelve a levantar el stack completo.

2. Llama al endpoint sin cabeceras:

   ```powershell
   Invoke-RestMethod -Uri http://localhost:3000/client/client1@example.com -Method POST
   ```

   Resultado esperado: `403 Forbidden: admin only`.

3. Repite la petición con cabeceras de super admin:

   ```powershell
   $headers = @{
     "X-Entity-Id" = "1"
     "X-Roles"     = "super-admin,authenticated"
     "X-Admin"     = "true"
     "Content-Type" = "application/json"
   }

   Invoke-RestMethod -Uri http://localhost:3000/client/client1@example.com `
     -Method POST -Headers $headers -Body '{"requestedAt":"test"}'
   ```

   Deberías recibir el payload con `tenant`, `db` y `storage`. Esto demuestra que ScopeKit identifica las cabeceras y permite el bypass cuando la entidad tiene permisos.

## 4. Verificar el aislamiento por tenant en PHP

1. Lista los colores de `client1`:

   ```powershell
   Invoke-RestMethod "http://localhost:8080/colors?clientEmail=client1@example.com"
   ```

2. Lista los colores de `client2`:

   ```powershell
   Invoke-RestMethod "http://localhost:8080/colors?clientEmail=client2@example.com"
   ```

   Cada respuesta debe contener únicamente los registros de su base de datos correspondiente.

3. Inserta un color nuevo para `client1`:

   ```powershell
   Invoke-RestMethod -Uri http://localhost:8080/colors -Method POST `
     -Headers @{ "Content-Type" = "application/json" } `
     -Body '{"clientEmail":"client1@example.com","name":"Azure","hexadecimal":"#007FFF"}'
   ```

   Vuelve a llamar al listado del paso 1: verás el nuevo color solo en `client1`.

4. (Opcional) Inserta un registro para `client2` y asegúrate de que no aparece en `client1`.

## 5. Trazar el principal en los logs (opcional)

Durante el desarrollo, puedes registrar el principal generado por ScopeKit para depurar cabeceras:

```js
// node-api/src/server.js
app.use(principalMiddleware({...}));

if (process.env.NODE_ENV !== 'production') {
  app.use((req, _res, next) => {
    console.log('principal', req.principal);
    next();
  });
}
```

Reinicia el contenedor Node y observa los logs al enviar peticiones. Una vez que valides el flujo, elimina o comenta el middleware temporal.

## 6. Limpiar el entorno

Cuando termines las pruebas:

```powershell
docker compose down -v
```

Esto elimina contenedores y volúmenes para dejar el entorno limpio.

---

Con estos pasos verificas:

- Que ScopeKit exige cabeceras y roles correctos antes de servir credenciales.
- Que los servicios aguas abajo respetan el scope por entidad/tenant.
- Que las herramientas de apoyo (logs y pruebas manuales) te permiten auditar cualquier integración con ScopeKit.
