<?php
/**
 * api/get_reportes.php — Datos de reportes filtrados por fecha
 * Sistema SaaS Restaurante | R.DEV
 */
require_once '../config/db.php';
requireRole(['admin', 'encargado', 'superadmin']);

require_once __DIR__ . '/../includes/reportes_financieros.php';
require_once __DIR__ . '/../config/finanzas.php';

$restauranteId = $_SESSION['restaurante_id'];
$fecha_inicio = fecha_reporte($_GET['fecha_inicio'] ?? null, date('Y-m-01'));
$fecha_fin = fecha_reporte($_GET['fecha_fin'] ?? null, date('Y-m-d'));
if ($fecha_inicio > $fecha_fin) jsonResponse(false, null, 'El rango de fechas es inválido.');
$fin_exclusivo = limite_exclusivo_reporte($fecha_fin);
$db = getDB();

$periodo = ingresos_periodo($db, $restauranteId, $fecha_inicio, $fin_exclusivo);
$ventasProds = productos_periodo($db, $restauranteId, $fecha_inicio, $fin_exclusivo);

// El turno abierto pertenece al restaurante, no solo al usuario que lo abrió.
$stTurno = $db->prepare("
    SELECT * FROM turnos
    WHERE restaurante_id = ? AND fin IS NULL
    ORDER BY inicio DESC LIMIT 1
");
$stTurno->execute([$restauranteId]);
$turnoActual = $stTurno->fetch();
if ($turnoActual) {
    $turnoActual = array_replace($turnoActual, resumen_turno($db, (int)$restauranteId, (int)$turnoActual['id']));
}

jsonResponse(true, [
    'ventas_dia'    => $periodo['dias'],
    'ventas_prods'  => $ventasProds,
    'ventas_metodos'=> $periodo['metodos'],
    'total_general' => $periodo['total_centimos'] / 100,
    'turno_actual'  => $turnoActual,
], 'OK');
