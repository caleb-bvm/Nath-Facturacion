let vendedorActual = null;
let catalogoProductos = [];
let catalogoSucursales = [];
let itemsFactura = [];
let guardandoFactura = false;
let solicitudFactura = '';
let iniciandoSesion = false;

// Instancias de las clases de cálculo
const calcEdad = new CalculoEdad();
const calcCumpleanos = new CalculoCumpleanos();
const calcFactura = new CalculoFactura();

// INICIAR SESIÓN
async function iniciarSesion() {
    const usuario = document.getElementById('login-user').value.trim();
    const clave = document.getElementById('login-pass').value;
    const errorLbl = document.getElementById('login-error');

    if (!usuario || !clave) {
        errorLbl.textContent = 'Complete todos los campos obligatorios.';
        return;
    }

    if (iniciandoSesion) return;
    iniciandoSesion = true;
    const boton = document.getElementById('btn-login');
    boton.disabled = true;
    boton.textContent = 'Ingresando…';
    errorLbl.textContent = '';
    let res;
    try { res = await BaseDatos.login(usuario, clave); }
    finally {
        iniciandoSesion = false;
        boton.disabled = false;
        boton.textContent = 'Ingresar';
    }

    if (res.success) {
        vendedorActual = res.vendedor;
        configurarPanel();
        
        // Cambiar vistas de la interfaz
        document.getElementById('view-login').style.display = 'none';
        document.getElementById('view-dashboard').style.display = 'block';
        document.getElementById('user-info').style.display = 'flex';
        document.getElementById('welcome-user').textContent = `Usuario: ${vendedorActual.usuario}`;

        // Rellenar datos institucionales y de usuario en el panel
        document.getElementById('fac-vendedor').value = `${vendedorActual.usuario} (${vendedorActual.nombre})`;
        document.getElementById('fac-telefono').value = '';
        document.getElementById('lbl-footer-user').value = vendedorActual.usuario;

        const fechaActual = fechaOperacion();
        document.getElementById('lbl-fecha-operacion').textContent = fechaTexto(fechaActual);
        document.getElementById('fac-fecha').value = fechaTexto(fechaActual).toUpperCase();

        /* Desglose de edad del usuario: mostrar los mismos datos en panel y factura. */
        const edadObj = calcEdad.calcular(vendedorActual.fecha_nacimiento, fechaActual);
        const unidadEdad = (cantidad, singular, plural) => `${cantidad} ${cantidad === 1 ? singular : plural}`;
        const desgloseEdad = [
            unidadEdad(edadObj.anos, 'año', 'años'),
            unidadEdad(edadObj.meses, 'mes', 'meses'),
            unidadEdad(edadObj.semanas, 'semana', 'semanas')
        ].join(', ') + ' y ' + unidadEdad(edadObj.dias, 'día', 'días');
        /* Mostrar el estado del cumpleaños junto al desglose completo de edad. */
        const mensajeCumpleanos = calcCumpleanos.calcular(vendedorActual.fecha_nacimiento, fechaActual);
        const edadLabel = document.getElementById('lbl-edad-vendedor');
        edadLabel.textContent = `Usuario: ${vendedorActual.usuario} | ${desgloseEdad} | ${mensajeCumpleanos}`;
        edadLabel.classList.toggle('birthday-message', mensajeCumpleanos.startsWith('¡Feliz cumpleaños!'));
        document.getElementById('lbl-footer-edad').textContent = desgloseEdad;

        // Cargar catálogos desde MySQL a través de la API
        await cargarCatalogos();
    } else {
        errorLbl.textContent = res.message;
    }
}

// CERRAR SESIÓN
async function logout() {
    if (guardandoFactura) { mostrarAviso('Espere a que termine el registro de la factura.'); return; }
    const res = await peticionPanel('logout', {});
    if (!res.success && !res.sesion_finalizada) { mostrarAviso(res.message); return; }
    limpiarSesion();
}

