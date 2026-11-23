-- MESORA: instalador oficial completo para una base nueva y vacia.
-- Esquema base 002 con cambios finales de 004, 006, 011, 013, 015, 017 y 021 integrados.
-- MariaDB 10.4 / InnoDB. Importar una sola vez en phpMyAdmin.
-- No aplicar despues 001-007, 011, 013, 015, 017 ni 021. No contiene DROP ni datos demo.
-- La protección adicional de auditoría (019) requiere permiso TRIGGER y se instala por separado.
-- No sirve para actualizar una base existente ni reparar XAMPP.
-- MariaDB confirma DDL por sentencia: ante fallo, usar otra base vacia.

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
-- El nombre puede incluir el prefijo de cuenta del hosting. Exigir esquema seleccionado y vacio.
SET @mesora_base_vacia = DATABASE() IS NOT NULL AND
  (SELECT COUNT(*) FROM information_schema.TABLES
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE') = 0;
SET @mesora_preflight = IF(@mesora_base_vacia,
  'SELECT 1 AS instalacion_autorizada',
  'MESORA_BASE_NO_VACIA_O_NO_SELECCIONADA');
PREPARE mesora_preflight FROM @mesora_preflight;
EXECUTE mesora_preflight;
DEALLOCATE PREPARE mesora_preflight;
SELECT DATABASE() AS base_seleccionada;

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- ── TABLA: auditoria ─────────────────────────────────────────────
CREATE TABLE `auditoria` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `restaurante_id` int(11) DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `usuario_nombre` varchar(100) DEFAULT NULL,
  `accion` varchar(120) NOT NULL,
  `detalle` text DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `restaurante_id` (`restaurante_id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `idx_audit_fecha` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── TABLA: restaurantes ──────────────────────────────────────────
CREATE TABLE `restaurantes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── TABLA: usuarios ──────────────────────────────────────────────
CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `restaurante_id` int(11) DEFAULT NULL,
  `nombre` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `rol` enum('superadmin','admin','encargado','atencion','cocina') NOT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `session_version` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `restaurante_id` (`restaurante_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── TABLA: mesas ─────────────────────────────────────────────────
CREATE TABLE `mesas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `restaurante_id` int(11) NOT NULL,
  `numero` int(11) NOT NULL,
  `capacidad` int(11) DEFAULT 4,
  `estado` enum('libre','ocupada','reservada') DEFAULT 'libre',
  `activo` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_mesa_numero` (`restaurante_id`,`numero`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── TABLA: categorias ────────────────────────────────────────────
CREATE TABLE `categorias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `restaurante_id` int(11) NOT NULL,
  `nombre` varchar(80) NOT NULL,
  `icono` varchar(10) DEFAULT '🍽️',
  `orden` int(11) DEFAULT 0,
  `activo` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `restaurante_id` (`restaurante_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── TABLA: productos ─────────────────────────────────────────────
CREATE TABLE `productos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `restaurante_id` int(11) NOT NULL,
  `categoria_id` int(11) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `precio` decimal(8,2) NOT NULL,
  `afectacion_igv` enum('gravada','exonerada','inafecta') NOT NULL DEFAULT 'gravada',
  `imagen` varchar(255) DEFAULT NULL,
  `tiene_opciones` tinyint(1) DEFAULT 0,
  `activo` tinyint(1) DEFAULT 1,
  `sin_stock` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `categoria_id` (`categoria_id`),
  KEY `restaurante_id` (`restaurante_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── TABLA: opciones_grupo ────────────────────────────────────────
CREATE TABLE `opciones_grupo` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `producto_id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `orden` int(11) DEFAULT 1,
  `requerido` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `producto_id` (`producto_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── TABLA: opciones_valor ────────────────────────────────────────
CREATE TABLE `opciones_valor` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `grupo_id` int(11) NOT NULL,
  `valor` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `grupo_id` (`grupo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── TABLA: pedidos ───────────────────────────────────────────────
CREATE TABLE `pedidos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `restaurante_id` int(11) NOT NULL,
  `mesa_id` int(11) DEFAULT NULL,
  `usuario_id` int(11) NOT NULL,
  `tipo` enum('aqui','llevar') NOT NULL,
  `estado` enum('activo','cobrado','cancelado') DEFAULT 'activo',
  `total` decimal(10,2) DEFAULT 0.00,
  `descuento` decimal(10,2) DEFAULT 0.00,
  `cargo_extra` decimal(10,2) DEFAULT 0.00,
  `notas` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `mesa_id` (`mesa_id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `restaurante_id` (`restaurante_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── TABLA: pedido_items ──────────────────────────────────────────
CREATE TABLE `pedido_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pedido_id` int(11) NOT NULL,
  `producto_id` int(11) DEFAULT NULL,
  `nombre_producto` varchar(120) DEFAULT NULL,
  `cantidad` int(11) DEFAULT 1,
  `precio_unitario` decimal(8,2) NOT NULL,
  `subtotal` decimal(8,2) NOT NULL,
  `afectacion_igv` enum('gravada','exonerada','inafecta') NOT NULL DEFAULT 'gravada',
  `notas` text DEFAULT NULL,
  `estado` enum('pendiente','en_preparacion','listo','entregado') DEFAULT 'pendiente',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `pedido_id` (`pedido_id`),
  KEY `fk_pi_producto` (`producto_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── TABLA: pedido_item_opciones ──────────────────────────────────
CREATE TABLE `pedido_item_opciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_id` int(11) NOT NULL,
  `grupo_id` int(11) NOT NULL,
  `valor_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `item_id` (`item_id`),
  KEY `grupo_id` (`grupo_id`),
  KEY `valor_id` (`valor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── TABLA: pagos ─────────────────────────────────────────────────
CREATE TABLE `pagos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pedido_id` int(11) NOT NULL,
  `metodo` enum('efectivo','yape','transferencia','tarjeta','otro') NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `referencia` varchar(100) DEFAULT NULL,
  `usuario_id` int(11) NOT NULL,
  `turno_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `anulado` tinyint(1) DEFAULT 0,
  `anulado_por` int(11) DEFAULT NULL,
  `anulado_en` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pedido_id` (`pedido_id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `idx_pagos_turno` (`turno_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── TABLA: comprobantes ──────────────────────────────────────────
-- Columnas verificadas contra api/guardar_comprobante.php
CREATE TABLE `comprobantes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `restaurante_id` int(11) NOT NULL,
  `pedido_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `tipo` enum('boleta','factura','simple') NOT NULL,
  `serie` varchar(4) NOT NULL,
  `correlativo` int(11) NOT NULL,
  `numero_comprobante` varchar(20) NOT NULL,
  `tipo_documento` enum('dni','ruc') DEFAULT NULL,
  `numero_documento` varchar(11) DEFAULT NULL,
  `nombre_cliente` varchar(200) NOT NULL,
  `direccion_cliente` varchar(300) DEFAULT NULL,
  `distrito` varchar(100) DEFAULT NULL,
  `provincia` varchar(100) DEFAULT NULL,
  `departamento` varchar(100) DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `igv` decimal(10,2) NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `descuento` decimal(10,2) DEFAULT 0.00,
  `cargo_extra` decimal(10,2) DEFAULT 0.00,
  `anulado` tinyint(1) DEFAULT 0,
  `motivo_anulacion` varchar(200) DEFAULT NULL,
  `items_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`items_json`)),
  `pagos_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`pagos_json`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_comprobante` (`restaurante_id`,`serie`,`correlativo`),
  KEY `usuario_id` (`usuario_id`),
  KEY `idx_comp_pedido` (`pedido_id`),
  KEY `idx_comp_documento` (`numero_documento`),
  KEY `idx_comp_fecha` (`created_at`),
  KEY `idx_comp_anulado` (`anulado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── TABLA: comprobante_items ─────────────────────────────────────
CREATE TABLE `comprobante_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `comprobante_id` int(11) NOT NULL,
  `descripcion` varchar(200) NOT NULL,
  `cantidad` int(11) NOT NULL DEFAULT 1,
  `precio_unitario` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `comprobante_id` (`comprobante_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── TABLA: facturacion_config ────────────────────────────────────
CREATE TABLE `facturacion_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `restaurante_id` int(11) NOT NULL,
  `ruc` varchar(11) DEFAULT NULL,
  `razon_social` varchar(200) DEFAULT NULL,
  `nombre_comercial` varchar(150) DEFAULT NULL,
  `direccion_fiscal` varchar(300) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `serie_boleta` varchar(4) DEFAULT 'B001',
  `serie_factura` varchar(4) DEFAULT 'F001',
  `serie_simple` varchar(4) DEFAULT 'T001',
  `correlativo_boleta` int(11) DEFAULT 0,
  `correlativo_factura` int(11) DEFAULT 0,
  `correlativo_simple` int(11) DEFAULT 0,
  `pie_mensaje` varchar(300) DEFAULT '¡Gracias por su visita!',
  `logo` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `restaurante_id` (`restaurante_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── TABLA: turnos ────────────────────────────────────────────────
CREATE TABLE `turnos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `restaurante_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `inicio` timestamp NOT NULL DEFAULT current_timestamp(),
  `fin` timestamp NULL DEFAULT NULL,
  `total_efectivo` decimal(10,2) DEFAULT 0.00,
  `total_yape` decimal(10,2) DEFAULT 0.00,
  `total_transferencia` decimal(10,2) DEFAULT 0.00,
  `total_tarjeta` decimal(10,2) DEFAULT 0.00,
  `total_otros` decimal(10,2) DEFAULT 0.00,
  `total_general` decimal(10,2) DEFAULT 0.00,
  `cierre_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`cierre_json`)),
  `_turno_abierto_key` int(11) GENERATED ALWAYS AS (case when `fin` is null then `restaurante_id` else NULL end) VIRTUAL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_turno_abierto_por_restaurante` (`_turno_abierto_key`),
  KEY `restaurante_id` (`restaurante_id`),
  KEY `usuario_id` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── TABLA: gastos ────────────────────────────────────────────────
CREATE TABLE `gastos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `restaurante_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `turno_id` int(11) DEFAULT NULL,
  `categoria` varchar(100) NOT NULL,
  `descripcion` varchar(255) NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `metodo_pago` enum('efectivo','yape','transferencia','tarjeta','otro') DEFAULT NULL,
  `fecha` date NOT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `idx_gastos_turno` (`turno_id`),
  KEY `idx_gastos_rest_fecha` (`restaurante_id`,`fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── TABLA: logs_acceso ───────────────────────────────────────────
CREATE TABLE `logs_acceso` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) DEFAULT NULL,
  `accion` varchar(100) NOT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── RESTRICCIONES (FK) ───────────────────────────────────────────
-- Tablas de Ola 1 incorporadas al esquema inicial.
CREATE TABLE `reversos_pago` (
  `pago_id` int(11) NOT NULL,
  `turno_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `motivo` varchar(200) NOT NULL,
  `venta_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`venta_json`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`pago_id`),
  KEY `idx_reversos_turno` (`turno_id`),
  KEY `fk_reversos_usuario` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `envios_comanda` (
  `restaurante_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `operacion_id` char(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `huella` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `resultado_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`resultado_json`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`restaurante_id`,`usuario_id`,`operacion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `login_limites` (
  `clave` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `inicio` bigint unsigned NOT NULL,
  `fallos` int unsigned NOT NULL DEFAULT 0,
  `proximo_en` bigint unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`clave`),
  KEY `idx_login_limites_inicio` (`inicio`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `usuarios`
  ADD CONSTRAINT `usuarios_ibfk_1` FOREIGN KEY (`restaurante_id`) REFERENCES `restaurantes` (`id`) ON DELETE SET NULL;

ALTER TABLE `mesas`
  ADD CONSTRAINT `mesas_ibfk_1` FOREIGN KEY (`restaurante_id`) REFERENCES `restaurantes` (`id`) ON DELETE CASCADE;

ALTER TABLE `categorias`
  ADD CONSTRAINT `categorias_ibfk_1` FOREIGN KEY (`restaurante_id`) REFERENCES `restaurantes` (`id`) ON DELETE CASCADE;

ALTER TABLE `productos`
  ADD CONSTRAINT `productos_ibfk_1` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `productos_ibfk_2` FOREIGN KEY (`restaurante_id`) REFERENCES `restaurantes` (`id`) ON DELETE CASCADE;

ALTER TABLE `opciones_grupo`
  ADD CONSTRAINT `opciones_grupo_ibfk_1` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE CASCADE;

ALTER TABLE `opciones_valor`
  ADD CONSTRAINT `opciones_valor_ibfk_1` FOREIGN KEY (`grupo_id`) REFERENCES `opciones_grupo` (`id`) ON DELETE CASCADE;

ALTER TABLE `pedidos`
  ADD CONSTRAINT `pedidos_ibfk_1` FOREIGN KEY (`mesa_id`) REFERENCES `mesas` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `pedidos_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `pedidos_ibfk_3` FOREIGN KEY (`restaurante_id`) REFERENCES `restaurantes` (`id`) ON DELETE CASCADE;

ALTER TABLE `pedido_items`
  ADD CONSTRAINT `fk_pi_producto` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `pedido_items_ibfk_1` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE;

ALTER TABLE `pedido_item_opciones`
  ADD CONSTRAINT `pedido_item_opciones_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `pedido_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `pedido_item_opciones_ibfk_2` FOREIGN KEY (`grupo_id`) REFERENCES `opciones_grupo` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `pedido_item_opciones_ibfk_3` FOREIGN KEY (`valor_id`) REFERENCES `opciones_valor` (`id`) ON DELETE CASCADE;

ALTER TABLE `pagos`
  ADD CONSTRAINT `pagos_ibfk_1` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `pagos_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `fk_pagos_turno` FOREIGN KEY (`turno_id`) REFERENCES `turnos` (`id`);

ALTER TABLE `comprobantes`
  ADD CONSTRAINT `comprobantes_ibfk_1` FOREIGN KEY (`restaurante_id`) REFERENCES `restaurantes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `comprobantes_ibfk_2` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`),
  ADD CONSTRAINT `comprobantes_ibfk_3` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

ALTER TABLE `comprobante_items`
  ADD CONSTRAINT `comprobante_items_ibfk_1` FOREIGN KEY (`comprobante_id`) REFERENCES `comprobantes` (`id`) ON DELETE CASCADE;

ALTER TABLE `facturacion_config`
  ADD CONSTRAINT `facturacion_config_ibfk_1` FOREIGN KEY (`restaurante_id`) REFERENCES `restaurantes` (`id`) ON DELETE CASCADE;

ALTER TABLE `turnos`
  ADD CONSTRAINT `turnos_ibfk_1` FOREIGN KEY (`restaurante_id`) REFERENCES `restaurantes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `turnos_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

ALTER TABLE `gastos`
  ADD CONSTRAINT `gastos_ibfk_1` FOREIGN KEY (`restaurante_id`) REFERENCES `restaurantes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `gastos_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `fk_gastos_turno` FOREIGN KEY (`turno_id`) REFERENCES `turnos` (`id`);

ALTER TABLE `logs_acceso`
  ADD CONSTRAINT `logs_acceso_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;
ALTER TABLE `reversos_pago`
  ADD CONSTRAINT `fk_reversos_pago` FOREIGN KEY (`pago_id`) REFERENCES `pagos` (`id`),
  ADD CONSTRAINT `fk_reversos_turno` FOREIGN KEY (`turno_id`) REFERENCES `turnos` (`id`),
  ADD CONSTRAINT `fk_reversos_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);
ALTER TABLE `envios_comanda`
  ADD CONSTRAINT `fk_envios_restaurante` FOREIGN KEY (`restaurante_id`) REFERENCES `restaurantes` (`id`);

-- Emision electronica: modo desactivado por defecto para cada restaurante.
-- En bases ya instaladas con una version anterior de 008, aplicar solo 011.
CREATE TABLE `emision_config` (
  `restaurante_id` int(11) NOT NULL,
  `modo` enum('desactivado','pruebas','produccion') NOT NULL DEFAULT 'desactivado',
  `ruc` varchar(11) NOT NULL DEFAULT '',
  `razon_social` varchar(200) NOT NULL DEFAULT '',
  `direccion` varchar(300) NOT NULL DEFAULT '',
  `ubigeo` varchar(6) NOT NULL DEFAULT '',
  `regimen` enum('RER','RMT','GENERAL') NOT NULL DEFAULT 'RER',
  `tasa_igv` decimal(5,2) NOT NULL DEFAULT 18.00,
  `serie_boleta` varchar(4) NOT NULL DEFAULT 'B001',
  `serie_factura` varchar(4) NOT NULL DEFAULT 'F001',
  `serie_nota_factura` varchar(4) NOT NULL DEFAULT 'FC01',
  `serie_nota_boleta` varchar(4) NOT NULL DEFAULT 'BC01',
  `correlativo_boleta` int unsigned NOT NULL DEFAULT 0,
  `correlativo_factura` int unsigned NOT NULL DEFAULT 0,
  `correlativo_boleta_pruebas` int unsigned NOT NULL DEFAULT 0,
  `correlativo_factura_pruebas` int unsigned NOT NULL DEFAULT 0,
  `correlativo_nota_factura` int unsigned NOT NULL DEFAULT 0,
  `correlativo_nota_boleta` int unsigned NOT NULL DEFAULT 0,
  `correlativo_nota_factura_pruebas` int unsigned NOT NULL DEFAULT 0,
  `correlativo_nota_boleta_pruebas` int unsigned NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`restaurante_id`),
  CONSTRAINT `fk_emision_config_restaurante` FOREIGN KEY (`restaurante_id`) REFERENCES `restaurantes` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `emision_documentos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `restaurante_id` int(11) NOT NULL,
  `pedido_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `ambiente` enum('pruebas','produccion') NOT NULL DEFAULT 'pruebas',
  `tipo` enum('boleta','factura') NOT NULL,
  `serie` varchar(4) NOT NULL,
  `correlativo` int unsigned NOT NULL,
  `intento` int unsigned NOT NULL DEFAULT 1,
  `estado` enum('pendiente','procesando','pendiente_ticket','incierto','aceptado','aceptado_observado','rechazado','prueba_aceptada','error') NOT NULL DEFAULT 'pendiente',
  `snapshot_json` longtext NOT NULL CHECK (json_valid(`snapshot_json`)),
  `xml_firmado` longtext DEFAULT NULL,
  `resumen_xml_firmado` longtext DEFAULT NULL,
  `cdr_xml` longtext DEFAULT NULL,
  `ticket` varchar(100) DEFAULT NULL,
  `resumen_identidad` varchar(64) DEFAULT NULL,
  `respuesta_codigo` varchar(20) DEFAULT NULL,
  `respuesta_mensaje` varchar(500) DEFAULT NULL,
  `intentos` int unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_emision_pedido_intento` (`restaurante_id`,`pedido_id`,`ambiente`,`intento`),
  UNIQUE KEY `uk_emision_numero` (`restaurante_id`,`ambiente`,`serie`,`correlativo`),
  UNIQUE KEY `uk_emision_resumen` (`restaurante_id`,`ambiente`,`resumen_identidad`),
  KEY `idx_emision_pendiente` (`restaurante_id`,`estado`,`id`),
  CONSTRAINT `fk_emision_documentos_restaurante` FOREIGN KEY (`restaurante_id`) REFERENCES `restaurantes` (`id`),
  CONSTRAINT `fk_emision_documentos_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`),
  CONSTRAINT `fk_emision_documentos_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Ajustes fiscales independientes: se aplica 015 únicamente en bases existentes.
CREATE TABLE `emision_ajustes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `restaurante_id` int(11) NOT NULL,
  `documento_id` bigint unsigned NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `ambiente` enum('pruebas','produccion') NOT NULL,
  `tipo` enum('nota_credito','baja_boleta') NOT NULL,
  `serie` varchar(4) DEFAULT NULL,
  `correlativo` int unsigned DEFAULT NULL,
  `motivo_codigo` varchar(2) NOT NULL,
  `motivo_texto` varchar(250) NOT NULL,
  `estado` enum('pendiente','procesando','pendiente_ticket','incierto','aceptado','aceptado_observado','rechazado','prueba_aceptada') NOT NULL DEFAULT 'pendiente',
  `snapshot_json` longtext NOT NULL CHECK (json_valid(`snapshot_json`)),
  `xml_firmado` longtext DEFAULT NULL,
  `cdr_xml` longtext DEFAULT NULL,
  `ticket` varchar(100) DEFAULT NULL,
  `resumen_identidad` varchar(64) DEFAULT NULL,
  `respuesta_codigo` varchar(20) DEFAULT NULL,
  `respuesta_mensaje` varchar(500) DEFAULT NULL,
  `intentos` int unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ajuste_original_tipo` (`restaurante_id`,`documento_id`,`tipo`),
  UNIQUE KEY `uk_ajuste_numero` (`restaurante_id`,`ambiente`,`serie`,`correlativo`),
  UNIQUE KEY `uk_ajuste_resumen` (`restaurante_id`,`ambiente`,`resumen_identidad`),
  KEY `idx_ajuste_pendiente` (`restaurante_id`,`estado`,`id`),
  CONSTRAINT `fk_ajuste_restaurante` FOREIGN KEY (`restaurante_id`) REFERENCES `restaurantes` (`id`),
  CONSTRAINT `fk_ajuste_documento` FOREIGN KEY (`documento_id`) REFERENCES `emision_documentos` (`id`),
  CONSTRAINT `fk_ajuste_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Conexiones y referencias de NubeFacT; 021 solo para instalaciones existentes.
CREATE TABLE `emision_nubefact_config` (
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

CREATE TABLE `emision_nubefact_documentos` (
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

-- Unico dato inicial: superadmin sin restaurante asignado.
INSERT INTO `usuarios` (`restaurante_id`, `nombre`, `email`, `password`, `rol`, `activo`, `session_version`)
VALUES (NULL, 'Super Admin', 'admin@mesora.local',
  '$2y$10$603N.8bo09r20VUwDGo/VOoJq8G62j1SOYwlfhHGHNSTS0sM5QdCq', 'superadmin', 1, 1);

-- Comprobaciones manuales: 1 superadmin, 1 usuario, 0 restaurantes, 26 tablas.
SELECT COUNT(*) AS superadmins FROM `usuarios` WHERE `rol` = 'superadmin';
SELECT COUNT(*) AS usuarios_total FROM `usuarios`;
SELECT COUNT(*) AS restaurantes_total FROM `restaurantes`;
SELECT COUNT(*) AS tablas_total FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE';
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
