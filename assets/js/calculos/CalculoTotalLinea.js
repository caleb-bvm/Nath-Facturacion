/* Bloque de cálculo: CalculoTotalLinea. */
// Calcula el total de una línea de producto.
class CalculoTotalLinea extends CalculoMonetario {
    calcular(precio, cantidad) {
        return super.calcular(Number(precio) * Number(cantidad));
    }
}
