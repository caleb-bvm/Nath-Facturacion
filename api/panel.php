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
            requerirSupremo($usuario);
            responder(['success'=>true,
                'correo_configuracion'=>configuracionCorreo($pdo),
                'usuarios'=>$pdo->query('SELECT id,nombre,usuario,carnet,rol,activo,fecha_nacimiento,dias,hora_inicio,hora_fin,fecha_desde,fecha_hasta FROM vendedores ORDER BY nombre')->fetchAll(PDO::FETCH_ASSOC),
                'accesos'=>$pdo->query('SELECT * FROM accesos ORDER BY id DESC LIMIT 100')->fetchAll(PDO::FETCH_ASSOC),
                'correos'=>$pdo->query('SELECT id,destinatario,asunto,estado,intentos FROM correos ORDER BY id DESC LIMIT 100')->fetchAll(PDO::FETCH_ASSOC)]);
        /* Solo el supremo puede elegir quién recibe alertas o probar el envío. */
        case 'save_mail_config':
            requerirSupremo($usuario);
            requerirPost();
            guardarConfiguracionCorreo($pdo,datosJson(),(int)$usuario['id']);
            responder(['success'=>true,'message'=>'Destinatario guardado. Las alertas nuevas y pendientes llegarán solo a este correo, sin copias.']);
        case 'test_mail':
            requerirSupremo($usuario);
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
            requerirSupremo($usuario);
            requerirPost();
            $d = datosJson();
            $id = (int)($d['id'] ?? 0);
            $nombre = trim($d['nombre'] ?? '');
            $login = trim($d['usuario'] ?? '');
            $rol = $d['rol'] ?? 'usuario';
            $dias = $d['dias'] ?? '';
            $inicio = $d['hora_inicio'] ?? '';
            $fin = $d['hora_fin'] ?? '';
            $nacimiento = $d['fecha_nacimiento'] ?? '';
            if (!$nombre || strlen($nombre)>100 || !preg_match('/^[a-zA-Z0-9_.-]{3,50}$/',$login) || !in_array($rol,['usuario','supremo'],true) ||
                !preg_match('/^[1-7](,[1-7])*$/',$dias) || !preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/',$inicio) ||
                !preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/',$fin) || $inicio === $fin || !fechaValida($nacimiento) || $nacimiento > date('Y-m-d')) responder(['success'=>false,'message'=>'Revise nombre, usuario, fecha, días y horario.'],422);
            $desde = ($d['fecha_desde'] ?? '') ?: null;
            $hasta = ($d['fecha_hasta'] ?? '') ?: null;
            if (($desde && !fechaValida($desde)) || ($hasta && !fechaValida($hasta)) || ($desde && $hasta && $desde>$hasta)) responder(['success'=>false,'message'=>'Período de fechas inválido.'],422);
            $activo = !empty($d['activo']) ? 1 : 0;
            if ($id === (int)$usuario['id'] && (!$activo || $rol !== 'supremo')) responder(['success'=>false,'message'=>'No puede bloquear ni quitar su propio rol supremo.'],422);
            $clave = $d['clave'] ?? '';
            if ((!$id || $clave !== '') && strlen($clave)<12) responder(['success'=>false,'message'=>'La contraseña debe tener al menos 12 caracteres.'],422);
            $valores = [$nombre,$login,$rol,trim($d['carnet'] ?? ''),$nacimiento,$dias,$inicio,$fin,$desde,$hasta,$activo];
            if ($id) {
                $sql = 'UPDATE vendedores SET nombre=?,usuario=?,rol=?,carnet=?,fecha_nacimiento=?,dias=?,hora_inicio=?,hora_fin=?,fecha_desde=?,fecha_hasta=?,activo=?';
                if ($clave !== '') { $sql .= ',clave=?'; $valores[] = password_hash($clave,PASSWORD_DEFAULT); }
                $valores[] = $id;
                $pdo->prepare($sql . ' WHERE id=?')->execute($valores);
            } else {
                $valores[] = password_hash($clave,PASSWORD_DEFAULT);
                $pdo->prepare('INSERT INTO vendedores (nombre,usuario,rol,carnet,fecha_nacimiento,dias,hora_inicio,hora_fin,fecha_desde,fecha_hasta,activo,clave) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')->execute($valores);
            }
            responder(['success'=>true,'message'=>'Usuario guardado.']);
        case 'save_client':
            requerirPost();
            $d = datosJson();
            $nombre = trim($d['nombre'] ?? '');
            $correo = trim($d['correo'] ?? '');
            if (!$nombre || strlen($nombre)>160 || !filter_var($correo,FILTER_VALIDATE_EMAIL)) responder(['success'=>false,'message'=>'Nombre y correo válido obligatorios.'],422);
            $pdo->prepare('INSERT INTO clientes (nombre,telefono,correo,nit,nrc) VALUES (?,?,?,?,?)')->execute([$nombre,substr(trim($d['telefono'] ?? ''),0,30),$correo,substr(trim($d['nit'] ?? ''),0,30),substr(trim($d['nrc'] ?? ''),0,30)]);
            responder(['success'=>true,'message'=>'Cliente registrado.']);
        case 'retry_mail':
            requerirPost();
            requerirSupremo($usuario);
            $d = datosJson();
            $id = (int)($d['id'] ?? 0);
            $config = configuracionCorreo($pdo);
            if (filter_var($config['jefe'],FILTER_VALIDATE_EMAIL)) $pdo->prepare("UPDATE correos SET destinatario=? WHERE id=? AND destinatario='' AND adjunto IS NULL")->execute([$config['jefe'],$id]);
            responder(['success'=>true,'message'=>'Estado: ' . enviarCorreo($pdo,$id)]);
        case 'pdf':
            $id = (int)($_GET['id'] ?? 0);
            $stmt = $pdo->prepare('SELECT vendedor_id,numero FROM facturas WHERE id=?');
            $stmt->execute([$id]);
            $f = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$f) responder(['success'=>false,'message'=>'Factura no encontrada.'],404);
            if ($usuario['rol'] !== 'supremo' && (int)$f['vendedor_id'] !== (int)$usuario['id']) responder(['success'=>false,'message'=>'Acceso denegado.'],403);
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $f['numero'] . '.pdf"');
            echo pdfFactura($pdo,$id);
            exit;
    }
}
/* Validación estricta: evita fechas que PHP normaliza silenciosamente. */
function fechaValida(string $valor): bool {
    $fecha = DateTimeImmutable::createFromFormat('!Y-m-d',$valor);
    return $fecha && $fecha->format('Y-m-d') === $valor;
}
