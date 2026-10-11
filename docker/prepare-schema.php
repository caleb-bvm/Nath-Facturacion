<?php
// Generar SQL de instalación sin fijar la base ni copiar datos de demostración.
if (PHP_SAPI !== 'cli' || count($argv) !== 3) {
    exit(1);
}
$sql = file_get_contents($argv[1]);
if ($sql === false) {
    exit(1);
}
$sql = preg_replace('/^(?:CREATE DATABASE\b|USE\b)[^;]*;\s*/m', '', $sql);
$sql = preg_replace('/^INSERT INTO\b.*?;\s*/ms', '', $sql);
if ($sql === null || !str_contains($sql, 'CREATE TABLE') || file_put_contents($argv[2], $sql) === false) {
    exit(1);
}
