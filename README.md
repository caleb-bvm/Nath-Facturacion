# Facturación: despliegue con Docker

Aplicación PHP 8.3 con Apache, MySQL 8.4 y Docker Compose v2. El mismo
servidor sirve la interfaz y la API; no se necesita XAMPP ni compilar JavaScript.

## Primer arranque

Desde la carpeta del proyecto, en un servidor Linux con Docker y Compose:

```sh
cp .env.example .env
chmod 600 .env
```

Edite `.env` y complete `FACTURACION_DB_PASS` y `MYSQL_ROOT_PASSWORD` con
contraseñas largas y distintas. Compose rechaza contraseñas vacías. No use
`root` como `FACTURACION_DB_USER`. Puede generar cada contraseña con
`openssl rand -hex 32`. No suba `.env` al repositorio.

```sh
docker compose config --quiet
docker compose up -d --build --wait
docker compose ps
```

En Windows, copie el archivo con `Copy-Item .env.example .env`.

La aplicación queda en `http://127.0.0.1:8080/`. Para entrar directamente
desde otro equipo, configure `APP_BIND_IP=0.0.0.0` y permita `APP_PORT` en
el firewall. Evite los puertos 5500 y 5501, que la interfaz reserva para Live Server.
MySQL permanece dentro de la red de Compose, sin publicar el puerto 3306.

Al iniciar un volumen vacío se crea el esquema completo a partir de
`database/schema.sql`, incluidas sus migraciones. Se omiten todos los datos
de demostración y las cuentas con contraseña `password`. No ejecute de nuevo
`schema.sql` sobre datos existentes: contiene instrucciones que eliminan tablas.

## Crear el administrador

```sh
docker compose exec app php database/crear-admin.php supremo "Administrador" 1990-01-01
```

Guarde la contraseña aleatoria que muestra la consola. El administrador puede
entrar de lunes a sábado, entre las 08:00 y las 18:00, según la zona
`America/El_Salvador` que usa el proyecto. Dentro del panel registre empleados,
sucursales, productos y clientes. El comando de creación se ejecuta una sola
vez por usuario; repetirlo con el mismo nombre falla por usuario duplicado.

## Dominio y HTTPS

Para producción, coloque un proxy con certificado HTTPS delante de Apache.
Si el proxy corre en el mismo servidor, conserve `APP_BIND_IP=127.0.0.1` y
redirija el dominio a `http://127.0.0.1:8080`. Si el proxy está en otro
contenedor, use una red compartida y el destino `app:80`, ajustando su Compose.
Sirva la aplicación en la raíz del dominio y preserve la cabecera `Host`.

Configure `FACTURACION_COOKIE_SECURE=1` en `.env` cuando acceda por HTTPS y
aplique el cambio con `docker compose up -d`. Así las cookies de sesión se
marcan como seguras incluso cuando el proxy termina TLS. Con este valor, el
inicio de sesión requiere acceder mediante HTTPS. La configuración entregada
usa HTTP local para permitir la comprobación inicial; no incluye certificados.

## Correo

Complete las variables SMTP de `.env` si necesita enviar facturas y alertas.
Después de cambiarlas, ejecute `docker compose up -d`. En el panel del
administrador configure el destinatario y pruebe el envío. Consulte
[CORREO.md](CORREO.md) para los detalles. Las credenciales se pasan al
contenedor en tiempo de ejecución y no se incorporan a la imagen.

## Operación y actualización

```sh
docker compose logs --tail=100 app db
curl --fail http://127.0.0.1:8080/api/health.php
docker compose up -d --build --wait
docker compose down
```

El endpoint de salud comprueba Apache, PHP y acceso al esquema de MySQL;
responde 200 con `{"status":"ok"}` o 503 sin exponer credenciales. Ambos
servicios reinician automáticamente salvo que los detenga explícitamente.
Los registros rotan para limitar el espacio ocupado. Las sesiones y MySQL
se conservan en volúmenes; reconstruir la imagen no borra la información.
Evite `docker compose down -v`: elimina esos volúmenes y todos sus datos.

El esquema se importa **solo en el primer arranque con el volumen vacío**.
Las actualizaciones de código no aplican migraciones a una base existente;
revise y aplique únicamente las migraciones que le falten, después de un
respaldo. Cambiar el nombre de la base, el usuario o sus contraseñas en `.env`
no modifica los usuarios de un volumen ya inicializado: requiere cambios SQL
coordinados antes de recrear la aplicación.

## Respaldo y restauración (servidor Linux)

Crear un respaldo consistente de las tablas InnoDB:

```sh
mkdir -p backups
chmod 700 backups
docker compose exec -T db sh -c 'MYSQL_PWD="$MYSQL_PASSWORD" exec mysqldump --no-tablespaces --single-transaction --skip-lock-tables -u "$MYSQL_USER" "$MYSQL_DATABASE"' > backups/facturacion.backup.sql
chmod 600 backups/facturacion.backup.sql
```

Compruebe el código de salida y conserve copias fuera del servidor. Para
restaurar ese respaldo en los servicios ya inicializados, detenga primero la
aplicación y luego importe el archivo. La restauración reemplaza las tablas
incluidas en el respaldo:

```sh
docker compose stop app
docker compose exec -T db sh -c 'MYSQL_PWD="$MYSQL_PASSWORD" exec mysql -u "$MYSQL_USER" "$MYSQL_DATABASE"' < backups/facturacion.backup.sql
docker compose start app
```

Para trasladar una instalación existente, respalde su base e impórtela mediante
este procedimiento. Confirme que contiene las columnas y tablas de
`database/actualizacion.sql` y `database/correo-configuracion.sql`; no aplique
dos veces las mismas instrucciones `ALTER TABLE`.

Las imágenes y su inicialización siguen las guías oficiales de
[PHP en Docker](https://docs.docker.com/guides/php/) y
[MySQL](https://hub.docker.com/_/mysql).
