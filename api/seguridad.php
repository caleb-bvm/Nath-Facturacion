<?php
require_once __DIR__ . '/correo-configuracion.php';
/* Sesiones y protección de escrituras: la autorización siempre vive en PHP. */
date_default_timezone_set('America/El_Salvador');
session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict', 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
session_start();

/* Restricciones compartidas por empleados y administrador supremo.
 * El intervalo es [inicio, fin); los turnos nocturnos pertenecen al día de inicio. */
function motivoRestriccion(array $usuario, ?DateTimeImmutable $ahora = null): string {
    $ahora = $ahora ?? new DateTimeImmutable();
    if (!(int)$usuario['activo']) return 'Cuenta bloqueada';
    $fecha = $ahora->format('Y-m-d');
    if ((!empty($usuario['fecha_desde']) && $fecha < $usuario['fecha_desde']) ||
        (!empty($usuario['fecha_hasta']) && $fecha > $usuario['fecha_hasta'])) return 'Fecha fuera del período autorizado';
    $hora = $ahora->format('H:i:s');
    $inicio = $usuario['hora_inicio'];
    $fin = $usuario['hora_fin'];
    $dia = $ahora;
    if ($inicio < $fin) $permitido = $hora >= $inicio && $hora < $fin;
    else {
        $permitido = $inicio !== $fin && ($hora >= $inicio || $hora < $fin);
        if ($hora < $fin) $dia = $ahora->modify('-1 day');
    }
    if (!in_array($dia->format('N'), explode(',', $usuario['dias']), true)) return 'Día no autorizado';
    return $permitido ? '' : 'Hora fuera del horario autorizado';
}

/* Cola persistente: un fallo del correo no elimina la alerta ni la factura. */
function encolarCorreo(PDO $pdo, string $destino, string $asunto, string $cuerpo, ?string $pdf = null, ?string $nombre = null): int {
    $stmt = $pdo->prepare('INSERT INTO correos (destinatario, asunto, cuerpo, adjunto, nombre_adjunto) VALUES (?,?,?,?,?)');
    $stmt->execute([$destino, $asunto, $cuerpo, $pdf, $nombre]);
    return (int)$pdo->lastInsertId();
}
function enviarCorreo(PDO $pdo, int $id): string {
    $config = configuracionCorreo($pdo);
    $transporte = require __DIR__ . '/../config/correo.php';
    $stmt = $pdo->prepare("SELECT * FROM correos WHERE id=? AND estado <> 'enviado'");
    $stmt->execute([$id]);
    $correo = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$correo) return 'enviado';
    /* Al reintentar una alerta, usar exclusivamente el destinatario vigente. */
    if ($correo['asunto'] === 'Alerta de acceso al sistema' && $correo['adjunto'] === null) {
        $correo['destinatario'] = $config['jefe'];
        $pdo->prepare('UPDATE correos SET destinatario=? WHERE id=?')->execute([$correo['destinatario'],$id]);
    }
    if (!filter_var($correo['destinatario'], FILTER_VALIDATE_EMAIL) || !filter_var($config['remitente'], FILTER_VALIDATE_EMAIL)) return 'pendiente';
    if (!$config['smtp_preparado'] || strcasecmp($config['remitente'],$transporte['smtp_usuario']) !== 0) return 'pendiente';

    /* Transporte autenticado Gmail con STARTTLS y verificación de certificados. */
    require_once __DIR__ . '/../vendor/PHPMailer/src/Exception.php';
    require_once __DIR__ . '/../vendor/PHPMailer/src/PHPMailer.php';
    require_once __DIR__ . '/../vendor/PHPMailer/src/SMTP.php';
    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->Port = 587;
        $mail->SMTPAuth = true;
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Username = $transporte['smtp_usuario'];
        $mail->Password = $transporte['smtp_clave'];
        $mail->Timeout = 12;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom($transporte['smtp_usuario'],'Sistema de facturación');
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
        error_log('No se pudo enviar el correo de facturación ' . $id . '. Revise la cuenta Gmail y la conexión SMTP.');
    }
    $pdo->prepare('UPDATE correos SET estado=?, intentos=intentos+1 WHERE id=?')->execute([$estado, $id]);
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
    $estado = enviarCorreo($pdo, $id);
    $pdo->prepare('UPDATE accesos SET correo_estado=? WHERE id=?')->execute([$estado, $acceso]);
}
function usuarioAutorizado(PDO $pdo): array {
    $stmt = $pdo->prepare('SELECT * FROM vendedores WHERE id=?');
    $stmt->execute([$_SESSION['usuario_id'] ?? 0]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$usuario) responder(['success'=>false,'message'=>'Inicie sesión.'],401);
    $motivo = motivoRestriccion($usuario);
    if ($motivo !== '') {
        registrarAlerta($pdo, $usuario['usuario'], $motivo);
        $_SESSION = [];
        responder(['success'=>false,'message'=>$motivo],403);
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hash_equals($_SESSION['csrf'] ?? '', $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) responder(['success'=>false,'message'=>'Sesión de formulario inválida.'],403);
    return $usuario;
}
function requerirSupremo(array $usuario): void {
    if ($usuario['rol'] !== 'supremo') responder(['success'=>false,'message'=>'Esta operación requiere administrador supremo.'],403);
}
