-- Ejecutar manualmente una sola vez sobre la base MESORA.
-- Respalda la base antes de aplicar cambios de esquema.
ALTER TABLE productos
    ADD COLUMN sin_stock TINYINT(1) NOT NULL DEFAULT 0 AFTER activo;

-- Verificación posterior:
-- SHOW COLUMNS FROM productos LIKE 'sin_stock';
-- SELECT COUNT(*) AS productos FROM productos WHERE sin_stock IN (0, 1);
