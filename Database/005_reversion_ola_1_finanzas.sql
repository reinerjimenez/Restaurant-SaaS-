-- Archivo: 005_reversion_ola_1_finanzas.sql
-- Descripción: Revierte 004 únicamente si ninguna operación depende de los cambios.
-- INSTRUCCIONES: Ejecutar manualmente en phpMyAdmin con respaldo y escrituras detenidas.
-- No ejecutar por numeración: es una reversión, no un paso del despliegue.
-- Si se bloquea, preservar estructura y datos, mantener mantenimiento y conciliar antes
-- de elegir una versión compatible. Volver al PHP anterior no revierte ventas ni cierres.
-- Aplicar solo sobre una instalación completa de 004 (DDL no es transaccional).

SET @ola1_reversion_ok = 0;
SET @ola1_reversion_ok = (
    NOT EXISTS (SELECT 1 FROM turnos WHERE fin IS NULL OR cierre_json IS NOT NULL)
    AND NOT EXISTS (SELECT 1 FROM pagos WHERE turno_id IS NOT NULL)
    AND NOT EXISTS (SELECT 1 FROM gastos WHERE turno_id IS NOT NULL)
    AND NOT EXISTS (SELECT 1 FROM reversos_pago)
    AND NOT EXISTS (SELECT 1 FROM envios_comanda)
);
SELECT IF(@ola1_reversion_ok, 'Reversión sin movimientos habilitada',
    'BLOQUEADO: hay turnos abiertos, cierres o movimientos de Ola 1') AS resultado;

SET @ddl_finanzas = IF(@ola1_reversion_ok, 'DROP TABLE envios_comanda', 'SELECT ''Omitido por precondición'' AS resultado');
PREPARE ddl_finanzas FROM @ddl_finanzas;
EXECUTE ddl_finanzas;
DEALLOCATE PREPARE ddl_finanzas;

SET @ddl_finanzas = IF(@ola1_reversion_ok, 'DROP TABLE reversos_pago', 'SELECT ''Omitido por precondición'' AS resultado');
PREPARE ddl_finanzas FROM @ddl_finanzas;
EXECUTE ddl_finanzas;
DEALLOCATE PREPARE ddl_finanzas;

SET @ddl_finanzas = IF(@ola1_reversion_ok, 'ALTER TABLE turnos DROP COLUMN cierre_json', 'SELECT ''Omitido por precondición'' AS resultado');
PREPARE ddl_finanzas FROM @ddl_finanzas;
EXECUTE ddl_finanzas;
DEALLOCATE PREPARE ddl_finanzas;

SET @ddl_finanzas = IF(@ola1_reversion_ok, 'ALTER TABLE gastos DROP FOREIGN KEY fk_gastos_turno, DROP INDEX idx_gastos_turno, DROP COLUMN turno_id', 'SELECT ''Omitido por precondición'' AS resultado');
PREPARE ddl_finanzas FROM @ddl_finanzas;
EXECUTE ddl_finanzas;
DEALLOCATE PREPARE ddl_finanzas;

SET @ddl_finanzas = IF(@ola1_reversion_ok, 'ALTER TABLE pagos DROP FOREIGN KEY fk_pagos_turno, DROP INDEX idx_pagos_turno, DROP COLUMN turno_id', 'SELECT ''Omitido por precondición'' AS resultado');
PREPARE ddl_finanzas FROM @ddl_finanzas;
EXECUTE ddl_finanzas;
DEALLOCATE PREPARE ddl_finanzas;

-- Revisar: no deben aparecer las tres columnas ni las dos tablas.
SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND ((TABLE_NAME IN ('pagos', 'gastos') AND COLUMN_NAME = 'turno_id')
    OR (TABLE_NAME = 'turnos' AND COLUMN_NAME = 'cierre_json'));
SELECT TABLE_NAME FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('reversos_pago', 'envios_comanda');
