-- Aplicar manualmente en phpMyAdmin sobre MESORA con el esquema fiscal actualizado.
CREATE TABLE IF NOT EXISTS `emision_nubefact_config` (
  `restaurante_id` int(11) NOT NULL,
  `ambiente` enum('pruebas','produccion') NOT NULL,
  `ruc` char(11) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `ruc_produccion` varchar(11) CHARACTER SET ascii COLLATE ascii_bin GENERATED ALWAYS AS (IF(`ambiente` = 'produccion', RTRIM(`ruc`), NULL)) STORED,
  `ruta` varchar(512) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `ruta_hash` binary(32) NOT NULL,
  `token_cifrado` varbinary(1024) NOT NULL,
  `serie_boleta` char(4) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `serie_factura` char(4) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `correlativo_boleta` int unsigned NOT NULL DEFAULT 0,
  `correlativo_factura` int unsigned NOT NULL DEFAULT 0,
  `habilitado` tinyint(1) NOT NULL DEFAULT 0,
  `verificado_en` datetime DEFAULT NULL,
  PRIMARY KEY (`restaurante_id`, `ambiente`),
  UNIQUE KEY `uk_nubefact_ruta` (`ruta_hash`),
  UNIQUE KEY `uk_nubefact_serie_boleta` (`ruc_produccion`, `serie_boleta`),
  UNIQUE KEY `uk_nubefact_serie_factura` (`ruc_produccion`, `serie_factura`),
  CONSTRAINT `fk_nubefact_config_restaurante` FOREIGN KEY (`restaurante_id`) REFERENCES `restaurantes` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `emision_nubefact_documentos` (
  `documento_id` bigint unsigned NOT NULL,
  `restaurante_id` int(11) NOT NULL,
  `pdf_url` varchar(2048) DEFAULT NULL,
  `xml_url` varchar(2048) DEFAULT NULL,
  `cdr_url` varchar(2048) DEFAULT NULL,
  `referencia_ajuste` varchar(250) DEFAULT NULL,
  PRIMARY KEY (`documento_id`),
  KEY `idx_nubefact_documentos_restaurante` (`restaurante_id`, `documento_id`),
  CONSTRAINT `fk_nubefact_documentos_origen` FOREIGN KEY (`documento_id`) REFERENCES `emision_documentos` (`id`),
  CONSTRAINT `fk_nubefact_documentos_restaurante` FOREIGN KEY (`restaurante_id`) REFERENCES `restaurantes` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
