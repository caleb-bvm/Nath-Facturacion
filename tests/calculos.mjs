import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import {execFileSync} from 'node:child_process';
const contexto = vm.createContext({});
for (const archivo of ['Calculo','CalculoMonetario','CalculoTotalLinea','CalculoIVA','CalculoSubtotal','CalculoTotalFactura','CalculoComision','CalculoFactura','CalculoEdad','CalculoCumpleanos']) {
    vm.runInContext(fs.readFileSync(`assets/js/calculos/${archivo}.js`,'utf8'), contexto);
}
const totales = [0.01,0.29,79,112.99,113,113.01,1130,1130.01,2260,2260.01,99999999.99];
for (let centavos=1;centavos<500000;centavos+=97) totales.push(centavos/100);
const php = process.env.PHP_BINARY || 'php';
const oficial = JSON.parse(execFileSync(php,['-r',
    "require 'api/calculos-factura.php'; echo json_encode(array_map('calcularImportesFactura',json_decode(stream_get_contents(STDIN),true)));"],
    {input:JSON.stringify(totales),encoding:'utf8'}));
totales.forEach((total,i)=> {
    const vista = vm.runInContext(`new CalculoFactura().calcular([{precio_unitario:${total},cantidad:1}])`,contexto);
    assert.equal(vista.total,oficial[i].total);
    assert.equal(vista.iva,oficial[i].iva);
    assert.equal(vista.subtotal,oficial[i].subtotal);
    assert.equal(vista.comision.monto,oficial[i].comision,`Comisión para ${total}`);
});
const lineas = [[79,1.5],[0.01,1.5],[0.29,0.5],[19.99,0.29],[79,0.01],[9999.99,9999]];
const importesLineas = JSON.parse(execFileSync(php,['-r',
    "require 'api/calculos-factura.php'; echo json_encode(array_map(fn($l) => calcularCentavosLinea(...$l),json_decode(stream_get_contents(STDIN),true)));"],
    {input:JSON.stringify(lineas),encoding:'utf8'}));
lineas.forEach(([precio,cantidad],i)=> {
    const total = vm.runInContext(`new CalculoTotalLinea().calcular(${precio},${cantidad})`,contexto);
    assert.equal(Math.round(total * 100),importesLineas[i],`Línea ${precio} x ${cantidad}`);
});
assert.equal(vm.runInContext('new CalculoTotalFactura().calcular([{precio_unitario:0.01,cantidad:1.5},{precio_unitario:0.29,cantidad:0.5}])',contexto),0.17);
assert.equal(vm.runInContext("new CalculoEdad().calcular('2000-02-29','2026-02-28').anos",contexto),26);
assert.ok(vm.runInContext("new CalculoCumpleanos().calcular('2000-02-29','2026-02-28')",contexto).startsWith('¡Feliz cumpleaños!'));
console.log(`${totales.length} importes coinciden entre PHP y JavaScript; edad y cumpleaños bisiestos correctos.`);
