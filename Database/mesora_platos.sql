-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Servidor: sql103.byetcluster.com
-- Tiempo de generación: 23-11-2026 a las 10:47:12
-- Versión del servidor: 11.4.14-MariaDB
-- Versión de PHP: 7.2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `icei_43456313_mesora`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `auditoria`
--

CREATE TABLE `auditoria` (
  `id` int(11) NOT NULL,
  `restaurante_id` int(11) DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `usuario_nombre` varchar(100) DEFAULT NULL,
  `accion` varchar(120) NOT NULL,
  `detalle` text DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `auditoria`
--

INSERT INTO `auditoria` (`id`, `restaurante_id`, `usuario_id`, `usuario_nombre`, `accion`, `detalle`, `ip`, `created_at`) VALUES
(1, 1, 2, 'admin esquina', 'Inició sesión', NULL, '132.184.129.182', '2026-11-21 15:32:02'),
(2, 1, 2, 'admin esquina', 'Inició sesión', NULL, '132.184.129.182', '2026-11-21 16:33:58'),
(3, 1, 2, 'admin esquina', 'Inició sesión', NULL, '179.6.39.12', '2026-11-21 16:45:17'),
(4, 2, 3, 'admin mirador', 'Inició sesión', NULL, '179.6.39.12', '2026-11-21 16:45:50'),
(5, 1, 2, 'admin esquina', 'Inició sesión', NULL, '132.184.129.182', '2026-11-21 18:08:24'),
(6, 1, 2, 'admin esquina', 'Inició sesión', NULL, '179.6.39.160', '2026-11-22 00:22:32'),
(7, 1, 2, 'admin esquina', 'Inició sesión', NULL, '179.6.39.160', '2026-11-22 00:59:52'),
(8, 1, 2, 'admin esquina', 'Inició sesión', NULL, '179.6.39.160', '2026-11-22 23:19:57'),
(9, 1, 2, 'admin esquina', 'Inició sesión', NULL, '179.6.39.160', '2026-11-22 23:37:25');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `categorias`
--

CREATE TABLE `categorias` (
  `id` int(11) NOT NULL,
  `restaurante_id` int(11) NOT NULL,
  `nombre` varchar(80) NOT NULL,
  `icono` varchar(10) DEFAULT '?️',
  `orden` int(11) DEFAULT 0,
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `categorias`
--

INSERT INTO `categorias` (`id`, `restaurante_id`, `nombre`, `icono`, `orden`, `activo`) VALUES
(1, 1, 'PESCADOS Y MARISCOS', '🐟', 0, 1),
(2, 1, 'DE LA CASA', '🍽️', 1, 1),
(3, 1, 'TACACHOS', '🍌', 2, 1),
(4, 1, 'SALTEADOS', '🍳', 3, 1),
(5, 1, 'PASTAS Y AL GRILL', '🍝', 4, 1),
(6, 1, 'ALITAS', '🪽', 5, 1),
(7, 1, 'HAMBURGUESA', '🍔', 6, 1),
(8, 1, 'SALCHIPAPA', '🍟', 7, 1),
(9, 1, 'BEBIDAS', '🍶', 8, 1),
(10, 1, 'DESAYUNOS', '🍲', 9, 1),
(11, 1, 'MAKIS', '🍣', 10, 1),
(12, 1, 'JUGOS', '🧃', 11, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `comprobantes`
--

CREATE TABLE `comprobantes` (
  `id` int(11) NOT NULL,
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

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `comprobante_items`
--

CREATE TABLE `comprobante_items` (
  `id` int(11) NOT NULL,
  `comprobante_id` int(11) NOT NULL,
  `descripcion` varchar(200) NOT NULL,
  `cantidad` int(11) NOT NULL DEFAULT 1,
  `precio_unitario` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `emision_ajustes`
--

CREATE TABLE `emision_ajustes` (
  `id` bigint unsigned NOT NULL,
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
  KEY `idx_ajuste_pendiente` (`restaurante_id`,`estado`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `emision_config`
--

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
  `correlativo_boleta` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `correlativo_factura` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `correlativo_boleta_pruebas` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `correlativo_factura_pruebas` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `correlativo_nota_factura` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `correlativo_nota_boleta` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `correlativo_nota_factura_pruebas` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `correlativo_nota_boleta_pruebas` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `emision_documentos`
--

CREATE TABLE `emision_documentos` (
  `id` bigint unsigned NOT NULL,
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
  KEY `idx_emision_pendiente` (`restaurante_id`,`estado`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `envios_comanda`
--

CREATE TABLE `envios_comanda` (
  `restaurante_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `operacion_id` char(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `huella` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `resultado_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`resultado_json`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`restaurante_id`,`usuario_id`,`operacion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `facturacion_config`
--

CREATE TABLE `facturacion_config` (
  `id` int(11) NOT NULL,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `facturacion_config`
--

INSERT INTO `facturacion_config` (`id`, `restaurante_id`, `ruc`, `razon_social`, `nombre_comercial`, `direccion_fiscal`, `telefono`, `serie_boleta`, `serie_factura`, `serie_simple`, `correlativo_boleta`, `correlativo_factura`, `correlativo_simple`, `pie_mensaje`, `logo`, `updated_at`) VALUES
(1, 1, NULL, '', 'Esquina del Tunche', '', NULL, 'B001', 'F001', 'T001', 0, 0, 0, '¡Gracias por su visita!', '/assets/logos/logo_rest_1_1795279531.webp', '2026-11-21 16:45:31'),
(2, 2, NULL, '', NULL, '', NULL, 'B001', 'F001', 'T001', 0, 0, 0, '¡Gracias por su visita!', '/assets/logos/logo_rest_2_1795279571.webp', '2026-11-21 16:46:11');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `gastos`
--

CREATE TABLE `gastos` (
  `id` int(11) NOT NULL,
  `restaurante_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `turno_id` int(11) DEFAULT NULL,
  `categoria` varchar(100) NOT NULL,
  `descripcion` varchar(255) NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `metodo_pago` enum('efectivo','yape','transferencia','tarjeta','otro') DEFAULT NULL,
  `fecha` date NOT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `login_limites`
--

CREATE TABLE `login_limites` (
  `clave` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `inicio` bigint(20) UNSIGNED NOT NULL,
  `fallos` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `proximo_en` bigint(20) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `login_limites`
--

INSERT INTO `login_limites` (`clave`, `inicio`, `fallos`, `proximo_en`) VALUES
('2311fb81dced5d3213c2f416ac5441c4cefb022e0c70d7fce84af90dbc503271', 1795284504, 0, 0),
('457f00bc745afb49fd578fbc48d7ff0370e74d48f85c1eaaf3c73ee3d575b510', 1795279118, 0, 0),
('5d19c9fff871d16d551e85344850401db206759c0bf3476544e6047b24b14cce', 1795279118, 0, 0),
('6711c2885d84cfc51f979fb149e4d26e8e3ed0fd479cccabe71d5aae391f9f94', 1795274539, 2, 1795274940),
('70e998f66211830947f85220cd0a23aeae39063371ec3d1f8190078061c80265', 1795274888, 1, 1795274888),
('90195ed9f48437cc6720d6f3c19b849cd627aa95c51e7a1dd999ffb2d24fb964', 1795390644, 0, 0),
('a885289426bfa2a0a632e79cbfccd78d40c7e8744ea99437afb8af5a7e6fb9a9', 1795279549, 0, 0),
('cb012e8360b1017ed3914d8c0d8dbf11e09605be860b2b7af6c3b442dee97d2b', 1795390644, 0, 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `logs_acceso`
--

CREATE TABLE `logs_acceso` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `accion` varchar(100) NOT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `logs_acceso`
--

INSERT INTO `logs_acceso` (`id`, `usuario_id`, `accion`, `ip`, `created_at`) VALUES
(1, 1, 'login', '179.6.39.12', '2026-11-21 15:28:39'),
(2, 2, 'login', '132.184.129.182', '2026-11-21 15:32:02'),
(3, 2, 'login', '132.184.129.182', '2026-11-21 16:33:58'),
(4, 1, 'login', '179.6.39.12', '2026-11-21 16:38:39'),
(5, 1, 'logout', '179.6.39.12', '2026-11-21 16:40:18'),
(6, 2, 'login', '179.6.39.12', '2026-11-21 16:45:17'),
(7, 2, 'logout', '179.6.39.12', '2026-11-21 16:45:35'),
(8, 3, 'login', '179.6.39.12', '2026-11-21 16:45:50'),
(9, 2, 'login', '132.184.129.182', '2026-11-21 18:08:24'),
(10, 2, 'login', '179.6.39.160', '2026-11-22 00:22:32'),
(11, 2, 'login', '179.6.39.160', '2026-11-22 00:59:52'),
(12, 2, 'login', '179.6.39.160', '2026-11-22 23:19:57'),
(13, 2, 'login', '179.6.39.160', '2026-11-22 23:37:25');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `mesas`
--

CREATE TABLE `mesas` (
  `id` int(11) NOT NULL,
  `restaurante_id` int(11) NOT NULL,
  `numero` int(11) NOT NULL,
  `capacidad` int(11) DEFAULT 4,
  `estado` enum('libre','ocupada','reservada') DEFAULT 'libre',
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `opciones_grupo`
--

CREATE TABLE `opciones_grupo` (
  `id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `orden` int(11) DEFAULT 1,
  `requerido` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `opciones_grupo`
--

INSERT INTO `opciones_grupo` (`id`, `producto_id`, `nombre`, `orden`, `requerido`) VALUES
(1, 17, '¿Proteína?', 1, 1),
(2, 43, '¿SABOR?', 1, 1),
(3, 55, '¿DE?', 1, 1),
(4, 56, '¿DE?', 1, 1),
(5, 57, '¿DE?', 1, 1),
(6, 58, '¿DE?', 1, 1),
(7, 69, '¿DE?', 1, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `opciones_valor`
--

CREATE TABLE `opciones_valor` (
  `id` int(11) NOT NULL,
  `grupo_id` int(11) NOT NULL,
  `valor` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `opciones_valor`
--

INSERT INTO `opciones_valor` (`id`, `grupo_id`, `valor`) VALUES
(1, 1, 'CECINA'),
(2, 1, 'CHORIZO'),
(3, 1, 'CHICHARRON'),
(4, 2, 'LEMON MIKEY'),
(5, 2, 'ACEVICHADA'),
(6, 2, 'BBQ'),
(7, 2, 'MARACUYA'),
(8, 2, 'MANGO HOT'),
(9, 2, 'CRISPI'),
(10, 2, 'ANTICUCHERAS'),
(11, 3, 'CHICHA MORADA'),
(12, 3, 'COCONA'),
(13, 4, 'CHICHA MORADA'),
(14, 4, 'COCONA'),
(15, 5, 'CHICHA MORADA'),
(16, 5, 'COCONA'),
(17, 6, 'INCA KOLA'),
(18, 6, 'COCA COLA'),
(19, 7, 'TE'),
(20, 7, 'ANIS'),
(21, 7, 'MANZANILLA');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pagos`
--

CREATE TABLE `pagos` (
  `id` int(11) NOT NULL,
  `pedido_id` int(11) NOT NULL,
  `metodo` enum('efectivo','yape','transferencia','tarjeta','otro') NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `referencia` varchar(100) DEFAULT NULL,
  `usuario_id` int(11) NOT NULL,
  `turno_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `anulado` tinyint(1) DEFAULT 0,
  `anulado_por` int(11) DEFAULT NULL,
  `anulado_en` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pedidos`
--

CREATE TABLE `pedidos` (
  `id` int(11) NOT NULL,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pedido_items`
--

CREATE TABLE `pedido_items` (
  `id` int(11) NOT NULL,
  `pedido_id` int(11) NOT NULL,
  `producto_id` int(11) DEFAULT NULL,
  `nombre_producto` varchar(120) DEFAULT NULL,
  `cantidad` int(11) DEFAULT 1,
  `precio_unitario` decimal(8,2) NOT NULL,
  `subtotal` decimal(8,2) NOT NULL,
  `afectacion_igv` enum('gravada','exonerada','inafecta') NOT NULL DEFAULT 'gravada',
  `notas` text DEFAULT NULL,
  `estado` enum('pendiente','en_preparacion','listo','entregado') DEFAULT 'pendiente',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pedido_item_opciones`
--

CREATE TABLE `pedido_item_opciones` (
  `id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `grupo_id` int(11) NOT NULL,
  `valor_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `productos`
--

CREATE TABLE `productos` (
  `id` int(11) NOT NULL,
  `restaurante_id` int(11) NOT NULL,
  `categoria_id` int(11) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `precio` decimal(8,2) NOT NULL,
  `afectacion_igv` enum('gravada','exonerada','inafecta') NOT NULL DEFAULT 'gravada',
  `imagen` varchar(255) DEFAULT NULL,
  `tiene_opciones` tinyint(1) DEFAULT 0,
  `activo` tinyint(1) DEFAULT 1,
  `sin_stock` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `productos`
--

INSERT INTO `productos` (`id`, `restaurante_id`, `categoria_id`, `nombre`, `descripcion`, `precio`, `afectacion_igv`, `imagen`, `tiene_opciones`, `activo`, `sin_stock`) VALUES
(1, 1, 1, 'LECHE DE TIGRE CARRETILLERO', NULL, '20.00', 'gravada', NULL, 0, 1, 0),
(2, 1, 1, 'CEVICHE DE BARRIO', NULL, '24.00', 'gravada', NULL, 0, 1, 0),
(3, 1, 1, 'ARROZ CON MARISCOS', NULL, '25.00', 'gravada', NULL, 0, 1, 0),
(4, 1, 1, 'CHAUFA DE MARISCOS', NULL, '22.00', 'gravada', NULL, 0, 1, 0),
(5, 1, 1, 'CHICHARRON DE PESCADO', NULL, '25.00', 'gravada', NULL, 0, 1, 0),
(6, 1, 1, 'JALEA MIXTA', NULL, '30.00', 'gravada', NULL, 0, 1, 0),
(7, 1, 1, 'CHICHARRON DE POTA', NULL, '15.00', 'gravada', NULL, 0, 1, 0),
(8, 1, 1, 'DUO MARINO', NULL, '30.00', 'gravada', NULL, 0, 1, 0),
(9, 1, 1, 'TRIO MARINO', NULL, '40.00', 'gravada', NULL, 0, 1, 0),
(10, 1, 2, 'CHAUFA DEL TUNCHE', NULL, '25.00', 'gravada', NULL, 0, 1, 0),
(11, 1, 2, 'FETUCCINNI DEL TUNCHE', NULL, '25.00', 'gravada', NULL, 0, 1, 0),
(12, 1, 2, 'SALTADO DEL TUNCHE', NULL, '25.00', 'gravada', NULL, 0, 1, 0),
(13, 1, 2, 'SALTADO AMAZONICO', NULL, '25.00', 'gravada', NULL, 0, 1, 0),
(14, 1, 2, 'CEVICHE DEL TUNCHE', NULL, '30.00', 'gravada', NULL, 0, 1, 0),
(15, 1, 2, 'COSTILLAS BBQ', NULL, '28.00', 'gravada', NULL, 0, 1, 0),
(16, 1, 2, 'COSTILLAS EN MIEL MARACUYA', NULL, '28.00', 'gravada', NULL, 0, 1, 0),
(17, 1, 3, 'COMBITO DEL TUNCHE', NULL, '15.00', 'gravada', NULL, 1, 1, 0),
(18, 1, 3, 'TACACHO CON CHICHARRON', NULL, '16.00', 'gravada', NULL, 0, 1, 0),
(19, 1, 3, 'TACACHO CON CECINA', NULL, '14.00', 'gravada', NULL, 0, 1, 0),
(20, 1, 3, 'TACACHO CON CHORIZO', NULL, '14.00', 'gravada', NULL, 0, 1, 0),
(21, 1, 3, 'TACACHO COMBINADO', NULL, '18.00', 'gravada', NULL, 0, 1, 0),
(22, 1, 3, 'TACACHO MIXTO', NULL, '22.00', 'gravada', NULL, 0, 1, 0),
(23, 1, 4, 'LOMO SALTADO', NULL, '35.00', 'gravada', NULL, 0, 1, 0),
(24, 1, 4, 'FETUCCINI A LA HUANCAINA LOMO SALTADO', NULL, '37.00', 'gravada', NULL, 0, 1, 0),
(25, 1, 4, 'TALLARINES SALTADOS LOMO FINO', NULL, '35.00', 'gravada', NULL, 0, 1, 0),
(26, 1, 4, 'TALLARINES SALTADOS DE POLLO', NULL, '22.00', 'gravada', NULL, 0, 1, 0),
(27, 1, 4, 'SALTADO DE POLLO', NULL, '22.00', 'gravada', NULL, 0, 1, 0),
(28, 1, 4, 'CHAUFA DE POLLO', NULL, '17.00', 'gravada', NULL, 0, 1, 0),
(29, 1, 4, 'CHAUFA AMAZONICO', NULL, '22.00', 'gravada', NULL, 0, 1, 0),
(30, 1, 4, 'CHAUFA DE CECINA', NULL, '20.00', 'gravada', NULL, 0, 1, 0),
(31, 1, 4, 'CHAUFA DE CHANCHO', NULL, '20.00', 'gravada', NULL, 0, 1, 0),
(32, 1, 4, 'CHAUFA MAR Y SELVA', NULL, '25.00', 'gravada', NULL, 0, 1, 0),
(33, 1, 5, 'FETUCCINNI A LO ALFREDO CLASICO', NULL, '20.00', 'gravada', NULL, 0, 1, 0),
(34, 1, 5, 'FETUCCINNI A LO ALFREDO PECHUGA GRILL', NULL, '25.00', 'gravada', NULL, 0, 1, 0),
(35, 1, 5, 'FETUCCINNI A LO ALFREDO BROCHETAS POLLO', NULL, '25.00', 'gravada', NULL, 0, 1, 0),
(36, 1, 5, 'FETUCCINNI A LO ALFREDO LOMO FINO GRILL', NULL, '37.00', 'gravada', NULL, 0, 1, 0),
(37, 1, 5, 'FETUCCINNI A LA HUANCAINA PECHUGA GRILL', NULL, '25.00', 'gravada', NULL, 0, 1, 0),
(38, 1, 5, 'FETUCCINNI A LA HUANCAINA BROCHETAS POLLO', NULL, '26.00', 'gravada', NULL, 0, 1, 0),
(39, 1, 5, 'FETUCCINNI A LA HUANCAINA LOMO FINO GRILL', NULL, '37.00', 'gravada', NULL, 0, 1, 0),
(40, 1, 5, 'PECHUGA AL GRILL', NULL, '22.00', 'gravada', NULL, 0, 1, 0),
(41, 1, 5, 'LOMO FINO AL GRILL', NULL, '35.00', 'gravada', NULL, 0, 1, 0),
(42, 1, 5, 'BROCHETAS DE POLLO', NULL, '24.00', 'gravada', NULL, 0, 1, 0),
(43, 1, 6, 'ALITAS PERSONAL', NULL, '20.00', 'gravada', NULL, 1, 1, 0),
(44, 1, 6, 'ALITAS PARA PICAR', NULL, '35.00', 'gravada', NULL, 0, 1, 0),
(45, 1, 7, 'HAMBURGUESA CLASICA', NULL, '16.00', 'gravada', NULL, 0, 1, 0),
(46, 1, 7, 'HAMBURGUESA DOBLE', NULL, '27.00', 'gravada', NULL, 0, 1, 0),
(47, 1, 7, 'HAMBURGUESA PALTEADA', NULL, '17.00', 'gravada', NULL, 0, 1, 0),
(48, 1, 7, 'HAMBURGUESA HAWAIANA', NULL, '18.00', 'gravada', NULL, 0, 1, 0),
(49, 1, 7, 'HAMBURGUESA A LO POBRE', NULL, '18.00', 'gravada', NULL, 0, 1, 0),
(50, 1, 7, 'HAMBURGUESA CRISPI', NULL, '15.00', 'gravada', NULL, 0, 1, 0),
(51, 1, 8, 'SALCHIPAPA CLASICA', NULL, '16.00', 'gravada', NULL, 0, 1, 0),
(52, 1, 8, 'SALCHIPAPA PARRILLERA', NULL, '18.00', 'gravada', NULL, 0, 1, 0),
(53, 1, 8, 'SALCHIPAPA AMAZONICA', NULL, '20.00', 'gravada', NULL, 0, 1, 0),
(54, 1, 8, 'SALCHIPAPA CRISPI CHIKEN', NULL, '18.00', 'gravada', NULL, 0, 1, 0),
(55, 1, 9, '1 JARRA REFRESCO', NULL, '16.00', 'gravada', NULL, 1, 1, 0),
(56, 1, 9, 'MEDIA JARRA REFRESCO', NULL, '8.00', 'gravada', NULL, 1, 1, 0),
(57, 1, 9, 'VASO REFRESCO', NULL, '5.00', 'gravada', NULL, 1, 1, 0),
(58, 1, 9, 'GASEOSA', NULL, '5.00', 'gravada', NULL, 1, 1, 0),
(59, 1, 9, 'AGUA MINERAL', NULL, '4.00', 'gravada', NULL, 0, 1, 0),
(60, 1, 10, 'YUQUITAS ARREBOSADAS', NULL, '12.00', 'gravada', NULL, 0, 1, 0),
(61, 1, 10, 'TORTILLA AMAZONICA', NULL, '14.00', 'gravada', NULL, 0, 1, 0),
(62, 1, 10, 'JUANECITO', NULL, '5.00', 'gravada', NULL, 0, 1, 0),
(63, 1, 10, 'TACACHO CON CHICHARRON', NULL, '16.00', 'gravada', NULL, 0, 1, 0),
(64, 1, 10, 'TACACHO CON CECINA', NULL, '14.00', 'gravada', NULL, 0, 1, 0),
(65, 1, 10, 'TACACHO CON CHORIZO', NULL, '14.00', 'gravada', NULL, 0, 1, 0),
(66, 1, 10, 'TACACHO COMBINADO', NULL, '18.00', 'gravada', NULL, 0, 1, 0),
(67, 1, 10, 'TACACHO MIXTO', NULL, '22.00', 'gravada', NULL, 0, 1, 0),
(68, 1, 10, 'CALDO DE POLLO', NULL, '10.00', 'gravada', NULL, 0, 1, 0),
(69, 1, 9, 'INFUSION', NULL, '4.00', 'gravada', NULL, 1, 1, 0),
(70, 1, 10, 'SANDWICHE DE POLLO', NULL, '10.00', 'gravada', NULL, 0, 1, 0),
(71, 1, 10, 'SANDWICHE DE CHORIZO', NULL, '12.00', 'gravada', NULL, 0, 1, 0),
(72, 1, 10, 'SANDWICHE DE CECINA', NULL, '12.00', 'gravada', NULL, 0, 1, 0),
(73, 1, 10, 'SANDWICHE DE CHICHARRON', NULL, '12.00', 'gravada', NULL, 0, 1, 0),
(74, 1, 10, 'SANDWICHE AMAZONICO', NULL, '14.00', 'gravada', NULL, 0, 1, 0),
(75, 1, 12, 'JUGO PAPAYA', NULL, '7.00', 'gravada', NULL, 0, 1, 0),
(76, 1, 12, 'JUGO PIÑA', NULL, '7.00', 'gravada', NULL, 0, 1, 0),
(77, 1, 12, 'JUGO TUNAS', NULL, '8.00', 'gravada', NULL, 0, 1, 0),
(78, 1, 12, 'JUGO ESPECIAL', NULL, '12.00', 'gravada', NULL, 0, 1, 0),
(79, 1, 12, 'FRESA CON LECHE', NULL, '12.00', 'gravada', NULL, 0, 1, 0),
(80, 1, 12, 'MANGO CON LECHE', NULL, '12.00', 'gravada', NULL, 0, 1, 0),
(81, 1, 12, 'GUANABANA CON LECHE', NULL, '12.00', 'gravada', NULL, 0, 1, 0),
(82, 1, 12, 'LUCUMA CON LECHE', NULL, '12.00', 'gravada', NULL, 0, 1, 0),
(83, 1, 12, 'PLATANO CON LECHE', NULL, '12.00', 'gravada', NULL, 0, 1, 0),
(84, 1, 12, 'JUGOS MIX', NULL, '14.00', 'gravada', NULL, 0, 1, 0),
(85, 1, 12, 'JUGO SURTIDO', NULL, '8.00', 'gravada', NULL, 0, 1, 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `restaurantes`
--

CREATE TABLE `restaurantes` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `restaurantes`
--

INSERT INTO `restaurantes` (`id`, `nombre`, `logo`, `activo`, `created_at`) VALUES
(1, 'Esquina del Tunche', NULL, 1, '2026-11-21 15:29:37'),
(2, 'Mirador del Tunche', NULL, 1, '2026-11-21 16:39:09');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `reversos_pago`
--

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

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `turnos`
--

CREATE TABLE `turnos` (
  `id` int(11) NOT NULL,
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

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `restaurante_id` int(11) DEFAULT NULL,
  `nombre` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `rol` enum('superadmin','admin','encargado','atencion','cocina') NOT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `session_version` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `restaurante_id`, `nombre`, `email`, `password`, `rol`, `activo`, `session_version`, `created_at`) VALUES
(1, NULL, 'Super Admin', 'admin@mesora.local', '$2y$12$Ts7SXCNUFRhs/FeUXwWvA.VRy1M7Ge31rOFXf8ycxMUMPtOzPS1z6', 'superadmin', 1, 2, '2026-11-21 15:07:07'),
(2, 1, 'admin esquina', 'admin@esquina.tunche', '$2y$12$JoCWk7xmzTphyhWc09pI8eFPztCgIefKU9/pbcgcS0p78bYCoucdy', 'admin', 1, 1, '2026-11-21 15:31:48'),
(3, 2, 'admin mirador', 'admin@mirador.tunche', '$2y$12$k5RyvZSKRXkbNRdPhvtNbeFgmmgUNcERrQmgiYhQuDtMO4OFAJFGC', 'admin', 1, 1, '2026-11-21 16:40:02');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `auditoria`
--
ALTER TABLE `auditoria`
  ADD PRIMARY KEY (`id`),
  ADD KEY `restaurante_id` (`restaurante_id`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `idx_audit_fecha` (`created_at`);

--
-- Indices de la tabla `categorias`
--
ALTER TABLE `categorias`
  ADD PRIMARY KEY (`id`),
  ADD KEY `restaurante_id` (`restaurante_id`);

--
-- Indices de la tabla `comprobante_items`
--
ALTER TABLE `comprobante_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `comprobante_id` (`comprobante_id`);

--
-- Indices de la tabla `emision_config`
--
ALTER TABLE `emision_config`
  ADD PRIMARY KEY (`restaurante_id`);

--
-- Indices de la tabla `facturacion_config`
--
ALTER TABLE `facturacion_config`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `restaurante_id` (`restaurante_id`);

--
-- Indices de la tabla `gastos`
--
ALTER TABLE `gastos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `idx_gastos_turno` (`turno_id`),
  ADD KEY `idx_gastos_rest_fecha` (`restaurante_id`,`fecha`);

--
-- Indices de la tabla `login_limites`
--
ALTER TABLE `login_limites`
  ADD PRIMARY KEY (`clave`),
  ADD KEY `idx_login_limites_inicio` (`inicio`);

--
-- Indices de la tabla `logs_acceso`
--
ALTER TABLE `logs_acceso`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Indices de la tabla `mesas`
--
ALTER TABLE `mesas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_mesa_numero` (`restaurante_id`,`numero`);

--
-- Indices de la tabla `opciones_grupo`
--
ALTER TABLE `opciones_grupo`
  ADD PRIMARY KEY (`id`),
  ADD KEY `producto_id` (`producto_id`);

--
-- Indices de la tabla `opciones_valor`
--
ALTER TABLE `opciones_valor`
  ADD PRIMARY KEY (`id`),
  ADD KEY `grupo_id` (`grupo_id`);

--
-- Indices de la tabla `pagos`
--
ALTER TABLE `pagos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pedido_id` (`pedido_id`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `idx_pagos_turno` (`turno_id`);

--
-- Indices de la tabla `pedidos`
--
ALTER TABLE `pedidos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `mesa_id` (`mesa_id`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `restaurante_id` (`restaurante_id`);

--
-- Indices de la tabla `pedido_items`
--
ALTER TABLE `pedido_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pedido_id` (`pedido_id`),
  ADD KEY `fk_pi_producto` (`producto_id`);

--
-- Indices de la tabla `pedido_item_opciones`
--
ALTER TABLE `pedido_item_opciones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `grupo_id` (`grupo_id`),
  ADD KEY `valor_id` (`valor_id`);

--
-- Indices de la tabla `productos`
--
ALTER TABLE `productos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `categoria_id` (`categoria_id`),
  ADD KEY `restaurante_id` (`restaurante_id`);

--
-- Indices de la tabla `restaurantes`
--
ALTER TABLE `restaurantes`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `restaurante_id` (`restaurante_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `auditoria`
--
ALTER TABLE `auditoria`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `categorias`
--
ALTER TABLE `categorias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de la tabla `comprobantes`
--
ALTER TABLE `comprobantes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `comprobante_items`
--
ALTER TABLE `comprobante_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `emision_ajustes`
--
ALTER TABLE `emision_ajustes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `emision_documentos`
--
ALTER TABLE `emision_documentos`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `facturacion_config`
--
ALTER TABLE `facturacion_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `gastos`
--
ALTER TABLE `gastos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `logs_acceso`
--
ALTER TABLE `logs_acceso`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de la tabla `mesas`
--
ALTER TABLE `mesas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `opciones_grupo`
--
ALTER TABLE `opciones_grupo`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `opciones_valor`
--
ALTER TABLE `opciones_valor`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT de la tabla `pagos`
--
ALTER TABLE `pagos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `pedidos`
--
ALTER TABLE `pedidos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `pedido_items`
--
ALTER TABLE `pedido_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `pedido_item_opciones`
--
ALTER TABLE `pedido_item_opciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `productos`
--
ALTER TABLE `productos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=86;

--
-- AUTO_INCREMENT de la tabla `restaurantes`
--
ALTER TABLE `restaurantes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `turnos`
--
ALTER TABLE `turnos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `categorias`
--
ALTER TABLE `categorias`
  ADD CONSTRAINT `categorias_ibfk_1` FOREIGN KEY (`restaurante_id`) REFERENCES `restaurantes` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `comprobante_items`
--
ALTER TABLE `comprobante_items`
  ADD CONSTRAINT `comprobante_items_ibfk_1` FOREIGN KEY (`comprobante_id`) REFERENCES `comprobantes` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `emision_config`
--
ALTER TABLE `emision_config`
  ADD CONSTRAINT `fk_emision_config_restaurante` FOREIGN KEY (`restaurante_id`) REFERENCES `restaurantes` (`id`);

--
-- Filtros para la tabla `facturacion_config`
--
ALTER TABLE `facturacion_config`
  ADD CONSTRAINT `facturacion_config_ibfk_1` FOREIGN KEY (`restaurante_id`) REFERENCES `restaurantes` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `gastos`
--
ALTER TABLE `gastos`
  ADD CONSTRAINT `fk_gastos_turno` FOREIGN KEY (`turno_id`) REFERENCES `turnos` (`id`),
  ADD CONSTRAINT `gastos_ibfk_1` FOREIGN KEY (`restaurante_id`) REFERENCES `restaurantes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `gastos_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `logs_acceso`
--
ALTER TABLE `logs_acceso`
  ADD CONSTRAINT `logs_acceso_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `mesas`
--
ALTER TABLE `mesas`
  ADD CONSTRAINT `mesas_ibfk_1` FOREIGN KEY (`restaurante_id`) REFERENCES `restaurantes` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `opciones_grupo`
--
ALTER TABLE `opciones_grupo`
  ADD CONSTRAINT `opciones_grupo_ibfk_1` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `opciones_valor`
--
ALTER TABLE `opciones_valor`
  ADD CONSTRAINT `opciones_valor_ibfk_1` FOREIGN KEY (`grupo_id`) REFERENCES `opciones_grupo` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `pagos`
--
ALTER TABLE `pagos`
  ADD CONSTRAINT `fk_pagos_turno` FOREIGN KEY (`turno_id`) REFERENCES `turnos` (`id`),
  ADD CONSTRAINT `pagos_ibfk_1` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `pagos_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `pedidos`
--
ALTER TABLE `pedidos`
  ADD CONSTRAINT `pedidos_ibfk_1` FOREIGN KEY (`mesa_id`) REFERENCES `mesas` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `pedidos_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `pedidos_ibfk_3` FOREIGN KEY (`restaurante_id`) REFERENCES `restaurantes` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `pedido_items`
--
ALTER TABLE `pedido_items`
  ADD CONSTRAINT `fk_pi_producto` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `pedido_items_ibfk_1` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `pedido_item_opciones`
--
ALTER TABLE `pedido_item_opciones`
  ADD CONSTRAINT `pedido_item_opciones_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `pedido_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `pedido_item_opciones_ibfk_2` FOREIGN KEY (`grupo_id`) REFERENCES `opciones_grupo` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `pedido_item_opciones_ibfk_3` FOREIGN KEY (`valor_id`) REFERENCES `opciones_valor` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `productos`
--
ALTER TABLE `productos`
  ADD CONSTRAINT `productos_ibfk_1` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `productos_ibfk_2` FOREIGN KEY (`restaurante_id`) REFERENCES `restaurantes` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `usuarios_ibfk_1` FOREIGN KEY (`restaurante_id`) REFERENCES `restaurantes` (`id`) ON DELETE SET NULL;

ALTER TABLE `emision_documentos`
  ADD CONSTRAINT `fk_emision_documentos_restaurante` FOREIGN KEY (`restaurante_id`) REFERENCES `restaurantes` (`id`),
  ADD CONSTRAINT `fk_emision_documentos_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`),
  ADD CONSTRAINT `fk_emision_documentos_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

ALTER TABLE `emision_ajustes`
  ADD CONSTRAINT `fk_ajuste_restaurante` FOREIGN KEY (`restaurante_id`) REFERENCES `restaurantes` (`id`),
  ADD CONSTRAINT `fk_ajuste_documento` FOREIGN KEY (`documento_id`) REFERENCES `emision_documentos` (`id`),
  ADD CONSTRAINT `fk_ajuste_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);
COMMIT;

-- 021_nubefact.sql: tablas del proveedor de emision electronica.
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

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
