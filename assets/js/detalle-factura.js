/* Consultar los datos emitidos, sin recalcular precios ni descargar archivos. */
let consultaDetalleFactura = 0;
const dialogoFactura = document.getElementById('detalle-factura');
let consultaResumenFactura = 0;

async function consultarFactura(id) {
    try {
        const respuesta = await BaseDatos.getInvoice(id);
        if (!respuesta || (respuesta.success && (!respuesta.factura || !Array.isArray(respuesta.factura.detalles)))) {
            throw new Error('Respuesta de factura inválida');
        }
        return respuesta;
    } catch (error) {
        console.error('No se pudo consultar la factura:', error);
        return {success: false, message: 'No se pudo cargar la factura. Recargue la página y vuelva a intentarlo.'};
    }
}

function cerrarResumenFactura() {
    consultaResumenFactura++;
    document.getElementById('sec-resumen-factura').hidden = true;
    const contenido = document.getElementById('resumen-factura-contenido');
    contenido.replaceChildren();
    contenido.setAttribute('aria-busy', 'false');
    const pdf = document.getElementById('resumen-factura-pdf');
    pdf.hidden = true;
    pdf.removeAttribute('href');
}

async function mostrarResumenFactura(id, mensaje = 'La factura se guardó correctamente.') {
    if (!vendedorActual) return;
    cerrarResumenFactura();
    cerrarDetalleFactura();
    const consulta = consultaResumenFactura;
    document.getElementById('view-dashboard').style.display = 'block';
    document.querySelectorAll('.modulo').forEach(elemento => elemento.hidden = true);
    ['sec-dashboard-menu', 'sec-nueva-factura', 'sec-historial', 'sec-productos'].forEach(seccion => {
        document.getElementById(seccion).style.display = 'none';
    });
    document.getElementById('sec-resumen-factura').hidden = false;
    marcarVistaMinina('ventas');
    const titulo = document.getElementById('resumen-factura-titulo');
    titulo.textContent = 'Resumen de factura';
    document.getElementById('resumen-factura-mensaje').textContent = mensaje;
    const pdf = document.getElementById('resumen-factura-pdf');
    pdf.href = `${API_URL}?action=pdf&id=${Number(id)}`;
    pdf.hidden = false;
    const contenido = document.getElementById('resumen-factura-contenido');
    contenido.textContent = 'Cargando resumen…';
    contenido.setAttribute('aria-busy', 'true');
    window.scrollTo(0, 0);
    titulo.focus({preventScroll: true});
    const res = await consultarFactura(id);
    if (consulta !== consultaResumenFactura || !vendedorActual) return;
    contenido.setAttribute('aria-busy', 'false');
    if (!res.success) {
        contenido.textContent = 'La factura ya está guardada. No se pudo cargar el resumen: ' + (res.message || 'Comprueba la conexión.');
        const boton = document.createElement('button');
        boton.type = 'button';
        boton.className = 'btn btn-gray';
        boton.textContent = 'Reintentar resumen';
        boton.addEventListener('click', () => mostrarResumenFactura(id, mensaje));
        contenido.append(document.createElement('br'), boton);
        return;
    }
    titulo.textContent = `Resumen · ${res.factura.numero}`;
    contenido.innerHTML = contenidoFacturaHtml(res.factura);
}

function limpiarDetalleFactura() {
    consultaDetalleFactura++;
    document.getElementById('detalle-factura-contenido').replaceChildren();
    document.getElementById('detalle-factura-contenido').setAttribute('aria-busy', 'false');
    const enlace = document.getElementById('detalle-factura-pdf');
    enlace.hidden = true;
    enlace.removeAttribute('href');
    document.getElementById('detalle-factura-titulo').textContent = 'Detalle de factura';
}

function cerrarDetalleFactura() {
    if (dialogoFactura.open) dialogoFactura.close();
    limpiarDetalleFactura();
}

dialogoFactura.addEventListener('close', () => {
    if (!dialogoFactura.open) limpiarDetalleFactura();
});
dialogoFactura.addEventListener('cancel', cerrarDetalleFactura);

