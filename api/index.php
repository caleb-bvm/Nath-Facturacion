<?php
/* Bloque principal: api/index.php. Responsabilidades separadas por secciones. */

// API sencilla para consultar catálogos y guardar facturas.
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/seguridad.php';
require_once __DIR__ . '/panel.php';
require_once __DIR__ . '/factura-pdf.php';
require_once __DIR__ . '/calculos-factura.php';

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed = ['http://127.0.0.1:5500', 'http://localhost:5500'];
if (in_array($origin, $allowed, true)) {
    header("Access-Control-Allow-Origin: $origin");
}
header('Content-Type: application/json; charset=utf-8');
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
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) {
        responder(['success' => false, 'message' => 'Datos JSON inválidos.'], 400);
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
            $user = trim($data['usuario'] ?? '');
            $pass = $data['clave'] ?? '';
            if ($user === '' || $pass === '') {
                responder(['success' => false, 'message' => 'Usuario y contraseña obligatorios.'], 422);
            }
            $stmt = $pdo->prepare('SELECT * FROM vendedores WHERE usuario = ? LIMIT 1');
            $stmt->execute([$user]);
            $seller = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$seller || !(password_verify($pass, $seller['clave']) || (!str_starts_with($seller['clave'], '$') && hash_equals($seller['clave'], $pass)))) {
                registrarAlerta($pdo, $user, 'Credenciales incorrectas');
                responder(['success' => false, 'message' => 'Usuario o contraseña incorrectos.'], 401);
            }
            $motivo = motivoRestriccion($seller);
            if ($motivo !== '') {
                registrarAlerta($pdo, $user, $motivo);
                responder(['success'=>false,'message'=>$motivo],403);
            }
            if (password_needs_rehash($seller['clave'], PASSWORD_DEFAULT)) {
                $pdo->prepare('UPDATE vendedores SET clave=? WHERE id=?')->execute([password_hash($pass, PASSWORD_DEFAULT),$seller['id']]);
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
                INNER JOIN sucursales s ON f.sucursal_id = s.id' . ($sesion['rol'] === 'supremo' ? '' : ' WHERE f.vendedor_id = ' . (int)$sesion['id']) . ' ORDER BY f.id DESC');
            responder(['success' => true, 'facturas' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);

        case 'invoice':
            $id = (int)($_GET['id'] ?? 0);
            if ($id < 1) responder(['success' => false, 'message' => 'Factura inválida.'], 422);
            $stmt = $pdo->prepare('SELECT f.*, v.nombre AS vendedor_nombre, s.nombre AS sucursal_nombre
                FROM facturas f INNER JOIN vendedores v ON f.vendedor_id = v.id
                INNER JOIN sucursales s ON f.sucursal_id = s.id WHERE f.id = ?');
            $stmt->execute([$id]);
            $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$invoice) responder(['success' => false, 'message' => 'Factura no encontrada.'], 404);
            if ($sesion['rol'] !== 'supremo' && (int)$invoice['vendedor_id'] !== (int)$sesion['id']) responder(['success'=>false,'message'=>'Acceso denegado.'],403);
            $details = $pdo->prepare('SELECT * FROM factura_detalles WHERE factura_id = ? ORDER BY id');
            $details->execute([$id]);
            $invoice['detalles'] = $details->fetchAll(PDO::FETCH_ASSOC);
            responder(['success' => true, 'factura' => $invoice]);

        case 'products':
            responder(['success' => true, 'productos' => $pdo->query('SELECT * FROM productos ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC)]);

        case 'save_product':
        case 'product':
            requerirPost();
            requerirSupremo($sesion);
            $data = datosJson();
            $code = trim($data['codigo'] ?? '');
            $description = trim($data['descripcion'] ?? '');
            $price = (float)($data['precio'] ?? 0);
            if ($code === '' || $description === '' || strlen($description) > 160 || $price <= 0 ||
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
            $number = 'FAC-00000000'; /* El correlativo definitivo se asigna dentro de la transacción. */
            $date = date('Y-m-d');
            $sellerId = (int)$sesion['id'];
            $branchId = (int)($data['sucursal_id'] ?? 0);
            $clienteStmt = $pdo->prepare('SELECT * FROM clientes WHERE id=? AND activo=1');
            $clienteStmt->execute([(int)($data['cliente_id'] ?? 0)]);
            $clienteRegistro = $clienteStmt->fetch(PDO::FETCH_ASSOC);
            if (!$clienteRegistro) responder(['success'=>false,'message'=>'Seleccione un cliente registrado.'],422);
            $tipo = $data['tipo'] ?? 'consumidor';
            if (!in_array($tipo,['consumidor','credito'],true) || ($tipo === 'credito' && (!$clienteRegistro['nit'] || !$clienteRegistro['nrc']))) responder(['success'=>false,'message'=>'Crédito fiscal requiere NIT y NRC del cliente.'],422);
            $client = $clienteRegistro['nombre'];
            $phone = $clienteRegistro['telefono'];
            $details = $data['detalles'] ?? [];
            if (!$sellerId || !$branchId || $client === '' || strlen($client) > 160 ||
                !is_array($details) || empty($details) || count($details)>200) {
                responder(['success' => false, 'message' => 'Faltan datos obligatorios en la factura.'], 422);
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date > date('Y-m-d')) {
                responder(['success' => false, 'message' => 'La fecha no es válida o es futura.'], 422);
            }
            $branch = $pdo->prepare('SELECT id FROM sucursales WHERE id=? AND activo=1');
            $branch->execute([$branchId]);
            if (!$branch->fetchColumn()) responder(['success'=>false,'message'=>'Sucursal inválida.'],422);
            $pdo->beginTransaction();
            $pdo->query('SELECT id FROM sucursales ORDER BY id FOR UPDATE')->fetchAll();
            $ultimo = (int)$pdo->query('SELECT COALESCE(MAX(id),0) FROM facturas')->fetchColumn();
            $number = 'FAC-' . str_pad((string)($ultimo+1),8,'0',STR_PAD_LEFT);
            $totalProductos = 0;
            $cleanDetails = [];
            foreach ($details as $detail) {
                $productId = (int)($detail['producto_id'] ?? 0);
                $quantity = filter_var($detail['cantidad'] ?? 0, FILTER_VALIDATE_INT);
                $product = $pdo->prepare('SELECT * FROM productos WHERE id = ? AND activo = 1');
                $product->execute([$productId]);
                $product = $product->fetch(PDO::FETCH_ASSOC);
                if (!$product || $quantity < 1 || $quantity > 9999) {
                    throw new Exception('Producto o cantidad inválida.');
                }
                $lineTotal = round((float)$product['precio'] * $quantity, 2);
                $totalProductos += $lineTotal;
                $cleanDetails[] = [$product, $quantity, $lineTotal];
            }
            if ($totalProductos <= 0) throw new Exception('El total debe ser mayor que cero.');
            $importes = calcularImportesFactura($totalProductos);
            $subtotal = $importes['subtotal'];
            $iva = $importes['iva'];
            $total = $importes['total'];
            $commissionRate = $importes['comision_porcentaje'];
            $commission = $importes['comision'];
            $stmt = $pdo->prepare('INSERT INTO facturas
                (numero, fecha, vendedor_id, sucursal_id, cliente, telefono, subtotal, iva_porcentaje, iva, total, comision_porcentaje, comision)
                VALUES (?, ?, ?, ?, ?, ?, ?, 13, ?, ?, ?, ?)');
            $stmt->execute([$number, $date, $sellerId, $branchId, $client, $phone, $subtotal, $iva, $total, $commissionRate, $commission]);
            $invoiceId = $pdo->lastInsertId();
            $detailStmt = $pdo->prepare('INSERT INTO factura_detalles
                (factura_id, producto_id, codigo_producto, descripcion, cantidad, precio_unitario, total_linea)
                VALUES (?, ?, ?, ?, ?, ?, ?)');
            foreach ($cleanDetails as $row) {
                [$product, $quantity, $lineTotal] = $row;
                $detailStmt->execute([$invoiceId, $product['id'], $product['codigo'], $product['descripcion'], $quantity, $product['precio'], $lineTotal]);
            }
            $pdo->prepare('UPDATE facturas SET tipo=?,cliente_correo=?,cliente_nit=?,cliente_nrc=? WHERE id=?')->execute([$tipo,$clienteRegistro['correo'],$clienteRegistro['nit'],$clienteRegistro['nrc'],$invoiceId]);
            $pdf = pdfFactura($pdo,(int)$invoiceId);
            $correoId = encolarCorreo($pdo,$clienteRegistro['correo'],'Factura ' . $number,'Adjuntamos su factura ' . $number,$pdf,$number . '.pdf');
            $pdo->commit();
            $estado = enviarCorreo($pdo,$correoId);
            responder(['success'=>true,'id'=>$invoiceId,'numero'=>$number,'correo_estado'=>$estado,'message'=>'Factura guardada. Correo: ' . $estado]);

        default:
            responder(['success' => false, 'message' => 'Acción no válida.'], 404);
    }
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Facturación: ' . $error->getMessage());
    responder(['success' => false, 'message' => 'No se pudo completar la operación. Verifique los datos y la configuración.'], 500);
}
