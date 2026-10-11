/* Bloque de cálculo: CalculoTotalVenta. */
// Suma el subtotal y el IVA.
class CalculoTotalVenta extends Calculo {
    calcular(subtotal, iva) {
        return Number((subtotal + iva).toFixed(2));
    }
}