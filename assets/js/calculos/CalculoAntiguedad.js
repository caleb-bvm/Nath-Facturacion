/* Edad calendario y totales: medianoche UTC evita variaciones por horario de verano. */
class CalculoEdad extends Calculo {
    calcular(nacimientoTexto, referenciaTexto = fechaOperacion()) {
        const parsear = texto => {
            if (!/^\d{4}-\d{2}-\d{2}$/.test(texto)) throw new Error('Fecha inválida.');
            const fecha = new Date(`${texto}T00:00:00Z`);
            if (!Number.isFinite(fecha.getTime()) || fecha.toISOString().slice(0,10) !== texto) throw new Error('Fecha inválida.');
            return fecha;
        };
        const nacimiento = parsear(nacimientoTexto), referencia = parsear(referenciaTexto);
        if (nacimiento > referencia) throw new Error('La fecha de nacimiento no puede ser futura.');
        const sumarMeses = cantidad => {
            const fecha = new Date(nacimiento); fecha.setUTCDate(1);
            fecha.setUTCMonth(fecha.getUTCMonth() + cantidad);
            const ultimo = new Date(Date.UTC(fecha.getUTCFullYear(),fecha.getUTCMonth()+1,0)).getUTCDate();
            fecha.setUTCDate(Math.min(nacimiento.getUTCDate(),ultimo)); return fecha;
        };
        let mesesTotales = (referencia.getUTCFullYear()-nacimiento.getUTCFullYear())*12 + referencia.getUTCMonth()-nacimiento.getUTCMonth();
        if (sumarMeses(mesesTotales)>referencia) mesesTotales--;
        const resto = Math.floor((referencia-sumarMeses(mesesTotales))/86400000);
        const dias = Math.floor((referencia-nacimiento)/86400000);
        return {anos:Math.floor(mesesTotales/12), meses:mesesTotales%12, semanas:Math.floor(resto/7), dias:resto%7,
            mesesTotales, semanasTotales:Math.floor(dias/7), horas:dias*24, minutos:dias*1440};
    }
}
