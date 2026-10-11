# Pruebas de desarrollo

Esta carpeta no se necesita para instalar la aplicación en el servidor.

Sin base de datos:

```sh
php tests/logica.php
node tests/calculos.mjs
```

La comparación usa `php` del PATH; se puede indicar otra ruta con `PHP_BINARY`.

Para probar la API, usar una instancia **desechable** de MySQL/MariaDB local:

- `FACTURACION_DB_HOST=127.0.0.1`
- `FACTURACION_DB_PORT=33317`
- `FACTURACION_DB_NAME=facturacion_pruebas`
- `FACTURACION_DB_USER=root`, sin contraseña, únicamente en esa instancia temporal.
- Dejar las variables de correo sin configurar para no enviar mensajes reales.

`php tests/preparar-base.php` reinicia exclusivamente `facturacion_pruebas` y crea
las dos cuentas de prueba. Requiere que la variable del nombre tenga ese valor exacto.
Luego iniciar `php -S 127.0.0.1:8187 -t .` con esas mismas variables y ejecutar:

```sh
node tests/api.mjs
php tests/migracion-admin.php
```

La prueba de API crea sus catálogos desde cero; repetirla requiere preparar nuevamente
la base temporal. `FACTURACION_TEST_URL` permite indicar otra URL del servidor de prueba.
La prueba de migración se ejecuta después de la de API.
