# Configuración del correo

El sistema utiliza `mail.ourpxi.dev`, puerto **465**, con TLS implícito y
autenticación **LOGIN**. Puede utilizar **PLAIN** si el servidor lo requiere.
La verificación de certificados permanece activada.

Copie `config/correo.ejemplo.php` a una ubicación fuera del directorio público.
Complete `usuario`, `clave` y `remitente` en esa copia. Configure la variable
del servidor `FACTURACION_CORREO_PRIVADO` con su ruta absoluta. Si no se define,
se busca `facturacion-privado/correo.php` tres niveles por encima de la raíz
del proyecto. El archivo privado debe devolver el arreglo del ejemplo.

También puede configurar variables de entorno, que tienen prioridad:

| Variable | Valor |
| --- | --- |
| `FACTURACION_SMTP_HOST` | `mail.ourpxi.dev` |
| `FACTURACION_SMTP_PUERTO` | `465` |
| `FACTURACION_SMTP_CIFRADO` | `ssl` (TLS implícito); `tls` para STARTTLS |
| `FACTURACION_SMTP_AUTH` | `LOGIN` o `PLAIN` |
| `FACTURACION_SMTP_USUARIO` | Usuario de autenticación |
| `FACTURACION_SMTP_CLAVE` | Contraseña exacta, incluidos sus espacios |
| `FACTURACION_CORREO_REMITENTE` | Dirección de envío autorizada |

Si no se especifica remitente, se utiliza el usuario SMTP cuando es una
dirección de correo válida. El usuario SMTP puede ser distinto del remitente.
Las credenciales no se devuelven por la API ni se muestran en el panel.

Reinicie el servicio PHP/Apache después de cambiar las variables de entorno.
En el panel del administrador supremo, guarde el destinatario de alertas y
pulse **Enviar correo de prueba**. Compruebe su bandeja de entrada y spam.
Las facturas y alertas utilizan el mismo transporte; los mensajes que no se
envían permanecen en la cola para reintentarlos.
