/* Bloque de cálculo: CalculoIVA. */
/* Extraer el IVA: base = total / (1 + porcentaje / 100).
 * Redondear la base primero conserva base + IVA = total a dos decimales. */
class CalculoIVA extends CalculoMonetario {
    calcular(total, porcentaje = 13) {
        const totalFinal = super.calcular(total);
        const base = super.calcular(totalFinal / (1 + Number(porcentaje) / 100));
        return super.calcular(totalFinal - base);
    }
}
