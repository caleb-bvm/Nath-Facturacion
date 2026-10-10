<?php
/* Gmail SMTP: leer la contraseña de aplicación desde un archivo privado o
 * variables del servidor. El archivo privado queda fuera de htdocs. */
$rutaPrivada = getenv('FACTURACION_CORREO_PRIVADO') ?: dirname(__DIR__,3) . '/facturacion-privado/correo.php';
$privado = is_file($rutaPrivada) ? require $rutaPrivada : [];
$usuarioSMTP = getenv('FACTURACION_SMTP_USUARIO') ?: ($privado['usuario'] ?? '');
$claveSMTP = getenv('FACTURACION_SMTP_CLAVE') ?: ($privado['clave_aplicacion'] ?? '');
return [
    'jefe' => getenv('FACTURACION_CORREO_JEFE') ?: '',
    'remitente' => $usuarioSMTP ?: (getenv('FACTURACION_CORREO_REMITENTE') ?: ''),
    'smtp_usuario' => $usuarioSMTP,
    'smtp_clave' => preg_replace('/\s+/', '', $claveSMTP),
];
