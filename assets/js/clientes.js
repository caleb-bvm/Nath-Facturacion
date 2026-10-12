/* Registro y edición de clientes; las facturas guardan su propia copia de los datos. */
let clientesPanel = [];
let guardandoCliente = false;
const formularioCliente = document.getElementById('form-cliente');

async function cargarClientes() {
    const respuesta = await peticionPanel('catalogs');
    if (!respuesta.success) { mostrarAviso(respuesta.message); return false; }
    clientesPanel = respuesta.clientes;
    document.getElementById('lista-clientes').innerHTML = clientesPanel.map(cliente => `
        <tr><td>${escaparHtml(cliente.nombre)}</td><td>${escaparHtml(cliente.correo)}</td>
        <td>${escaparHtml(cliente.telefono)}</td><td><button type="button" class="btn btn-small"
        onclick="editarCliente(${Number(cliente.id)})">Editar</button></td></tr>`).join('')
        || '<tr><td colspan="4" class="empty-state">Todavía no hay clientes registrados.</td></tr>';
    return true;
}

async function mostrarClientes() {
    if (!await cargarClientes()) return;
    nuevoCliente();
    abrirModulo('sec-clientes');
}

function nuevoCliente() {
    if (guardandoCliente) return;
    formularioCliente.reset();
    formularioCliente.elements.namedItem('id').value = '';
    document.getElementById('titulo-form-cliente').textContent = 'Nuevo cliente';
    document.getElementById('guardar-cliente').textContent = 'Guardar cliente';
    document.getElementById('cancelar-cliente').hidden = true;
}

function editarCliente(id) {
    if (guardandoCliente) return;
    const cliente = clientesPanel.find(cliente => Number(cliente.id) === id);
    if (!cliente) return;
    ['id', 'nombre', 'correo', 'telefono', 'nit', 'nrc'].forEach(campo => {
        formularioCliente.elements.namedItem(campo).value = cliente[campo] ?? '';
    });
    document.getElementById('titulo-form-cliente').textContent = 'Editar cliente';
    document.getElementById('guardar-cliente').textContent = 'Guardar cambios';
    document.getElementById('cancelar-cliente').hidden = false;
    document.getElementById('editor-cliente').open = true;
    formularioCliente.elements.namedItem('nombre').focus();
}

formularioCliente.addEventListener('submit', async evento => {
    evento.preventDefault();
    if (guardandoCliente) return;
    guardandoCliente = true;
    const boton = document.getElementById('guardar-cliente');
    const texto = boton.textContent;
    boton.disabled = true;
    boton.textContent = 'Guardando…';
    let respuesta;
    try {
        respuesta = await peticionPanel('save_client', Object.fromEntries(new FormData(formularioCliente)));
    } finally {
        guardandoCliente = false;
        boton.disabled = false;
        boton.textContent = texto;
    }
    mostrarAviso(respuesta.message, respuesta.success ? 'exito' : 'error');
    if (respuesta.success && vendedorActual) {
        nuevoCliente();
        if (await cargarClientes()) await cargarCatalogos();
    }
});
