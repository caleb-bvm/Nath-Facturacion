<?php
/* Resolver las direcciones guardadas en el panel admin.
 * La configuración local aporta valores iniciales; las credenciales del
 * transporte permanecen fuera de los formularios y del código JavaScript. */
function configuracionCorreo(PDO $pdo): array {
    $inicial = require __DIR__ . '/../config/correo.php';
    $guardada = $pdo->query('SELECT jefe,remitente FROM configuracion_correo WHERE id=1')->fetch(PDO::FETCH_ASSOC);
    /* Mostrar el problema de configuración sin revelar el contenido de la clave. */
    if (!filter_var($inicial['smtp_host'],FILTER_VALIDATE_DOMAIN,FILTER_FLAG_HOSTNAME)) {
        $estado = 'Configure un servidor SMTP válido.';
    } elseif (filter_var($inicial['smtp_puerto'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>65535]]) === false) {
        $estado = 'Configure un puerto SMTP válido (1 a 65535).';
    } elseif (!in_array($inicial['smtp_cifrado'],['ssl','tls'],true)) {
        $estado = 'Configure TLS implícito (ssl) o STARTTLS (tls) para el servidor SMTP.';
    } elseif (!in_array($inicial['smtp_auth'],['PLAIN','LOGIN'],true)) {
        $estado = 'Configure la autenticación SMTP como PLAIN o LOGIN.';
    } elseif (trim($inicial['smtp_usuario']) === '') {
        $estado = 'Falta configurar el usuario SMTP en .env.';
    } elseif ($inicial['smtp_clave'] === '') {
        $estado = 'Falta configurar la contraseña SMTP en .env.';
    } elseif (!filter_var($inicial['remitente'],FILTER_VALIDATE_EMAIL)) {
        $estado = 'Falta configurar una dirección de correo remitente válida.';
    } else {
        $estado = '';
    }
    /* Devolver solo campos públicos: jamás exponer la contraseña por la API. */
    return [
        'jefe' => $guardada['jefe'] ?? $inicial['jefe'],
        'remitente' => $inicial['remitente'],
        'smtp_preparado' => $estado === '',
        'smtp_mensaje' => $estado ?: 'SMTP configurado. Use el correo de prueba para comprobar el envío.',
    ];
}

/* Validar antes de guardar para evitar direcciones inválidas y saltos de línea. */
function guardarConfiguracionCorreo(PDO $pdo, array $datos, int $administrador): void {
    $jefe = trim((string)($datos['jefe'] ?? ''));
    if (strlen($jefe) > 254 || !filter_var($jefe,FILTER_VALIDATE_EMAIL)) {
        responder(['success'=>false,'message'=>'Ingrese un correo válido para recibir las alertas.'],422);
    }
    $transporte = require __DIR__ . '/../config/correo.php';
    /* La cuenta de envío permanece fija; el panel cambia solo el destinatario. */
    $remitente = $transporte['remitente'];
    $pdo->prepare('INSERT INTO configuracion_correo (id,jefe,remitente,actualizado_por) VALUES (1,?,?,?)
        ON DUPLICATE KEY UPDATE jefe=VALUES(jefe),remitente=VALUES(remitente),actualizado_por=VALUES(actualizado_por)')
        ->execute([$jefe,$remitente,$administrador]);
    /* Redirigir las alertas sin enviar; las facturas de clientes no se modifican. */
    $pdo->prepare("UPDATE correos SET destinatario=? WHERE asunto='Alerta de acceso al sistema'
        AND adjunto IS NULL AND estado IN ('pendiente','fallido')")->execute([$jefe]);
}
