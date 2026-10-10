/* Comunicación, escape de datos y navegación de paneles. */
function peticionPanel(accion, datos) {
    return BaseDatos.solicitar(`${API_URL}?action=${accion}`, datos === undefined ? {} : {
        method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(datos)
    });
}
function escaparHtml(valor) {
    return String(valor ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}
function configurarPanel() {
    const supremo = vendedorActual.rol === 'supremo';
    document.getElementById('panel-titulo').textContent = supremo ? 'Panel de administrador supremo' : 'Panel de usuario';
    document.getElementById('btn-admin').hidden = !supremo;
    document.getElementById('btn-productos').hidden = !supremo;
    document.getElementById('login-error').textContent = '';
    mostrarHorarioUsuario(vendedorActual);
}
/* Horario personal de solo consulta: nunca enviar estos datos como una modificación.
 * El permiso para guardar horarios se valida también en PHP mediante requerirSupremo. */
function mostrarHorarioUsuario(usuario) {
    const nombresDias = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
    const dias = new Set(String(usuario.dias || '').split(',').map(Number));
    document.getElementById('horario-dias').textContent = nombresDias
        .filter((nombre, indice) => dias.has(indice + 1)).join(', ') || 'Sin días autorizados';

    const inicio = String(usuario.hora_inicio || '').slice(0, 5);
    const fin = String(usuario.hora_fin || '').slice(0, 5);
    document.getElementById('horario-horas').textContent = `${inicio} a ${fin}`;
    document.getElementById('horario-turno-nocturno').hidden = !(inicio > fin);

    const desde = usuario.fecha_desde ? `Desde el ${fechaTexto(usuario.fecha_desde)}` : 'Sin fecha de inicio límite';
    const hasta = usuario.fecha_hasta ? `hasta el ${fechaTexto(usuario.fecha_hasta)}` : 'sin fecha de fin límite';
    document.getElementById('horario-fechas').textContent = `${desde}; ${hasta}.`;
}
function abrirModulo(id) {
    document.querySelectorAll('.modulo').forEach(e => { e.hidden = e.id !== id; });
    document.getElementById('view-dashboard').style.display = 'none';
}
function cerrarModulos() {
    document.querySelectorAll('.modulo').forEach(e => { e.hidden = true; });
    document.getElementById('view-dashboard').style.display = vendedorActual ? 'block' : 'none';
    cancelarFactura();
}
/* Clientes: datos de contacto y campos del crédito fiscal. */
document.getElementById('form-cliente').addEventListener('submit', async e => {
    e.preventDefault();
    const r = await peticionPanel('save_client', Object.fromEntries(new FormData(e.target)));
    alert(r.message);
    if (r.success) { e.target.reset(); await cargarCatalogos(); }
});
/* Gestión suprema: empleados, restricciones, auditoría y cola de correo. */
let empleadosPanel = [];
async function mostrarAdministracion() {
    const r = await peticionPanel('admin');
    if (!r.success) return alert(r.message);
    abrirModulo('sec-admin'); empleadosPanel = r.usuarios;
    document.getElementById('correo-jefe').value = r.correo_configuracion.jefe || '';
    document.getElementById('correo-transporte-estado').textContent = r.correo_configuracion.smtp_mensaje;
    document.getElementById('lista-usuarios').innerHTML = r.usuarios.map(u => `<p>${escaparHtml(u.nombre)} (${escaparHtml(u.usuario)}) — ${escaparHtml(u.rol)} — ${Number(u.activo) ? 'Activo':'Bloqueado'} <button class="btn btn-small" onclick="editarEmpleado(${Number(u.id)})">Editar</button></p>`).join('');
    document.getElementById('lista-accesos').innerHTML = r.accesos.map(a => `<p>${escaparHtml(a.fecha)} | ${escaparHtml(a.usuario)} | ${escaparHtml(a.motivo)} | IP: ${escaparHtml(a.ip)} | ${escaparHtml(a.agente)}</p>`).join('') || 'Sin alertas.';
    document.getElementById('lista-correos').innerHTML = r.correos.map(c => `<p>${escaparHtml(c.destinatario || 'Destinatario por configurar')} | ${escaparHtml(c.asunto)} | ${escaparHtml(c.estado)} ${c.estado !== 'enviado' ? `<button class="btn btn-small" onclick="reintentarCorreo(${Number(c.id)})">Reintentar</button>`:''}</p>`).join('') || 'Sin correos.';
}
function editarEmpleado(id) {
    const u = empleadosPanel.find(u => Number(u.id) === id);
    const form = document.getElementById('form-usuario'); form.reset();
    Object.entries(u).forEach(([nombre,valor]) => {
        const campo = form.elements.namedItem(nombre);
        if (!campo) return;
        if (campo.type === 'checkbox') campo.checked = Boolean(Number(valor));
        else campo.value = nombre.startsWith('hora_') ? valor.slice(0,5) : (valor ?? '');
    });
    form.scrollIntoView({behavior:'smooth'});
}
/* Guardar el destinatario no envía mensajes: el botón de prueba comprueba el envío. */
document.getElementById('form-correo-configuracion').addEventListener('submit', async evento => {
    evento.preventDefault();
    const datos = Object.fromEntries(new FormData(evento.target));
    const respuesta = await peticionPanel('save_mail_config',datos);
    document.getElementById('correo-configuracion-mensaje').textContent = respuesta.message;
});
async function probarCorreoAlertas() {
    const formulario = document.getElementById('form-correo-configuracion');
    if (!formulario.reportValidity()) return;
    const mensaje = document.getElementById('correo-configuracion-mensaje');
    /* Guardar primero para que la prueba llegue a la dirección que está visible. */
    const guardado = await peticionPanel('save_mail_config',Object.fromEntries(new FormData(formulario)));
    if (!guardado.success) { mensaje.textContent = guardado.message; return; }
    const respuesta = await peticionPanel('test_mail',{});
    mensaje.textContent = respuesta.message;
    if (respuesta.success) await mostrarAdministracion();
}
document.getElementById('form-usuario').addEventListener('submit', async e => {
    e.preventDefault();
    const datos = Object.fromEntries(new FormData(e.target)); datos.activo = e.target.elements.activo.checked;
    const r = await peticionPanel('save_user',datos); alert(r.message);
    if (r.success) { e.target.reset(); await mostrarAdministracion(); }
});
async function reintentarCorreo(id) {
    const r = await peticionPanel('retry_mail',{id}); alert(r.message); await mostrarAdministracion();
}
