/* Navegación y herramientas de trabajo de Papelería Gráfica Minina. */
let vistaMinina = 'inicio';
const vistasMinina = { inicio: 'sec-dashboard-menu', factura: 'sec-nueva-factura', ventas: 'sec-historial', productos: 'sec-productos', clientes: 'sec-clientes', equipo: 'sec-admin', sucursales: 'sec-admin', correo: 'sec-admin', actividad: 'sec-admin' };

function marcarVistaMinina(vista) {
    vistaMinina = vista;
    document.querySelectorAll('[data-vista]').forEach(boton => {
        if (boton.dataset.vista === vista) boton.setAttribute('aria-current', 'page');
        else boton.removeAttribute('aria-current');
    });
}

async function navegarMinina(vista) {
    if (!vendedorActual || guardandoFactura) return;
    if (['equipo', 'sucursales', 'correo', 'actividad', 'productos'].includes(vista) && vendedorActual.rol !== 'admin') return;
    cerrarResumenFactura();
    document.querySelectorAll('.modulo').forEach(elemento => elemento.hidden = true);
    document.getElementById('view-dashboard').style.display = 'block';
    const acciones = { inicio: cancelarFactura, factura: mostrarSeccionNuevaFactura, ventas: mostrarHistorial, productos: mostrarProductos, clientes: mostrarClientes };
    if (['equipo', 'sucursales', 'correo', 'actividad'].includes(vista)) {
        mostrarPanelAdmin(vista);
        document.getElementById('titulo-gestion').textContent = {equipo:'Empleados', sucursales:'Sucursales', correo:'Correo', actividad:'Actividad'}[vista];
        await mostrarAdministracion();
        if (vista === 'sucursales') { await cargarCatalogos(); renderizarSucursales(); }
    } else {
        await acciones[vista]();
        if (vista === 'inicio') await actualizarInicio();
    }
    const seccion = document.getElementById(vistasMinina[vista]);
    if (!seccion.hidden && seccion.style.display !== 'none') {
        marcarVistaMinina(vista);
        const titulo = seccion.querySelector('h1, h2, .invoice-title');
        titulo.setAttribute('tabindex', '-1');
        titulo.focus({ preventScroll: true });
    }
}

function cambiarCantidad(index, campo) {
    const cantidad = Number(campo.value);
    if (!Number.isInteger(cantidad) || cantidad < 1 || cantidad > 9999) {
        campo.value = itemsFactura[index].cantidad;
        mostrarAviso('Usa una cantidad entera entre 1 y 9,999.');
        return;
    }
    itemsFactura[index].cantidad = cantidad;
    itemsFactura[index].total_linea = new CalculoTotalLinea().calcular(itemsFactura[index].precio_unitario, cantidad);
    renderizarItemsFactura();
}

function descartarBorrador() {
    if (guardandoFactura || !confirm('¿Descartar los productos de esta factura? Esta acción no se puede deshacer.')) return;
    itemsFactura = [];
    solicitudFactura = '';
    document.getElementById('busqueda-producto').value = '';
    cancelarFactura();
}

function filtrarTabla(campo, selector) {
    const texto = campo.value.toLocaleLowerCase('es').trim();
    const tabla = document.querySelector(selector);
    let visibles = 0;
    tabla.querySelectorAll('tbody tr').forEach(fila => {
        if (fila.querySelector('.empty-state')) { fila.hidden = false; return; }
        fila.hidden = !fila.textContent.toLocaleLowerCase('es').includes(texto);
        if (!fila.hidden) visibles++;
    });
    campo.closest('.list-toolbar').querySelector('[role="status"]').textContent = visibles ? `${visibles} registro${visibles === 1 ? '' : 's'}` : 'Sin coincidencias.';
}

function buscarProducto(texto) {
    const selector = document.getElementById('sel-producto');
    const productos = catalogoProductos.filter(p => `${p.codigo} ${p.descripcion}`.toLocaleLowerCase('es').includes(texto.toLocaleLowerCase('es').trim()));
    selector.replaceChildren();
    productos.forEach(p => {
        const opcion = new Option(`${p.codigo} · ${p.descripcion} ($${p.precio})`, p.id);
        Object.assign(opcion.dataset, { codigo: p.codigo, descripcion: p.descripcion, precio: p.precio });
        selector.add(opcion);
    });
    document.getElementById('txt-precio').value = selector.options[0]?.dataset.precio || '';
    if (!productos.length) selector.add(new Option('Sin productos que coincidan', ''));
}

function mostrarPanelAdmin(nombre) {
    document.querySelectorAll('[data-panel-admin]').forEach(panel => panel.hidden = panel.dataset.panelAdmin !== nombre);

}

document.querySelector('.brand').addEventListener('click', evento => { evento.preventDefault(); if (vendedorActual) navegarMinina('inicio'); });
window.addEventListener('beforeunload', evento => { if (itemsFactura.length) { evento.preventDefault(); evento.returnValue = ''; } });

