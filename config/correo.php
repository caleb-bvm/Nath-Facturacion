<?php
/* SMTP: leer las credenciales desde un archivo privado o
 * variables del servidor. El archivo privado queda fuera de htdocs. */
$rutaPrivada = getenv('FACTURACION_CORREO_PRIVADO') ?: dirname(__DIR__,3) . '/facturacion-privado/correo.php';
$privado = is_file($rutaPrivada) ? require $rutaPrivada : [];
$usuarioSMTP = getenv('FACTURACION_SMTP_USUARIO') ?: ($privado['usuario'] ?? '');
$claveEntorno = getenv('FACTURACION_SMTP_CLAVE');
$claveSMTP = $claveEntorno !== false ? $claveEntorno : ($privado['clave'] ?? '');
$puertoEntorno = getenv('FACTURACION_SMTP_PUERTO');
return [
    'jefe' => getenv('FACTURACION_CORREO_JEFE') ?: '',
    'remitente' => getenv('FACTURACION_CORREO_REMITENTE') ?: ($privado['remitente'] ?? $usuarioSMTP),
    'smtp_host' => getenv('FACTURACION_SMTP_HOST') ?: ($privado['host'] ?? 'mail.ourpxi.dev'),
    'smtp_puerto' => $puertoEntorno !== false ? $puertoEntorno : ($privado['puerto'] ?? 465),
    /* ssl en PHPMailer significa TLS implícito; tls significa STARTTLS. */
    'smtp_cifrado' => getenv('FACTURACION_SMTP_CIFRADO') ?: ($privado['cifrado'] ?? 'ssl'),
    'smtp_auth' => strtoupper(getenv('FACTURACION_SMTP_AUTH') ?: ($privado['auth'] ?? 'LOGIN')),
    'smtp_usuario' => $usuarioSMTP,
    'smtp_clave' => $claveSMTP,
];
