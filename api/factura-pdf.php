<?php
/* Facturas A4 con identidad Minina. Sin dependencias ni datos del navegador. */
function pdfFactura(PDO $pdo, int $id): string {
    $stmt = $pdo->prepare('SELECT f.*,s.nombre sucursal,v.nombre vendedor FROM facturas f JOIN sucursales s ON s.id=f.sucursal_id JOIN vendedores v ON v.id=f.vendedor_id WHERE f.id=?');
    $stmt->execute([$id]);
    $factura = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$factura) throw new RuntimeException('Factura no encontrada.');
    $stmt = $pdo->prepare('SELECT * FROM factura_detalles WHERE factura_id=? ORDER BY id');
    $stmt->execute([$id]);
    return renderizarFacturaMinina($factura, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

/* Separar el dibujo de la consulta permite comprobar paginación sin MySQL. */
function renderizarFacturaMinina(array $f, array $detalles): string {
    $ink = '0.094 0.094 0.094'; $paper = '1 0.98 0.941'; $yellow = '1 0.855 0.271';
    $encode = static function ($text): string {
        $text = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string)$text);
        return iconv('UTF-8', 'Windows-1252//TRANSLIT', $text) ?: '';
    };
    // Anchos Helvetica en milésimas de em; acentos conservan el ancho de su letra.
    $metrics = [278,278,355,556,556,889,667,191,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,278,278,584,584,584,556,1015,667,667,722,722,667,611,778,722,278,500,667,556,833,722,778,667,778,722,667,611,722,667,944,667,667,611,278,278,278,469,556,333,556,556,500,556,556,278,556,556,222,222,500,222,833,556,556,556,556,333,500,278,556,500,722,500,500,500,334,260,334,584];
    $width = static function (string $text, float $size, bool $bold = false) use ($metrics): float {
        $ascii = iconv('Windows-1252', 'ASCII//TRANSLIT', $text);
        $units = 0;
        for ($i=0; $i<strlen($ascii); $i++) $units += $metrics[ord($ascii[$i])-32] ?? 600;
        return $units * $size / 1000 * ($bold ? 1.08 : 1);
    };
    $wrap = static function ($value, float $max, float $size = 10) use ($encode, $width): array {
        $text = trim($encode($value)); $lines = []; $line = '';
        foreach (preg_split('/\s+/', $text) as $word) {
            if ($line !== '' && $width($line . ' ' . $word, $size) > $max) { $lines[] = $line; $line = ''; }
            while ($width($word, $size) > $max) {
                $cut = 1;
                while ($cut < strlen($word) && $width(substr($word,0,$cut+1),$size) <= $max) $cut++;
                if ($line !== '') { $lines[] = $line; $line = ''; }
                $lines[] = substr($word,0,$cut); $word = substr($word,$cut);
            }
            $line .= ($line === '' ? '' : ' ') . $word;
        }
        $lines[] = $line;
        return $lines;
    };
    $stream = ''; $pages = [];
    $rect = static function ($x,$top,$w,$h,$color) use (&$stream): void {
        $stream .= "$color rg $x " . (842-$top-$h) . " $w $h re f\n";
    };
    $line = static function ($x,$top,$x2,$top2,$color='0.094 0.094 0.094',$thickness=1) use (&$stream): void {
        $stream .= "$color RG $thickness w $x " . (842-$top) . " m $x2 " . (842-$top2) . " l S\n";
    };
    $text = static function ($value,$x,$top,$size=10,$bold=false,$color='0.094 0.094 0.094',$right=false,$encoded=false) use (&$stream,$encode,$width): void {
        $value = $encoded ? $value : $encode($value);
        if ($right) $x -= $width($value,$size,$bold);
        $escaped = str_replace(['\\','(',')'],['\\\\','\\(','\\)'],$value);
        $font = $bold ? 'F2' : 'F1';
        $stream .= "BT /$font $size Tf $color rg 1 0 0 1 $x " . (842-$top) . " Tm ($escaped) Tj ET\n";
    };
    $block = static function ($value,$x,$top,$max,$size=10) use ($wrap,$text): float {
        foreach ($wrap($value,$max,$size) as $part) { $text($part,$x,$top,$size,false,'0.094 0.094 0.094',false,true); $top += $size+4; }
        return $top;
    };
    $money = static fn($value): string => '$' . number_format((float)$value,2,'.',',');
    $header = static function (bool $continued=false) use (&$stream,$rect,$line,$text,$block,$f,$ink,$paper,$yellow): float {
        $stream = ''; $rect(0,0,595,842,$paper); $rect(36,34,50,50,$yellow);
        // Gato vectorial inspirado en la marca de la interfaz.
        $stream .= "$ink rg 44 779 m 44 801 l 55 793 l 67 793 l 78 801 l 78 779 l 78 764 44 764 44 779 c f\n";
        $line(52,63,55,63,$yellow,2); $line(68,63,71,63,$yellow,2);
        $line(59,70,61,72,$yellow,2); $line(61,72,64,70,$yellow,2);
        $text('PAPELERÍA GRÁFICA',100,46,9,true); $text('Minina',98,78,32,true);
        $rect(209,72,6,6,'0.471 0.263 0.82');
        $text($f['tipo']==='credito' ? 'CRÉDITO FISCAL' : 'FACTURA',559,50,16,true,$ink,true);
        $end = $block($f['numero'],370,70,189,11);
        $text('Fecha: ' . $f['fecha'],559,$end+2,10,false,$ink,true);
        $top = max(110,$end+18); $line(36,$top,559,$top,$ink,2);
        $text($f['tipo']==='credito' ? 'COMPROBANTE DE CRÉDITO FISCAL' : 'FACTURA CONSUMIDOR FINAL',36,$top+23,10,true);
        if ($continued) { $text('Detalle de productos · continuación',36,$top+46,10); return $top+62; }
        $top += 47;
        $text('FACTURAR A',36,$top,9,true); $text('ATENDIDO POR',330,$top,9,true);
        $left = $block($f['cliente'],36,$top+20,270,12);
        foreach (['telefono'=>'Teléfono','cliente_correo'=>'Correo','cliente_nit'=>'NIT','cliente_nrc'=>'NRC'] as $field=>$label) {
            if (!empty($f[$field])) $left = $block($label . ': ' . $f[$field],36,$left+3,270,10);
        }
        $right = $block('Sucursal: ' . $f['sucursal'],330,$top+20,229,10);
        $right = $block('Vendedor: ' . $f['vendedor'],330,$right+4,229,10);
        return max($left,$right)+20;
    };
    $table = static function ($top) use ($rect,$text,$yellow): float {
        $rect(36,$top,523,28,$yellow);
        $text('CÓDIGO / DESCRIPCIÓN',46,$top+18,9,true);
        $text('CANT.',370,$top+18,9,true,'0.094 0.094 0.094',true);
        $text('PRECIO',463,$top+18,9,true,'0.094 0.094 0.094',true);
        $text('TOTAL',549,$top+18,9,true,'0.094 0.094 0.094',true);
        return $top+28;
    };
    $top = $table($header());
    foreach ($detalles as $index=>$d) {
        $rows = array_merge($wrap($d['codigo_producto'],275,9),$wrap($d['descripcion'],275,10));
        // Continuar incluso una descripción excepcionalmente extensa sin recortarla.
        $first = true;
        while ($rows) {
            $available = (int)floor((748-$top-16)/14);
            if ($available < 1) { $pages[]=$stream; $top=$table($header(true)); continue; }
            if (count($rows)>$available && $top>300 && count($rows)<=30) { $pages[]=$stream; $top=$table($header(true)); continue; }
            $chunk = array_splice($rows,0,$available); $height = count($chunk)*14+16;
            if ($index%2===0) $rect(36,$top,523,$height,'1 0.996 0.984');
            foreach ($chunk as $n=>$part) $text($part,46,$top+18+$n*14,$first && $n===0 ? 9 : 10,false,$first && $n===0 ? '0.4 0.4 0.4' : $ink,false,true);
            if ($first) {
                $quantity = rtrim(rtrim(number_format((float)$d['cantidad'],2,'.',','),'0'),'.');
                $text($quantity,370,$top+18,10,false,$ink,true);
                $text($money($d['precio_unitario']),463,$top+18,10,false,$ink,true);
                $text($money($d['total_linea']),549,$top+18,10,false,$ink,true);
            }
            $top += $height; $line(36,$top,559,$top,'0.84 0.82 0.77',0.5); $first=false;
        }
    }
    if (!$detalles) { $text('Sin productos registrados.',46,$top+23,10); $top+=42; }
    if ($top+144>748) { $pages[]=$stream; $top=$header(true); }
    $top+=20;
    $text('Base sin IVA',330,$top+16,10); $text($money($f['subtotal']),549,$top+16,10,false,$ink,true);
    $text('IVA incluido (' . rtrim(rtrim(number_format((float)$f['iva_porcentaje'],2),'0'),'.') . '%)',330,$top+40,10);
    $text($money($f['iva']),549,$top+40,10,false,$ink,true);
    $rect(326,$top+56,233,46,$ink); $text('TOTAL A PAGAR',338,$top+74,9,true,$yellow);
    $text($money($f['total']),547,$top+91,19,true,'1 1 1',true);
    $text('Gracias por elegir Minina.',36,$top+122,12,true);
    $pages[]=$stream;
    $objects=[1=>'<< /Type /Catalog /Pages 2 0 R >>',2=>'',3=>'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',4=>'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>'];
    $kids=[];
    foreach ($pages as $i=>$content) {
        $stream=$content; $line(36,780,559,780,$ink,1);
        $text('MININA · PAPELERÍA GRÁFICA',36,801,8,true);
        $text('Página ' . ($i+1) . ' de ' . count($pages),559,801,9,false,$ink,true);
        $pageId=5+$i*2; $streamId=$pageId+1; $kids[]="$pageId 0 R";
        $objects[$pageId]="<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents $streamId 0 R >>";
        $objects[$streamId]='<< /Length ' . strlen($stream) . ">>\nstream\n$stream\nendstream";
    }
    $objects[2]='<< /Type /Pages /Kids [' . implode(' ',$kids) . '] /Count ' . count($pages) . ' >>';
    $pdf="%PDF-1.4\n"; $offsets=[0];
    foreach ($objects as $number=>$object) { $offsets[$number]=strlen($pdf); $pdf.="$number 0 obj\n$object\nendobj\n"; }
    $xref=strlen($pdf); $pdf.="xref\n0 " . (count($objects)+1) . "\n0000000000 65535 f \n";
    foreach ($objects as $number=>$object) $pdf.=sprintf('%010d 00000 n ',$offsets[$number]) . "\n";
    return $pdf . 'trailer << /Size ' . (count($objects)+1) . " /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
}
