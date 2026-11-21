-- Ejecutar manualmente en phpMyAdmin con la misma base seleccionada.
-- Retira la protección sin modificar los eventos guardados.
DROP TRIGGER IF EXISTS auditoria_impedir_borrado;
DROP TRIGGER IF EXISTS auditoria_impedir_edicion;
