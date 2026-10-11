/* Comunicación, escape de datos y navegación de paneles. */
function peticionPanel(accion, datos) {
    return datos === undefined
        ? BaseDatos.solicitar(`${API_URL}?action=${accion}`)
        : BaseDatos.enviar(accion, datos);
}
function escaparHtml(valor) {
    return String(valor ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}
function configurarPanel() {
    const admin = vendedorActual.rol === 'admin';
    document.getElementById('panel-titulo').textContent = admin ? 'Panel de administrador' : 'Panel de usuario';
    document.getElementById('btn-admin').hidden = !admin;
    document.getElementById('btn-productos').hidden = !admin;
    document.getElementById('login-error').textContent = '';
    mostrarHorarioUsuario(vendedorActual);
}
/* Horario personal de solo consulta: nunca enviar estos datos como una modificación.
 * El permiso para guardar horarios se valida también en PHP mediante requerirAdmin. */
function mostrarHorarioUsuario(usuario) {
    const admin = usuario.rol === 'admin';
    document.getElementById('horario-nota').textContent = admin
        ? 'El administrador activo tiene acceso permanente.'
        : 'Hora de El Salvador. El final del turno no está incluido. Solo el administrador puede cambiar tu horario.';
    if (admin) {
        document.getElementById('horario-dias').textContent = 'Todos los días';
        document.getElementById('horario-horas').textContent = '24 horas';
        document.getElementById('horario-fechas').textContent = 'Sin restricciones de fechas';
        document.getElementById('horario-turno-nocturno').hidden = true;
        return;
    }
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
    window.scrollTo(0, 0);
    document.querySelectorAll('.modulo').forEach(e => { e.hidden = e.id !== id; });
    document.getElementById('view-dashboard').style.display = 'none';
}
function cerrarModulos() {
    document.querySelectorAll('.modulo').forEach(e => { e.hidden = true; });
    document.getElementById('view-dashboard').style.display = vendedorActual ? 'block' : 'none';
    cancelarFactura();
}
/* Administración: empleados, restricciones, auditoría y cola de correo. */
let empleadosPanel = [];
async function mostrarAdministracion() {
    const r = await peticionPanel('admin');
    if (!r.success) return mostrarAviso(r.message);
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
    actualizarCamposHorario();
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
    const r = await peticionPanel('save_user',datos); mostrarAviso(r.message, r.success ? 'exito' : 'error');
    if (r.success) { e.target.reset(); await mostrarAdministracion(); }
});
async function reintentarCorreo(id) {
    const r = await peticionPanel('retry_mail',{id}); mostrarAviso(r.message, r.success ? 'exito' : 'error'); await mostrarAdministracion();
}

/* Los campos de horario solo tienen sentido para los empleados. */
function actualizarCamposHorario() {
    const form = document.getElementById('form-usuario');
    const admin = form.elements.rol.value === 'admin';
    ['dias','hora_inicio','hora_fin','fecha_desde','fecha_hasta'].forEach(nombre => {
        form.elements[nombre].disabled = admin;
    });
    form.elements.clave.required = !form.elements.id.value;
    document.getElementById('titulo-form-usuario').textContent = form.elements.id.value ? 'Editar empleado' : 'Nuevo empleado';
}
const formularioUsuario = document.getElementById('form-usuario');
formularioUsuario.elements.rol.addEventListener('change', actualizarCamposHorario);
formularioUsuario.addEventListener('reset', () => setTimeout(actualizarCamposHorario, 0));
actualizarCamposHorario();

document.getElementById('form-sucursal').addEventListener('submit', async evento => {
    evento.preventDefault();
    const resultado = await peticionPanel('save_branch', Object.fromEntries(new FormData(evento.target)));
    mostrarAviso(resultado.message, resultado.success ? 'exito' : 'error');
    if (resultado.success) { evento.target.reset(); await cargarCatalogos(); }
});
