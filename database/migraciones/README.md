# Solo para bases anteriores

Una instalación nueva no necesita ninguno de estos archivos.
El esquema completo está en `../schema.sql` y Docker lo carga automáticamente.

Para conservar una instalación antigua, respaldar la base y aplicar únicamente
los cambios que falten. No ejecutar todos los archivos a ciegas: los `ALTER TABLE`
no se deben repetir. Seleccionar la base correspondiente antes de importar.

| Archivo | Cuándo corresponde |
| --- | --- |
| `01-roles-y-correo.sql` | No existen el rol y horario en vendedores, ni las tablas de alertas y correo. |
| `02-configuracion-correo.sql` | Solo falta la tabla `configuracion_correo`. Ya está incluida en 01. |
| `03-facturas-y-alertas.sql` | Faltan `facturas.solicitud_id` y `accesos.correo_id`. |
| `04-admin.sql` | El rol anterior todavía se llama `supremo` o quedan contraseñas con hash. |
| `05-cantidades-decimales.sql` | `factura_detalles.cantidad` todavía es de tipo entero (`INT`). |

La migración 04 cambia los hashes por `admin123` para administradores y
`usuario123` para empleados. Conserva las contraseñas que ya estaban en texto.
También renombra la cuenta `supremo` a `admin` si el nombre está disponible.
