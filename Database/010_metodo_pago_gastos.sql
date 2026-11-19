-- Ejecutar manualmente una sola vez en la base MESORA existente, después de un respaldo.
-- NULL conserva honestamente los gastos históricos cuyo método no se registró.
ALTER TABLE gastos
    ADD COLUMN metodo_pago ENUM('efectivo','yape','transferencia','tarjeta','otro') NULL DEFAULT NULL AFTER monto;

-- Verificación posterior:
-- SHOW COLUMNS FROM gastos LIKE 'metodo_pago';
-- SELECT metodo_pago, COUNT(*) AS cantidad FROM gastos GROUP BY metodo_pago;
