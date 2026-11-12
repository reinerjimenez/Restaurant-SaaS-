<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../includes/reportes_financieros.php';
require_once __DIR__ . '/../includes/gastos_financieros.php';
require_once __DIR__ . '/../config/finanzas.php';

function verificar_reporte(bool $ok, string $mensaje): void {
    if (!$ok) throw new RuntimeException($mensaje);
}

// Dobles PDO: verifican el contrato SQL y el flujo, sin ejecutar consultas ni conectar una base.
final class ConsultaReportePrueba extends PDOStatement {
    public function __construct(private array $paso) {}
    public function execute(?array $params = null): bool {
        verificar_reporte($params === $this->paso['parametros'], 'Parámetros inesperados.');
        return true;
    }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array {
        return $this->paso['filas'];
    }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed {
        return $this->paso['filas'][0] ?? false;
    }
    public function fetchColumn(int $column = 0): mixed {
        $fila = $this->fetch();
        return $fila === false ? false : array_values($fila)[$column];
    }
}

final class ConexionReportePrueba extends PDO {
    public array $eventos = [];
    public bool $fallar_auditoria = false;
    private bool $transaccion = false;
    public function __construct(public array $pasos) {}
    public function prepare(string $query, array $options = []): PDOStatement|false {
        $paso = array_shift($this->pasos);
        verificar_reporte($paso !== null, 'Consulta adicional inesperada.');
        foreach ($paso['fragmentos'] as $fragmento) {
            verificar_reporte(str_contains($query, $fragmento), 'Falta en la consulta: ' . $fragmento);
        }
        foreach ($paso['excluir'] ?? [] as $fragmento) {
            verificar_reporte(!str_contains($query, $fragmento), 'Consulta inesperada: ' . $fragmento);
        }
        return new ConsultaReportePrueba($paso);
    }
    public function beginTransaction(): bool {
        $this->eventos[] = 'begin';
        return $this->transaccion = true;
    }
    public function inTransaction(): bool {
        return $this->transaccion;
    }
    public function commit(): bool {
        $this->eventos[] = 'commit';
        $this->transaccion = false;
        return true;
    }
    public function rollBack(): bool {
        $this->eventos[] = 'rollback';
        $this->transaccion = false;
        return true;
    }
    public function lastInsertId(?string $name = null): string|false {
        return '51';
    }
}

function registrarAuditoria(PDO $db, string $accion, string $detalle = '', bool $obligatoria = false): void {
    verificar_reporte($db->inTransaction() && $obligatoria, 'La auditoría de gasto no es obligatoria dentro de la transacción.');
    $db->eventos[] = $accion . '|' . $detalle;
    if ($db->fallar_auditoria) throw new RuntimeException('Fallo de auditoría simulado.');
}

function paso_reporte(array $fragmentos, array $parametros, array $filas = [], array $excluir = []): array {
    return compact('fragmentos', 'parametros', 'filas', 'excluir');
}

date_default_timezone_set('America/Lima');
$inicio = '2026-11-11';
$fin = limite_exclusivo_reporte($inicio);
verificar_reporte($fin === '2026-11-12', 'El límite exclusivo debe ser el día siguiente.');
verificar_reporte(fecha_reporte('2026-02-30', $inicio) === $inicio, 'Fecha imposible aceptada.');
verificar_reporte(fecha_reporte(['2026-02-01'], $inicio) === $inicio, 'Filtro de fecha no escalar aceptado.');
verificar_reporte(limite_exclusivo_reporte('2024-02-29') === '2024-03-01', 'Cambio de mes bisiesto incorrecto.');

