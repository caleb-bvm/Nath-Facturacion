# Papelería Gráfica Minina

Proyecto académico de facturación con PHP 8.3, MySQL 8.4 y una interfaz en
HTML, CSS y JavaScript. Se instala con **Docker Compose**; no necesita XAMPP,
Node.js, Composer ni importar archivos SQL manualmente en el servidor.

## Funciones

- Facturas de consumidor final y crédito fiscal, con descarga en PDF y envío por correo.
- Resumen después de guardar: consulta los datos y totales, crea una nueva factura,
  vuelve al inicio o descarga el PDF cuando lo necesites, sin descarga automática.
- Consulta de detalles desde el historial y las ventas recientes dentro del sitio:
  cliente, productos, cantidades, precios, IVA, comisión y total.
- PDF A4 con la identidad visual de Minina, tabla de productos, totales destacados,
  encabezados y numeración de páginas. Admite textos largos y facturas de varias páginas.
- Registro y edición de clientes. Las facturas anteriores conservan los datos del cliente
  que tenían al emitirse. El crédito fiscal requiere NIT y NRC.
- Catálogos de productos y sucursales administrados desde el panel.
- Administración de usuarios, bloqueo de cuentas y horarios por días, horas y fechas.
- Historial de facturas: cada empleado consulta sus ventas; el administrador consulta todas.
- Registro de accesos rechazados, alertas por correo y reintentos de envío desde administración.

Los empleados pueden consultar su horario autorizado en el inicio. Los horarios usan
la zona `America/El_Salvador`; la hora de salida no está incluida y los turnos nocturnos
pertenecen al día en que comenzaron. Un administrador activo tiene acceso las 24 horas.
El desglose de edad se muestra únicamente en la información personal; no aparece
en la factura.

## Emitir y consultar una factura

1. En **Nueva factura**, selecciona la sucursal, el cliente, el tipo de documento
   y los productos con sus cantidades.
2. Pulsa **Guardar factura**. El sistema guarda la venta y abre su resumen dentro
   del sitio, con los datos e importes registrados por el servidor.
3. Elige **Nueva factura**, **Volver al inicio** o **Descargar PDF**.
   Si falla la carga del resumen, usa **Reintentar resumen**; la venta ya está guardada.

Para consultar una venta anterior, pulsa **Ver detalle** en las ventas recientes
o en el historial. La ventana muestra los datos de la factura y permite descargar
el PDF opcionalmente. Cada empleado conserva acceso únicamente a sus propias ventas.
El envío por correo mantiene el PDF adjunto, según la configuración del servicio.

## Cálculos de facturación

Los precios del catálogo **ya incluyen IVA del 13%**. El sistema suma los importes
de las líneas, obtiene la base sin IVA dividiendo el total entre `1.13` y calcula
el IVA por diferencia. Las cantidades admitidas son enteras, de 1 a 9999 por línea.
El servidor valida los productos y recalcula los importes con los precios del catálogo.

La comisión del vendedor se calcula sobre la base sin IVA y no aumenta el total del cliente:

| Base sin IVA | Comisión |
| --- | --- |
| Hasta $100.00 | 1% |
| Más de $100.00 y hasta $1,000.00 | 2% |
| Más de $1,000.00 y hasta $2,000.00 | 3% |
| Más de $2,000.00 | 4% |

Por ejemplo, una venta de **$113.00** tiene una base de **$100.00**, IVA de **$13.00**
y comisión de **$1.00**. Los importes se redondean a dos decimales.

## Instalar en un servidor nuevo

Requisitos: servidor con Docker y el comando `docker compose` disponibles.
Clona el repositorio desde GitHub en el servidor y entra en la carpeta del proyecto.

1. Copia la configuración:

   ```sh
   cp .env.example .env
   chmod 600 .env
   ```

   En Windows con PowerShell, usa `Copy-Item .env.example .env`.

2. Edita `.env`. Completa `FACTURACION_DB_PASS` y `MYSQL_ROOT_PASSWORD`
   con contraseñas distintas. Deja las opciones de correo vacías si aún no las usarás.

3. Inicia el sistema y crea tu administrador:

   ```sh
   docker compose up -d --build --wait
   docker compose exec app php database/crear-admin.php admin "Administrador" 1990-01-01
   ```

Abre **http://IP-DEL-SERVIDOR:8080**. Permite el puerto 8080 en el firewall de tu
servidor. Para probar en tu propia computadora usa http://localhost:8080.
Puedes cambiar el puerto mediante `APP_PORT` en `.env`.

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
Después de configurar SMTP, puedes reintentar los correos pendientes desde administración.

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
Las actualizaciones anteriores están descritas en la [guía de migraciones](database/migraciones/README.md).
Docker no ejecuta esas migraciones sobre tus datos existentes.

## Organización

- `api/`: permisos, horarios, facturas, cálculos y correo, separados por responsabilidad.
- `assets/` e `index.html`: interfaz y clases de cálculo para la materia.
- `config/`: lectura de las variables de entorno que Docker recibe de `.env`.
- `database/`: esquema limpio y comando para crear el administrador.
- `docker/`, `Dockerfile` y `compose.yaml`: ejecución en el servidor.

## Pruebas de desarrollo

Las pruebas se conservan en `tests/` y no se incluyen en la imagen de la aplicación.
Para ejecutar las pruebas de lógica y comparar los cálculos de JavaScript con PHP,
necesitas PHP y Node.js disponibles localmente:

```sh
php tests/logica.php
php tests/pdf.php
node tests/calculos.mjs
```

Las pruebas de API y migración necesitan una base desechable llamada
`facturacion_pruebas`. La preparación reinicia esa base; sigue la
[guía de pruebas](tests/README.md) para configurarla y ejecutar la suite completa.
La prueba de PDF genera ejemplos en `output/pdf/` para comprobar visualmente una
factura simple y otra de varias páginas. Estas exportaciones no se incluyen en Git.
