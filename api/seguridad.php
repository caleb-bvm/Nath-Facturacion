<?php
require_once __DIR__ . '/correo-configuracion.php';
/* Sesiones y protección de escrituras: la autorización siempre vive en PHP. */
date_default_timezone_set('America/El_Salvador');
session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict', 'secure' => getenv('FACTURACION_COOKIE_SECURE') === '1' || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')]);
session_start();

require_once __DIR__ . '/horarios.php';
require_once __DIR__ . '/correos.php';

function usuarioAutorizado(PDO $pdo): array {
    $stmt = $pdo->prepare('SELECT * FROM vendedores WHERE id=?');
    $stmt->execute([$_SESSION['usuario_id'] ?? 0]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$usuario) responder(['success'=>false,'message'=>'Inicie sesión.'],401);
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))) responder(['success'=>false,'message'=>'Sesión de formulario inválida.'],403);
    // Cerrar sesión siempre es posible, aunque el turno ya haya terminado.
    if (($_GET['action'] ?? '') === 'logout') return $usuario;
    $motivo = motivoRestriccion($usuario);
    if ($motivo !== '') {
        registrarAlerta($pdo, $usuario['usuario'], $motivo);
        $_SESSION = [];
        session_destroy();
        responder(['success'=>false,'message'=>$motivo,'sesion_finalizada'=>true],403);
    }
    return $usuario;
}
function requerirAdmin(array $usuario): void {
    if ($usuario['rol'] !== 'admin') responder(['success'=>false,'message'=>'Esta operación requiere administrador.'],403);
}
