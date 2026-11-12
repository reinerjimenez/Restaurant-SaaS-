-- Archivo: 007_reversion_ola_1_intentos_login.sql
-- REVERSIÓN: no ejecutar durante la instalación ni el despliegue normal.
-- INSTRUCCIONES: Ejecutar manualmente en phpMyAdmin solo tras volver al código
-- previo y detener los accesos. Borra únicamente contadores temporales de login.
-- No afecta a las contraseñas, usuarios, sesiones, logs de acceso ni datos de caja.
-- Después de revertir, vuelve a quedar activo el comportamiento previo sin límite.

DROP TABLE IF EXISTS login_limites;
