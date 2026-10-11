/* Bloque principal: assets/js/base-datos.js. Responsabilidades separadas por secciones. */
// Centraliza la comunicación entre JavaScript y la API PHP.
// ======================================================
// ARCHIVO: base-datos.js
// FUNCIÓN:
// Este archivo se encarga solamente de comunicarse
// con la API PHP usando fetch().
// ======================================================


// Dirección de nuestra API PHP
const API_URL =
    "api/index.php";


// ======================================================
// OBJETO BASE DE DATOS
// ======================================================

const BaseDatos = {


    // --------------------------------------------------
    // FUNCIÓN GENERAL PARA HACER PETICIONES A PHP
    // --------------------------------------------------

    async solicitar(url, opciones = {}) {

        /* PHP necesita ejecutarse desde Apache, no desde un archivo o Live Server. */
        if (location.protocol === 'file:' || ['5500', '5501'].includes(location.port)) {
            return {
                success: false,
                message: 'Abra el sistema desde http://localhost/facturacion/. Esta dirección ejecuta PHP con Apache.'
            };
        }

        try {

            // Enviamos la petición
            const respuesta =
                await fetch(url, { ...opciones, credentials: 'same-origin', headers: { ...opciones.headers, 'X-CSRF-Token': this.csrf || '' } });


            /* Distinguir una respuesta inválida de un problema de conexión. */
            let datos;
            try {
                datos = await respuesta.json();
                if (!datos || typeof datos !== 'object' || Array.isArray(datos)) {
                    throw new Error('Respuesta JSON inválida');
                }
            } catch {
                return {
                    success: false,
                    message: `El servidor respondió, pero no devolvió datos válidos (HTTP ${respuesta.status}). Abra http://localhost/facturacion/; si ya está allí, revise el registro de errores de PHP.`
                };
            }


            // Si PHP devuelve un error
            if (!respuesta.ok) {

                return {
                    success: false,
                    message:
                        datos.message ||
                        "Ocurrió un error en el servidor."
                };

            }


            // Si todo salió bien
            if (datos.csrf) this.csrf = datos.csrf;
            return datos;

        }

        catch (error) {

            /* Fallo de red: no se recibió una respuesta del servidor. */
            return {
                success: false,
                message:
                    "No se recibió respuesta del servidor. " +
                    "Verifique Apache en XAMPP y abra http://localhost/facturacion/."
            };

        }

    },



    // ==================================================
    // LOGIN
    // ==================================================

    async login(usuario, clave) {

        return await this.solicitar(

            API_URL + "?action=login",

            {
                method: "POST",

                headers: {
                    "Content-Type":
                        "application/json"
                },

                body: JSON.stringify({
                    usuario: usuario,
                    clave: clave
                })
            }

        );

    },



    // ==================================================
    // OBTENER PRODUCTOS, SUCURSALES Y CLIENTES
    // ==================================================

    async getCatalogs() {

        return await this.solicitar(

            API_URL + "?action=catalogs"

        );

    },



    // ==================================================
    // OBTENER HISTORIAL DE FACTURAS
    // ==================================================

    async getInvoices() {

        return await this.solicitar(

            API_URL + "?action=invoices"

        );

    },

    async getInvoice(id) {
        return await this.solicitar(API_URL + "?action=invoice&id=" + encodeURIComponent(id));
    },

    async getProducts() {
        return await this.solicitar(API_URL + "?action=products");
    },



    // ==================================================
    // GUARDAR PRODUCTO
    // ==================================================

    async saveProduct(producto) {

        return await this.solicitar(

            API_URL + "?action=save_product",

            {
                method: "POST",

                headers: {
                    "Content-Type":
                        "application/json"
                },

                body:
                    JSON.stringify(producto)
            }

        );

    },



    // ==================================================
    // GUARDAR FACTURA
    // ==================================================

    async saveInvoice(factura) {

        return await this.solicitar(

            API_URL + "?action=save_invoice",

            {
                method: "POST",

                headers: {
                    "Content-Type":
                        "application/json"
                },

                body:
                    JSON.stringify(factura)
            }

        );

    }

};
