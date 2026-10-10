/* Bloque de cálculo: CalculoTotalFactura. */
/* Los precios ya son finales: sumar las líneas sin agregar otro IVA. */
class CalculoTotalFactura extends CalculoMonetario {
    calcular(items) {
        const calculoLinea = new CalculoTotalLinea();
        const total = items.reduce((suma, item) =>
            suma + calculoLinea.calcular(item.precio_unitario, item.cantidad), 0);
        return super.calcular(total);
    }
}
