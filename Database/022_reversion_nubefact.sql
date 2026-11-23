-- Ejecutar manualmente solo con la aplicación detenida, después de respaldo.
-- Si existe cualquier emisión NubeFacT, conservar las tablas y deshabilitar el proveedor.
SET @nubefact_reversion_segura =
  (SELECT COUNT(*) FROM emision_nubefact_documentos) = 0;
SET @nubefact_sql = IF(@nubefact_reversion_segura,
  'DROP TABLE emision_nubefact_documentos, emision_nubefact_config',
  'SELECT ''REVERSIÓN BLOQUEADA: hay evidencia fiscal de NubeFacT'' AS resultado');
PREPARE nubefact_reversion FROM @nubefact_sql;
EXECUTE nubefact_reversion;
DEALLOCATE PREPARE nubefact_reversion;