// Pedido abierto el 10, cobrado el 11 en efectivo y otro; ambos pagos pertenecen al 11.
$filtro_pago = ['pa.created_at >= ?', 'pa.created_at < ?', 'COALESCE(pa.anulado, 0) = 0'];
$db = new ConexionReportePrueba([
    paso_reporte([...$filtro_pago, 'COUNT(DISTINCT pa.pedido_id)'], [7, $inicio, $fin],
        [['fecha' => $inicio, 'pedidos' => 1, 'total' => '100.05']]),
    paso_reporte([...$filtro_pago, 'GROUP BY pa.metodo'], [7, $inicio, $fin],
        [['metodo' => 'efectivo', 'total' => '70.01'], ['metodo' => 'otro', 'total' => '30.04']]),
    paso_reporte([...$filtro_pago, 'EXISTS (', 'SUM(pi.cantidad)'], [7, $inicio, $fin],
        [['nombre' => 'Producto', 'vendidos' => 1, 'ingreso' => '100.05']]),
    paso_reporte(['fecha >= ?', 'fecha < ?', 'activo = 1'], [7, '2026-11-10', $inicio], [['total' => '12.25']]),
    paso_reporte(['pago.usuario_id AS cobrador_id', 'GROUP BY pa.pedido_id'], [7, $inicio, $fin],
        [['id' => 41, 'cobrador_id' => 9, 'total' => '100.05']]),
    paso_reporte(['pa.turno_id = ?', 'pv.turno_id = ?', 'pv.turno_id <> ?'], [7, 5, 5, 5]),
    paso_reporte(['pa.turno_id = ?', 'LEFT JOIN comprobantes c ON c.id = NULL'], [7, 5]),
]);
$ventas = ingresos_periodo($db, 7, $inicio, $fin);
verificar_reporte($ventas['total_centimos'] === 10005 && $ventas['pedidos'] === 1
    && $ventas['metodos'][1]['metodo'] === 'otro', 'Cobro mixto incorrecto.');
verificar_reporte(productos_periodo($db, 7, $inicio, $fin)[0]['vendidos'] === 1, 'La cantidad de producto no debe duplicarse.');
verificar_reporte(gastos_contables_periodo($db, 7, '2026-11-10', $inicio) === '12.25', 'El gasto usa su fecha contable.');
verificar_reporte(cobros_periodo($db, 7, $inicio, $fin)[0]['cobrador_id'] === 9, 'El actor se obtiene del pago.');
cobros_periodo($db, 7, $inicio, $fin, turno_id: 5);
cobros_periodo($db, 7, $inicio, $fin, turno_id: 5, comprobante_vigente: false);
verificar_reporte($db->pasos === [], 'Consultas del escenario sin ejecutar.');