async function verDetalleFactura(id) {
    if (!vendedorActual) return;
    limpiarDetalleFactura();
    const consulta = consultaDetalleFactura;
    const contenido = document.getElementById('detalle-factura-contenido');
    contenido.textContent = 'Cargando factura…';
    contenido.setAttribute('aria-busy', 'true');
    if (!dialogoFactura.open) dialogoFactura.showModal();
    const res = await consultarFactura(id);
    // Una respuesta anterior no debe reemplazar otra factura ni reabrir la ventana.
    if (consulta !== consultaDetalleFactura || !dialogoFactura.open || !vendedorActual) return;
    contenido.setAttribute('aria-busy', 'false');
    if (!res.success) {
        contenido.textContent = res.message || 'No se pudo cargar la factura.';
        const reintentar = document.createElement('button');
        reintentar.type = 'button';
        reintentar.className = 'btn btn-gray';
        reintentar.textContent = 'Reintentar';
        reintentar.addEventListener('click', () => verDetalleFactura(id));
        contenido.append(document.createElement('br'), reintentar);
        return;
    }
    const f = res.factura;
    document.getElementById('detalle-factura-titulo').textContent = f.numero;
    contenido.innerHTML = contenidoFacturaHtml(f);
    const enlace = document.getElementById('detalle-factura-pdf');
    enlace.href = `${API_URL}?action=pdf&id=${Number(f.id)}`;
    enlace.hidden = false;
}

function contenidoFacturaHtml(f) {
    const dato = valor => escaparHtml(valor || 'Sin registrar');
    return `
        <p class="invoice-detail-type">${f.tipo === 'credito' ? 'Comprobante de crédito fiscal' : 'Factura consumidor final'} · ${escaparHtml(fechaTexto(f.fecha))}</p>
        <div class="invoice-detail-grid">
            <section><h3>Cliente</h3><dl>
                <dt>Nombre</dt><dd>${dato(f.cliente)}</dd>
                <dt>Teléfono</dt><dd>${dato(f.telefono)}</dd>
                <dt>Correo</dt><dd>${dato(f.cliente_correo)}</dd>
                <dt>NIT</dt><dd>${dato(f.cliente_nit)}</dd>
                <dt>NRC</dt><dd>${dato(f.cliente_nrc)}</dd>
            </dl></section>
            <section><h3>Datos de la venta</h3><dl>
                <dt>Sucursal</dt><dd>${dato(f.sucursal_nombre)}</dd>
                <dt>Vendedor</dt><dd>${dato(f.vendedor_nombre)}</dd>
            </dl></section>
        </div>
        <h3>Productos · ${f.detalles.length}</h3>
        <div class="table-scroll" tabindex="0" role="region" aria-label="Productos de la factura"><table>
            <thead><tr><th scope="col">Código</th><th scope="col">Descripción</th><th scope="col" class="numeric">Cantidad</th><th scope="col" class="numeric">Precio</th><th scope="col" class="numeric">Total</th></tr></thead>
            <tbody>${f.detalles.map(d => `<tr><td>${escaparHtml(d.codigo_producto)}</td><td>${escaparHtml(d.descripcion)}</td><td class="numeric">${Number(d.cantidad)}</td><td class="numeric">${dineroMinina(d.precio_unitario)}</td><td class="numeric">${dineroMinina(d.total_linea)}</td></tr>`).join('') || '<tr><td colspan="5">Sin productos registrados.</td></tr>'}</tbody>
        </table></div>
        <div class="invoice-detail-totals">
            <p>Base sin IVA <strong>${dineroMinina(f.subtotal)}</strong></p>
            <p>IVA incluido (${Number(f.iva_porcentaje)}%) <strong>${dineroMinina(f.iva)}</strong></p>
            <p>Comisión (${Number(f.comision_porcentaje)}%) <strong>${dineroMinina(f.comision)}</strong></p>
            <p class="invoice-detail-total">Total a pagar <strong>${dineroMinina(f.total)}</strong></p>
        </div>`;
}
