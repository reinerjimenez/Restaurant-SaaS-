-- Archivo: 006_ola_1_intentos_login.sql
-- Descripción: contadores temporales por cuenta e IP para limitar el acceso.
-- INSTRUCCIONES: Ejecutar manualmente en phpMyAdmin con la aplicación detenida.
-- Aplicar antes del código nuevo de login. No cambia usuarios ni credenciales.
-- Compatible con MariaDB 10.4 / MySQL con InnoDB. No contiene datos iniciales.
-- Reversión manual: 007_reversion_ola_1_intentos_login.sql (solo al revertir el código).

CREATE TABLE login_limites (
    clave CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    inicio BIGINT UNSIGNED NOT NULL,
    fallos INT UNSIGNED NOT NULL DEFAULT 0,
    proximo_en BIGINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (clave),
    KEY idx_login_limites_inicio (inicio)
) ENGINE=InnoDB;
