/* Bloque de utilidades: fechas. */
// Funciones pequeñas para validar fechas de operación.
function fechaOperacion() {
    const partes = new Intl.DateTimeFormat('en-CA', { timeZone: 'America/El_Salvador', year: 'numeric', month: '2-digit', day: '2-digit' }).formatToParts(new Date());
    const valor = tipo => partes.find(p => p.type === tipo).value;
    return `${valor('year')}-${valor('month')}-${valor('day')}`;
}

function fechaTexto(fecha = fechaOperacion()) {
    return new Date(`${fecha}T00:00:00`).toLocaleDateString('es-SV', {
        day: 'numeric',
        month: 'long',
        year: 'numeric'
    });
}
