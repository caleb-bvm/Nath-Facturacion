/* Bloque de cálculo: CalculoSubtotal. */
/* Base sin IVA: restar el IVA desglosado del total final de los artículos. */
class CalculoSubtotal extends Calculo {
    calcular(total, iva) {
        return Number((Number(total) - Number(iva)).toFixed(2));
    }
}
