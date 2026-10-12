# Datos actuales de Minina

`datos-minina.sql` conserva los datos de la instalación local: 24 productos
MIN-0001 a MIN-0024, los dos clientes indicados por el propietario, la sucursal
Minina y la cuenta admin. No contiene ventas del negocio anterior.

Los precios son de ejemplo. Los nombres y correos de los clientes son los
proporcionados por el propietario; teléfono, NIT y NRC son ficticios.
La dirección de la sucursal es Centro Histórico, Avenida España.

Para reproducir la instalación, seleccionar una base vacía, importar primero
`schema.sql` y después `datos-minina.sql`. No importar sobre una base con datos:
los identificadores se conservan y pueden entrar en conflicto.

La cuenta académica inicial es admin / admin123. El archivo contiene esa
contraseña en texto, conforme al funcionamiento actual del proyecto.
Esta carga es manual; no cambia la instalación vacía predeterminada de Docker.
