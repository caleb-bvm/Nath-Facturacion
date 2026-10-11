<?php
/* Alta inicial exclusivamente desde consola; no puede ejecutarse por HTTP.
 * Uso: php database/crear-admin.php usuario nombre fecha-nacimiento [contraseña]
 * Versión académica: contraseña en texto; por defecto admin123. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../api/validaciones.php';
date_default_timezone_set('America/El_Salvador');
$usuario = $argv[1] ?? 'admin';
$nombre = $argv[2] ?? 'Administrador';
$fecha = $argv[3] ?? '1990-01-01';
if (!preg_match('/^[a-zA-Z0-9_.-]{3,50}$/',$usuario)) exit("Usuario inválido.\n");
if (trim($nombre) === '' || strlen($nombre)>100 || !fechaValida($fecha) || $fecha>date('Y-m-d')) exit("Nombre o fecha inválidos.\n");
$clave = $argv[4] ?? 'admin123';
if (trim($clave) === '' || strlen($clave)>255) exit("Contraseña inválida.\n");
$pdo = db();
$stmt = $pdo->prepare('INSERT INTO vendedores (nombre,usuario,clave,fecha_nacimiento,rol) VALUES (?,?,?,?,?)');
$stmt->execute([$nombre,$usuario,$clave,$fecha,'admin']);
echo "Usuario: $usuario\nContraseña inicial: $clave\nAcceso permanente: todos los días, las 24 horas.\n";
