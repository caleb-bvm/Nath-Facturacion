// Todas las peticiones usan la misma sesión y el mismo token de formulario.
const API_URL = 'api/index.php';

const BaseDatos = {
    csrf: '',

    async solicitar(url, opciones = {}) {
        if (location.protocol === 'file:') {
            return {success: false, message: 'Abra la dirección del servidor; este sistema necesita PHP.'};
        }
        const {timeoutMs, ...opcionesFetch} = opciones;
        const controlador = timeoutMs ? new AbortController() : null;
        const temporizador = controlador ? setTimeout(() => controlador.abort(), timeoutMs) : null;
        try {
            const respuesta = await fetch(url, {
                ...opcionesFetch,
                ...(controlador ? {signal: controlador.signal} : {}),
                credentials: 'same-origin',
                headers: {...opciones.headers, 'X-CSRF-Token': this.csrf}
            });
            let datos;
            try {
                datos = await respuesta.json();
                if (!datos || typeof datos !== 'object' || Array.isArray(datos)) throw new Error();
            } catch {
                if (controlador?.signal.aborted) throw new Error('Tiempo de espera agotado');
                return {success: false, message: `Respuesta inválida del servidor (HTTP ${respuesta.status}).`};
            }
            if (!respuesta.ok) {
                const finalizada = !url.includes('action=login') &&
                    (respuesta.status === 401 || datos.sesion_finalizada);
                if (finalizada) limpiarSesion();
                return {
                    success: false,
                    sesion_finalizada: Boolean(finalizada),
                    message: datos.message || 'No se pudo completar la operación.'
                };
            }
            if (datos.csrf) this.csrf = datos.csrf;
            return datos;
        } catch {
            if (controlador?.signal.aborted) {
                return {success: false, message: 'El servidor tardó demasiado en responder. Reintente cargar la factura.'};
            }
            return {success: false, message: 'No se recibió respuesta del servidor. Compruebe la conexión.'};
        } finally {
            if (temporizador !== null) clearTimeout(temporizador);
        }
    },

    enviar(accion, datos) {
        return this.solicitar(`${API_URL}?action=${accion}`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(datos)
        });
    },

    login(usuario, clave) { return this.enviar('login', {usuario, clave}); },
    getCatalogs() { return this.solicitar(`${API_URL}?action=catalogs`); },
    getInvoices() { return this.solicitar(`${API_URL}?action=invoices`); },
    getInvoice(id) { return this.solicitar(`${API_URL}?action=invoice&id=${encodeURIComponent(id)}`, {timeoutMs: 15000}); },
    getProducts() { return this.solicitar(`${API_URL}?action=products`); },
    saveProduct(producto) { return this.enviar('save_product', producto); },
    saveInvoice(factura) { return this.enviar('save_invoice', factura); }
};
