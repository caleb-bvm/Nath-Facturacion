<?php
require __DIR__ . '/../api/horarios.php';
require __DIR__ . '/../api/validaciones.php';
require __DIR__ . '/../api/calculos-factura.php';
date_default_timezone_set('America/El_Salvador');
$pruebas = 0;
function comprobar(bool $condicion, string $caso): void {
    global $pruebas;
    if (!$condicion) throw new RuntimeException($caso);
    $pruebas++;
}
$empleado = ['activo'=>1,'rol'=>'usuario','dias'=>'1','hora_inicio'=>'08:00:00','hora_fin'=>'18:00:00',
    'fecha_desde'=>'2026-10-12','fecha_hasta'=>'2026-10-12'];
function restriccion(array $usuario, string $fecha): string {
    return motivoRestriccion($usuario, new DateTimeImmutable($fecha));
}
comprobar(restriccion($empleado,'2026-10-12 08:00:00') === '', 'Inicio inclusivo');
comprobar(restriccion($empleado,'2026-10-12 18:00:00') !== '', 'Final exclusivo');
comprobar(restriccion($empleado,'2026-10-13 09:00:00') !== '', 'Fecha no autorizada');
$nocturno = array_replace($empleado,['hora_inicio'=>'22:00:00','hora_fin'=>'06:00:00']);
comprobar(restriccion($nocturno,'2026-10-13 05:59:59') === '', 'Último turno sigue hasta la madrugada');
comprobar(restriccion($nocturno,'2026-10-12 05:00:00') !== '', 'Madrugada previa al primer turno');
comprobar(restriccion($nocturno,'2026-10-13 06:00:00') !== '', 'Fin del turno nocturno');
$admin = array_replace($empleado,['rol'=>'admin']);
foreach (['2026-10-11 00:00:00','2026-12-25 23:59:59','2027-01-01 04:00:00'] as $fecha) {
    comprobar(restriccion($admin,$fecha) === '', 'Admin permanente: ' . $fecha);
}
comprobar(restriccion(array_replace($admin,['activo'=>0]),'2026-10-11') === 'Cuenta bloqueada', 'Bloqueo explícito');
comprobar(!fechaValida('2026-02-29') && fechaValida('2024-02-29'), 'Fechas bisiestas');
foreach ([0,-1,'1.5','2texto',[],true] as $id) {
    try { idValido($id); comprobar(false,'Identificador inválido aceptado'); }
    catch (InvalidArgumentException $e) { comprobar(true,'Identificador rechazado'); }
}
foreach ([1,2,9999,'2',2.0,'2.00'] as $cantidad) {
    comprobar(cantidadValida($cantidad) === (int)$cantidad, 'Cantidad entera aceptada');
}
foreach ([0,-1,1.5,'1.5',0.01,0.29,'2.75',1.000000001,0.001,1.234,9999.01,10000,[],true,null,'','abc',INF,NAN] as $cantidad) {
    try { cantidadValida($cantidad); comprobar(false,'Cantidad inválida aceptada'); }
    catch (InvalidArgumentException $e) { comprobar(true,'Cantidad rechazada'); }
}
foreach ([[79,1.5,11850],[0.01,1.5,2],[0.29,0.5,15],[19.99,0.29,580],[79,0.01,79]] as [$precio,$cantidad,$esperado]) {
    comprobar(calcularCentavosLinea($precio,$cantidad) === $esperado, 'Redondeo de línea decimal');
}
foreach ([0.01,0.29,79,113,1130,2260,99999999.99] as $total) {
    $f = calcularImportesFactura($total);
    comprobar(round($f['subtotal']+$f['iva'],2) === (float)$total, 'Base + IVA = total');
}
echo "$pruebas pruebas de lógica correctas.\n";
