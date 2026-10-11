/* Bloque de cálculo: CalculoCorrelativo. */
// Genera códigos consecutivos para productos y facturas.
class CalculoCorrelativo extends Calculo {
    calcular(prefijo, ultimoNumero, digitos = 8) {
        const numero = Number(ultimoNumero);
        if (!prefijo || !Number.isInteger(numero) || numero < 0) {
            throw new Error('Datos inválidos para generar correlativo');
        }
        const siguiente = numero + 1;
        return `${prefijo}-${String(siguiente).padStart(digitos, '0')}`;
    }
}
