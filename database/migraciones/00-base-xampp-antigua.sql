-- Solo para la versión antigua de XAMPP SIN clientes, roles, accesos ni correos.
-- Respaldar primero. No ejecutar sobre una instalación actual ni repetir.
-- Conserva usuarios, contraseñas y facturas. No asigna administradores.
ALTER TABLE vendedores
 MODIFY clave VARCHAR(255) NOT NULL,
 ADD rol ENUM('usuario','admin') NOT NULL DEFAULT 'usuario',
 ADD carnet VARCHAR(50) NULL,
 ADD dias VARCHAR(20) NOT NULL DEFAULT '1,2,3,4,5,6',
 ADD hora_inicio TIME NOT NULL DEFAULT '08:00:00',
 ADD hora_fin TIME NOT NULL DEFAULT '18:00:00',
 ADD fecha_desde DATE NULL,
 ADD fecha_hasta DATE NULL;
CREATE TABLE clientes (
 id INT AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(160) NOT NULL,
 telefono VARCHAR(30) DEFAULT '', activo TINYINT(1) DEFAULT 1,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 correo VARCHAR(254) NOT NULL DEFAULT '', nit VARCHAR(30) NOT NULL DEFAULT '',
 nrc VARCHAR(30) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE facturas
 ADD cliente VARCHAR(160) NOT NULL DEFAULT '',
 ADD telefono VARCHAR(30) DEFAULT '',
 ADD tipo ENUM('consumidor','credito') NOT NULL DEFAULT 'consumidor',
 ADD cliente_correo VARCHAR(254) NOT NULL DEFAULT '',
 ADD cliente_nit VARCHAR(30) NOT NULL DEFAULT '',
 ADD cliente_nrc VARCHAR(30) NOT NULL DEFAULT '',
 ADD solicitud_id VARCHAR(64) NULL UNIQUE;
ALTER TABLE sucursales MODIFY direccion VARCHAR(255) NOT NULL;
CREATE TABLE correos (
 id INT AUTO_INCREMENT PRIMARY KEY, destinatario VARCHAR(254) NOT NULL,
 asunto VARCHAR(200) NOT NULL, cuerpo TEXT NOT NULL,
 adjunto LONGBLOB NULL, nombre_adjunto VARCHAR(80) NULL,
 estado VARCHAR(30) NOT NULL DEFAULT 'pendiente', intentos INT NOT NULL DEFAULT 0,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE accesos (
 id INT AUTO_INCREMENT PRIMARY KEY, usuario VARCHAR(50) NOT NULL,
 motivo VARCHAR(255) NOT NULL, ip VARCHAR(45) NOT NULL,
 agente VARCHAR(255) NOT NULL, fecha DATETIME NOT NULL,
 correo_estado VARCHAR(30) NOT NULL DEFAULT 'pendiente', correo_id INT NULL,
 INDEX idx_accesos_ip_fecha (ip,fecha),
 FOREIGN KEY (correo_id) REFERENCES correos(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE configuracion_correo (
 id TINYINT PRIMARY KEY, jefe VARCHAR(254) NOT NULL DEFAULT '',
 remitente VARCHAR(254) NOT NULL DEFAULT '', actualizado_por INT UNSIGNED NULL,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY (actualizado_por) REFERENCES vendedores(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
