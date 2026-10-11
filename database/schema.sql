-- Instalación nueva en una base vacía. Docker selecciona la base automáticamente.
-- Sin datos de ejemplo, migraciones ni instrucciones para borrar información.

CREATE TABLE vendedores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    usuario VARCHAR(50) NOT NULL UNIQUE,
    clave VARCHAR(255) NOT NULL,
    fecha_nacimiento DATE NOT NULL,
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    rol ENUM('usuario','admin') NOT NULL DEFAULT 'usuario',
    carnet VARCHAR(50) NULL,
    dias VARCHAR(20) NOT NULL DEFAULT '1,2,3,4,5,6',
    hora_inicio TIME NOT NULL DEFAULT '08:00:00',
    hora_fin TIME NOT NULL DEFAULT '18:00:00',
    fecha_desde DATE NULL,
    fecha_hasta DATE NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sucursales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    direccion VARCHAR(255) NOT NULL,
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE productos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(50) NOT NULL UNIQUE,
    descripcion VARCHAR(160) NOT NULL,
    precio DECIMAL(10,2) NOT NULL,
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(160) NOT NULL,
    telefono VARCHAR(30) DEFAULT '',
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    correo VARCHAR(254) NOT NULL DEFAULT '',
    nit VARCHAR(30) NOT NULL DEFAULT '',
    nrc VARCHAR(30) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE facturas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero VARCHAR(50) NOT NULL UNIQUE,
    fecha DATE NOT NULL,
    vendedor_id INT NOT NULL,
    sucursal_id INT NOT NULL,
    cliente VARCHAR(160) NOT NULL,
    telefono VARCHAR(30) DEFAULT '',
    subtotal DECIMAL(10,2) NOT NULL,
    iva_porcentaje DECIMAL(5,2) DEFAULT 13.00,
    iva DECIMAL(10,2) NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    comision_porcentaje DECIMAL(5,2) NOT NULL,
    comision DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    tipo ENUM('consumidor','credito') NOT NULL DEFAULT 'consumidor',
    cliente_correo VARCHAR(254) NOT NULL DEFAULT '',
    cliente_nit VARCHAR(30) NOT NULL DEFAULT '',
    cliente_nrc VARCHAR(30) NOT NULL DEFAULT '',
    solicitud_id VARCHAR(64) NULL UNIQUE,
    FOREIGN KEY (vendedor_id) REFERENCES vendedores(id),
    FOREIGN KEY (sucursal_id) REFERENCES sucursales(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE factura_detalles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    factura_id INT NOT NULL,
    producto_id INT NOT NULL,
    codigo_producto VARCHAR(50) NOT NULL,
    descripcion VARCHAR(160) NOT NULL,
    cantidad DECIMAL(6,2) NOT NULL,
    precio_unitario DECIMAL(10,2) NOT NULL,
    total_linea DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (factura_id) REFERENCES facturas(id) ON DELETE CASCADE,
    FOREIGN KEY (producto_id) REFERENCES productos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE correos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    destinatario VARCHAR(254) NOT NULL,
    asunto VARCHAR(200) NOT NULL,
    cuerpo TEXT NOT NULL,
    adjunto LONGBLOB NULL,
    nombre_adjunto VARCHAR(80) NULL,
    estado VARCHAR(30) NOT NULL DEFAULT 'pendiente',
    intentos INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE accesos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario VARCHAR(50) NOT NULL,
    motivo VARCHAR(255) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    agente VARCHAR(255) NOT NULL,
    fecha DATETIME NOT NULL,
    correo_estado VARCHAR(30) NOT NULL DEFAULT 'pendiente',
    correo_id INT NULL,
    INDEX idx_accesos_ip_fecha (ip,fecha),
    FOREIGN KEY (correo_id) REFERENCES correos(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE configuracion_correo (
    id TINYINT PRIMARY KEY,
    jefe VARCHAR(254) NOT NULL DEFAULT '',
    remitente VARCHAR(254) NOT NULL DEFAULT '',
    actualizado_por INT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (actualizado_por) REFERENCES vendedores(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
