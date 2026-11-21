-- Ejecutar manualmente en phpMyAdmin con la base MESORA seleccionada.
-- Requiere el permiso TRIGGER sobre auditoria. Aplicar por separado tras instalar 008.
-- Permite insertar y consultar eventos, pero impide editar o borrar sus filas.
CREATE TRIGGER IF NOT EXISTS auditoria_impedir_edicion
BEFORE UPDATE ON auditoria
FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Los eventos de auditoría no se pueden editar';

CREATE TRIGGER IF NOT EXISTS auditoria_impedir_borrado
BEFORE DELETE ON auditoria
FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Los eventos de auditoría no se pueden borrar';
