-- Solo lectura. Seleccionar restaurant_ola1_test en la VM.
-- Capturar antes de 004, después de 004 y después de 006.
SELECT VERSION() AS version_servidor, DATABASE() AS base,
       @@foreign_key_checks AS fk_activas, @@check_constraint_checks AS checks_activos;

SELECT TABLE_NAME, ENGINE, TABLE_COLLATION
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND DATABASE() = 'restaurant_ola1_test'
ORDER BY TABLE_NAME;

SELECT TABLE_NAME, ORDINAL_POSITION, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT,
       CHARACTER_SET_NAME, COLLATION_NAME, EXTRA, GENERATION_EXPRESSION
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND DATABASE() = 'restaurant_ola1_test'
ORDER BY TABLE_NAME, ORDINAL_POSITION;

SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME, SUB_PART, INDEX_TYPE
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE() AND DATABASE() = 'restaurant_ola1_test'
ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX;

SELECT k.TABLE_NAME, k.CONSTRAINT_NAME, k.ORDINAL_POSITION, k.COLUMN_NAME,
       k.REFERENCED_TABLE_SCHEMA, k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME,
       r.UPDATE_RULE, r.DELETE_RULE
FROM information_schema.KEY_COLUMN_USAGE k
JOIN information_schema.REFERENTIAL_CONSTRAINTS r
  ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.TABLE_NAME = k.TABLE_NAME
 AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
WHERE k.TABLE_SCHEMA = DATABASE() AND DATABASE() = 'restaurant_ola1_test'
ORDER BY k.TABLE_NAME, k.CONSTRAINT_NAME, k.ORDINAL_POSITION;

SELECT TABLE_NAME, CONSTRAINT_NAME, CHECK_CLAUSE
FROM information_schema.CHECK_CONSTRAINTS
WHERE CONSTRAINT_SCHEMA = DATABASE() AND DATABASE() = 'restaurant_ola1_test'
ORDER BY TABLE_NAME, CONSTRAINT_NAME;

-- Ejecutar esta parte solamente después de identificar y verificar la base.
SELECT COUNT(*) AS turnos, COUNT(CASE WHEN fin IS NULL THEN 1 END) AS abiertos,
       COALESCE(SUM(total_general), 0) AS importe_cierres FROM restaurant_ola1_test.turnos;
SELECT COUNT(*) AS pagos, COALESCE(SUM(monto), 0) AS importe_pagos FROM restaurant_ola1_test.pagos;
SELECT COUNT(*) AS gastos, COALESCE(SUM(monto), 0) AS importe_gastos FROM restaurant_ola1_test.gastos;
SELECT COUNT(*) AS comprobantes, COALESCE(SUM(total), 0) AS importe_comprobantes FROM restaurant_ola1_test.comprobantes;
