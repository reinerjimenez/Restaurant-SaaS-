-- Aplicar manualmente. Los ítems existentes conservan afectación gravada.
-- La afectación del producto se copia al ítem al agregarlo a un pedido.
ALTER TABLE productos
  ADD COLUMN afectacion_igv enum('gravada','exonerada','inafecta') NOT NULL DEFAULT 'gravada';
ALTER TABLE pedido_items
  ADD COLUMN afectacion_igv enum('gravada','exonerada','inafecta') NOT NULL DEFAULT 'gravada';
