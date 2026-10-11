<?php
// Docker recibe estos valores del archivo .env. No hay configuración privada adicional.
$usuario = getenv('FACTURACION_SMTP_USUARIO') ?: '';
$puerto = getenv('FACTURACION_SMTP_PUERTO');
return [
    'jefe' => getenv('FACTURACION_CORREO_JEFE') ?: '',
    'remitente' => getenv('FACTURACION_CORREO_REMITENTE') ?: $usuario,
    'smtp_host' => getenv('FACTURACION_SMTP_HOST') ?: '',
    'smtp_puerto' => $puerto !== false ? $puerto : 465,
    'smtp_cifrado' => getenv('FACTURACION_SMTP_CIFRADO') ?: 'ssl',
    'smtp_auth' => strtoupper(getenv('FACTURACION_SMTP_AUTH') ?: 'LOGIN'),
    'smtp_usuario' => $usuario,
    'smtp_clave' => (string)getenv('FACTURACION_SMTP_CLAVE'),
];
