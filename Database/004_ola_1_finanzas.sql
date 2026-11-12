-- Archivo: 004_ola_1_finanzas.sql
-- Descripción: Turno explícito para pagos y gastos, cierres persistidos, reversos e idempotencia de comandas.
-- INSTRUCCIONES: Ejecutar manualmente en phpMyAdmin, en una copia de ensayo antes del corte.
-- Compatibilidad objetivo: MariaDB 10.4 (InnoDB). Seleccionar la base correcta.
-- Antes: respaldar y verificar restauración, cerrar todos los turnos con el código anterior,
-- detener escrituras, confirmar 002_produccion_limpia o esquema equivalente y Ola 0 / 003.
-- No se reasignan movimientos existentes: turno_id y cierre_json quedan NULL para legado.
-- Mantener la aplicación en mantenimiento hasta verificar las cinco modificaciones.
-- DDL confirma implícitamente; si hay error parcial, detener y revisar estructura. No reejecutar a ciegas.
-- Reversión previa a recibir movimientos: 005_reversion_ola_1_finanzas.sql.

SET @ola1_finanzas_ok = 0;
SET @ola1_esquema_ok = 0;
SET @ola1_esquema_ok = (
    16 = (
        SELECT COUNT(*) FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND ENGINE = 'InnoDB'
          AND TABLE_NAME IN ('restaurantes', 'usuarios', 'mesas', 'pedidos', 'pedido_items',
              'pedido_item_opciones', 'pagos', 'gastos', 'turnos', 'comprobantes', 'auditoria',
              'facturacion_config', 'logs_acceso', 'productos', 'opciones_grupo', 'opciones_valor')
    )
    AND NOT EXISTS (
        SELECT 1 FROM (
            SELECT 'restaurantes' AS tabla, 'id,activo' AS columnas
            UNION ALL SELECT 'usuarios', 'id,restaurante_id,nombre,email,password,rol,activo,session_version'
            UNION ALL SELECT 'mesas', 'id,restaurante_id,numero,estado,activo'
            UNION ALL SELECT 'pedidos', 'id,restaurante_id,mesa_id,usuario_id,tipo,estado,total,descuento,cargo_extra,notas'
            UNION ALL SELECT 'pedido_items', 'id,pedido_id,producto_id,nombre_producto,cantidad,precio_unitario,subtotal,notas,estado'
            UNION ALL SELECT 'pedido_item_opciones', 'item_id,grupo_id,valor_id'
            UNION ALL SELECT 'pagos', 'id,pedido_id,metodo,monto,referencia,usuario_id,anulado,anulado_por,anulado_en,created_at'
            UNION ALL SELECT 'gastos', 'id,restaurante_id,usuario_id,categoria,descripcion,monto,fecha,activo'
            UNION ALL SELECT 'turnos', 'id,restaurante_id,usuario_id,inicio,fin,total_efectivo,total_yape,total_transferencia,total_tarjeta,total_otros,total_general'
            UNION ALL SELECT 'comprobantes', 'id,restaurante_id,pedido_id,usuario_id,tipo,serie,correlativo,numero_comprobante,tipo_documento,numero_documento,nombre_cliente,direccion_cliente,distrito,provincia,departamento,subtotal,igv,total,descuento,cargo_extra,items_json,pagos_json,created_at,anulado,motivo_anulacion'
            UNION ALL SELECT 'auditoria', 'restaurante_id,usuario_id,usuario_nombre,accion,detalle,ip,created_at'
            UNION ALL SELECT 'facturacion_config', 'restaurante_id,serie_boleta,serie_factura,serie_simple,correlativo_boleta,correlativo_factura,correlativo_simple'
            UNION ALL SELECT 'logs_acceso', 'usuario_id,accion,ip,created_at'
            UNION ALL SELECT 'productos', 'id,restaurante_id,nombre,precio,activo'
            UNION ALL SELECT 'opciones_grupo', 'id,producto_id'
            UNION ALL SELECT 'opciones_valor', 'id,grupo_id,valor'
        ) AS requisito
        WHERE (SELECT COUNT(*) FROM information_schema.COLUMNS c
            WHERE c.TABLE_SCHEMA = DATABASE() AND c.TABLE_NAME = requisito.tabla
              AND FIND_IN_SET(c.COLUMN_NAME, requisito.columnas) > 0)
          <> 1 + LENGTH(requisito.columnas) - LENGTH(REPLACE(requisito.columnas, ',', ''))
    )
    -- Las nuevas FK referencian identificadores INT con signo y PK individual.
    AND 4 = (
        SELECT COUNT(*) FROM information_schema.COLUMNS c
        WHERE c.TABLE_SCHEMA = DATABASE() AND c.COLUMN_NAME = 'id'
          AND c.TABLE_NAME IN ('pagos', 'turnos', 'usuarios', 'restaurantes')
          AND c.DATA_TYPE = 'int' AND c.COLUMN_TYPE NOT LIKE '%unsigned%'
          AND EXISTS (SELECT 1 FROM information_schema.STATISTICS s
              WHERE s.TABLE_SCHEMA = c.TABLE_SCHEMA AND s.TABLE_NAME = c.TABLE_NAME
                AND s.INDEX_NAME = 'PRIMARY' AND s.SEQ_IN_INDEX = 1 AND s.COLUMN_NAME = 'id')
          AND NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS s
              WHERE s.TABLE_SCHEMA = c.TABLE_SCHEMA AND s.TABLE_NAME = c.TABLE_NAME
                AND s.INDEX_NAME = 'PRIMARY' AND s.SEQ_IN_INDEX > 1)
    )
    AND NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND ((TABLE_NAME IN ('pagos', 'gastos') AND COLUMN_NAME = 'turno_id')
            OR (TABLE_NAME = 'turnos' AND COLUMN_NAME = 'cierre_json'))
    )
    AND NOT EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('reversos_pago', 'envios_comanda')
    )
);
-- Preparar la lectura de turnos solo después de confirmar que existe su estructura.
SET @ola1_turnos_ok = 0;
SET @consulta_turnos = IF(@ola1_esquema_ok,
    'SELECT NOT EXISTS (SELECT 1 FROM turnos WHERE fin IS NULL) INTO @ola1_turnos_ok',
    'SELECT 0 INTO @ola1_turnos_ok');
