# TecControl

Sistema web orientado al registro y seguimiento de órdenes de reparación.

## Requisitos

- Docker Desktop con motor de contenedores Linux y Docker Compose. Los comandos de ejemplo son para PowerShell desde la raíz del proyecto.
- Git para control de versiones y MySQL Workbench si se requiere una conexión gráfica a la base local.
- Puertos locales `8080` (HTTP), `8443` (HTTPS) y `3307` (MySQL). Los tres se asignan únicamente a `127.0.0.1` y se pueden cambiar en `.env`.

## Configuración y arranque

1. Abre Docker Desktop y espera que el motor esté listo. `docker version` debe mostrar la sección `Server` y `docker compose version` debe indicar una versión instalada.
2. Si todavía no tienes `.env`, cópialo desde el ejemplo. Edita `DB_PASSWORD` y `DB_ROOT_PASSWORD` con dos contraseñas locales distintas. `DB_USER` es la cuenta de la aplicación y de Workbench.

   ```powershell
   Copy-Item .env.example .env
   ```

3. Valida la configuración e inicia los contenedores. MySQL crea `DB_NAME` y ejecuta el [esquema provisional](database/01_esquema_provisional.sql) al inicializar el volumen por primera vez. Apache arranca con un certificado local para `localhost`.

   ```powershell
   docker compose config --quiet
   docker compose up -d --build --wait
   docker compose ps
   ```

4. Exporta únicamente el certificado público de la autoridad local para verificar HTTPS. La conexión HTTP redirige al puerto HTTPS configurado. Ajusta ambas URL si cambiaste los puertos.

   ```powershell
   New-Item -ItemType Directory -Force .local | Out-Null
   docker compose cp web:/var/lib/teccontrol/tls/ca.crt ./.local/localhost-ca.crt
   curl.exe --head http://localhost:8080/
   curl.exe --cacert .local/localhost-ca.crt --ssl-revoke-best-effort https://localhost:8443/
   ```

   `--ssl-revoke-best-effort` permite a Schannel validar la CA local sin un servicio de revocación. Para usar un navegador en Windows, importa `localhost-ca.crt` en **Entidades de certificación raíz de confianza** del **Usuario actual** mediante `certmgr.msc`. Abre `https://localhost:8443/ingresar`. La clave privada de Apache queda en el volumen `tls_local`.

## Base de datos y datos de prueba

En el esquema provisional, el cliente del equipo y el equipo de la orden son vínculos fijos. La titularidad de una orden se obtiene mediante su equipo y el último evento del historial determina el estado vigente. La aplicación registra el estado inicial en la misma transacción que la orden. El [script original de ocho tablas](database/Jaime_Cariqueo_TecControl.sql) se conserva con sus bytes originales.

La separación respeta la tercera forma normal con las dependencias asumidas. Los datos de contacto dependen del cliente, los detalles físicos del equipo y la falla de la orden. La orden referencia el equipo y el historial referencia el catálogo de estados y al usuario responsable. Las claves alternativas son el correo de cuenta, el folio de orden y el código de estado.

Para cargar una vez el [conjunto de datos ficticios](database/02_datos_prueba.sql), ejecuta:

```powershell
docker compose exec -T db sh -c 'MYSQL_PWD="$MYSQL_PASSWORD" mysql --protocol=TCP --host=127.0.0.1 --user="$MYSQL_USER" --database="$MYSQL_DATABASE" --default-character-set=utf8mb4 < /opt/teccontrol/datos-prueba.sql'
```

La carga usa una transacción y deja 20 órdenes, dos clientes y las cuentas ficticias de ambos perfiles. Una nueva ejecución sobre los mismos datos se rechaza por duplicados y la transacción se revierte.

| Perfil | Correo ficticio | Contraseña ficticia |
| --- | --- | --- |
| Administrador | `admin@teccontrol.example` | `DemoTec-Admin-2026!` |
| Cliente A | `cliente-a@teccontrol.example` | `DemoTec-Cliente-A-2026!` |
| Cliente B | `cliente-b@teccontrol.example` | `DemoTec-Cliente-B-2026!` |

Para Workbench crea una conexión **Standard (TCP/IP)** con host `127.0.0.1`, puerto `DB_LOCAL_PORT`, usuario `DB_USER`, contraseña `DB_PASSWORD` y esquema predeterminado `DB_NAME`. Prueba la conexión y consulta `SELECT VERSION();`.

## Ingreso y sesiones

En `/ingresar`, usa una cuenta ficticia de la tabla anterior. Los indicadores y listados se consultan en MySQL.

- **Administrador.** En `/administracion` revisa los indicadores y usa el menú lateral para registrar clientes y equipos. En **Órdenes**, crea una orden para un equipo y luego abre **Editar** para registrar el diagnóstico y cambiar el estado. **Usuarios** permite crear, editar, activar y desactivar cuentas.
- **Cliente.** En `/mis-ordenes` revisa los indicadores propios y filtra por estado o folio. Abre una orden para ver su detalle y su historial. El titular se obtiene de la cuenta activa en MySQL. Una orden ajena devuelve la misma página `404` que una inexistente.
- **Ambos perfiles.** En **Mi perfil** se editan el nombre y el correo. El cambio de contraseña solicita la actual y revoca las sesiones iniciadas con la clave anterior.

