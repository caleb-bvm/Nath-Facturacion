/* Bloque de cálculo: CalculoFactura. */
/* Extraer el IVA incluido en el precio final.
 * Ejemplo: total $79, base $69.91 e IVA $9.09. */
class CalculoFactura extends Calculo {
    constructor() {
        super();
        this.subtotal = new CalculoSubtotal();
        this.iva = new CalculoIVA();
        this.total = new CalculoTotalFactura();
        this.comision = new CalculoComision();
    }

    calcular(items) {
        const total = this.total.calcular(items);
        const iva = this.iva.calcular(total);
        const subtotal = this.subtotal.calcular(total, iva);
        return {
            subtotal,
            iva,
            total,
            comision: this.comision.calcular(subtotal)
        };
    }
}
