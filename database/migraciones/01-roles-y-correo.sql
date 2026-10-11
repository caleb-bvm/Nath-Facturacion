/* Migración no destructiva: ejecutar una vez sobre la base existente. */
-- Seleccionar la base existente antes de ejecutar.
ALTER TABLE vendedores
 ADD COLUMN rol ENUM('usuario','admin') NOT NULL DEFAULT 'usuario',
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
-- La configuración de correo forma parte de esta misma actualización.
CREATE TABLE IF NOT EXISTS configuracion_correo (
    id TINYINT PRIMARY KEY,
    jefe VARCHAR(254) NOT NULL DEFAULT '',
    remitente VARCHAR(254) NOT NULL DEFAULT '',
    actualizado_por INT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (actualizado_por) REFERENCES vendedores(id) ON DELETE SET NULL
);
