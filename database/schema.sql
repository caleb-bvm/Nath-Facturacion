-- Estructura y datos iniciales de la base de datos.
CREATE DATABASE IF NOT EXISTS facturacion_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE facturacion_db;

/* Instalación nueva: este script reinicia las tablas. Para bases existentes usar actualizacion.sql. */
DROP TABLE IF EXISTS configuracion_correo;
DROP TABLE IF EXISTS accesos;
DROP TABLE IF EXISTS correos;
DROP TABLE IF EXISTS factura_detalles;
DROP TABLE IF EXISTS facturas;
DROP TABLE IF EXISTS clientes;
DROP TABLE IF EXISTS productos;
DROP TABLE IF EXISTS sucursales;
DROP TABLE IF EXISTS vendedores;

CREATE TABLE vendedores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    usuario VARCHAR(50) NOT NULL UNIQUE,
    clave VARCHAR(255) NOT NULL,
    fecha_nacimiento DATE NOT NULL,
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE sucursales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    direccion VARCHAR(255) NOT NULL,
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE productos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(50) NOT NULL UNIQUE,
    descripcion VARCHAR(160) NOT NULL,
    precio DECIMAL(10,2) NOT NULL,
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(160) NOT NULL,
    telefono VARCHAR(30) DEFAULT '',
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

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
    FOREIGN KEY (vendedor_id) REFERENCES vendedores(id),
    FOREIGN KEY (sucursal_id) REFERENCES sucursales(id)
);

CREATE TABLE factura_detalles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    factura_id INT NOT NULL,
    producto_id INT NOT NULL,
    codigo_producto VARCHAR(50) NOT NULL,
    descripcion VARCHAR(160) NOT NULL,
    cantidad INT NOT NULL,
    precio_unitario DECIMAL(10,2) NOT NULL,
    total_linea DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (factura_id) REFERENCES facturas(id) ON DELETE CASCADE,
    FOREIGN KEY (producto_id) REFERENCES productos(id)
);

INSERT INTO vendedores (nombre, usuario, clave, fecha_nacimiento) VALUES
('Nathaly Martínez', 'vendedor', 'password', '2007-06-08'),
('Juan Pérez', 'juan', 'password', '1998-09-28'),
('Carlos Cumpleaños', 'cumple', 'password', '2000-09-21');

INSERT INTO sucursales (nombre, direccion) VALUES
('Sucursal Central San Salvador', 'Alameda Juan Pablo II'),
('Sucursal San Miguel', 'Avenida Roosevelt Norte');

INSERT INTO productos (codigo, descripcion, precio) VALUES
('PROD-000001', 'Cuaderno cosido cuadriculado 100 hojas', 2.50),
('PROD-000002', 'Caja de lápices de grafito HB (12 u.)', 3.75),
('PROD-000003', 'Bolígrafo azul punta fina', 0.50);

INSERT INTO clientes (nombre, telefono) VALUES
('Institución Educativa ABC', '2255-8899'),
('Librería Comercial San Miguel', '2441-1234');
/* Migración no destructiva: ejecutar una vez sobre la base existente. */
USE facturacion_db;
ALTER TABLE vendedores
 ADD COLUMN rol ENUM('usuario','supremo') NOT NULL DEFAULT 'usuario',
 ADD COLUMN carnet VARCHAR(50) NULL,
 ADD COLUMN dias VARCHAR(20) NOT NULL DEFAULT '1,2,3,4,5,6',
 ADD COLUMN hora_inicio TIME NOT NULL DEFAULT '08:00:00',
 ADD COLUMN hora_fin TIME NOT NULL DEFAULT '18:00:00',
 ADD COLUMN fecha_desde DATE NULL,
 ADD COLUMN fecha_hasta DATE NULL;
ALTER TABLE clientes ADD COLUMN correo VARCHAR(254) NOT NULL DEFAULT '',
 ADD COLUMN nit VARCHAR(30) NOT NULL DEFAULT '', ADD COLUMN nrc VARCHAR(30) NOT NULL DEFAULT '';
ALTER TABLE facturas ADD COLUMN tipo ENUM('consumidor','credito') NOT NULL DEFAULT 'consumidor',
 ADD COLUMN cliente_correo VARCHAR(254) NOT NULL DEFAULT '',
 ADD COLUMN cliente_nit VARCHAR(30) NOT NULL DEFAULT '', ADD COLUMN cliente_nrc VARCHAR(30) NOT NULL DEFAULT '';
CREATE TABLE IF NOT EXISTS accesos (
 id INT AUTO_INCREMENT PRIMARY KEY, usuario VARCHAR(50) NOT NULL,
 motivo VARCHAR(255) NOT NULL, ip VARCHAR(45) NOT NULL,
 agente VARCHAR(255) NOT NULL, fecha DATETIME NOT NULL,
 correo_estado VARCHAR(30) NOT NULL DEFAULT 'pendiente'
);
CREATE TABLE IF NOT EXISTS correos (
 id INT AUTO_INCREMENT PRIMARY KEY, destinatario VARCHAR(254) NOT NULL,
 asunto VARCHAR(200) NOT NULL, cuerpo TEXT NOT NULL,
 adjunto LONGBLOB NULL, nombre_adjunto VARCHAR(80) NULL,
 estado VARCHAR(30) NOT NULL DEFAULT 'pendiente', intentos INT NOT NULL DEFAULT 0,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

/* Configuración de correo editable: ejecutar sin eliminar datos existentes. */
USE facturacion_db;
CREATE TABLE IF NOT EXISTS configuracion_correo (
    id TINYINT PRIMARY KEY,
    jefe VARCHAR(254) NOT NULL DEFAULT '',
    remitente VARCHAR(254) NOT NULL DEFAULT '',
    actualizado_por INT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (actualizado_por) REFERENCES vendedores(id) ON DELETE SET NULL
);
