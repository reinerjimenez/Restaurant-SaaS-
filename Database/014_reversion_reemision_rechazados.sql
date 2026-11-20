-- Aplicar manualmente solo tras eliminar o exportar todos los intentos > 1.
-- La clave original no permite más de un documento por pedido y ambiente.
ALTER TABLE emision_documentos
  DROP INDEX uk_emision_pedido_intento,
  ADD UNIQUE KEY uk_emision_pedido (restaurante_id,pedido_id,ambiente),
  DROP COLUMN intento;
