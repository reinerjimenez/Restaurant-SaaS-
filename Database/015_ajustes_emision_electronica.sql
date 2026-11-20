-- Aplicar manualmente sobre una instalación con 011. No ejecutar desde PHP.
-- Notas de crédito y bajas conservan evidencia independiente del original.
ALTER TABLE emision_config
 ADD COLUMN serie_nota_factura varchar(4) NOT NULL DEFAULT 'FC01',
 ADD COLUMN serie_nota_boleta varchar(4) NOT NULL DEFAULT 'BC01',
 ADD COLUMN correlativo_nota_factura int unsigned NOT NULL DEFAULT 0,
 ADD COLUMN correlativo_nota_boleta int unsigned NOT NULL DEFAULT 0,
 ADD COLUMN correlativo_nota_factura_pruebas int unsigned NOT NULL DEFAULT 0,
 ADD COLUMN correlativo_nota_boleta_pruebas int unsigned NOT NULL DEFAULT 0;

CREATE TABLE emision_ajustes (
 id bigint unsigned NOT NULL AUTO_INCREMENT,
 restaurante_id int(11) NOT NULL,
 documento_id bigint unsigned NOT NULL,
 usuario_id int(11) NOT NULL,
 ambiente enum('pruebas','produccion') NOT NULL,
 tipo enum('nota_credito','baja_boleta') NOT NULL,
 serie varchar(4) DEFAULT NULL,
 correlativo int unsigned DEFAULT NULL,
 motivo_codigo varchar(2) NOT NULL,
 motivo_texto varchar(250) NOT NULL,
 estado enum('pendiente','procesando','pendiente_ticket','incierto','aceptado','aceptado_observado','rechazado','prueba_aceptada') NOT NULL DEFAULT 'pendiente',
 snapshot_json longtext NOT NULL CHECK (json_valid(snapshot_json)),
 xml_firmado longtext DEFAULT NULL,
 cdr_xml longtext DEFAULT NULL,
 ticket varchar(100) DEFAULT NULL,
 resumen_identidad varchar(64) DEFAULT NULL,
 respuesta_codigo varchar(20) DEFAULT NULL,
 respuesta_mensaje varchar(500) DEFAULT NULL,
 intentos int unsigned NOT NULL DEFAULT 0,
 created_at timestamp NOT NULL DEFAULT current_timestamp(),
 updated_at timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
 PRIMARY KEY (id),
 UNIQUE KEY uk_ajuste_original_tipo (restaurante_id,documento_id,tipo),
 UNIQUE KEY uk_ajuste_numero (restaurante_id,ambiente,serie,correlativo),
 UNIQUE KEY uk_ajuste_resumen (restaurante_id,ambiente,resumen_identidad),
 KEY idx_ajuste_pendiente (restaurante_id,estado,id),
 CONSTRAINT fk_ajuste_restaurante FOREIGN KEY (restaurante_id) REFERENCES restaurantes(id),
 CONSTRAINT fk_ajuste_documento FOREIGN KEY (documento_id) REFERENCES emision_documentos(id),
 CONSTRAINT fk_ajuste_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
