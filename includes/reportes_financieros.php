<?php

function fecha_reporte(mixed $valor, string $alternativa): string {
    if (!is_string($valor) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $valor, $partes)
        || $valor < '1000-01-01' || $valor >= '9999-12-31'
        || !checkdate((int)$partes[2], (int)$partes[3], (int)$partes[1])) {
        return $alternativa;
    }
    return $valor;
}

function limite_exclusivo_reporte(string $fecha): string {
    return (new DateTimeImmutable($fecha))->modify('+1 day')->format('Y-m-d');
}

function centimos_reporte(mixed $importe): int {
    // SUM(DECIMAL) puede superar el límite por movimiento y el saldo puede ser negativo.
    $texto = (string)$importe;
    $partes = explode('.', ltrim($texto, '-'), 2);
    $centimos = (int)$partes[0] * 100 + (int)str_pad($partes[1] ?? '', 2, '0');
    return str_starts_with($texto, '-') ? -$centimos : $centimos;
}

function consultar_filas_reporte(PDO $db, string $sql, array $parametros): array {
    $consulta = $db->prepare($sql);
    $consulta->execute($parametros);
    return $consulta->fetchAll();
}

function ingresos_periodo(PDO $db, int $restaurante_id, string $inicio, string $fin): array {
    $desde_pagos = "
        FROM pagos pa JOIN pedidos pe ON pe.id = pa.pedido_id
        WHERE pe.restaurante_id = ? AND pe.estado = 'cobrado'
          AND COALESCE(pa.anulado, 0) = 0 AND pa.created_at >= ? AND pa.created_at < ?
    ";
    $parametros = [$restaurante_id, $inicio, $fin];
    $dias = consultar_filas_reporte($db, "
        SELECT DATE(pa.created_at) AS fecha, COUNT(DISTINCT pa.pedido_id) AS pedidos, SUM(pa.monto) AS total
        $desde_pagos
        GROUP BY DATE(pa.created_at) ORDER BY fecha ASC
    ", $parametros);
    $metodos = consultar_filas_reporte($db, "
        SELECT pa.metodo, SUM(pa.monto) AS total, COUNT(*) AS operaciones
        $desde_pagos
        GROUP BY pa.metodo ORDER BY total DESC
    ", $parametros);

    return [
        'dias' => $dias,
        'metodos' => $metodos,
        'total_centimos' => array_sum(array_map('centimos_reporte', array_column($dias, 'total'))),
        'pedidos' => array_sum(array_column($dias, 'pedidos')),
    ];
}

function productos_periodo(PDO $db, int $restaurante_id, string $inicio, string $fin, int $limite = 0): array {
    return consultar_filas_reporte($db, "
        SELECT COALESCE(pi.nombre_producto, pr.nombre, '(Producto eliminado)') AS nombre,
               SUM(pi.cantidad) AS vendidos, SUM(pi.subtotal) AS ingreso
        FROM pedido_items pi
        JOIN pedidos pe ON pe.id = pi.pedido_id
        LEFT JOIN productos pr ON pr.id = pi.producto_id AND pr.restaurante_id = pe.restaurante_id
        WHERE pe.restaurante_id = ? AND pe.estado = 'cobrado'
          AND EXISTS (
              SELECT 1 FROM pagos pa
              WHERE pa.pedido_id = pe.id AND COALESCE(pa.anulado, 0) = 0
                AND pa.created_at >= ? AND pa.created_at < ?
          )
        GROUP BY pi.producto_id, COALESCE(pi.nombre_producto, pr.nombre, '(Producto eliminado)')
        ORDER BY vendidos DESC, nombre
    " . ($limite > 0 ? " LIMIT $limite" : ''), [$restaurante_id, $inicio, $fin]);
}

function gastos_contables_periodo(PDO $db, int $restaurante_id, string $inicio, string $fin): string {
    $consulta = $db->prepare("
        SELECT COALESCE(SUM(monto), 0) FROM gastos
        WHERE restaurante_id = ? AND activo = 1 AND fecha >= ? AND fecha < ?
    ");
    $consulta->execute([$restaurante_id, $inicio, $fin]);
    return (string)$consulta->fetchColumn();
}

function mesas_del_turno(PDO $db, int $restaurante_id, int $turno_id): array {
    $filas = consultar_filas_reporte($db, '
        SELECT DISTINCT p.id, m.numero
        FROM pagos pa JOIN pedidos p ON p.id = pa.pedido_id
        LEFT JOIN mesas m ON m.id = p.mesa_id AND m.restaurante_id = p.restaurante_id
        WHERE pa.turno_id = ? AND p.restaurante_id = ?
    ', [$turno_id, $restaurante_id]);
    return array_column($filas, 'numero', 'id');
}

function cobros_periodo(
    PDO $db, int $restaurante_id, string $inicio, string $fin,
    string $tipo = '', int $pedido_id = 0, int $limite = 100, ?int $turno_id = null,
    bool $comprobante_vigente = true, ?array $mesas_cierre = null
): array {
    $parametros = [$restaurante_id];
    if ($turno_id === null) {
        $filtro = "p.estado = 'cobrado' AND COALESCE(pa.anulado, 0) = 0 AND pa.created_at >= ? AND pa.created_at < ?";
        array_push($parametros, $inicio, $fin);
    } else {
        // En caja se muestra el cobro original, aunque su reverso se registre en otro turno.
        $filtro = 'pa.turno_id = ?';
        $parametros[] = $turno_id;
    }
    if ($tipo !== '') {
        $filtro .= ' AND p.tipo = ?';
        $parametros[] = $tipo;
    }
    if ($pedido_id > 0) {
        $filtro .= ' AND p.id = ?';
        $parametros[] = $pedido_id;
    }

    $id_comprobante = 'NULL';
    if ($comprobante_vigente) {
        $filtro_comprobante = '';
        if ($turno_id !== null) {
            // No enlazar un comprobante de un recobro de otro turno.
            $filtro_comprobante = "
                AND EXISTS (
                    SELECT 1 FROM pagos pv WHERE pv.pedido_id = pe.id
                    AND COALESCE(pv.anulado, 0) = 0 AND pv.turno_id = ?
                )
                AND NOT EXISTS (
                    SELECT 1 FROM pagos pv WHERE pv.pedido_id = pe.id
                    AND COALESCE(pv.anulado, 0) = 0 AND (pv.turno_id IS NULL OR pv.turno_id <> ?)
                )
            ";
            array_push($parametros, $turno_id, $turno_id);
        }
        $id_comprobante = "(
            SELECT MAX(c2.id) FROM comprobantes c2
            WHERE c2.pedido_id = pe.id AND c2.restaurante_id = pe.restaurante_id AND COALESCE(c2.anulado, 0) = 0
            $filtro_comprobante
        )";
    }

    $numero_mesa = $mesas_cierre === null ? 'm.numero' : 'NULL';
    $join_mesa = $mesas_cierre === null ? 'LEFT JOIN mesas m ON m.id = pe.mesa_id AND m.restaurante_id = pe.restaurante_id' : '';
    $filas = consultar_filas_reporte($db, "
        SELECT pe.id, pe.tipo, ventas.total, ventas.cobrado_en, $numero_mesa AS mesa_numero, pago.usuario_id AS cobrador_id,
               COALESCE(u.nombre, 'Usuario no disponible') AS cajero,
               c.id AS comprobante_id, c.numero_comprobante, c.tipo AS comprobante_tipo
        FROM (
            SELECT pa.pedido_id, SUM(pa.monto) AS total, MAX(pa.created_at) AS cobrado_en,
                   MAX(pa.id) AS pago_id
            FROM pagos pa JOIN pedidos p ON p.id = pa.pedido_id
            WHERE p.restaurante_id = ? AND $filtro
            GROUP BY pa.pedido_id
            ORDER BY cobrado_en DESC, pa.pedido_id DESC
            LIMIT $limite
        ) ventas
        JOIN pedidos pe ON pe.id = ventas.pedido_id
        JOIN pagos pago ON pago.id = ventas.pago_id
        LEFT JOIN usuarios u ON u.id = pago.usuario_id
        $join_mesa
        LEFT JOIN comprobantes c ON c.id = $id_comprobante
        ORDER BY ventas.cobrado_en DESC, pe.id DESC
    ", $parametros);
    if ($mesas_cierre !== null) {
        foreach ($filas as &$fila) {
            $fila['mesa_numero'] = $mesas_cierre[$fila['id']] ?? null;
        }
        unset($fila);
    }
    return $filas;
}

function restaurantes_con_ventas(PDO $db, string $inicio, string $fin): array {
    return consultar_filas_reporte($db, "
        SELECT r.id, r.nombre, r.activo, COALESCE(u.usuarios, 0) AS usuarios,
               COALESCE(m.mesas, 0) AS mesas, COALESCE(v.pedidos_hoy, 0) AS pedidos_hoy,
               COALESCE(v.ventas_hoy, 0) AS ventas_hoy
        FROM restaurantes r
        LEFT JOIN (
            SELECT restaurante_id, COUNT(*) AS usuarios FROM usuarios
            WHERE activo = 1 AND rol != 'superadmin' GROUP BY restaurante_id
        ) u ON u.restaurante_id = r.id
        LEFT JOIN (
            SELECT restaurante_id, COUNT(*) AS mesas FROM mesas
            WHERE activo = 1 GROUP BY restaurante_id
        ) m ON m.restaurante_id = r.id
        LEFT JOIN (
            SELECT pe.restaurante_id, COUNT(DISTINCT pa.pedido_id) AS pedidos_hoy, SUM(pa.monto) AS ventas_hoy
            FROM pagos pa JOIN pedidos pe ON pe.id = pa.pedido_id
            WHERE pe.estado = 'cobrado' AND COALESCE(pa.anulado, 0) = 0
              AND pa.created_at >= ? AND pa.created_at < ?
            GROUP BY pe.restaurante_id
        ) v ON v.restaurante_id = r.id
        ORDER BY r.id DESC
    ", [$inicio, $fin]);
}

function totales_panel_global(array $restaurantes): array {
    return [
        'restaurantes_activos' => count(array_filter($restaurantes, static fn(array $r): bool => (bool)$r['activo'])),
        'usuarios_activos' => array_sum(array_column($restaurantes, 'usuarios')),
        'ventas_centimos' => array_sum(array_map('centimos_reporte', array_column($restaurantes, 'ventas_hoy'))),
    ];
}

function leer_cierre_turno(array $turno): ?array {
    $cierre = json_decode($turno['cierre_json'] ?? '', true);
    return is_array($cierre) && isset($cierre['num_pedidos'], $cierre['total_gastos'], $cierre['desglose_usuarios'])
        ? $cierre : null;
}
