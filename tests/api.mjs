// Ejecutar contra el servidor temporal configurado con facturacion_pruebas.
import assert from 'node:assert/strict';
const base = process.env.FACTURACION_TEST_URL || 'http://127.0.0.1:8187/api/index.php';
let comprobaciones = 0;
function cliente() {
    return { cookie:'', csrf:'', async pedir(accion, datos, codigo = 200) {
        const respuesta = await fetch(`${base}?action=${accion}`, {
            method: datos === undefined ? 'GET' : 'POST',
            headers: { Cookie:this.cookie, 'X-CSRF-Token':this.csrf, 'Content-Type':'application/json' },
            body: datos === undefined ? undefined : JSON.stringify(datos)
        });
        const cookie = respuesta.headers.get('set-cookie');
        if (cookie) this.cookie = cookie.split(';')[0];
        const cuerpo = await respuesta.json();
        assert.equal(respuesta.status, codigo, `${accion}: ${JSON.stringify(cuerpo)}`);
        comprobaciones++;
        if (cuerpo.csrf) this.csrf = cuerpo.csrf;
        return cuerpo;
    }};
}
const admin = cliente(), empleado = cliente(), anonimo = cliente();
await anonimo.pedir('catalogs', undefined, 401);
await admin.pedir('login', {usuario:'admin_prueba',clave:'1234'});
await empleado.pedir('login', {usuario:'empleado_prueba',clave:'1234'});
await admin.pedir('save_user',{nombre:'Admin simple',usuario:'simple_prueba',rol:'admin',activo:true,fecha_nacimiento:'1990-01-01',clave:'hola'});
const simple = cliente();
await simple.pedir('login',{usuario:'simple_prueba',clave:'incorrecta'},401);
await simple.pedir('login',{usuario:'simple_prueba',clave:'hola'});
await empleado.pedir('admin', undefined, 403);
await empleado.pedir('save_branch', {nombre:'No permitida',direccion:'Prueba'},403);
const csrf = admin.csrf;
admin.csrf = '';
await admin.pedir('save_branch', {nombre:'No permitida',direccion:'Prueba'},403);
admin.csrf = csrf;
await admin.pedir('save_branch',{nombre:'Sucursal de prueba',direccion:'Dirección de prueba'});
await admin.pedir('save_product',{codigo:'PROD-999999',descripcion:'Prueba',precio:'79.00'});
await admin.pedir('save_product',{codigo:'PROD-999999',descripcion:'Duplicado',precio:'1.00'},409);
await admin.pedir('save_product',{codigo:'PROD-999998',descripcion:'Desbordamiento',precio:'100000000.00'},422);
await empleado.pedir('save_client',{nombre:'Cliente de prueba',correo:'cliente@example.com',nit:'123',nrc:'456'});
const catalogo = await admin.pedir('catalogs');
const producto = catalogo.productos.find(p=>p.codigo === 'PROD-999999');
const comprador = catalogo.clientes.find(c=>c.nombre === 'Cliente de prueba');
const sucursal = catalogo.sucursales.find(s=>s.nombre === 'Sucursal de prueba');
const venta = {solicitud_id:'solicitud-prueba-0001',cliente_id:comprador.id,sucursal_id:sucursal.id,tipo:'credito',
    total:0,detalles:[{producto_id:producto.id,cantidad:1,precio_unitario:0}]};
