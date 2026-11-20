-- Reversión manual: conservar primero XML y CDR de los ajustes.
DROP TABLE emision_ajustes;
ALTER TABLE emision_config
 DROP COLUMN correlativo_nota_boleta_pruebas,
 DROP COLUMN correlativo_nota_factura_pruebas,
 DROP COLUMN correlativo_nota_boleta,
 DROP COLUMN correlativo_nota_factura,
 DROP COLUMN serie_nota_boleta,
 DROP COLUMN serie_nota_factura;
