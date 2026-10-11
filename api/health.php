<?php
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 2]);
    $pdo->query('SELECT id FROM configuracion_correo LIMIT 1');
    $pdo->query('SELECT solicitud_id FROM facturas LIMIT 1');
    $pdo->query('SELECT correo_id FROM accesos LIMIT 1');
    echo json_encode(['status' => 'ok']);
} catch (Throwable $error) {
    http_response_code(503);
    echo json_encode(['status' => 'unavailable']);
}
