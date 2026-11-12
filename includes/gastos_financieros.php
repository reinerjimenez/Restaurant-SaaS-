<?php

function guardar_gasto(PDO $db, int $restaurante_id, int $usuario_id, string $rol, array $entrada, ?string $fecha_fija = null): string {
    $accion = $entrada['accion'] ?? '';
    if (!in_array($accion, ['registrar', 'editar', 'eliminar'], true)) {
        throw new DomainException('Acción de gasto inválida.');
    }
    if ($fecha_fija !== null && $accion !== 'registrar') {
        throw new DomainException('En Atención solo se pueden registrar gastos.');
    }
    if ($accion !== 'registrar' && !in_array($rol, ['admin', 'superadmin'], true)) {
        throw new DomainException('No tienes permiso para editar o anular gastos.');
    }
    $gasto_id = filter_var($entrada['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($accion !== 'registrar' && !$gasto_id) {
        throw new DomainException('Gasto inválido.');
    }
    $motivo = '';
    if ($accion === 'eliminar') {
        $motivo = is_string($entrada['motivo'] ?? null) ? trim($entrada['motivo']) : '';
        if ($motivo === '' || mb_strlen($motivo, 'UTF-8') > 200) {
            throw new DomainException('Indica un motivo de hasta 200 caracteres.');
        }
    } else {
        $categoria = is_string($entrada['categoria'] ?? null) ? trim($entrada['categoria']) : '';
        $descripcion = is_string($entrada['descripcion'] ?? null) ? trim($entrada['descripcion']) : '';
        $fecha = $fecha_fija ?? fecha_reporte($entrada['fecha'] ?? null, '');
        $centimos = importe_centimos($entrada['monto'] ?? null);
        if ($categoria === '' || mb_strlen($categoria, 'UTF-8') > 100
            || $descripcion === '' || mb_strlen($descripcion, 'UTF-8') > 255
            || $fecha === '' || $centimos <= 0) {
            throw new DomainException('Completa los datos del gasto con una fecha contable y un monto positivo.');
        }
        $monto = decimal_centimos($centimos);
    }

    $db->beginTransaction();
    try {
        bloquear_restaurante($db, $restaurante_id);
        $turno = turno_abierto($db, $restaurante_id);
        if (!$turno) {
            throw new DomainException('Abre un turno de caja para registrar o ajustar gastos.');
        }
        $turno_id = (int)$turno['id'];
        if ($accion === 'registrar') {
            $consulta = $db->prepare("
                INSERT INTO gastos (restaurante_id, usuario_id, turno_id, categoria, descripcion, monto, fecha)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $consulta->execute([$restaurante_id, $usuario_id, $turno_id, $categoria, $descripcion, $monto, $fecha]);
            $gasto_id = (int)$db->lastInsertId();
            $evento = 'Registró gasto';
            $detalle = "S/ 0.00 → S/ $monto. Fecha contable: $fecha.";
            $mensaje = 'Gasto registrado.';
        } else {
            $consulta = $db->prepare("
                SELECT monto, fecha FROM gastos
                WHERE id = ? AND restaurante_id = ? AND turno_id = ? AND activo = 1
                FOR UPDATE
            ");
            $consulta->execute([$gasto_id, $restaurante_id, $turno_id]);
            $anterior = $consulta->fetch();
            if (!$anterior) {
                throw new DomainException('Solo se pueden ajustar gastos activos del turno abierto.');
            }
            if ($accion === 'editar') {
                $consulta = $db->prepare("
                    UPDATE gastos SET categoria = ?, descripcion = ?, monto = ?, fecha = ?
                    WHERE id = ? AND restaurante_id = ? AND turno_id = ?
                ");
                $consulta->execute([$categoria, $descripcion, $monto, $fecha, $gasto_id, $restaurante_id, $turno_id]);
                $evento = 'Editó gasto';
                $detalle = "S/ {$anterior['monto']} → S/ $monto. Fecha contable: {$anterior['fecha']} → $fecha.";
                $mensaje = 'Gasto actualizado.';
            } else {
                $consulta = $db->prepare("UPDATE gastos SET activo = 0 WHERE id = ? AND restaurante_id = ? AND turno_id = ?");
                $consulta->execute([$gasto_id, $restaurante_id, $turno_id]);
                $evento = 'Anuló gasto';
                $detalle = "S/ {$anterior['monto']} → S/ 0.00. Fecha contable: {$anterior['fecha']}. Motivo: $motivo.";
                $mensaje = 'Gasto anulado. Se conserva su registro.';
            }
        }
        registrarAuditoria($db, $evento, "Gasto #$gasto_id, turno #$turno_id. $detalle", true);
        $db->commit();
        return $mensaje;
    } catch (Throwable $error) {
        if ($db->inTransaction()) $db->rollBack();
        throw $error;
    }
}
