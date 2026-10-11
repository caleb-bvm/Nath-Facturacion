<?php
/* Importes oficiales de la factura: el precio de catálogo es el precio final.
 * Extraer la base dividiendo el total entre 1.13; IVA = total - base.
 * La comisión se calcula sobre esa base y no modifica el total del cliente. */
function calcularCentavosLinea(float $precio, float $cantidad): int {
    $centavos = (int)round($precio * 100);
    $centesimas = (int)round($cantidad * 100);
    return intdiv($centavos * $centesimas + 50, 100);
}

function calcularImportesFactura(float $total): array {
    $total = round($total, 2);
    $subtotal = round($total / 1.13, 2);
    $iva = round($total - $subtotal, 2);
    $porcentaje = $subtotal > 2000 ? 4 : ($subtotal > 1000 ? 3 : ($subtotal > 100 ? 2 : 1));
    return [
        'total' => $total,
        'iva' => $iva,
        'subtotal' => $subtotal,
        'comision_porcentaje' => $porcentaje,
        'comision' => round($subtotal * $porcentaje / 100, 2),
    ];
}
