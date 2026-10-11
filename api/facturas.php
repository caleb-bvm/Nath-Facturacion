<?php
/* Una transacción guarda cabecera, detalles y correo juntos.
 * La clave de solicitud permite repetir un envío sin duplicar la venta. */
function facturaPorSolicitud(PDO $pdo, string $solicitud, int $vendedor): ?array {
    $stmt = $pdo->prepare('SELECT id,numero,vendedor_id FROM facturas WHERE solicitud_id=?');
    $stmt->execute([$solicitud]);
    $factura = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$factura) return null;
    if ((int)$factura['vendedor_id'] !== $vendedor) throw new InvalidArgumentException('La solicitud pertenece a otro usuario.');
    return ['success'=>true, 'id'=>$factura['id'], 'numero'=>$factura['numero'],
        'message'=>'La factura ya estaba registrada. Puede consultar el envío en administración.'];
}

function guardarFactura(PDO $pdo, array $datos, int $vendedor): array {
    $solicitud = textoCampo($datos, 'solicitud_id', 64, true);
    if (!preg_match('/^[a-zA-Z0-9-]{16,64}$/', $solicitud)) throw new InvalidArgumentException('Solicitud de factura inválida.');
    $anterior = facturaPorSolicitud($pdo, $solicitud, $vendedor);
    if ($anterior) return $anterior;
    $clienteId = idValido($datos['cliente_id'] ?? 0);
    $sucursalId = idValido($datos['sucursal_id'] ?? 0);
    $tipo = textoCampo($datos, 'tipo', 20, true);
    $detalles = $datos['detalles'] ?? [];
    if (!in_array($tipo, ['consumidor','credito'], true) || !is_array($detalles) ||
        !array_is_list($detalles) || count($detalles) < 1 || count($detalles) > 200) {
        throw new InvalidArgumentException('Revise el tipo y los productos de la factura (1 a 200 líneas).');
    }
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM clientes WHERE id=? AND activo=1 LOCK IN SHARE MODE');
        $stmt->execute([$clienteId]);
        $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$cliente) throw new InvalidArgumentException('Seleccione un cliente registrado.');
        if ($tipo === 'credito' && (trim($cliente['nit']) === '' || trim($cliente['nrc']) === '')) {
            throw new InvalidArgumentException('Crédito fiscal requiere NIT y NRC del cliente.');
        }
        $stmt = $pdo->prepare('SELECT id FROM sucursales WHERE id=? AND activo=1 LOCK IN SHARE MODE');
        $stmt->execute([$sucursalId]);
        if (!$stmt->fetchColumn()) throw new InvalidArgumentException('Seleccione una sucursal activa.');
        $lineas = [];
        $centavos = 0;
        $consulta = $pdo->prepare('SELECT * FROM productos WHERE id=? AND activo=1 LOCK IN SHARE MODE');
        foreach ($detalles as $detalle) {
            if (!is_array($detalle)) throw new InvalidArgumentException('Detalle de factura inválido.');
            $id = idValido($detalle['producto_id'] ?? 0);
            $cantidad = cantidadValida($detalle['cantidad'] ?? 0);
            if (isset($lineas[$id])) throw new InvalidArgumentException('Producto repetido.');
            $consulta->execute([$id]);
            $producto = $consulta->fetch(PDO::FETCH_ASSOC);
            if (!$producto || (float)$producto['precio'] <= 0) throw new InvalidArgumentException('Producto inactivo o precio inválido.');
            $importe = calcularCentavosLinea((float)$producto['precio'], $cantidad);
            $centavos += $importe;
            // DECIMAL(10,2) admite hasta 99,999,999.99.
            if ($centavos > 9999999999) throw new InvalidArgumentException('El total supera el máximo permitido.');
            $lineas[$id] = [$producto, $cantidad, $importe / 100];
        }
        $importes = calcularImportesFactura($centavos / 100);
        $stmt = $pdo->prepare('INSERT INTO facturas
            (numero,solicitud_id,fecha,vendedor_id,sucursal_id,cliente,telefono,subtotal,iva_porcentaje,iva,total,comision_porcentaje,comision,tipo,cliente_correo,cliente_nit,cliente_nrc)
            VALUES (?,?,?, ?,?,?,?, ?,13,?,?,?, ?,?,?,?,?)');
        $stmt->execute(['BORRADOR-' . bin2hex(random_bytes(16)), $solicitud, date('Y-m-d'), $vendedor,
            $sucursalId, $cliente['nombre'], $cliente['telefono'], $importes['subtotal'], $importes['iva'],
            $importes['total'], $importes['comision_porcentaje'], $importes['comision'], $tipo,
            $cliente['correo'], $cliente['nit'], $cliente['nrc']]);
        $id = (int)$pdo->lastInsertId();
        // AUTO_INCREMENT asigna un identificador único incluso con ventas simultáneas.
        $numero = 'FAC-' . str_pad((string)$id, 8, '0', STR_PAD_LEFT);
        $pdo->prepare('UPDATE facturas SET numero=? WHERE id=?')->execute([$numero, $id]);
        $stmt = $pdo->prepare('INSERT INTO factura_detalles
            (factura_id,producto_id,codigo_producto,descripcion,cantidad,precio_unitario,total_linea) VALUES (?,?,?,?,?,?,?)');
        foreach ($lineas as [$producto, $cantidad, $importe]) {
            $stmt->execute([$id, $producto['id'], $producto['codigo'], $producto['descripcion'], $cantidad, $producto['precio'], $importe]);
        }
        $correo = encolarCorreo($pdo, $cliente['correo'], 'Factura ' . $numero, 'Adjuntamos su factura ' . $numero,
            pdfFactura($pdo, $id), $numero . '.pdf');
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        // Dos peticiones simultáneas con la misma clave pueden llegar hasta el INSERT.
        if ($error instanceof PDOException && $error->getCode() === '23000') {
            $anterior = facturaPorSolicitud($pdo, $solicitud, $vendedor);
            if ($anterior) return $anterior;
        }
        throw $error;
    }
    $estado = enviarCorreo($pdo, $correo);
    return ['success'=>true, 'id'=>$id, 'numero'=>$numero, 'correo_estado'=>$estado,
        'message'=>'Factura guardada. Correo: ' . $estado];
}
