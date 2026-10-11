-- Ejecutar UNA VEZ en la base existente, después de un respaldo.
-- Los registros anteriores conservan su contenido y admiten valores NULL.
ALTER TABLE facturas ADD COLUMN solicitud_id VARCHAR(64) NULL UNIQUE;
ALTER TABLE accesos ADD COLUMN correo_id INT NULL,
    ADD INDEX idx_accesos_ip_fecha (ip,fecha),
    ADD FOREIGN KEY (correo_id) REFERENCES correos(id) ON DELETE SET NULL;
