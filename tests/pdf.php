<?php
require __DIR__ . '/../api/factura-pdf.php';
$f = ['tipo'=>'consumidor','numero'=>'FAC-2026-000042','fecha'=>'2026-10-11','cliente'=>'María José López','telefono'=>'7777-1234','cliente_correo'=>'maria@example.com','cliente_nit'=>'0614-123456-101-2','cliente_nrc'=>'123456-7','sucursal'=>'Casa matriz','vendedor'=>'Ana García','subtotal'=>'34.51','iva_porcentaje'=>'13','iva'=>'4.49','total'=>'39.00'];
$items = [
 ['codigo_producto'=>'PAP-001','descripcion'=>'Cuaderno universitario, 100 hojas rayadas','cantidad'=>'2.00','precio_unitario'=>'4.50','total_linea'=>'9.00'],
 ['codigo_producto'=>'ART-024','descripcion'=>'Set de marcadores de colores, punta fina','cantidad'=>'1.00','precio_unitario'=>'12.00','total_linea'=>'12.00'],
 ['codigo_producto'=>'IMP-003','descripcion'=>'Impresión a color en papel couché tamaño carta','cantidad'=>'12.50','precio_unitario'=>'1.44','total_linea'=>'18.00']
];
$path = __DIR__ . '/../output/pdf/';
if (!is_dir($path)) mkdir($path,0777,true);
file_put_contents($path . 'minina-factura-ejemplo.pdf',renderizarFacturaMinina($f,$items));
$f['tipo']='credito'; $f['numero']='CCF-2026-000043';
$f['cliente']=str_repeat('Comercial Gráfica Álvarez ',6);
$f['cliente_correo']=str_repeat('correoextenso',12).'@example.com';
$items=[];
for ($i=1;$i<=70;$i++) $items[]=['codigo_producto'=>'PROD-' . $i,'descripcion'=>"Producto $i: impresión y encuadernación profesional, papel de alta calidad y acabado especial para material educativo.",'cantidad'=>'1.25','precio_unitario'=>'12.00','total_linea'=>'15.00'];
$f['subtotal']='929.20'; $f['iva']='120.80'; $f['total']='1050.00';
$pdf=renderizarFacturaMinina($f,$items);
file_put_contents($path . 'minina-factura-extensa.pdf',$pdf);
if (substr($pdf,0,8)!=='%PDF-1.4') throw new Exception('Cabecera PDF inv?lida');
if (substr_count($pdf,'/Type /Page ')<2) throw new Exception('No se pagino la factura');
if (strpos($pdf,'Producto 70:')===false || strpos($pdf,'1,050.00')===false) throw new Exception('Contenido perdido');
if (strpos($pdf,'edad')!==false) throw new Exception('La factura incluye edad');
echo "PDF simple y multipagina generados correctamente.\n";
