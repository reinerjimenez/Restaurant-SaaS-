-- Archivo: verificar_aislamiento.sql
-- Solo lectura. Ejecutar en la VM con cada cuenta limitada, seleccionando su base.
-- Revisar resultados antes de importar. La verificación de red corresponde a la VM.
-- Las consultas informan. El rechazo obligatorio de grants corresponde al
-- validador perfil_privilegios.php alimentado con SHOW GRANTS por cuenta.

SELECT VERSION() AS version_servidor, @@hostname AS equipo, @@port AS puerto,
       @@datadir AS directorio_datos, DATABASE() AS base, CURRENT_USER() AS cuenta;

SHOW VARIABLES WHERE Variable_name IN (
    'bind_address', 'port', 'skip_networking', 'character_set_server',
    'collation_server', 'foreign_key_checks', 'check_constraint_checks',
    'autocommit', 'tx_isolation', 'transaction_isolation', 'sql_mode'
);

SELECT SCHEMA_NAME, DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME
FROM information_schema.SCHEMATA ORDER BY SCHEMA_NAME;

SELECT GRANTEE, PRIVILEGE_TYPE, IS_GRANTABLE
FROM information_schema.USER_PRIVILEGES ORDER BY GRANTEE, PRIVILEGE_TYPE;

SELECT GRANTEE, TABLE_SCHEMA, PRIVILEGE_TYPE, IS_GRANTABLE
FROM information_schema.SCHEMA_PRIVILEGES ORDER BY GRANTEE, TABLE_SCHEMA, PRIVILEGE_TYPE;

SELECT GRANTEE, TABLE_SCHEMA, TABLE_NAME, PRIVILEGE_TYPE, IS_GRANTABLE
FROM information_schema.TABLE_PRIVILEGES
ORDER BY GRANTEE, TABLE_SCHEMA, TABLE_NAME, PRIVILEGE_TYPE;

SELECT TABLE_SCHEMA, COUNT(*) AS objetos_visibles
FROM information_schema.TABLES
WHERE TABLE_SCHEMA IN ('restaurant_ola1_test', 'restaurant_ola1_restore')
GROUP BY TABLE_SCHEMA;
