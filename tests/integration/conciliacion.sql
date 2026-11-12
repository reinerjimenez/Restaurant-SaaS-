-- Solo lectura del origen. Ejecutar únicamente con restaurant_ola1_test seleccionada.
SELECT DATABASE() AS base, @@hostname AS equipo, @@datadir AS directorio_datos, VERSION() AS version_servidor;

-- El operador copia y ejecuta las consultas de conteo devueltas tras comprobar la base.
SELECT CONCAT('SELECT ', QUOTE(TABLE_NAME), ' AS tabla, COUNT(*) AS filas FROM `',
              REPLACE(TABLE_NAME, '`', '``'), '`', CHAR(59)) AS consulta
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'
  AND DATABASE() IN ('restaurant_ola1_test', 'restaurant_ola1_restore')
ORDER BY TABLE_NAME;

-- Ejecutar las consultas siguientes solo en bases con 004 y 006 verificadas.
SELECT pe.restaurante_id, pa.turno_id, pa.metodo, COALESCE(pa.anulado, 0) AS anulado,
       COUNT(*) AS filas, SUM(pa.monto) AS importe
FROM restaurant_ola1_test.pagos pa JOIN restaurant_ola1_test.pedidos pe ON pe.id = pa.pedido_id
GROUP BY pe.restaurante_id, pa.turno_id, pa.metodo, COALESCE(pa.anulado, 0)
ORDER BY pe.restaurante_id, pa.turno_id, pa.metodo, anulado;

SELECT pe.restaurante_id, r.turno_id, pa.metodo, COUNT(*) AS filas, SUM(pa.monto) AS importe
FROM restaurant_ola1_test.reversos_pago r JOIN restaurant_ola1_test.pagos pa ON pa.id = r.pago_id JOIN restaurant_ola1_test.pedidos pe ON pe.id = pa.pedido_id
GROUP BY pe.restaurante_id, r.turno_id, pa.metodo
ORDER BY pe.restaurante_id, r.turno_id, pa.metodo;

SELECT restaurante_id, turno_id, fecha, activo, COUNT(*) AS filas, SUM(monto) AS importe
FROM restaurant_ola1_test.gastos GROUP BY restaurante_id, turno_id, fecha, activo
ORDER BY restaurante_id, turno_id, fecha, activo;

SELECT restaurante_id, tipo, COALESCE(anulado, 0) AS estado_anulacion,
       COUNT(*) AS filas, SUM(total) AS importe
FROM restaurant_ola1_test.comprobantes GROUP BY restaurante_id, tipo, COALESCE(anulado, 0)
ORDER BY restaurante_id, tipo, estado_anulacion;

WITH brutos AS (
    SELECT turno_id, SUM(monto) AS importe FROM restaurant_ola1_test.pagos WHERE turno_id IS NOT NULL GROUP BY turno_id
), reversos AS (
    SELECT r.turno_id, SUM(pa.monto) AS importe
    FROM restaurant_ola1_test.reversos_pago r JOIN restaurant_ola1_test.pagos pa ON pa.id = r.pago_id GROUP BY r.turno_id
), egresos AS (
    SELECT turno_id, SUM(monto) AS importe FROM restaurant_ola1_test.gastos WHERE activo = 1 GROUP BY turno_id
)
SELECT t.restaurante_id, t.id, t.inicio, t.fin, t.total_general,
       COALESCE(b.importe, 0) - COALESCE(r.importe, 0) AS neto_cobros,
       COALESCE(e.importe, 0) AS gastos_activos,
       SHA2(t.cierre_json, 256) AS huella_cierre
FROM restaurant_ola1_test.turnos t LEFT JOIN brutos b ON b.turno_id = t.id
LEFT JOIN reversos r ON r.turno_id = t.id LEFT JOIN egresos e ON e.turno_id = t.id
ORDER BY t.restaurante_id, t.id;

SELECT 'pagos' AS relacion, COUNT(*) AS inconsistencias
FROM restaurant_ola1_test.pagos pa LEFT JOIN restaurant_ola1_test.pedidos pe ON pe.id = pa.pedido_id LEFT JOIN restaurant_ola1_test.turnos t ON t.id = pa.turno_id
WHERE pe.id IS NULL OR (pa.turno_id IS NOT NULL AND (t.id IS NULL OR t.restaurante_id <> pe.restaurante_id))
UNION ALL
SELECT 'pedidos_mesas', COUNT(*)
FROM restaurant_ola1_test.pedidos pe LEFT JOIN restaurant_ola1_test.mesas m ON m.id = pe.mesa_id
WHERE pe.mesa_id IS NOT NULL AND (m.id IS NULL OR m.restaurante_id <> pe.restaurante_id)
UNION ALL
SELECT 'gastos_turnos', COUNT(*)
FROM restaurant_ola1_test.gastos g LEFT JOIN restaurant_ola1_test.turnos t ON t.id = g.turno_id
WHERE g.turno_id IS NOT NULL AND (t.id IS NULL OR t.restaurante_id <> g.restaurante_id)
UNION ALL
SELECT 'reversos', COUNT(*)
FROM restaurant_ola1_test.reversos_pago r LEFT JOIN restaurant_ola1_test.pagos pa ON pa.id = r.pago_id
LEFT JOIN restaurant_ola1_test.pedidos pe ON pe.id = pa.pedido_id LEFT JOIN restaurant_ola1_test.turnos t ON t.id = r.turno_id
WHERE pe.id IS NULL OR t.id IS NULL OR t.restaurante_id <> pe.restaurante_id
UNION ALL
SELECT 'comprobantes', COUNT(*)
FROM restaurant_ola1_test.comprobantes c LEFT JOIN restaurant_ola1_test.pedidos pe ON pe.id = c.pedido_id
WHERE pe.id IS NULL OR pe.restaurante_id <> c.restaurante_id;

SELECT restaurante_id, accion, COUNT(*) AS filas, MIN(created_at) AS primero, MAX(created_at) AS ultimo
FROM restaurant_ola1_test.auditoria GROUP BY restaurante_id, accion ORDER BY restaurante_id, accion;