PREPARE verificar_turnos FROM @consulta_turnos;
EXECUTE verificar_turnos;
DEALLOCATE PREPARE verificar_turnos;
SET @ola1_finanzas_ok = @ola1_esquema_ok AND @ola1_turnos_ok;
SELECT IF(@ola1_finanzas_ok, 'Precondiciones de corte satisfechas',
    'BLOQUEADO: turnos abiertos, estructura incompleta o migración ya iniciada') AS resultado;

SET @ddl_finanzas = IF(@ola1_finanzas_ok, 'ALTER TABLE pagos
    ADD COLUMN turno_id INT NULL,
    ADD INDEX idx_pagos_turno (turno_id),
    ADD CONSTRAINT fk_pagos_turno FOREIGN KEY (turno_id) REFERENCES turnos (id)', 'SELECT ''Omitido por precondición'' AS resultado');
PREPARE ddl_finanzas FROM @ddl_finanzas;
EXECUTE ddl_finanzas;
DEALLOCATE PREPARE ddl_finanzas;

SET @ddl_finanzas = IF(@ola1_finanzas_ok, 'ALTER TABLE gastos
    ADD COLUMN turno_id INT NULL,
    ADD INDEX idx_gastos_turno (turno_id),
    ADD CONSTRAINT fk_gastos_turno FOREIGN KEY (turno_id) REFERENCES turnos (id)', 'SELECT ''Omitido por precondición'' AS resultado');
PREPARE ddl_finanzas FROM @ddl_finanzas;
EXECUTE ddl_finanzas;
DEALLOCATE PREPARE ddl_finanzas;

SET @ddl_finanzas = IF(@ola1_finanzas_ok, 'ALTER TABLE turnos
    ADD COLUMN cierre_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL
    CHECK (JSON_VALID(cierre_json))', 'SELECT ''Omitido por precondición'' AS resultado');
PREPARE ddl_finanzas FROM @ddl_finanzas;
EXECUTE ddl_finanzas;
DEALLOCATE PREPARE ddl_finanzas;

SET @ddl_finanzas = IF(@ola1_finanzas_ok, 'CREATE TABLE reversos_pago (
    pago_id INT NOT NULL,
    turno_id INT NOT NULL,
    usuario_id INT NOT NULL,
    motivo VARCHAR(200) NOT NULL,
    venta_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL CHECK (JSON_VALID(venta_json)),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (pago_id),
    KEY idx_reversos_turno (turno_id),
    CONSTRAINT fk_reversos_pago FOREIGN KEY (pago_id) REFERENCES pagos (id),
    CONSTRAINT fk_reversos_turno FOREIGN KEY (turno_id) REFERENCES turnos (id),
    CONSTRAINT fk_reversos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci', 'SELECT ''Omitido por precondición'' AS resultado');
PREPARE ddl_finanzas FROM @ddl_finanzas;
EXECUTE ddl_finanzas;
DEALLOCATE PREPARE ddl_finanzas;

SET @ddl_finanzas = IF(@ola1_finanzas_ok, 'CREATE TABLE envios_comanda (
    restaurante_id INT NOT NULL,
    usuario_id INT NOT NULL,
    operacion_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    huella CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    resultado_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (JSON_VALID(resultado_json)),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (restaurante_id, usuario_id, operacion_id),
    CONSTRAINT fk_envios_restaurante FOREIGN KEY (restaurante_id) REFERENCES restaurantes (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci', 'SELECT ''Omitido por precondición'' AS resultado');
PREPARE ddl_finanzas FROM @ddl_finanzas;
EXECUTE ddl_finanzas;
DEALLOCATE PREPARE ddl_finanzas;

-- Revisar: deben aparecer las tres columnas y ambas tablas.
SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND ((TABLE_NAME IN ('pagos', 'gastos') AND COLUMN_NAME = 'turno_id')
    OR (TABLE_NAME = 'turnos' AND COLUMN_NAME = 'cierre_json'));
SELECT TABLE_NAME FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('reversos_pago', 'envios_comanda');
