/* Configuración de correo editable: ejecutar sin eliminar datos existentes. */
-- Seleccionar la base existente antes de ejecutar.
CREATE TABLE IF NOT EXISTS configuracion_correo (
    id TINYINT PRIMARY KEY,
    jefe VARCHAR(254) NOT NULL DEFAULT '',
    remitente VARCHAR(254) NOT NULL DEFAULT '',
    actualizado_por INT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (actualizado_por) REFERENCES vendedores(id) ON DELETE SET NULL
);
