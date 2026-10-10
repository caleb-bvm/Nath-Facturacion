<?php
/* Resolver las direcciones guardadas en el panel supremo.
 * La configuración local aporta valores iniciales; las credenciales del
 * transporte permanecen fuera de los formularios y del código JavaScript. */
function configuracionCorreo(PDO $pdo): array {
    $inicial = require __DIR__ . '/../config/correo.php';
    $guardada = $pdo->query('SELECT jefe,remitente FROM configuracion_correo WHERE id=1')->fetch(PDO::FETCH_ASSOC);
    /* Mostrar el problema de configuración sin revelar el contenido de la clave. */
    if (!filter_var($inicial['smtp_usuario'],FILTER_VALIDATE_EMAIL)) {
        $estado = 'Falta configurar la dirección Gmail que enviará los mensajes.';
    } elseif ($inicial['smtp_clave'] === '') {
        $estado = 'Falta pegar la contraseña de aplicación de Gmail en el archivo privado.';
    } elseif (strlen($inicial['smtp_clave']) !== 16) {
        $estado = 'La contraseña de aplicación de Gmail debe tener 16 caracteres, sin contar los espacios. No use la contraseña habitual de Gmail.';
    } else {
        $estado = 'Gmail configurado. Use el correo de prueba para comprobar el envío.';
    }
    /* Devolver solo campos públicos: jamás exponer la contraseña por la API. */
    return [
        'jefe' => $guardada['jefe'] ?? $inicial['jefe'],
        'remitente' => !empty($guardada['remitente']) ? $guardada['remitente'] : $inicial['remitente'],
        'smtp_preparado' => filter_var($inicial['smtp_usuario'],FILTER_VALIDATE_EMAIL) !== false && strlen($inicial['smtp_clave']) === 16,
        'smtp_mensaje' => $estado,
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
