/* Estado del cumpleaños según la fecha de operación de El Salvador.
 * Para nacimientos del 29 de febrero, usar el 28 en años no bisiestos,
 * igual que el cálculo de edad. Las diferencias se calculan en días UTC. */
class CalculoCumpleanos extends Calculo {
    calcular(fechaNacimiento, fechaReferencia = fechaOperacion()) {
        const nacimiento = new Date(`${fechaNacimiento}T00:00:00Z`);
        const referencia = new Date(`${fechaReferencia}T00:00:00Z`);
        if (!Number.isFinite(nacimiento.getTime()) || !Number.isFinite(referencia.getTime()) ||
            nacimiento.toISOString().slice(0, 10) !== fechaNacimiento ||
            referencia.toISOString().slice(0, 10) !== fechaReferencia || nacimiento > referencia) {
            throw new Error('Fecha de nacimiento o referencia inválida.');
        }

        /* Ajustar el aniversario al último día disponible del mes. */
        const aniversario = ano => {
            const fecha = new Date(nacimiento);
            fecha.setUTCDate(1);
            fecha.setUTCFullYear(ano);
            const siguienteMes = new Date(fecha);
            siguienteMes.setUTCMonth(siguienteMes.getUTCMonth() + 1);
            siguienteMes.setUTCDate(0);
            fecha.setUTCDate(Math.min(nacimiento.getUTCDate(), siguienteMes.getUTCDate()));
            return fecha;
        };

        const anoActual = referencia.getUTCFullYear();
        const cumpleEsteAno = aniversario(anoActual);
        if (cumpleEsteAno.getTime() === referencia.getTime()) {
            const edad = anoActual - nacimiento.getUTCFullYear();
            return `¡Feliz cumpleaños! Hoy cumples ${edad} ${edad === 1 ? 'año' : 'años'}.`;
        }

        /* Indicar si ya cumplió este año y cuánto falta para el próximo. */
        const yaPaso = cumpleEsteAno < referencia;
        const proximo = yaPaso ? aniversario(anoActual + 1) : cumpleEsteAno;
        const dias = Math.round((proximo - referencia) / 86400000);
        const espera = `${dias === 1 ? 'Falta' : 'Faltan'} ${dias} ${dias === 1 ? 'día' : 'días'}`;
        return yaPaso
            ? `Ya cumpliste años este año; tu cumpleaños ya pasó. ${espera} para el próximo.`
            : `Todavía no has cumplido años este año. ${espera} para tu cumpleaños.`;
    }
}
