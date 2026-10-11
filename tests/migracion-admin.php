<?php
// Verifica la transición desde una cuenta antigua sin tocar la base de la tarea.
if (PHP_SAPI !== 'cli' || getenv('FACTURACION_DB_NAME') !== 'facturacion_pruebas') exit(1);
require __DIR__ . '/../config/database.php';
$pdo = db();
$pdo->exec("ALTER TABLE vendedores MODIFY rol ENUM('usuario','supremo','admin') NOT NULL DEFAULT 'usuario'");
$stmt = $pdo->prepare('INSERT INTO vendedores (nombre,usuario,clave,fecha_nacimiento,rol) VALUES (?,?,?,?,?)');
$hashAnterior = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
$stmt->execute(['Administrador anterior','supremo',$hashAnterior,'1990-01-01','supremo']);
$stmt->execute(['Empleado anterior','anterior_prueba',$hashAnterior,'1990-01-01','usuario']);
$pdo->exec(file_get_contents(__DIR__ . '/../database/migraciones/04-admin.sql'));
$admin = $pdo->query("SELECT rol,clave FROM vendedores WHERE usuario='admin'")->fetch(PDO::FETCH_ASSOC);
if ($admin !== ['rol'=>'admin','clave'=>'admin123']) throw new RuntimeException('Falló la migración del administrador.');
if ($pdo->query("SELECT clave FROM vendedores WHERE usuario='anterior_prueba'")->fetchColumn() !== 'usuario123') throw new RuntimeException('Falló la migración del empleado.');
if ($pdo->query("SELECT clave FROM vendedores WHERE usuario='simple_prueba'")->fetchColumn() !== 'hola') throw new RuntimeException('Se modificó una contraseña que ya era texto.');
echo "Migración verificada: rol, usuario, contraseñas antiguas y conservación del texto.\n";
