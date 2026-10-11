<?php
/* Los turnos nocturnos pertenecen a la fecha en que comenzaron.
 * El final del turno es exclusivo. Un admin activo no tiene horario. */
function motivoRestriccion(array $usuario, ?DateTimeImmutable $ahora = null): string {
    if (!(int)$usuario['activo']) return 'Cuenta bloqueada';
    if ($usuario['rol'] === 'admin') return '';
    $ahora = $ahora ?? new DateTimeImmutable();
    $hora = $ahora->format('H:i:s');
    $inicio = $usuario['hora_inicio'];
    $fin = $usuario['hora_fin'];
    $dia = $inicio > $fin && $hora < $fin ? $ahora->modify('-1 day') : $ahora;
    $fecha = $dia->format('Y-m-d');
    if ((!empty($usuario['fecha_desde']) && $fecha < $usuario['fecha_desde']) ||
        (!empty($usuario['fecha_hasta']) && $fecha > $usuario['fecha_hasta'])) return 'Fecha fuera del período autorizado';
    if (!in_array($dia->format('N'), explode(',', $usuario['dias']), true)) return 'Día no autorizado';
    $permitido = $inicio < $fin ? $hora >= $inicio && $hora < $fin
        : ($inicio > $fin && ($hora >= $inicio || $hora < $fin));
    return $permitido ? '' : 'Hora fuera del horario autorizado';
}
