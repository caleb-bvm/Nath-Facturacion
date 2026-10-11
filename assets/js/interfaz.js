// Mensajes visibles sin interrumpir el formulario con ventanas emergentes.
function mostrarAviso(mensaje, tipo = 'error') {
    const aviso = document.getElementById('aviso');
    aviso.className = `aviso aviso-${tipo}`;
    aviso.hidden = false;
    document.getElementById('aviso-texto').textContent = mensaje;
}

function alternarClave() {
    const campo = document.getElementById('login-pass');
    const boton = document.getElementById('btn-ver-clave');
    const visible = campo.type === 'password';
    campo.type = visible ? 'text' : 'password';
    boton.textContent = visible ? 'Ocultar' : 'Mostrar';
    boton.setAttribute('aria-pressed', String(visible));
}

document.getElementById('form-login').addEventListener('submit', evento => {
    evento.preventDefault();
    iniciarSesion();
});
