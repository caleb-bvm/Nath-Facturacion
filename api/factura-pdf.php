<?php
/* PDF de texto paginado sin dependencias externas; importes guardados en servidor. */
function pdfFactura(PDO $pdo, int $id): string {
    $stmt = $pdo->prepare('SELECT f.*,s.nombre sucursal,v.nombre vendedor FROM facturas f JOIN sucursales s ON s.id=f.sucursal_id JOIN vendedores v ON v.id=f.vendedor_id WHERE f.id=?');
    $stmt->execute([$id]);
    $f = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt = $pdo->prepare('SELECT * FROM factura_detalles WHERE factura_id=? ORDER BY id');
    $stmt->execute([$id]);
    $lineas = ['PAPELERIA Y LIBRERIA EL ESTUDIANTE',
        $f['tipo']==='credito' ? 'COMPROBANTE DE CREDITO FISCAL' : 'FACTURA CONSUMIDOR FINAL',
        $f['numero'] . ' | Fecha: ' . $f['fecha'], 'Sucursal: ' . $f['sucursal'],
        'Vendedor: ' . $f['vendedor'], 'Cliente: ' . $f['cliente'], 'Telefono: ' . $f['telefono'],
        'Correo: ' . $f['cliente_correo'], 'NIT: ' . $f['cliente_nit'] . ' | NRC: ' . $f['cliente_nrc'], '', 'DETALLE DE PRODUCTOS'];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $d) {
        $texto = $d['codigo_producto'] . ' - ' . $d['descripcion'];
        $lineas[] = $texto;
        $lineas[] = 'Cantidad: ' . $d['cantidad'] . ' | Precio: $' . $d['precio_unitario'] . ' | Total: $' . $d['total_linea'];
        $lineas[] = '';
    }
    $lineas = array_merge($lineas,['','Base sin IVA: $' . $f['subtotal'],'IVA desglosado (' . $f['iva_porcentaje'] . '%): $' . $f['iva'],'TOTAL A PAGAR: $' . $f['total']]);
    /* Ajustar todas las líneas, incluidos los nombres largos de los clientes. */
    $lineasAjustadas = [];
    foreach ($lineas as $linea) {
        $linea = preg_replace('/[\r\n\t]+/u', ' ', $linea);
        // Dividir por caracteres Unicode, sin cortar los bytes de letras acentuadas.
        preg_match_all('/.{1,50}(?:\s+|$)|.{1,50}/u', $linea, $partes);
        foreach ($partes[0] ?: [''] as $parte) $lineasAjustadas[] = $parte;
    }
    $paginas = array_chunk($lineasAjustadas,45);
    $objetos = [1=>'<< /Type /Catalog /Pages 2 0 R >>',2=>'',3=>'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>'];
    $hijos = [];
    foreach ($paginas as $i=>$pagina) {
        $pageId = 4+$i*2; $streamId = $pageId+1;
        $hijos[] = "$pageId 0 R";
        $stream = "BT /F1 10.5 Tf 45 790 Td 16 TL\n";
        $pagina[] = 'Pagina ' . ($i+1) . ' / ' . count($paginas);
        foreach ($pagina as $linea) {
            $texto = iconv('UTF-8','Windows-1252//TRANSLIT',$linea);
            $texto = str_replace(['\\','(',')',"\r","\n"],['\\\\','\\(','\\)',' ',' '],$texto);
            $stream .= "($texto) Tj T*\n";
        }
        $stream .= 'ET';
        $objetos[$pageId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents $streamId 0 R >>";
        $objetos[$streamId] = '<< /Length ' . strlen($stream) . ">>\nstream\n$stream\nendstream";
    }
    $objetos[2] = '<< /Type /Pages /Kids [' . implode(' ',$hijos) . '] /Count ' . count($paginas) . ' >>';
    ksort($objetos);
    $pdf = "%PDF-1.4\n"; $offsets = [0];
    foreach ($objetos as $numero=>$objeto) { $offsets[$numero] = strlen($pdf); $pdf .= "$numero 0 obj\n$objeto\nendobj\n"; }
    $xref = strlen($pdf);
    $pdf .= 'xref' . "\n0 " . (count($objetos)+1) . "\n0000000000 65535 f \n";
    foreach ($objetos as $numero=>$objeto) $pdf .= sprintf('%010d 00000 n ',$offsets[$numero]) . "\n";
    return $pdf . 'trailer << /Size ' . (count($objetos)+1) . " /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
}
