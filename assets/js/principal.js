/* Bloque principal: assets/js/principal.js. Responsabilidades separadas por secciones. */
// Controla las pantallas, validaciones y acciones del usuario.
// ======================================================
// ARCHIVO: assets/js/principal.js
// FUNCIÓN:
// Controla toda la interfaz, eventos, validaciones y 
// el uso de las clases de cálculo polimórficas.
// ======================================================

let vendedorActual = null;
let catalogoProductos = [];
let catalogoSucursales = [];
let itemsFactura = [];

// Instancias de las clases de cálculo
const calcEdad = new CalculoEdad();
const calcCumpleanos = new CalculoCumpleanos();
const calcCorrelativo = new CalculoCorrelativo();
const calcFactura = new CalculoFactura();


// ======================================================
// INICIAR SESIÓN
// ======================================================
async function iniciarSesion() {
    const usuario = document.getElementById('login-user').value.trim();
    const clave = document.getElementById('login-pass').value;
    const errorLbl = document.getElementById('login-error');

    if (!usuario || !clave) {
        errorLbl.textContent = 'Complete todos los campos obligatorios.';
        return;
    }

    const res = await BaseDatos.login(usuario, clave);

    if (res.success) {
        vendedorActual = res.vendedor;
        configurarPanel();
        
        // Cambiar vistas de la interfaz
        document.getElementById('view-login').style.display = 'none';
        document.getElementById('view-dashboard').style.display = 'block';
        document.getElementById('user-info').style.display = 'block';
        document.getElementById('welcome-user').textContent = `Usuario: ${vendedorActual.usuario}`;

        // Rellenar datos institucionales y de usuario en el panel
        document.getElementById('fac-vendedor').value = `${vendedorActual.usuario} (${vendedorActual.nombre})`;
        document.getElementById('fac-telefono').value = vendedorActual.telefono || '2255-8899';
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


// ======================================================
// CERRAR SESIÓN
// ======================================================
async function logout() {
    await peticionPanel('logout', {});
    cerrarModulos();
    vendedorActual = null;
    document.getElementById('view-dashboard').style.display = 'none';
    document.getElementById('user-info').style.display = 'none';
    document.getElementById('view-login').style.display = 'block';
    document.getElementById('login-pass').value = '';
}


// ======================================================
// CARGAR CATÁLOGOS (PRODUCTOS Y SUCURSALES)
// ======================================================
async function cargarCatalogos() {
    const res = await BaseDatos.getCatalogs();
    if (!res.success) { alert(res.message); return; }
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
            document.getElementById('fac-telefono').value = cliente.dataset.telefono || '';
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

        if (selProd.options.length > 0) {
            document.getElementById('txt-precio').value = selProd.options[0].dataset.precio;
        }

        // Actualizar precio automáticamente al cambiar de producto
        selProd.onchange = (e) => {
            const optSel = e.target.options[e.target.selectedIndex];
            document.getElementById('txt-precio').value = optSel.dataset.precio;
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


// ======================================================
// NAVEGACIÓN ENTRE SECCIONES
// ======================================================
async function mostrarSeccionNuevaFactura() {
    document.getElementById('sec-dashboard-menu').style.display = 'none';
    document.getElementById('sec-nueva-factura').style.display = 'block';
    document.getElementById('sec-historial').style.display = 'none';
    document.getElementById('sec-productos').style.display = 'none';
    itemsFactura = [];

    // Generar correlativo automático para el número de documento
    const histRes = await BaseDatos.getInvoices();
    let ultimoNum = 0;
    if (histRes.success && histRes.facturas.length > 0) {
        const partes = histRes.facturas[0].numero.split('-');
        if (partes.length > 1) ultimoNum = parseInt(partes[1], 10);
    }
    const nuevoCorrelativo = calcCorrelativo.calcular('FAC', ultimoNum);
    document.getElementById('lbl-nro-doc').textContent = nuevoCorrelativo;

    renderizarItemsFactura();
}

async function mostrarHistorial() {
    document.getElementById('sec-dashboard-menu').style.display = 'none';
    document.getElementById('sec-nueva-factura').style.display = 'none';
    document.getElementById('sec-historial').style.display = 'block';
    document.getElementById('sec-productos').style.display = 'none';

    const res = await BaseDatos.getInvoices();
    const tbody = document.querySelector('#tabla-historial tbody');
    tbody.innerHTML = '';

    if (res.success) {
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
    document.getElementById('sec-nueva-factura').style.display = 'none';
    document.getElementById('sec-historial').style.display = 'none';
    document.getElementById('sec-dashboard-menu').style.display = 'block';
    document.getElementById('sec-productos').style.display = 'none';
}

async function mostrarProductos() {
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
    res.productos.forEach(producto => {
        tbody.innerHTML += `<tr><td>${escaparHtml(producto.codigo)}</td><td>${escaparHtml(producto.descripcion)}</td><td>$${Number(producto.precio).toFixed(2)}</td></tr>`;
    });
}

async function guardarProducto() {
    const code = document.getElementById('nuevo-codigo').value.trim();
    const description = document.getElementById('nueva-descripcion').value.trim();
    const price = document.getElementById('nuevo-precio').value;
    const message = document.getElementById('producto-mensaje');
    if (!/^PROD-\d{6}$/.test(code) || !description || description.length > 160 || Number(price) <= 0) {
        message.textContent = 'Use un código PROD-000000, descripción y precio mayor que cero.';
        return;
    }
    const res = await BaseDatos.saveProduct({ codigo: code, descripcion: description, precio: price });
    message.textContent = res.message;
    if (res.success) {
        document.getElementById('nuevo-codigo').value = '';
        document.getElementById('nueva-descripcion').value = '';
        document.getElementById('nuevo-precio').value = '';
        cargarCatalogos();
        mostrarProductos();
    }
}


// ======================================================
// GESTIÓN DE ITEMS EN LA FACTURA
// ======================================================
function agregarItemFactura() {
    const selProd = document.getElementById('sel-producto');
    const opt = selProd.options[selProd.selectedIndex];
    const cantidad = Number(document.getElementById('txt-cantidad').value);

    if (!Number.isInteger(cantidad) || cantidad < 1 || cantidad > 9999) {
        alert('Ingrese una cantidad válida entre 1 y 9,999.');
        return;
    }

    if (!opt) { alert('No hay productos disponibles.'); return; }
    const producto_id = opt.value;
    const codigo_producto = opt.dataset.codigo;
    const descripcion = opt.dataset.descripcion;
    const precio_unitario = parseFloat(opt.dataset.precio);
    
    // Validar productos repetidos en la misma factura
    const repetido = itemsFactura.find(item => item.producto_id === producto_id);
    if (repetido) {
        alert('Este producto ya fue agregado. Modifique la cantidad si lo requiere.');
        return;
    }

    const total_linea = cantidad * precio_unitario;

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
    tbody.innerHTML = '';

    itemsFactura.forEach((item, index) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td style="text-align:center;">${index + 1}</td>
            <td>${escaparHtml(item.codigo_producto)}</td>
            <td>${escaparHtml(item.descripcion)}</td>
            <td style="text-align:center;">${item.cantidad}</td>
            <td>$${item.precio_unitario.toFixed(2)}</td>
            <td>$${item.total_linea.toFixed(2)}</td>
            <td class="no-print" style="text-align:center;"><button class="btn" style="background:#dc2626; padding:0.1rem 0.4rem; font-size:0.75rem;" onclick="eliminarItem(${index})">X</button></td>
        `;
        tbody.appendChild(tr);
    });

    calcularTotalesFactura();
}

function eliminarItem(index) {
    itemsFactura.splice(index, 1);
    renderizarItemsFactura();
}


// ======================================================
// CÁLCULOS FINANCIEROS (SUBTOTAL, IVA, TOTAL, COMISIÓN)
// ======================================================
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


// ======================================================
// GUARDAR FACTURA EN BASE DE DATOS Y GENERAR PDF
// ======================================================
async function guardarFacturaBD() {
    if (itemsFactura.length === 0) {
        alert('La factura debe contener al menos un producto.');
        return;
    }

    const cliente = document.getElementById('fac-cliente').value.trim();
    const telefono = document.getElementById('fac-telefono').value.trim();

    if (!cliente) {
        alert('El nombre del cliente es obligatorio.');
        document.getElementById('fac-cliente').focus();
        return;
    }

    /* Usar el mismo desglose para la vista y el envío al servidor. */
    const { subtotal, iva, total, comision: comisionData } = calcFactura.calcular(itemsFactura);
    const numeroFactura = document.getElementById('lbl-nro-doc').textContent;

    const facturaData = {
        numero: numeroFactura,
        fecha: fechaOperacion(),
        vendedor_id: vendedorActual.id,
        sucursal_id: document.getElementById('fac-sucursal').value,
        cliente_id: cliente,
        tipo: document.getElementById('fac-tipo').value,
        telefono: telefono,
        subtotal: subtotal,
        iva_porcentaje: 13.00,
        iva: iva,
        total: total,
        comision_porcentaje: comisionData.porcentaje,
        comision: comisionData.monto,
        detalles: itemsFactura
    };

    const res = await BaseDatos.saveInvoice(facturaData);

    if (res.success) {
        document.getElementById('lbl-nro-doc').textContent = res.numero;
        alert(res.message);
        window.open(API_URL + '?action=pdf&id=' + res.id, '_blank');

        cancelarFactura();
    } else {
        alert('Error al registrar la factura: ' + res.message);
    }
}
