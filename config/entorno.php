<?php
/* Carga de configuración local: leer .env una sola vez sin reemplazar
 * variables que ya estén configuradas directamente en el servidor. */
function cargarEntorno(): void {
    static $cargado = false;
    if ($cargado) return;
    $cargado = true;
    $archivo = dirname(__DIR__) . '/.env';
    if (!is_readable($archivo)) return;

    /* Cada línea contiene NOMBRE=valor. Se admiten comentarios completos,
     * valores vacíos y comillas exteriores; no se ejecuta código del archivo. */
    foreach (file($archivo, FILE_IGNORE_NEW_LINES) as $linea) {
        $linea = trim($linea);
        if ($linea === '' || $linea[0] === '#') continue;
        if (!preg_match('/^([A-Z][A-Z0-9_]*)\s*=(.*)$/', $linea, $partes)) continue;
        $nombre = $partes[1];
        $valor = trim($partes[2]);
        if (strlen($valor) >= 2 && (($valor[0] === '"' && substr($valor, -1) === '"') || ($valor[0] === "'" && substr($valor, -1) === "'"))) {
            $valor = substr($valor, 1, -1);
        }
        if (getenv($nombre) === false) putenv($nombre . '=' . $valor);
    }
}
cargarEntorno();
