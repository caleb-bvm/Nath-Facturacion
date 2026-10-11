/* Bloque de cálculo: CalculoComision. */
// Calcula la comisión según el subtotal.
class CalculoComision extends Calculo {
    calcular(subtotal) {
        let porcentaje = 1;
        if (subtotal > 2000) porcentaje = 4;
        else if (subtotal > 1000) porcentaje = 3;
        else if (subtotal > 100) porcentaje = 2;

        /* Trabajar desde los centavos evita diferencias en el redondeo de la comisión. */
        const centavos = Math.round(subtotal * 100);
        const monto = Math.round(centavos * porcentaje / 100) / 100;
        return { porcentaje, monto };
    }
}
