<?php
/* Acciones del panel: empleados, clientes, auditoría y reintentos de correo. */
function accionesPanel(string $accion, PDO $pdo, array $usuario): void {
    switch ($accion) {
        case 'logout':
            requerirPost();
            $_SESSION = [];
            session_destroy();
            responder(['success'=>true]);
        case 'admin':
            requerirAdmin($usuario);
            responder(['success'=>true,
                'correo_configuracion'=>configuracionCorreo($pdo),
                'usuarios'=>$pdo->query('SELECT id,nombre,usuario,carnet,rol,activo,fecha_nacimiento,dias,hora_inicio,hora_fin,fecha_desde,fecha_hasta FROM vendedores ORDER BY nombre')->fetchAll(PDO::FETCH_ASSOC),
                'accesos'=>$pdo->query('SELECT * FROM accesos ORDER BY id DESC LIMIT 100')->fetchAll(PDO::FETCH_ASSOC),
                'correos'=>$pdo->query('SELECT id,destinatario,asunto,estado,intentos FROM correos ORDER BY id DESC LIMIT 100')->fetchAll(PDO::FETCH_ASSOC)]);
        /* Solo el admin puede elegir quién recibe alertas o probar el envío. */
        case 'save_mail_config':
            requerirAdmin($usuario);
            requerirPost();
            guardarConfiguracionCorreo($pdo,datosJson(),(int)$usuario['id']);
            responder(['success'=>true,'message'=>'Destinatario guardado. Las alertas nuevas y pendientes llegarán solo a este correo, sin copias.']);
        case 'test_mail':
            requerirAdmin($usuario);
            requerirPost();
            $config = configuracionCorreo($pdo);
            if (!filter_var($config['jefe'],FILTER_VALIDATE_EMAIL) || !filter_var($config['remitente'],FILTER_VALIDATE_EMAIL)) {
                responder(['success'=>false,'message'=>'Primero guarde el correo de alertas y el correo remitente.'],422);
            }
            if (!$config['smtp_preparado']) responder(['success'=>false,'message'=>$config['smtp_mensaje']],422);
            $id = encolarCorreo($pdo,$config['jefe'],'Prueba de alertas de facturación',
                "Este es un correo de prueba del sistema de facturación.\nDestinatario de alertas: {$config['jefe']}\nSolicitado por: {$usuario['usuario']}\nFecha: " . date('Y-m-d H:i:s') . ' (America/El_Salvador)');
            $estado = enviarCorreo($pdo,$id);
            responder(['success'=>true,'estado'=>$estado,'message'=>$estado === 'enviado'
                ? 'El servidor de correo aceptó la prueba. Revise la bandeja de entrada y spam del destinatario.'
                : 'No se pudo enviar la prueba. El mensaje quedó guardado; falta revisar la configuración del servicio de correo.']);
        case 'save_user':
            requerirAdmin($usuario);
            requerirPost();
            $d = datosJson();
            $id = empty($d['id']) ? 0 : idValido($d['id']);
            $nombre = textoCampo($d, 'nombre', 100, true);
            $login = textoCampo($d, 'usuario', 50, true);
            $carnet = textoCampo($d, 'carnet', 50);
            $rol = $d['rol'] ?? 'usuario';
            if ($rol === 'admin') {
                // Compatibilidad con las columnas existentes; el rol omite estas restricciones.
                $d['dias'] = '1,2,3,4,5,6,7';
                $d['hora_inicio'] = '00:00';
                $d['hora_fin'] = '23:59';
                $d['fecha_desde'] = $d['fecha_hasta'] = '';
            }
            $dias = $d['dias'] ?? '';
            $inicio = $d['hora_inicio'] ?? '';
            $fin = $d['hora_fin'] ?? '';
            $nacimiento = $d['fecha_nacimiento'] ?? '';
            if (!$nombre || strlen($nombre)>100 || !preg_match('/^[a-zA-Z0-9_.-]{3,50}$/',$login) || !in_array($rol,['usuario','admin'],true) ||
                strlen($dias)>20 || !preg_match('/^[1-7](,[1-7])*$/',$dias) || !preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/',$inicio) ||
                !preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/',$fin) || $inicio === $fin || !fechaValida($nacimiento) || $nacimiento > date('Y-m-d')) responder(['success'=>false,'message'=>'Revise nombre, usuario, fecha, días y horario.'],422);
            $desde = ($d['fecha_desde'] ?? '') ?: null;
            $hasta = ($d['fecha_hasta'] ?? '') ?: null;
            if (($desde && !fechaValida($desde)) || ($hasta && !fechaValida($hasta)) || ($desde && $hasta && $desde>$hasta)) responder(['success'=>false,'message'=>'Período de fechas inválido.'],422);
            $activo = filter_var($d['activo'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($activo === null) throw new InvalidArgumentException('Estado de cuenta inválido.');
            $activo = (int)$activo;
            if ($id === (int)$usuario['id'] && (!$activo || $rol !== 'admin')) responder(['success'=>false,'message'=>'No puede bloquear ni quitar su propio rol admin.'],422);
            if ($id) {
                $existe = $pdo->prepare('SELECT id FROM vendedores WHERE id=?');
                $existe->execute([$id]);
                if (!$existe->fetchColumn()) responder(['success'=>false,'message'=>'Usuario no encontrado.'],404);
            }
            $clave = $d['clave'] ?? '';
            if (!is_string($clave) || strlen($clave)>255) throw new InvalidArgumentException('La contraseña admite hasta 255 bytes.');
            if ((!$id || $clave !== '') && trim($clave) === '') responder(['success'=>false,'message'=>'La contraseña es obligatoria.'],422);
            $valores = [$nombre,$login,$rol,$carnet,$nacimiento,$dias,$inicio,$fin,$desde,$hasta,$activo];
            if ($id) {
                $sql = 'UPDATE vendedores SET nombre=?,usuario=?,rol=?,carnet=?,fecha_nacimiento=?,dias=?,hora_inicio=?,hora_fin=?,fecha_desde=?,fecha_hasta=?,activo=?';
                if ($clave !== '') { $sql .= ',clave=?'; $valores[] = $clave; }
                $valores[] = $id;
                $pdo->prepare($sql . ' WHERE id=?')->execute($valores);
            } else {
                $valores[] = $clave;
                $pdo->prepare('INSERT INTO vendedores (nombre,usuario,rol,carnet,fecha_nacimiento,dias,hora_inicio,hora_fin,fecha_desde,fecha_hasta,activo,clave) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')->execute($valores);
            }
            responder(['success'=>true,'message'=>'Usuario guardado.']);
        case 'save_branch':
            requerirPost();
            requerirAdmin($usuario);
            $d = datosJson();
            $nombre = textoCampo($d, 'nombre', 100, true);
            $direccion = textoCampo($d, 'direccion', 255, true);
            $pdo->prepare('INSERT INTO sucursales (nombre,direccion) VALUES (?,?)')->execute([$nombre,$direccion]);
            responder(['success'=>true,'message'=>'Sucursal registrada.']);
        case 'save_client':
            requerirPost();
            $d = datosJson();
            $nombre = textoCampo($d, 'nombre', 160, true);
            $id = !array_key_exists('id', $d) || $d['id'] === '' ? 0 : idValido($d['id']);
            $correo = textoCampo($d, 'correo', 254, true);
            if (!$nombre || strlen($nombre)>160 || !filter_var($correo,FILTER_VALIDATE_EMAIL)) responder(['success'=>false,'message'=>'Nombre y correo válido obligatorios.'],422);
            $valores = [$nombre,textoCampo($d,'telefono',30),$correo,textoCampo($d,'nit',30),textoCampo($d,'nrc',30)];
            if ($id) {
                $consulta = $pdo->prepare('SELECT id FROM clientes WHERE id=? AND activo=1');
                $consulta->execute([$id]);
                if (!$consulta->fetchColumn()) responder(['success'=>false,'message'=>'Cliente no encontrado.'],404);
                $valores[] = $id;
                // Las facturas anteriores conservan los datos registrados al emitirlas.
                $pdo->prepare('UPDATE clientes SET nombre=?,telefono=?,correo=?,nit=?,nrc=? WHERE id=? AND activo=1')->execute($valores);
            } else {
                $pdo->prepare('INSERT INTO clientes (nombre,telefono,correo,nit,nrc) VALUES (?,?,?,?,?)')->execute($valores);
            }
            responder(['success'=>true,'message'=>$id ? 'Cliente actualizado.' : 'Cliente registrado.']);
        case 'retry_mail':
            requerirPost();
            requerirAdmin($usuario);
            $d = datosJson();
            $id = idValido($d['id'] ?? 0);
            $consulta = $pdo->prepare('SELECT id FROM correos WHERE id=?');
            $consulta->execute([$id]);
            if (!$consulta->fetchColumn()) responder(['success'=>false,'message'=>'Correo no encontrado.'],404);
            $config = configuracionCorreo($pdo);
            if (filter_var($config['jefe'],FILTER_VALIDATE_EMAIL)) $pdo->prepare("UPDATE correos SET destinatario=? WHERE id=? AND destinatario='' AND adjunto IS NULL")->execute([$config['jefe'],$id]);
            responder(['success'=>true,'message'=>'Estado: ' . enviarCorreo($pdo,$id)]);
        case 'pdf':
            $id = idValido($_GET['id'] ?? 0);
            $stmt = $pdo->prepare('SELECT vendedor_id,numero FROM facturas WHERE id=?');
            $stmt->execute([$id]);
            $f = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$f) responder(['success'=>false,'message'=>'Factura no encontrada.'],404);
            if ($usuario['rol'] !== 'admin' && (int)$f['vendedor_id'] !== (int)$usuario['id']) responder(['success'=>false,'message'=>'Acceso denegado.'],403);
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $f['numero'] . '.pdf"');
            echo pdfFactura($pdo,$id);
            exit;
    }
}
