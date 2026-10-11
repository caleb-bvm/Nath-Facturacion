<?php
/* Errores de entrada se comunican como 422, nunca como fallo del servidor. */
function textoCampo(array $datos, string $campo, int $maximo, bool $obligatorio = false): string {
    $valor = $datos[$campo] ?? '';
    if (!is_string($valor) || strlen($valor) > $maximo || ($obligatorio && trim($valor) === '')) {
        throw new InvalidArgumentException('Revise el campo ' . $campo . '.');
    }
    return trim($valor);
}
function idValido($valor): int {
    if (!is_int($valor) && !is_string($valor)) throw new InvalidArgumentException('Identificador inválido.');
    $id = filter_var($valor, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
    if ($id === false) throw new InvalidArgumentException('Identificador inválido.');
    return $id;
}
function cantidadValida($valor): int {
    if ((!is_int($valor) && !is_float($valor) && !is_string($valor)) || !is_numeric($valor)) {
        throw new InvalidArgumentException('Cantidad inválida.');
    }
    $cantidad = (float)$valor;
    if (!is_finite($cantidad) || $cantidad < 1 || $cantidad > 9999 || floor($cantidad) !== $cantidad) {
        throw new InvalidArgumentException('Ingrese una cantidad entera entre 1 y 9,999. No se permiten unidades decimales.');
    }
    return (int)$cantidad;
}
function fechaValida(string $valor): bool {
    $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);
    return $fecha && $fecha->format('Y-m-d') === $valor;
}
