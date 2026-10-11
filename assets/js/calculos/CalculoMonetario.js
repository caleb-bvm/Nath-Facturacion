/* Bloque de cálculo: CalculoMonetario. */
// Redondea cualquier cantidad monetaria a dos decimales.
class CalculoMonetario extends Calculo {
    calcular(valor) {
        return Math.round((Number(valor) + Number.EPSILON) * 100) / 100;
    }
}
