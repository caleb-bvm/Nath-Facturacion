/* Bloque de cálculo: CalculoTotalLinea. */
// Calcula el total de una línea de producto.
class CalculoTotalLinea extends CalculoMonetario {
    calcular(precio, cantidad) {
        const centavos = Math.round(Number(precio) * 100);
        const centesimas = Math.round(Number(cantidad) * 100);
        return Math.floor((centavos * centesimas + 50) / 100) / 100;
    }
}
