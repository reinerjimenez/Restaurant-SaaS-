-- ============================================================
-- Script: 002_produccion_limpia.sql
-- Propósito: Instalador completo desde cero para producción.
--            Solo contiene el superadmin inicial. No incluye
--            datos de restaurantes de prueba.
-- Uso: Importar en una BD vacía (restaurante_db) desde cero.
--      NUNCA sobre una instalación existente.
-- Referencia: Reconstruido contra 000_restaurante_db.sql y
--             api/guardar_comprobante.php para garantizar
--             coherencia de columnas. Corrige H-018.
-- Fecha: 2026-11-10
-- Autor: R.DEV
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

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
  `imagen` varchar(255) DEFAULT NULL,
  `tiene_opciones` tinyint(1) DEFAULT 0,
  `activo` tinyint(1) DEFAULT 1,
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `anulado` tinyint(1) DEFAULT 0,
  `anulado_por` int(11) DEFAULT NULL,
  `anulado_en` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pedido_id` (`pedido_id`),
  KEY `usuario_id` (`usuario_id`)
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
  `categoria` varchar(100) NOT NULL,
  `descripcion` varchar(255) NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `fecha` date NOT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
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
  ADD CONSTRAINT `pagos_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

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
  ADD CONSTRAINT `gastos_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

ALTER TABLE `logs_acceso`
  ADD CONSTRAINT `logs_acceso_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

-- ── DATO INICIAL: superadmin ─────────────────────────────────────
-- Contraseña por defecto: "admin123" — CAMBIAR INMEDIATAMENTE tras la instalación
-- Hash generado con password_hash("admin123", PASSWORD_BCRYPT)
INSERT INTO `usuarios` (`restaurante_id`, `nombre`, `email`, `password`, `rol`, `activo`, `session_version`)
VALUES (NULL, 'Super Admin', 'superadmin@restsaas.com',
        '$2y$12$PLACEHOLDER_HASH_CHANGE_ON_INSTALL', 'superadmin', 1, 1);

-- ── INSTRUCCIONES POST-INSTALACIÓN ───────────────────────────────
-- 1. Genera un hash real con:
--      php -r "echo password_hash('TU_PASSWORD_SEGURA', PASSWORD_BCRYPT);"
--    y actualiza la fila del superadmin.
-- 2. Configura tu .env con los datos de conexión reales.
-- 3. Ejecuta Database/003_session_version_y_restaurante_activo.sql
--    si ya tenías una instalación anterior (no aplica en instalación nueva).
-- 4. Verifica el ciclo completo:
--      crear pedido → agregar ítems → cobrar → emitir comprobante → imprimir.

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- ============================================================
-- REVERSIÓN: DROP DATABASE restaurante_db; CREATE DATABASE restaurante_db;
-- ============================================================