function limpiarSesion() {
    guardandoFactura = false;
    cerrarModulos();
    itemsFactura = [];
    catalogoProductos = [];
    catalogoSucursales = [];
    empleadosPanel = [];
    clientesPanel = [];
    solicitudFactura = '';
    BaseDatos.csrf = '';
    document.querySelectorAll('.modulo form').forEach(form => form.reset());
    ['lista-usuarios','lista-accesos','lista-correos'].forEach(id => document.getElementById(id).textContent = '');
    document.querySelectorAll('tbody').forEach(tabla => tabla.textContent = '');
    vendedorActual = null;
    document.getElementById('view-dashboard').style.display = 'none';
    document.getElementById('user-info').style.display = 'none';
    document.getElementById('view-login').style.display = 'block';
    document.getElementById('login-pass').value = '';
    document.getElementById('login-pass').type = 'password';
    document.getElementById('btn-ver-clave').textContent = 'Mostrar';
    document.getElementById('btn-ver-clave').setAttribute('aria-pressed','false');
    document.getElementById('aviso').hidden = true;
    document.getElementById('login-user').focus();
}

// CARGAR CATÁLOGOS (PRODUCTOS Y SUCURSALES)
async function cargarCatalogos() {
    const res = await BaseDatos.getCatalogs();
    if (!res.success) { mostrarAviso(res.message); return; }
    if (res.success) {
        catalogoProductos = res.productos;
        catalogoSucursales = res.sucursales;

        const listaClientes = document.getElementById('fac-cliente');
        listaClientes.innerHTML = '';
        (res.clientes || []).forEach(cliente => {
            const option = document.createElement('option');
            option.value = cliente.id;
            option.dataset.telefono = cliente.telefono || '';
            option.textContent = cliente.nombre;
            listaClientes.appendChild(option);
        });
        listaClientes.onchange = () => {
            const cliente = listaClientes.options[listaClientes.selectedIndex];
            document.getElementById('fac-telefono').value = cliente?.dataset.telefono || '';
        };
        if (listaClientes.options.length > 0) {
            listaClientes.selectedIndex = 0;
            document.getElementById('fac-telefono').value =
                listaClientes.options[0].dataset.telefono || '';
        } else {
            document.getElementById('fac-telefono').value = '';
        }

        // Llenar selector de productos
        const selProd = document.getElementById('sel-producto');
        selProd.innerHTML = '';
        catalogoProductos.forEach(p => {
            const opt = document.createElement('option');
            opt.value = p.id;
            opt.textContent = `${p.codigo} - ${p.descripcion} ($${p.precio})`;
            opt.dataset.precio = p.precio;
            opt.dataset.codigo = p.codigo;
            opt.dataset.descripcion = p.descripcion;
            selProd.appendChild(opt);
        });

        document.getElementById('txt-precio').value = selProd.options[0]?.dataset.precio || '';

        // Actualizar precio automáticamente al cambiar de producto
        selProd.onchange = (e) => {
            const optSel = e.target.options[e.target.selectedIndex];
            document.getElementById('txt-precio').value = optSel?.dataset.precio || '';
        };

        // Llenar selector de sucursales
        const selSuc = document.getElementById('fac-sucursal');
        selSuc.innerHTML = '';
        catalogoSucursales.forEach(s => {
            const opt = document.createElement('option');
            opt.value = s.id;
            opt.textContent = s.nombre;
            selSuc.appendChild(opt);
        });
    }
}

// NAVEGACIÓN ENTRE SECCIONES
async function mostrarSeccionNuevaFactura() {
    if (guardandoFactura) return;
    window.scrollTo(0, 0);
    document.getElementById('sec-dashboard-menu').style.display = 'none';
    document.getElementById('sec-nueva-factura').style.display = 'block';
    document.getElementById('sec-historial').style.display = 'none';
    document.getElementById('sec-productos').style.display = 'none';
    itemsFactura = [];

    // El número definitivo solo existe cuando el servidor guarda la factura.
    solicitudFactura = Array.from(crypto.getRandomValues(new Uint8Array(16)), byte => byte.toString(16).padStart(2,'0')).join('');
    document.getElementById('lbl-nro-doc').textContent = 'Se asignará al guardar';
    document.getElementById('fac-fecha').value = fechaTexto(fechaOperacion()).toUpperCase();
    document.getElementById('fac-tipo').value = 'consumidor';
    await cargarCatalogos();

    renderizarItemsFactura();
}

