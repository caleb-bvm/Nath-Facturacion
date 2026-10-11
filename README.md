# Facturación

Proyecto PHP + MySQL. Se instala con **Docker Compose**; no necesita XAMPP,
Node.js, Composer ni importar archivos SQL manualmente en el servidor.

## Instalar en un servidor nuevo

Requisitos: servidor con Docker y el comando `docker compose` disponibles.
Clona el repositorio desde GitHub en el servidor y entra en la carpeta del proyecto.

1. Copia la configuración:

   ```sh
   cp .env.example .env
   chmod 600 .env
   ```

2. Edita `.env`. Completa `FACTURACION_DB_PASS` y `MYSQL_ROOT_PASSWORD`
   con contraseñas distintas. Deja las opciones de correo vacías si aún no las usarás.

3. Inicia el sistema y crea tu administrador:

   ```sh
   docker compose up -d --build --wait
   docker compose exec app php database/crear-admin.php admin "Administrador" 1990-01-01
   ```

Abre **http://IP-DEL-SERVIDOR:8080**. Permite el puerto 8080 en el firewall de tu
servidor. Para probar en tu propia computadora usa http://localhost:8080.

Entra con **admin / admin123**. El administrador tiene acceso permanente.
Puedes indicar otra contraseña como cuarto argumento del comando de creación.
Las contraseñas de usuarios se guardan como texto, según la versión académica solicitada.

Registra una sucursal, productos y clientes desde el panel para comenzar a facturar.
La instalación arranca vacía: no agrega empleados ni ventas de demostración.

## Dominio y HTTPS

En el proxy de tu servidor, apunta el dominio a `http://127.0.0.1:8080` y activa
su certificado HTTPS. En `.env`, cambia `APP_BIND_IP=127.0.0.1` y
`FACTURACION_COOKIE_SECURE=1`. Aplica con `docker compose up -d`.
Si el proxy está en otro contenedor, usa una red compartida y el destino `app:80`.

## Correo (opcional)

Configura en `.env` el host, puerto, usuario, clave y remitente SMTP.
Usa `ssl` para el puerto 465 o `tls` para el 587, según tu proveedor.
Ejecuta `docker compose up -d`; luego entra en **Administración → Correo de alertas**,
guarda el destinatario y prueba el envío. Sin SMTP, las facturas se guardan y los
correos quedan pendientes. La biblioteca de correo ya viene incluida en `vendor/`.

## Comandos útiles

```sh
docker compose ps                         # Ver estado
docker compose logs --tail=50 app db      # Ver errores
docker compose up -d --build --wait       # Aplicar cambios de código
docker compose down                      # Detener, conservando datos
```

Los datos se guardan en volúmenes. **No uses `docker compose down -v`** si quieres
conservarlos. Cambiar contraseñas de MySQL en `.env` no cambia las de una base
ya inicializada. La configuración privada `.env` no se incluye en Git.

## Si ya tienes una base anterior

Conserva un respaldo. No importes `database/schema.sql`: es solo para una base vacía.
Las actualizaciones anteriores están separadas en `database/migraciones/README.md`.
Docker no ejecuta esas migraciones sobre tus datos existentes.

## Organización

- `api/`: permisos, horarios, facturas, cálculos y correo, separados por responsabilidad.
- `assets/` e `index.html`: interfaz y clases de cálculo para la materia.
- `config/`: lectura de las variables de entorno que Docker recibe de `.env`.
- `database/`: esquema limpio y comando para crear el administrador.
- `docker/`, `Dockerfile` y `compose.yaml`: ejecución en el servidor.

Las pruebas de desarrollo se conservan en el repositorio en `tests/`. No se incluyen
en la imagen de la aplicación.
