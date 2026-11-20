-- Aplicar manualmente solo a una base existente con 011 instalada.
-- Conserva cada rechazo y su CDR; una nueva numeración exige acción explícita.
ALTER TABLE emision_documentos
  ADD COLUMN intento int unsigned NOT NULL DEFAULT 1 AFTER correlativo,
  DROP INDEX uk_emision_pedido,
  ADD UNIQUE KEY uk_emision_pedido_intento (restaurante_id,pedido_id,ambiente,intento);