async function mostrarHistorial() {
    window.scrollTo(0, 0);
    document.getElementById('sec-dashboard-menu').style.display = 'none';
    document.getElementById('sec-nueva-factura').style.display = 'none';
    document.getElementById('sec-historial').style.display = 'block';
    document.getElementById('sec-productos').style.display = 'none';

    const res = await BaseDatos.getInvoices();
    const tbody = document.querySelector('#tabla-historial tbody');
    tbody.innerHTML = '';

    if (!res.success) { mostrarAviso(res.message); return; }
    if (res.success) {
        if (!res.facturas.length) tbody.innerHTML = '<tr><td colspan="10" class="empty-state">Todavía no hay ventas registradas.</td></tr>';
        res.facturas.forEach(f => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${escaparHtml(f.numero)}</td>
                <td>${escaparHtml(f.fecha)}</td>
                <td>${escaparHtml(f.sucursal_nombre)}</td>
                <td>${escaparHtml(f.cliente)}</td>
                <td>${escaparHtml(f.vendedor_nombre)}</td>
                <td>$${Number(f.subtotal).toFixed(2)}</td>
                <td>$${Number(f.iva).toFixed(2)}</td>
                <td>$${Number(f.total).toFixed(2)}</td>
                <td>${Number(f.comision).toFixed(2)} (${Number(f.comision_porcentaje)}%)</td>
                <td><a href="${API_URL}?action=pdf&id=${Number(f.id)}" target="_blank" rel="noopener">Descargar PDF</a></td>
            `;
            tbody.appendChild(tr);
        });
    }
}

function cancelarFactura() {
    if (guardandoFactura) return;
    window.scrollTo(0, 0);
    document.getElementById('sec-nueva-factura').style.display = 'none';
    document.getElementById('sec-historial').style.display = 'none';
    document.getElementById('sec-dashboard-menu').style.display = 'block';
    document.getElementById('sec-productos').style.display = 'none';
}

async function mostrarProductos() {
    window.scrollTo(0, 0);
    document.getElementById('sec-dashboard-menu').style.display = 'none';
    document.getElementById('sec-nueva-factura').style.display = 'none';
    document.getElementById('sec-historial').style.display = 'none';
    document.getElementById('sec-productos').style.display = 'block';
    const res = await BaseDatos.getProducts();
    const tbody = document.querySelector('#tabla-productos tbody');
    tbody.innerHTML = '';
    if (!res.success) {
        document.getElementById('producto-mensaje').textContent = res.message;
        return;
    }
    if (!res.productos.length) tbody.innerHTML = '<tr><td colspan="3" class="empty-state">Agrega tu primer producto usando el formulario.</td></tr>';
    res.productos.forEach(producto => {
        tbody.innerHTML += `<tr><td>${escaparHtml(producto.codigo)}</td><td>${escaparHtml(producto.descripcion)}</td><td>$${Number(producto.precio).toFixed(2)}</td></tr>`;
    });
}

async function guardarProducto() {
    const code = document.getElementById('nuevo-codigo').value.trim();
    const description = document.getElementById('nueva-descripcion').value.trim();
    const price = document.getElementById('nuevo-precio').value;
    const message = document.getElementById('producto-mensaje');
    message.classList.remove('mensaje-exito');
    if (!/^PROD-\d{6}$/.test(code) || !description || description.length > 160 || Number(price) <= 0) {
        message.textContent = 'Use un código PROD-000000, descripción y precio mayor que cero.';
        return;
    }
    const res = await BaseDatos.saveProduct({ codigo: code, descripcion: description, precio: price });
    message.textContent = res.message;
    message.classList.toggle('mensaje-exito', Boolean(res.success));
    if (res.success) {
        document.getElementById('nuevo-codigo').value = '';
        document.getElementById('nueva-descripcion').value = '';
        document.getElementById('nuevo-precio').value = '';
        cargarCatalogos();
        mostrarProductos();
    }
}

// GESTIÓN DE ITEMS EN LA FACTURA
function agregarItemFactura() {
    const selProd = document.getElementById('sel-producto');
    const opt = selProd.options[selProd.selectedIndex];
    const cantidad = Number(document.getElementById('txt-cantidad').value);

    if (!Number.isInteger(cantidad) || cantidad < 1 || cantidad > 9999) {
        mostrarAviso('Ingrese una cantidad entera entre 1 y 9,999. No se permiten unidades decimales.');
        return;
    }

    if (!opt) { mostrarAviso('No hay productos disponibles.'); return; }
    const producto_id = opt.value;
    const codigo_producto = opt.dataset.codigo;
    const descripcion = opt.dataset.descripcion;
    const precio_unitario = parseFloat(opt.dataset.precio);
    
    // Validar productos repetidos en la misma factura
    const repetido = itemsFactura.find(item => item.producto_id === producto_id);
    if (repetido) {
        mostrarAviso('Este producto ya está en la factura. Quítalo y agrégalo con la cantidad correcta.');
        return;
    }

    if (itemsFactura.length >= 200) { mostrarAviso('Se permiten hasta 200 productos por factura.'); return; }
    const total_linea = new CalculoTotalLinea().calcular(precio_unitario, cantidad);

    itemsFactura.push({
        producto_id,
        codigo_producto,
        descripcion,
        cantidad,
        precio_unitario,
        total_linea
    });

    renderizarItemsFactura();
}

function renderizarItemsFactura() {
    const tbody = document.querySelector('#tabla-items tbody');
    tbody.innerHTML = itemsFactura.length ? '' : '<tr><td colspan="7" class="empty-state">Selecciona un producto y pulsa Añadir para comenzar.</td></tr>';

    itemsFactura.forEach((item, index) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td style="text-align:center;">${index + 1}</td>
            <td>${escaparHtml(item.codigo_producto)}</td>
            <td>${escaparHtml(item.descripcion)}</td>
            <td style="text-align:center;">${item.cantidad}</td>
            <td>$${item.precio_unitario.toFixed(2)}</td>
            <td>$${item.total_linea.toFixed(2)}</td>
            <td class="no-print" style="text-align:center;"><button class="btn btn-small btn-eliminar" aria-label="Quitar ${escaparHtml(item.descripcion)}" onclick="eliminarItem(${index})">Quitar</button></td>
        `;
        tbody.appendChild(tr);
    });

    calcularTotalesFactura();
}