El identificador de sesión se regenera al ingresar y se invalida al cerrar sesión. La cookie usa `Secure`, `HttpOnly` y `SameSite=Lax`. Las operaciones POST validan el token CSRF en el servidor. Los límites predeterminados son 30 minutos de inactividad y 8 horas de duración total, configurables con `SESSION_IDLE_SECONDS` y `SESSION_MAX_SECONDS`.

## Pruebas de la base de datos

Después de cargar los datos ficticios, ejecuta el verificador de integración. Comprueba la versión real del servidor, las relaciones, los datos y los hashes. También provoca rechazos controlados de claves foráneas, duplicados, campos obligatorios y restricciones de titularidad. Las operaciones de verificación se revierten.

```powershell
[Console]::OutputEncoding = [System.Text.UTF8Encoding]::new($false)
docker compose exec -T web php tests/verificar-base.php
```

### Autenticación y permisos por HTTPS

Con las órdenes ficticias cargadas y el certificado de la CA exportado, usa Python 3.10 o superior para verificar el ingreso, la salida, las cookies, el aislamiento entre clientes y las respuestas del servidor.

```powershell
python tests/verificar-seguridad.py
```

Para comprobar también la expiración sin reducir los tiempos de la instancia principal, inicia un contenedor web local auxiliar. Espera a que responda `https://localhost:8444/ingresar`, ejecuta el verificador y detén el contenedor de prueba.

```powershell
docker compose run -d --no-deps --rm --name teccontrol-expiracion -p 127.0.0.1:8444:443 -e SESSION_IDLE_SECONDS=3 -e SESSION_MAX_SECONDS=8 web
python tests/verificar-seguridad.py --puerto-expiracion 8444
docker stop teccontrol-expiracion
```

Usa `--puerto`, `--puerto-http` o `--ca` si cambiaste los valores locales de los puertos o la ubicación del certificado.

### Flujos completos y navegador

El verificador funcional crea datos ficticios y cambia contraseñas de prueba. Usa un proyecto de Compose y un volumen nuevos para cada ejecución completa.

```powershell
$env:HTTP_PORT = '9080'
$env:HTTPS_PORT = '9443'
$env:DB_LOCAL_PORT = '0'
docker compose -p teccontrol_entrega up -d --build --wait
docker compose -p teccontrol_entrega exec -T db sh -c 'MYSQL_PWD="$MYSQL_PASSWORD" mysql --protocol=TCP --host=127.0.0.1 --user="$MYSQL_USER" --database="$MYSQL_DATABASE" --default-character-set=utf8mb4 < /opt/teccontrol/datos-prueba.sql'
New-Item -ItemType Directory -Force .local | Out-Null
docker compose -p teccontrol_entrega cp web:/var/lib/teccontrol/tls/ca.crt ./.local/entrega-ca.crt
python tests/verificar-entrega.py
```

Con Node.js y Playwright disponibles, ejecuta `node tests/verificar-navegador.cjs` para los flujos de Chrome y las capturas. Para instalar Playwright de forma local, usa `npm install --no-save --no-package-lock --prefix .local/browser playwright` y apunta `TEC_PLAYWRIGHT_PATH` a la ruta absoluta de `.local/browser/node_modules/playwright`. `TEC_CHROME` indica el ejecutable de Chrome y `TEC_BROWSER_PORT` cambia el puerto de prueba. El verificador HTTP contrasta el certificado con la CA exportada. El navegador automatizado usa un contexto sin persistencia para capturar la interfaz local. Al terminar, detén el proyecto aislado con `docker compose -p teccontrol_entrega down` y cierra esa sesión de PowerShell para volver a los puertos principales.

## Problemas frecuentes

| Síntoma | Comprobación |
| --- | --- |
| Docker no conecta al motor | Espera que Docker Desktop termine de arrancar y consulta `docker context ls`. En Windows, el contexto Linux suele ser `desktop-linux`. |
| Puerto ocupado | Cambia el puerto correspondiente en `.env` y vuelve a ejecutar `docker compose up -d --build --wait`. Actualiza la URL o conexión de Workbench. |
| MySQL espera o rechaza la contraseña | Ejecuta `docker compose logs db`. El usuario y las contraseñas del volumen se establecen en su primera inicialización. Recupera las credenciales locales usadas o cámbialas dentro de MySQL antes de actualizar `.env`. |
| El navegador indica una autoridad desconocida | Vuelve a exportar `ca.crt`, verifica con `curl.exe --cacert` e importa el certificado público en el almacén del usuario actual. Accede por el nombre `localhost`. |
| Se cambió el SQL con un volumen existente | El directorio de inicialización se ejecuta al crear la base inicial. Aplica los cambios mediante una migración revisada y respalda los datos. `docker compose down` conserva los volúmenes en su uso normal. |

### Referencias técnicas

- Imágenes oficiales de los servicios. [PHP](https://hub.docker.com/_/php) · [MySQL](https://hub.docker.com/_/mysql)
- Arranque de servicios y condiciones de disponibilidad. [Docker Compose](https://docs.docker.com/compose/how-tos/startup-order/)
- Configuración TLS y claves foráneas. [Apache HTTP Server](https://httpd.apache.org/docs/current/ssl/ssl_howto.html) · [curl para Windows](https://curl.se/docs/manpage.html#--ssl-revoke-best-effort) · [MySQL 8.4](https://dev.mysql.com/doc/refman/8.4/en/create-table-foreign-keys.html)
- Configuración de cookies y gestión de sesiones. [Manual de PHP](https://www.php.net/manual/en/session.security.ini.php)

