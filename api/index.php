<?php
// API sencilla para consultar catálogos y guardar facturas.
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/seguridad.php';
require_once __DIR__ . '/panel.php';
require_once __DIR__ . '/factura-pdf.php';
require_once __DIR__ . '/calculos-factura.php';
require_once __DIR__ . '/validaciones.php';
require_once __DIR__ . '/facturas.php';

// La interfaz y la API se sirven desde el mismo origen.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function responder($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function datosJson() {
    if (strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/json') {
        responder(['success'=>false,'message'=>'Envíe los datos como JSON.'],415);
    }
    $contenido = file_get_contents('php://input');
    $data = json_decode($contenido, true);
    if (!is_array($data) || !str_starts_with(ltrim($contenido), '{')) {
        responder(['success' => false, 'message' => 'Datos JSON inválidos.'], 400);
    }
    foreach ($data as $campo => $valor) {
        if ($campo !== 'detalles' && (is_array($valor) || is_object($valor))) {
            throw new InvalidArgumentException('Campo inválido: ' . $campo);
        }
    }
    return $data;
}

function requerirPost() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        responder(['success' => false, 'message' => 'Método no permitido.'], 405);
    }
}

$action = $_GET['action'] ?? '';
$pdo = db();

try {
    if ($action !== 'login') {
        $sesion = usuarioAutorizado($pdo);
        // La autorización ya leyó la sesión; no bloquear otras consultas durante SQL o SMTP.
        // Logout conserva la sesión abierta para poder destruirla.
        if ($action !== 'logout') session_write_close();
        accionesPanel($action, $pdo, $sesion);
    }
    switch ($action) {
        case 'login':
            requerirPost();
            $data = datosJson();
            /* Limitar intentos por IP para evitar fuerza bruta y correos repetitivos. */
            $limite = $pdo->prepare('SELECT COUNT(*) FROM accesos WHERE ip=? AND fecha >= ?');
            $limite->execute([$_SERVER['REMOTE_ADDR'] ?? '', date('Y-m-d H:i:s',time()-900)]);
            if ((int)$limite->fetchColumn() >= 10) responder(['success'=>false,'message'=>'Demasiados intentos. Espere 15 minutos.'],429);
            $user = textoCampo($data, 'usuario', 50, true);
            $pass = $data['clave'] ?? '';
            if (!is_string($pass) || strlen($pass) > 4096) throw new InvalidArgumentException('Contraseña inválida.');
            if ($user === '' || $pass === '') {
                responder(['success' => false, 'message' => 'Usuario y contraseña obligatorios.'], 422);
            }
            $stmt = $pdo->prepare('SELECT * FROM vendedores WHERE usuario = ? LIMIT 1');
            $stmt->execute([$user]);
            $seller = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$seller || $pass !== $seller['clave']) {
                registrarAlerta($pdo, $user, 'Credenciales incorrectas');
                responder(['success' => false, 'message' => 'Usuario o contraseña incorrectos.'], 401);
            }
            $motivo = motivoRestriccion($seller);
            if ($motivo !== '') {
                registrarAlerta($pdo, $user, $motivo);
                responder(['success'=>false,'message'=>$motivo],403);
            }
            session_regenerate_id(true);
            $_SESSION['usuario_id'] = $seller['id'];
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
            unset($seller['clave']);
            responder(['success' => true, 'vendedor' => $seller, 'csrf' => $_SESSION['csrf']]);

        case 'catalogs':
            $products = $pdo->query('SELECT * FROM productos WHERE activo = 1 ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
            $branches = $pdo->query('SELECT * FROM sucursales WHERE activo = 1 ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
            $clients = $pdo->query('SELECT * FROM clientes WHERE activo = 1 ORDER BY nombre')->fetchAll(PDO::FETCH_ASSOC);
            responder(['success' => true, 'productos' => $products, 'sucursales' => $branches, 'clientes' => $clients]);

        case 'invoices':
            $stmt = $pdo->query('SELECT f.*, v.nombre AS vendedor_nombre, s.nombre AS sucursal_nombre
                FROM facturas f INNER JOIN vendedores v ON f.vendedor_id = v.id
                INNER JOIN sucursales s ON f.sucursal_id = s.id' . ($sesion['rol'] === 'admin' ? '' : ' WHERE f.vendedor_id = ' . (int)$sesion['id']) . ' ORDER BY f.id DESC');
            responder(['success' => true, 'facturas' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);

        case 'invoice':
            $id = idValido($_GET['id'] ?? 0);
            $stmt = $pdo->prepare('SELECT f.*, v.nombre AS vendedor_nombre, s.nombre AS sucursal_nombre
                FROM facturas f INNER JOIN vendedores v ON f.vendedor_id = v.id
                INNER JOIN sucursales s ON f.sucursal_id = s.id WHERE f.id = ?');
            $stmt->execute([$id]);
            $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$invoice) responder(['success' => false, 'message' => 'Factura no encontrada.'], 404);
            if ($sesion['rol'] !== 'admin' && (int)$invoice['vendedor_id'] !== (int)$sesion['id']) responder(['success'=>false,'message'=>'Acceso denegado.'],403);
            $details = $pdo->prepare('SELECT * FROM factura_detalles WHERE factura_id = ? ORDER BY id');
            $details->execute([$id]);
            $invoice['detalles'] = $details->fetchAll(PDO::FETCH_ASSOC);
            responder(['success' => true, 'factura' => $invoice]);

        case 'products':
            responder(['success' => true, 'productos' => $pdo->query('SELECT * FROM productos ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC)]);

        case 'save_product':
        case 'product':
            requerirPost();
            requerirAdmin($sesion);
            $data = datosJson();
            $code = textoCampo($data, 'codigo', 50, true);
            $description = textoCampo($data, 'descripcion', 160, true);
            $price = (float)($data['precio'] ?? 0);
            if (!preg_match('/^MIN-\d{4}$/', $code) || $price <= 0 || $price > 99999999.99 ||
                !preg_match('/^\d+(\.\d{1,2})?$/', (string)($data['precio'] ?? ''))) {
                responder(['success' => false, 'message' => 'Descripción, código y precio válido son obligatorios.'], 422);
            }
            $check = $pdo->prepare('SELECT id FROM productos WHERE codigo = ? LIMIT 1');
            $check->execute([$code]);
            if ($check->fetch()) responder(['success' => false, 'message' => 'El código de producto ya existe.'], 409);
            $stmt = $pdo->prepare('INSERT INTO productos (codigo, descripcion, precio) VALUES (?, ?, ?)');
            $stmt->execute([$code, $description, $price]);
            responder(['success' => true, 'id' => $pdo->lastInsertId(), 'message' => 'Producto guardado correctamente.']);

        case 'save_invoice':
        case 'invoice_save':
            requerirPost();
            $data = datosJson();
            responder(guardarFactura($pdo, $data, (int)$sesion['id']));

        default:
            responder(['success' => false, 'message' => 'Acción no válida.'], 404);
    }
} catch (InvalidArgumentException $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    responder(['success'=>false,'message'=>$error->getMessage()],422);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if ($error instanceof PDOException && $error->getCode() === '23000') {
        responder(['success'=>false,'message'=>'El registro ya existe o está relacionado con otros datos.'],409);
    }
    error_log('Facturación: ' . $error->getMessage());
    responder(['success' => false, 'message' => 'No se pudo completar la operación. Verifique los datos y la configuración.'], 500);
}
