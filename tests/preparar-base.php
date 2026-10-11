<?php
/* Solo una base desechable con este nombre exacto. Nunca usar la base del usuario. */
if (PHP_SAPI !== 'cli' || getenv('FACTURACION_DB_NAME') !== 'facturacion_pruebas') exit("Configure facturacion_pruebas.\n");
$pdo = new PDO('mysql:host=127.0.0.1;port=' . (getenv('FACTURACION_DB_PORT') ?: '33317') . ';charset=utf8mb4', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('DROP DATABASE IF EXISTS facturacion_pruebas');
$pdo->exec('CREATE DATABASE facturacion_pruebas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$pdo->exec('USE facturacion_pruebas');
$pdo->exec(file_get_contents(__DIR__ . '/../database/schema.sql'));
$stmt = $pdo->prepare('INSERT INTO vendedores (nombre,usuario,clave,fecha_nacimiento,rol,dias,hora_inicio,hora_fin,fecha_hasta) VALUES (?,?,?,?,?,?,?,?,?)');
$clave = '1234';
$stmt->execute(['Administrador','admin_prueba',$clave,'1990-01-01','admin','1','08:00','09:00','2000-01-01']);
$stmt->execute(['Empleado','empleado_prueba',$clave,'1990-01-01','usuario','1,2,3,4,5,6,7','00:00','23:59:59',null]);
echo "Base desechable preparada.\n";