function eliminarItem(index) {
    itemsFactura.splice(index, 1);
    renderizarItemsFactura();
}

// CÁLCULOS FINANCIEROS (SUBTOTAL, IVA, TOTAL, COMISIÓN)
function calcularTotalesFactura() {
    const valores = calcFactura.calcular(itemsFactura);
    const subtotal = valores.subtotal;
    const iva = valores.iva;
    const total = valores.total;
    const comision = valores.comision;

    document.getElementById('lbl-subtotal').textContent = subtotal.toFixed(2);
    document.getElementById('lbl-iva').textContent = iva.toFixed(2);
    document.getElementById('lbl-total').textContent = total.toFixed(2);
    document.getElementById('lbl-comision-porc').textContent = comision.porcentaje;
    document.getElementById('lbl-comision-monto').textContent = comision.monto.toFixed(2);
}

// GUARDAR FACTURA EN BASE DE DATOS Y GENERAR PDF
async function guardarFacturaBD() {
    if (guardandoFactura) return;
    if (itemsFactura.length === 0) {
        mostrarAviso('La factura debe contener al menos un producto.');
        return;
    }

    const cliente = document.getElementById('fac-cliente').value.trim();

    if (!cliente) {
        mostrarAviso('El nombre del cliente es obligatorio.');
        document.getElementById('fac-cliente').focus();
        return;
    }

    // PHP toma de la base los precios, el cliente, el usuario y los importes.
    const facturaData = {
        solicitud_id: solicitudFactura,
        sucursal_id: document.getElementById('fac-sucursal').value,
        cliente_id: cliente,
        tipo: document.getElementById('fac-tipo').value,
        detalles: itemsFactura.map(({producto_id, cantidad}) => ({producto_id, cantidad}))
    };

    guardandoFactura = true;
    const boton = document.getElementById('btn-guardar-factura');
    boton.disabled = true;
    boton.textContent = 'Guardando…';
    let res;
    try { res = await BaseDatos.saveInvoice(facturaData); }
    finally { guardandoFactura = false; boton.disabled = false; boton.textContent = 'Guardar factura'; }

    if (res.success) {
        document.getElementById('lbl-nro-doc').textContent = res.numero;
        mostrarAviso(`${res.numero}: ${res.message}`, 'exito');
        window.open(API_URL + '?action=pdf&id=' + res.id, '_blank');

        itemsFactura = [];
        solicitudFactura = '';
        cancelarFactura();
    } else {
        mostrarAviso('Error al registrar la factura: ' + res.message);
    }
}
