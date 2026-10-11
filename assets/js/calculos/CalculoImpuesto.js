/* Bloque de cálculo: CalculoImpuesto. */
// Calcula el IVA de la factura.
class CalculoImpuesto extends Calculo {
    calcular(subtotal, porcentaje = 13.00) {
        return Number((subtotal * (porcentaje / 100)).toFixed(2));
    }
}