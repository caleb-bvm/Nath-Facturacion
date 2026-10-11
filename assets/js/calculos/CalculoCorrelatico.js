/* Bloque de cálculo: CalculoCorrelatico. */
class CalculoCorrelativo extends Calculo {
    calcular(prefijo, ultimoNumero) {
        const siguiente = Number(ultimoNumero) + 1;
        return `${prefijo}-${String(siguiente).padStart(8, '0')}`;
    }
}