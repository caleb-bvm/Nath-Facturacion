<?php
/* Alta inicial exclusivamente desde consola; no puede ejecutarse por HTTP.
 * Uso: php database/crear-admin.php usuario nombre fecha-nacimiento
 * La contraseña aleatoria se muestra una sola vez en la consola local. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/database.php';
$usuario = $argv[1] ?? 'supremo';
$nombre = $argv[2] ?? 'Administrador supremo';
$fecha = $argv[3] ?? '1990-01-01';
if (!preg_match('/^[a-zA-Z0-9_.-]{3,50}$/',$usuario)) exit("Usuario inválido.\n");
$clave = bin2hex(random_bytes(10));
$pdo = db();
$stmt = $pdo->prepare('INSERT INTO vendedores (nombre,usuario,clave,fecha_nacimiento,rol) VALUES (?,?,?,?,?)');
$stmt->execute([$nombre,$usuario,password_hash($clave,PASSWORD_DEFAULT),$fecha,'supremo']);
echo "Usuario: $usuario\nContraseña inicial: $clave\nHorario: lunes a sábado, 08:00 a 18:00.\n";
