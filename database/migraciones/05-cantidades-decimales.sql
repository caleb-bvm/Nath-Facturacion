-- Conserva las cantidades de las facturas existentes y permite hasta dos decimales.
ALTER TABLE factura_detalles MODIFY COLUMN cantidad DECIMAL(6,2) NOT NULL;
