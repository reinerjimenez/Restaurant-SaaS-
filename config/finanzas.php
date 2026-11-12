<?php

const METODOS_PAGO = ['efectivo', 'yape', 'transferencia', 'tarjeta', 'otro'];
const MAX_CENTIMOS = 9999999999; // DECIMAL(10,2)

function importe_centimos(mixed $valor): int {
    if ((!is_int($valor) && !is_float($valor) && !is_string($valor))
        || !preg_match('/\A(\d{1,8})(?:\.(\d{1,2}))?\z/', (string)$valor, $partes)) {
        throw new DomainException('Importe inválido: usa hasta dos decimales y un monto no negativo.');
    }
    return (int)$partes[1] * 100 + (int)str_pad($partes[2] ?? '', 2, '0');
}

function decimal_centimos(int $centimos): string {
    $monto = abs($centimos);
    return ($centimos < 0 ? '-' : '') . intdiv($monto, 100) . '.' . str_pad((string)($monto % 100), 2, '0', STR_PAD_LEFT);
}

function total_cobro(int $subtotal, int $descuento, int $cargo): int {
    $total = $subtotal + $cargo - $descuento;
    if ($subtotal <= 0 || $descuento < 0 || $cargo < 0 || $total <= 0 || $total > MAX_CENTIMOS) {
        throw new DomainException('Revisa los ajustes: el pedido debe tener productos y un total positivo válido.');
    }
    return $total;
}

function normalizar_pagos(mixed $entrada): array {
    if (!is_array($entrada) || !array_is_list($entrada) || !$entrada || count($entrada) > 100) {
        throw new DomainException('Indica al menos un pago válido.');
    }
    $pagos = [];
    foreach ($entrada as $pago) {
        $metodo = $pago['metodo'] ?? null;
        $referencia = $pago['referencia'] ?? '';
        if (!is_array($pago) || !in_array($metodo, METODOS_PAGO, true)
            || !is_string($referencia) || mb_strlen($referencia) > 100) {
            throw new DomainException('Método o referencia de pago inválidos.');
        }
        $centimos = importe_centimos($pago['monto'] ?? null);
        if (!$centimos) {
            throw new DomainException('Cada pago debe ser mayor a cero.');
        }
        $pagos[] = ['metodo' => $metodo, 'monto' => decimal_centimos($centimos), 'referencia' => trim($referencia) ?: null];
    }
    return $pagos;
}

function validar_total_pagos(array $pagos, int $total): void {
    $suma = array_sum(array_map(static fn(array $pago): int => importe_centimos($pago['monto']), $pagos));
    if ($suma !== $total) {
        throw new DomainException('Los pagos deben sumar exactamente S/ ' . decimal_centimos($total) . '.');
    }
}

function bloquear_restaurante(PDO $db, int $restaurante_id): void {
    $stmt = $db->prepare('SELECT id FROM restaurantes WHERE id = ? AND activo = 1 FOR UPDATE');
    $stmt->execute([$restaurante_id]);
    if (!$stmt->fetchColumn()) {
        throw new DomainException('El restaurante no está activo.');
    }
}

function turno_abierto(PDO $db, int $restaurante_id): ?array {
    $stmt = $db->prepare('SELECT id, inicio FROM turnos WHERE restaurante_id = ? AND fin IS NULL ORDER BY inicio DESC LIMIT 1 FOR UPDATE');
    $stmt->execute([$restaurante_id]);
    return $stmt->fetch() ?: null;
}