$db = new ConexionReportePrueba([
    paso_reporte(['SELECT DISTINCT p.id, m.numero', 'pa.turno_id = ?', 'p.restaurante_id = ?'], [5, 7],
        [['id' => 41, 'numero' => 12], ['id' => 42, 'numero' => null]]),
    paso_reporte(['m.numero AS mesa_numero'], [7, $inicio, $fin], [['id' => 41, 'mesa_numero' => 99]]),
    paso_reporte(['NULL AS mesa_numero'], [7, 5], [['id' => 41], ['id' => 42]], ['JOIN mesas']),
    paso_reporte(['NULL AS mesa_numero'], [7, 5], [['id' => 41]], ['JOIN mesas']),
]);
$mesas_al_cierre = json_decode(json_encode(mesas_del_turno($db, 7, 5), JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
verificar_reporte(cobros_periodo($db, 7, $inicio, $fin)[0]['mesa_numero'] === 99, 'La vista vigente debe mostrar el número editado.');
$historico = cobros_periodo($db, 7, $inicio, $fin, turno_id: 5, comprobante_vigente: false, mesas_cierre: $mesas_al_cierre);
verificar_reporte($historico[0]['mesa_numero'] === 12 && $historico[1]['mesa_numero'] === null, 'El histórico debe usar el número guardado.');
verificar_reporte(cobros_periodo($db, 7, $inicio, $fin, turno_id: 5, comprobante_vigente: false,
    mesas_cierre: [])[0]['mesa_numero'] === null && !$db->pasos, 'Un cierre antiguo no debe consultar la mesa actual.');

$candado_restaurante = paso_reporte(['FROM restaurantes', 'FOR UPDATE'], [7], [['id' => 7]]);
$candado_mesa = paso_reporte(['FROM mesas', 'FOR UPDATE'], [12, 7], [['id' => 12]]);
$mesa_libre = paso_reporte(['FROM pedidos', "estado = 'activo'", 'FOR UPDATE'], [12, 7]);
$db = new ConexionReportePrueba([$candado_restaurante, $candado_mesa, $mesa_libre, paso_reporte(['DELETE FROM mesas'], [12, 7])]);
eliminar_mesa_sin_pedido($db, 7, 12);
verificar_reporte(!$db->pasos, 'El borrado requiere los bloqueos en orden y la comprobación de pedidos.');
$db = new ConexionReportePrueba([$candado_restaurante, $candado_mesa, array_replace($mesa_libre, ['filas' => [['id' => 41]]])]);
try {
    eliminar_mesa_sin_pedido($db, 7, 12);
    throw new RuntimeException('Se intentó borrar una mesa con un pedido activo.');
} catch (DomainException $error) {
    verificar_reporte(!$db->pasos, 'No debe ejecutar un DELETE si existe un pedido activo.');
}
$crear = file_get_contents(__DIR__ . '/../api/crear_pedido.php');
$borrar = file_get_contents(__DIR__ . '/../api/admin_mesas.php');
verificar_reporte(strpos($crear, 'beginTransaction()') < strpos($crear, 'bloquear_restaurante(')
    && strpos($crear, 'bloquear_restaurante(') < strpos($crear, 'FROM mesas')
    && strpos($borrar, 'beginTransaction()') < strpos($borrar, 'eliminar_mesa_sin_pedido(')
    && strpos($borrar, 'eliminar_mesa_sin_pedido(') < strpos($borrar, 'commit()'), 'Los endpoints deben compartir el primer bloqueo dentro de una transacción.');

// Panel global: dos cobros del mismo importe en un restaurante con 2 usuarios y 3 mesas.
$filas = [
    ['id' => 7, 'activo' => 1, 'usuarios' => 2, 'mesas' => 3, 'pedidos_hoy' => 2, 'ventas_hoy' => '200.00'],
    ['id' => 8, 'activo' => 0, 'usuarios' => 1, 'mesas' => 1, 'pedidos_hoy' => 1, 'ventas_hoy' => '0.05'],
];
$db = new ConexionReportePrueba([
    paso_reporte(['COUNT(*) AS usuarios FROM usuarios', 'COUNT(*) AS mesas FROM mesas',
        'SUM(pa.monto) AS ventas_hoy', 'GROUP BY pe.restaurante_id'], [$inicio, $fin], $filas),
]);
$totales = totales_panel_global(restaurantes_con_ventas($db, $inicio, $fin));
verificar_reporte($totales === ['restaurantes_activos' => 1, 'usuarios_activos' => 3, 'ventas_centimos' => 20005],
    'La tarjeta debe sumar la misma tabla por restaurante.');
verificar_reporte(centimos_reporte('-100.01') === -10001, 'Saldo negativo incorrecto.');
verificar_reporte(leer_cierre_turno(['cierre_json' => null]) === null, 'Un cierre legado no tiene desglose.');
$cierre = ['num_pedidos' => 1, 'total_gastos' => '12.25', 'desglose_usuarios' => []];
verificar_reporte(leer_cierre_turno(['cierre_json' => json_encode($cierre)]) === $cierre, 'El resumen guardado no se debe recalcular.');

// Guardar, editar y anular gastos con cierre abierto y auditoría previa al COMMIT.
$bloqueos = [
    paso_reporte(['FROM restaurantes', 'FOR UPDATE'], [7], [['id' => 7]]),
    paso_reporte(['FROM turnos', 'fin IS NULL', 'FOR UPDATE'], [7], [['id' => 5]]),
];
$gasto = ['accion' => 'registrar', 'categoria' => 'Insumos', 'descripcion' => 'Material', 'monto' => '12.25', 'fecha' => '2026-11-10'];
$guardar = paso_reporte(['INSERT INTO gastos', 'turno_id'], [7, 9, 5, 'Insumos', 'Material', '12.25', '2026-11-10']);
$db = new ConexionReportePrueba([...$bloqueos, $guardar]);
verificar_reporte(guardar_gasto($db, 7, 9, 'admin', $gasto) === 'Gasto registrado.', 'Alta de gasto fallida.');
verificar_reporte($db->eventos[0] === 'begin' && str_contains($db->eventos[1], 'Gasto #51, turno #5')
    && $db->eventos[2] === 'commit' && !$db->pasos, 'Alta no audita antes de confirmar.');

$buscar_gasto = paso_reporte(['FROM gastos', 'turno_id = ?', 'FOR UPDATE'], [51, 7, 5],
    [['monto' => '12.25', 'fecha' => '2026-11-10']]);
$db = new ConexionReportePrueba([...$bloqueos, $buscar_gasto,
    paso_reporte(['UPDATE gastos SET categoria'], ['Insumos', 'Material', '10.00', '2026-11-10', 51, 7, 5]),
]);
guardar_gasto($db, 7, 9, 'admin', array_replace($gasto, ['accion' => 'editar', 'id' => '51', 'monto' => '10.00']));
verificar_reporte(str_contains($db->eventos[1], '12.25 → S/ 10.00') && $db->eventos[2] === 'commit', 'Edición sin ambos importes.');

$db = new ConexionReportePrueba([...$bloqueos, $buscar_gasto,
    paso_reporte(['UPDATE gastos SET activo = 0'], [51, 7, 5]),
]);
guardar_gasto($db, 7, 9, 'admin', ['accion' => 'eliminar', 'id' => '51', 'motivo' => 'Registro duplicado']);
verificar_reporte(str_contains($db->eventos[1], 'Motivo: Registro duplicado') && $db->eventos[2] === 'commit', 'Anulación lógica no trazada.');

$db = new ConexionReportePrueba([...$bloqueos, $guardar]);
$db->fallar_auditoria = true;
try {
    guardar_gasto($db, 7, 9, 'admin', $gasto);
    throw new LogicException('Se esperaba fallo de auditoría.');
} catch (RuntimeException $error) {
    verificar_reporte($error->getMessage() === 'Fallo de auditoría simulado.' && $db->eventos[2] === 'rollback', 'Fallo obligatorio no revierte.');
}

$db = new ConexionReportePrueba([...$bloqueos, paso_reporte(['FROM gastos', 'turno_id = ?'], [51, 7, 5])]);
try {
    guardar_gasto($db, 7, 9, 'admin', array_replace($gasto, ['accion' => 'editar', 'id' => '51']));
    throw new LogicException('Se esperaba rechazo de gasto de otro turno.');
} catch (DomainException $error) {
    verificar_reporte($db->eventos === ['begin', 'rollback'], 'Un gasto cerrado debe quedar intacto.');
}
foreach ([['encargado', null], ['admin', $inicio]] as [$rol, $fecha_fija]) {
    $db = new ConexionReportePrueba([]);
    try {
        guardar_gasto($db, 7, 9, $rol, array_replace($gasto, ['accion' => 'editar', 'id' => '51']), $fecha_fija);
        throw new LogicException('Se esperaba rechazo de edición no permitida.');
    } catch (DomainException $error) {
        verificar_reporte($db->eventos === [], 'Una operación no permitida no debe abrir una transacción.');
    }
}

echo "OK H-001, H-002, H-022 y H-015: contratos, fechas, totales y gastos con PDO simulado.\n";
