-- Archivo: crear_bases_ensayo.sql
-- INSTRUCCIONES: referencia de las dos sentencias usadas por aprovisionar_bases.php.
-- No importar directamente en phpMyAdmin. El ejecutor valida VM y servidor primero.
-- Requiere un operador de aprovisionamiento. Confirmar antes que ambas bases
-- estén ausentes. Si alguna sentencia devuelve error, detener y revisar.
-- No contiene secretos, cuentas, borrados ni datos iniciales.

CREATE DATABASE restaurant_ola1_test
    CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

CREATE DATABASE restaurant_ola1_restore
    CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