function eliminar_mesa_sin_pedido(PDO $db, int $restaurante_id, int $mesa_id): void {
    // Igual que al crear un pedido: restaurante primero, luego mesa y pedidos.
    bloquear_restaurante($db, $restaurante_id);
    $mesa = $db->prepare('SELECT id FROM mesas WHERE id = ? AND restaurante_id = ? FOR UPDATE');
    $mesa->execute([$mesa_id, $restaurante_id]);
    if (!$mesa->fetchColumn()) throw new DomainException('Mesa no encontrada');

    $pedido = $db->prepare("SELECT id FROM pedidos WHERE mesa_id = ? AND restaurante_id = ? AND estado = 'activo' LIMIT 1 FOR UPDATE");
    $pedido->execute([$mesa_id, $restaurante_id]);
    if ($pedido->fetchColumn()) throw new DomainException('No se puede eliminar: la mesa tiene un pedido activo');

    $db->prepare('DELETE FROM mesas WHERE id = ? AND restaurante_id = ?')->execute([$mesa_id, $restaurante_id]);
}

function exigir_turno_abierto(PDO $db, int $restaurante_id): array {
    return turno_abierto($db, $restaurante_id) ?? throw new DomainException('Abre un turno de caja para continuar.');
}

function bloquear_pedido(PDO $db, int $restaurante_id, int $pedido_id, string $estado): array {
    $stmt = $db->prepare('SELECT id, mesa_id, estado, total, descuento, cargo_extra FROM pedidos WHERE id = ? AND restaurante_id = ? FOR UPDATE');
    $stmt->execute([$pedido_id, $restaurante_id]);
    $pedido = $stmt->fetch();
    if (!$pedido || $pedido['estado'] !== $estado) {
        throw new DomainException('El pedido no existe o ya cambió de estado.');
    }
    return $pedido;
}

function subtotal_pedido(PDO $db, int $pedido_id): int {
    $stmt = $db->prepare('SELECT COALESCE(SUM(subtotal), 0) FROM pedido_items WHERE pedido_id = ?');
    $stmt->execute([$pedido_id]);
    return importe_centimos($stmt->fetchColumn());
}

function calcular_cobro(PDO $db, array $pedido, array $input, array $pagos): array {
    $descuento = importe_centimos(array_key_exists('descuento', $input) ? $input['descuento'] : ($pedido['descuento'] ?? '0'));
    $cargo_extra = importe_centimos(array_key_exists('cargo_extra', $input) ? $input['cargo_extra'] : ($pedido['cargo_extra'] ?? '0'));
    $total = total_cobro(subtotal_pedido($db, (int)$pedido['id']), $descuento, $cargo_extra);
    validar_total_pagos($pagos, $total);
    return [$total, $descuento, $cargo_extra];
}

