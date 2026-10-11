<?php
/* Cola persistente: un fallo del correo no elimina la alerta ni la factura. */
function encolarCorreo(PDO $pdo, string $destino, string $asunto, string $cuerpo, ?string $pdf = null, ?string $nombre = null): int {
    $stmt = $pdo->prepare('INSERT INTO correos (destinatario, asunto, cuerpo, adjunto, nombre_adjunto) VALUES (?,?,?,?,?)');
    $stmt->execute([$destino, $asunto, $cuerpo, $pdf, $nombre]);
    return (int)$pdo->lastInsertId();
}
function enviarCorreo(PDO $pdo, int $id): string {
    $bloqueo = 'facturacion-correo-' . $id;
    $obtenido = false;
    try {
        $stmt = $pdo->prepare('SELECT GET_LOCK(?, 0)');
        $stmt->execute([$bloqueo]);
        $obtenido = (int)$stmt->fetchColumn() === 1;
        if (!$obtenido) return 'pendiente';
        return entregarCorreo($pdo, $id);
    } catch (Throwable $error) {
        // La factura ya está guardada: el transporte nunca debe convertirla en un error.
        error_log('No se pudo procesar el correo ' . $id . '.');
        return 'pendiente';
    } finally {
        if ($obtenido) {
            try { $pdo->prepare('SELECT RELEASE_LOCK(?)')->execute([$bloqueo]); }
            catch (Throwable $error) { error_log('No se pudo liberar el bloqueo de correo.'); }
        }
    }
}
function entregarCorreo(PDO $pdo, int $id): string {
    $config = configuracionCorreo($pdo);
    $transporte = require __DIR__ . '/../config/correo.php';
    $stmt = $pdo->prepare("SELECT * FROM correos WHERE id=?");
    $stmt->execute([$id]);
    $correo = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$correo) throw new InvalidArgumentException('Correo no encontrado.');
    if ($correo['estado'] === 'enviado') return 'enviado';
    /* Al reintentar una alerta, usar exclusivamente el destinatario vigente. */
    if ($correo['asunto'] === 'Alerta de acceso al sistema' && $correo['adjunto'] === null) {
        $correo['destinatario'] = $config['jefe'];
        $pdo->prepare('UPDATE correos SET destinatario=? WHERE id=?')->execute([$correo['destinatario'],$id]);
    }
    if (!filter_var($correo['destinatario'], FILTER_VALIDATE_EMAIL) || !filter_var($config['remitente'], FILTER_VALIDATE_EMAIL)) return 'pendiente';
    if (!$config['smtp_preparado']) return 'pendiente';

    /* Transporte SMTP autenticado con TLS y verificación de certificados. */
    require_once __DIR__ . '/../vendor/PHPMailer/src/Exception.php';
    require_once __DIR__ . '/../vendor/PHPMailer/src/PHPMailer.php';
    require_once __DIR__ . '/../vendor/PHPMailer/src/SMTP.php';
    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $transporte['smtp_host'];
        $mail->Port = (int)$transporte['smtp_puerto'];
        $mail->SMTPAuth = true;
        $mail->SMTPSecure = $transporte['smtp_cifrado'];
        $mail->AuthType = $transporte['smtp_auth'];
        $mail->Username = $transporte['smtp_usuario'];
        $mail->Password = $transporte['smtp_clave'];
        $mail->Timeout = 12;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom($config['remitente'],'Sistema de facturación');
        $mail->addAddress($correo['destinatario']);
        $mail->Subject = $correo['asunto'];
        $mail->Body = $correo['cuerpo'];
        if ($correo['adjunto'] !== null) {
            $nombre = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $correo['nombre_adjunto']);
            $mail->addStringAttachment($correo['adjunto'],$nombre,'base64','application/pdf');
        }
        $estado = $mail->send() ? 'enviado' : 'fallido';
    } catch (Throwable $error) {
        $estado = 'fallido';
        error_log('No se pudo enviar el correo de facturación ' . $id . '. Revise las credenciales y la conexión SMTP.');
    }
    $pdo->prepare('UPDATE correos SET estado=?, intentos=intentos+1 WHERE id=?')->execute([$estado, $id]);
    if ($correo['asunto'] === 'Alerta de acceso al sistema') {
        $pdo->prepare('UPDATE accesos SET correo_estado=? WHERE correo_id=?')->execute([$estado, $id]);
    }
    return $estado;
}
function registrarAlerta(PDO $pdo, string $usuario, string $motivo): void {
    $config = configuracionCorreo($pdo);
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'desconocida';
    $agente = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
    $fecha = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare('INSERT INTO accesos (usuario,motivo,ip,agente,fecha) VALUES (?,?,?,?,?)');
    $stmt->execute([substr($usuario,0,50), $motivo, $ip, $agente, $fecha]);
    $acceso = $pdo->lastInsertId();
    /* Incluir datos del empleado y las restricciones que originaron la alerta. */
    $consulta = $pdo->prepare('SELECT nombre,rol,dias,hora_inicio,hora_fin,fecha_desde,fecha_hasta FROM vendedores WHERE usuario=?');
    $consulta->execute([$usuario]);
    $empleado = $consulta->fetch(PDO::FETCH_ASSOC);
    $detalle = $empleado ? "\nNombre: {$empleado['nombre']}\nRol: {$empleado['rol']}\nDías autorizados (1=lunes, 7=domingo): {$empleado['dias']}\nHorario autorizado: {$empleado['hora_inicio']} a {$empleado['hora_fin']}\nFechas: " . ($empleado['fecha_desde'] ?: 'sin límite inicial') . ' a ' . ($empleado['fecha_hasta'] ?: 'sin límite final') : "\nUsuario no registrado";
    $id = encolarCorreo($pdo, $config['jefe'], 'Alerta de acceso al sistema', "Usuario: $usuario\nMotivo: $motivo\nFecha: $fecha (America/El_Salvador)\nIP: $ip\nNavegador: $agente\nAcción: " . ($_GET['action'] ?? '') . "\nResultado: acceso denegado" . $detalle);
    $pdo->prepare('UPDATE accesos SET correo_id=? WHERE id=?')->execute([$id, $acceso]);
    enviarCorreo($pdo, $id);
}
