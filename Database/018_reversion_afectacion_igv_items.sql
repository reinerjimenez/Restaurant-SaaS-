-- Aplicar manualmente solo tras exportar los datos de afectación.
ALTER TABLE pedido_items DROP COLUMN afectacion_igv;
ALTER TABLE productos DROP COLUMN afectacion_igv;