function items_comprobante(PDO $db, int $pedido_id): array {
    $stmt = $db->prepare("
        SELECT pi.cantidad, pi.precio_unitario, pi.subtotal,
               COALESCE(pi.nombre_producto, pr.nombre, 'Producto no disponible') AS producto_nombre,
               GROUP_CONCAT(ov.valor ORDER BY ov.id SEPARATOR ' · ') AS opciones_texto
        FROM pedido_items pi JOIN pedidos pe ON pe.id = pi.pedido_id
        LEFT JOIN productos pr ON pr.id = pi.producto_id AND pr.restaurante_id = pe.restaurante_id
        LEFT JOIN pedido_item_opciones pio ON pio.item_id = pi.id
        LEFT JOIN opciones_valor ov ON ov.id = pio.valor_id
        WHERE pi.pedido_id = ?
        GROUP BY pi.id, pi.cantidad, pi.precio_unitario, pi.subtotal, pi.nombre_producto, pr.nombre
        ORDER BY pi.id
    ");
    $stmt->execute([$pedido_id]);
    return $stmt->fetchAll();
}

function liberar_mesa(PDO $db, int $restaurante_id, ?int $mesa_id): void {
    if ($mesa_id !== null) {
        $stmt = $db->prepare("UPDATE mesas m SET estado = 'libre'
            WHERE m.id = ? AND m.restaurante_id = ? AND NOT EXISTS
              (SELECT 1 FROM pedidos p WHERE p.mesa_id = m.id AND p.restaurante_id = m.restaurante_id AND p.estado = 'activo')");
        $stmt->execute([$mesa_id, $restaurante_id]);
    }
}

/**
 * Procesa los pagos validados de ambos flujos de cobro, bajo los bloqueos de
 * restaurante, turno y pedido ya adquiridos por el llamador.
 */
function persistir_cobro(PDO $db, int $restaurante_id, int $turno_id, int $usuario_id, array $pedido,
    array $pagos, int $total, int $descuento, int $cargo_extra, string $notas): string {
    $fecha_cobro = $db->query('SELECT NOW()')->fetchColumn();
    $pago_stmt = $db->prepare('INSERT INTO pagos (pedido_id, metodo, monto, referencia, usuario_id, turno_id, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?)');
    foreach ($pagos as $pago) {
        $pago_stmt->execute([$pedido['id'], $pago['metodo'], $pago['monto'], $pago['referencia'], $usuario_id, $turno_id, $fecha_cobro]);
    }
    $stmt = $db->prepare("UPDATE pedidos SET estado = 'cobrado', notas = ?, descuento = ?, cargo_extra = ?, total = ?
        WHERE id = ? AND restaurante_id = ? AND estado = 'activo'");
    $stmt->execute([$notas ?: null, decimal_centimos($descuento), decimal_centimos($cargo_extra),
        decimal_centimos($total), $pedido['id'], $restaurante_id]);
    liberar_mesa($db, $restaurante_id, $pedido['mesa_id']);
    return $fecha_cobro;
}

function revertir_pagos(PDO $db, array $pedido, int $turno_id, int $usuario_id, string $motivo): array {
    $pedido_id = (int)$pedido['id'];
    $stmt = $db->prepare('SELECT id, monto FROM pagos WHERE pedido_id = ? AND COALESCE(anulado, 0) = 0 FOR UPDATE');
    $stmt->execute([$pedido_id]);
    $pagos = $stmt->fetchAll();
    if (!$pagos) {
        throw new DomainException('No hay pagos activos para revertir. Concilia esta venta antes de modificarla.');
    }
    // Una sola copia preserva el detalle vendido antes de que la comanda vuelva a editarse.
    $venta_json = json_encode([
        'pedido_id' => $pedido_id,
        'total' => $pedido['total'],
        'descuento' => $pedido['descuento'],
        'cargo_extra' => $pedido['cargo_extra'],
        'items' => items_comprobante($db, $pedido_id),
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $fecha_reverso = $db->query('SELECT NOW()')->fetchColumn();
    $reverso = $db->prepare('INSERT INTO reversos_pago (pago_id, turno_id, usuario_id, motivo, venta_json, created_at) VALUES (?, ?, ?, ?, ?, ?)');
    $anular = $db->prepare('UPDATE pagos SET anulado = 1, anulado_por = ?, anulado_en = ? WHERE id = ?');
    $centimos = 0;
    foreach ($pagos as $pago) {
        $monto = importe_centimos($pago['monto']);
        if (!$monto) {
            throw new DomainException('La venta contiene un pago histórico que requiere conciliación.');
        }
        $centimos += $monto;
        $reverso->execute([$pago['id'], $turno_id, $usuario_id, $motivo, $venta_json, $fecha_reverso]);
        $venta_json = null;
        $anular->execute([$usuario_id, $fecha_reverso, $pago['id']]);
    }
    return ['monto' => decimal_centimos($centimos), 'pagos' => array_column($pagos, 'id')];
}

function error_financiero(PDO $db, Throwable $error, string $operacion): never {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    if ($error instanceof DomainException) {
        jsonResponse(false, null, $error->getMessage());
    }
    error_log($operacion . ': ' . get_class($error) . ' (' . $error->getCode() . ')');
    jsonResponse(false, null, 'Ocurrió un error interno en el servidor.');
}

function resumen_turno(PDO $db, int $restaurante_id, int $turno_id): array {
    // El pago original conserva su turno aunque luego se anule. Solo el
    // turno donde se registró el reverso recibe el débito correspondiente.
    $stmt = $db->prepare("
        SELECT m.usuario_id, COALESCE(u.nombre, 'Usuario no disponible') AS usuario, m.clase, m.metodo, m.monto, m.cantidad
        FROM (
            SELECT p.usuario_id, 'pago' AS clase, p.metodo, SUM(p.monto) AS monto, COUNT(*) AS cantidad
            FROM pagos p JOIN pedidos pe ON pe.id = p.pedido_id
            WHERE p.turno_id = ? AND pe.restaurante_id = ?
            GROUP BY p.usuario_id, p.metodo
            UNION ALL
            SELECT r.usuario_id, 'reverso', p.metodo, SUM(p.monto), COUNT(*)
            FROM reversos_pago r JOIN pagos p ON p.id = r.pago_id JOIN pedidos pe ON pe.id = p.pedido_id
            WHERE r.turno_id = ? AND pe.restaurante_id = ?
            GROUP BY r.usuario_id, p.metodo
            UNION ALL
            SELECT g.usuario_id, 'gasto', NULL, SUM(g.monto), COUNT(*)
            FROM gastos g
            WHERE g.turno_id = ? AND g.restaurante_id = ? AND g.activo = 1
            GROUP BY g.usuario_id
        ) m LEFT JOIN usuarios u ON u.id = m.usuario_id
        ORDER BY m.usuario_id
    ");
    $stmt->execute([$turno_id, $restaurante_id, $turno_id, $restaurante_id, $turno_id, $restaurante_id]);
    $por_metodo = ['efectivo' => 'total_efectivo', 'yape' => 'total_yape', 'transferencia' => 'total_transferencia',
        'tarjeta' => 'total_tarjeta', 'otro' => 'total_otros'];
    $base = array_fill_keys(array_values($por_metodo), 0) + ['total_general' => 0, 'total_gastos' => 0];
    $totales = $base + ['total_reversos' => 0];
    $usuarios = [];
    $num_reversos = 0;
    foreach ($stmt->fetchAll() as $fila) {
        $id = (int)$fila['usuario_id'];
        $usuarios[$id] ??= ['id' => $id, 'usuario' => $fila['usuario']] + $base;
        // Las sumas pueden exceder el rango de un movimiento individual.
        $partes = explode('.', (string)$fila['monto'], 2);
        $monto = (int)$partes[0] * 100 + (int)str_pad($partes[1] ?? '', 2, '0');
        if ($fila['clase'] === 'gasto') {
            $totales['total_gastos'] += $monto;
            $usuarios[$id]['total_gastos'] += $monto;
        } else {
            if ($fila['clase'] === 'reverso') {
                $totales['total_reversos'] += $monto;
                $num_reversos += (int)$fila['cantidad'];
                $monto = -$monto;
            }
            $clave = $por_metodo[$fila['metodo']];
            $totales[$clave] += $monto;
            $totales['total_general'] += $monto;
            $usuarios[$id][$clave] += $monto;
            $usuarios[$id]['total_general'] += $monto;
        }
    }
    foreach ($usuarios as &$usuario) {
        foreach ($base as $clave => $_) {
            $usuario[$clave] = decimal_centimos($usuario[$clave]);
        }
    }
    unset($usuario);
    $num_pedidos = $db->prepare('SELECT COUNT(DISTINCT p.pedido_id) FROM pagos p JOIN pedidos pe ON pe.id = p.pedido_id
        WHERE p.turno_id = ? AND pe.restaurante_id = ?');
    $num_pedidos->execute([$turno_id, $restaurante_id]);
    return array_map('decimal_centimos', $totales) + [
        'num_pedidos' => (int)$num_pedidos->fetchColumn(),
        'num_reversos' => $num_reversos,
        'desglose_usuarios' => array_values($usuarios),
    ];
}
