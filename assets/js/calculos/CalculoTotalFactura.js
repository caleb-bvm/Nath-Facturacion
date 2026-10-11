/* Bloque de cálculo: CalculoTotalFactura. */
/* Los precios ya son finales: sumar las líneas sin agregar otro IVA. */
class CalculoTotalFactura extends CalculoMonetario {
    calcular(items) {
        const calculoLinea = new CalculoTotalLinea();
        const centavos = items.reduce((suma, item) =>
            suma + Math.round(calculoLinea.calcular(item.precio_unitario, item.cantidad) * 100), 0);
        return centavos / 100;
    }
}
