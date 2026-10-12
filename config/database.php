<?php
// Conexión a MySQL con las variables proporcionadas por Docker.
define('DB_HOST', getenv('FACTURACION_DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('FACTURACION_DB_PORT') ?: '3306');
define('DB_NAME', getenv('FACTURACION_DB_NAME') ?: 'facturacion_db');
define('DB_USER', getenv('FACTURACION_DB_USER') ?: 'root');
define('DB_PASS', getenv('FACTURACION_DB_PASS') ?: '');

function db() {
    try {
        $conexion = new PDO("mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
        $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $conexion;
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        http_response_code(503);
        error_log('Conexión de facturación: ' . $e->getMessage());
        echo json_encode(['success'=>false,'message'=>'No se pudo conectar a MySQL. Verifique que esté iniciado.']);
        exit();
    }
}
?>