const factura = await admin.pedir('save_invoice',venta);
const repetida = await admin.pedir('save_invoice',venta);
assert.equal(repetida.id, factura.id, 'Reintento debe devolver la misma factura');
const detalle = (await admin.pedir(`invoice&id=${factura.id}`)).factura;
assert.equal(Number(detalle.total),79,'El servidor ignora importes manipulados');
assert.equal(Number(detalle.subtotal),69.91);
assert.equal(Number(detalle.iva),9.09);
assert.equal((await admin.pedir('invoices')).facturas.length,1);
await empleado.pedir(`invoice&id=${factura.id}`,undefined,403);
await empleado.pedir(`pdf&id=${factura.id}`,undefined,403);
assert.equal((await empleado.pedir('invoices')).facturas.length,0);
// Editar mantiene el mismo cliente y solo afecta a facturas nuevas.
const editado = {...comprador,nombre:'Cliente actualizado',correo:'nuevo@example.com',telefono:'22223333'};
await empleado.pedir('save_client',{...editado,correo:'correo-invalido'},422);
assert.equal((await admin.pedir('catalogs')).clientes.find(c=>c.id === comprador.id).correo,'cliente@example.com');
await empleado.pedir('save_client',{...editado,id:999999},404);
await empleado.pedir('save_client',{...editado,id:0},422);
await empleado.pedir('save_client',editado);
await empleado.pedir('save_client',editado); // Guardar sin cambios también es válido.
const clientesActualizados = (await admin.pedir('catalogs')).clientes;
assert.equal(clientesActualizados.length,catalogo.clientes.length);
assert.equal(clientesActualizados.find(c=>c.id === comprador.id).correo,'nuevo@example.com');
assert.equal(clientesActualizados.find(c=>c.id === comprador.id).telefono,'22223333');
assert.equal((await admin.pedir(`invoice&id=${factura.id}`)).factura.cliente_correo,'cliente@example.com');
const facturaNueva = await admin.pedir('save_invoice',{...venta,solicitud_id:'solicitud-cliente-editado'});
assert.equal((await admin.pedir(`invoice&id=${facturaNueva.id}`)).factura.cliente_correo,'nuevo@example.com');
for (const cantidad of [1,2,9999]) {
    const entera = await admin.pedir('save_invoice',{...venta,solicitud_id:`solicitud-entera-${cantidad}`,detalles:[{producto_id:producto.id,cantidad}]});
    const guardada = (await admin.pedir(`invoice&id=${entera.id}`)).factura;
    assert.equal(Number(guardada.detalles[0].cantidad),cantidad);
    assert.equal(Number(guardada.total),Math.round(79 * cantidad * 100) / 100);
    assert.equal(Number(guardada.detalles[0].total_linea),Number(guardada.total));
}
for (const detalles of [[null], ...[0,-1,1.5,'1.5',0.01,0.29,1.000000001,0.001,1.234,9999.01].map(cantidad=>[{producto_id:producto.id,cantidad}]), [venta.detalles[0],venta.detalles[0]]]) {
    await admin.pedir('save_invoice',{...venta,solicitud_id:'solicitud-prueba-invalida',detalles},422);
}
await admin.pedir('save_invoice',{...venta,solicitud_id:'solicitud-prueba-desborde',detalles:[{producto_id:producto.id,cantidad:10000}]},422);
await admin.pedir('save_user',{nombre:[],usuario:'invalido'},422);
await admin.pedir('retry_mail',{id:999999},404);
const pdf = await fetch(`${base}?action=pdf&id=${factura.id}`,{headers:{Cookie:admin.cookie}});
assert.equal(pdf.status,200);
assert.ok((await pdf.text()).startsWith('%PDF-1.4'));
// Cambiar el horario después del login debe impedir la siguiente operación.
const panel = await admin.pedir('admin');
const u = panel.usuarios.find(u=>u.usuario === 'empleado_prueba');
const administrador = panel.usuarios.find(u=>u.usuario === 'admin_prueba');
await admin.pedir('save_user',{...administrador,activo:false},422);
await admin.pedir('save_user',{...administrador,clave:'',dias:'',hora_inicio:'',hora_fin:'',fecha_hasta:'2000-01-01'});
assert.equal((await admin.pedir('admin')).usuarios.find(u=>u.id === administrador.id).fecha_hasta,null);
await admin.pedir('save_user',{...u,clave:'',hora_inicio:'08:00',hora_fin:'09:00',fecha_hasta:'2000-01-01'});
const denegado = await empleado.pedir('catalogs',undefined,403);
assert.equal(denegado.sesion_finalizada,true);
await empleado.pedir('catalogs',undefined,401);
await admin.pedir('save_user',{...u,clave:'',hora_inicio:'00:00',hora_fin:'23:59',fecha_hasta:''});
await empleado.pedir('login',{usuario:'empleado_prueba',clave:'1234'});
await admin.pedir('save_user',{...u,clave:'',hora_inicio:'08:00',hora_fin:'09:00',fecha_hasta:'2000-01-01'});
await empleado.pedir('logout',{}); // El cierre sigue permitido fuera del horario.
await admin.pedir('logout',{});
await admin.pedir('catalogs',undefined,401);
console.log(`${comprobaciones} respuestas HTTP y contenido de facturas, permisos y PDF verificados.`);