// Mantener los filtros vigentes después de recargar los listados.
document.querySelectorAll('[data-tabla]').forEach(campo => {
    new MutationObserver(() => filtrarTabla(campo, campo.dataset.tabla)).observe(document.querySelector(`${campo.dataset.tabla} tbody`), { childList: true });
});

document.querySelectorAll('[data-dia]').forEach(campo => campo.addEventListener('change', () => {
    formularioUsuario.elements.dias.value = [...formularioUsuario.querySelectorAll('[data-dia]:checked')].map(dia => dia.dataset.dia).join(',');
}));

async function actualizarInicio() {
    const estado = document.getElementById('resumen-estado');
    estado.textContent = 'Cargando resumen…';
    const r = await BaseDatos.getInvoices();
    if (!vendedorActual) return;
    if (!r.success) { estado.textContent = r.message; return; }
    const hoy = r.facturas.filter(f => String(f.fecha).slice(0, 10) === fechaOperacion());
    const total = hoy.reduce((s, f) => s + Number(f.total), 0);
    document.getElementById('resumen-total').textContent = dineroMinina(total);
    document.getElementById('resumen-cantidad').textContent = hoy.length;
    document.getElementById('resumen-promedio').textContent = dineroMinina(hoy.length ? total / hoy.length : 0);
    estado.textContent = vendedorActual.rol === 'admin' ? 'Ventas de todas las sucursales.' : 'Resumen de tus ventas.';
    document.querySelector('#tabla-recientes tbody').innerHTML = r.facturas.slice(0, 7).map(f => `<tr><td>${escaparHtml(f.numero)}</td><td>${escaparHtml(f.fecha)}</td><td>${escaparHtml(f.cliente || 'Sin cliente registrado')}</td><td class="numeric">${dineroMinina(f.total)}</td><td><button class="btn btn-small btn-gray" onclick="verDetalleFactura(${Number(f.id)})">Ver detalle</button></td></tr>`).join('') || '<tr><td colspan="5" class="empty-state">Tu primera venta empieza con Nueva factura.</td></tr>';
}

function dineroMinina(valor) { return new Intl.NumberFormat('es-SV', {style:'currency',currency:'USD'}).format(Number(valor)); }
function tablaGestion(cabeceras, filas, vacio) {
    return `<div class="table-scroll" tabindex="0"><table><thead><tr>${cabeceras.map(c => `<th scope="col">${c}</th>`).join('')}</tr></thead><tbody>${filas.join('') || `<tr><td class="empty-state" colspan="${cabeceras.length}">${vacio}</td></tr>`}</tbody></table></div>`;
}
function renderizarGestion(r) {
    document.getElementById('lista-usuarios').innerHTML = tablaGestion(['Nombre','Usuario','Rol','Estado','Acciones'], r.usuarios.map(u => `<tr><td>${escaparHtml(u.nombre)}</td><td>${escaparHtml(u.usuario)}</td><td>${u.rol === 'admin' ? 'Administrador' : 'Empleado'}</td><td><span class="status-badge ${Number(u.activo) ? 'status-ok' : 'status-muted'}">${Number(u.activo) ? 'Activo' : 'Bloqueado'}</span></td><td><button class="btn btn-small btn-gray" onclick="editarEmpleado(${Number(u.id)})">Editar</button></td></tr>`), 'No hay empleados.');
    document.getElementById('lista-accesos').innerHTML = tablaGestion(['Fecha','Usuario','Motivo','Dirección IP'],r.accesos.map(a => `<tr><td>${escaparHtml(a.fecha)}</td><td>${escaparHtml(a.usuario)}</td><td>${escaparHtml(a.motivo)}</td><td>${escaparHtml(a.ip)}</td></tr>`),'No hay accesos denegados.');
    document.getElementById('lista-correos').innerHTML = tablaGestion(['Destinatario','Asunto','Estado','Intentos','Acciones'],r.correos.map(c => `<tr><td>${escaparHtml(c.destinatario || 'Por configurar')}</td><td>${escaparHtml(c.asunto)}</td><td><span class="status-badge ${c.estado === 'enviado' ? 'status-ok' : 'status-pending'}">${escaparHtml(c.estado)}</span></td><td>${Number(c.intentos)}</td><td>${c.estado !== 'enviado' ? `<button class="btn btn-small btn-gray" onclick="reintentarCorreo(${Number(c.id)})">Reintentar</button>` : '—'}</td></tr>`),'No hay correos.');
}
function renderizarSucursales() {
    document.getElementById('lista-sucursales').innerHTML = tablaGestion(['Sucursal','Dirección'],catalogoSucursales.map(s => `<tr><td>${escaparHtml(s.nombre)}</td><td>${escaparHtml(s.direccion)}</td></tr>`),'No hay sucursales registradas.');
}
// Los encabezados describen columnas también para lectores de pantalla.
document.querySelectorAll('thead th').forEach(th => th.scope = 'col');
