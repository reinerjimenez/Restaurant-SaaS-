<?php
/** Envío fuera de la transacción de caja y conciliación por identidad fiscal. */
require_once __DIR__ . '/nubefact_config.php';
require_once __DIR__ . '/nubefact_api.php';

function nubefact_procesar(PDO $db, int $restaurante_id, int $id, bool $consultar, ?callable $transporte = null): ?string {
    if ($db->inTransaction()) throw new LogicException('El proveedor requiere una transacción independiente.');
    if (!nubefact_esquema_disponible($db)) return null;

    $db->beginTransaction();
    try {
        $st = $db->prepare("SELECT d.ambiente,d.estado,d.snapshot_json,d.serie,d.correlativo,d.tipo,
            TIMESTAMPDIFF(SECOND,d.updated_at,NOW()) AS segundos
            FROM emision_documentos d JOIN emision_nubefact_documentos n
            ON n.documento_id=d.id AND n.restaurante_id=d.restaurante_id
            WHERE d.id=? AND d.restaurante_id=? FOR UPDATE");
        $st->execute([$id, $restaurante_id]);
        $d = $st->fetch();
        if (!$d) { $db->commit(); return null; }
        $estado = $d['estado'];
        if (!$consultar && $estado !== 'pendiente') { $db->commit(); return $estado; }
        if ($consultar && ($estado === 'pendiente' || $estado === 'rechazado'
            || ($estado === 'procesando' && (int)$d['segundos'] < 45))) {
            $db->commit();
            return $estado;
        }
        $snapshot = json_decode($d['snapshot_json'], true, 512, JSON_THROW_ON_ERROR);
        $conexion = nubefact_config($db, $restaurante_id, $d['ambiente']);
        if (!$conexion || ($snapshot['proveedor'] ?? null) !== 'nubefact'
            || ($snapshot['emisor']['ruc'] ?? null) !== $conexion['ruc'] || ($snapshot['serie'] ?? null) !== $d['serie']
            || (int)($snapshot['correlativo'] ?? 0) !== (int)$d['correlativo'] || ($snapshot['tipo'] ?? null) !== $d['tipo']) {
            throw new DomainException('La conexión no concilia con la identidad del comprobante.');
        }
        if (!$consultar && !nubefact_config_activa($conexion)) {
            throw new DomainException('El envío NubeFacT está pausado para este restaurante.');
        }
        nubefact_ruta($conexion['ruta'], $d['ambiente']);
        $token = nubefact_token($conexion);
        // Registrar el intento antes de invocar al proveedor. La consulta nunca emite.
        if (!$consultar) {
            $db->prepare("UPDATE emision_documentos SET estado='procesando',intentos=intentos+1,
                respuesta_mensaje='Envío iniciado; esperando confirmación del proveedor'
                WHERE id=? AND restaurante_id=?")->execute([$id,$restaurante_id]);
        }
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }

    try {
        $respuesta = $consultar
            ? nubefact_consultar($conexion['ruta'], $token, $snapshot, $transporte)
            : nubefact_emitir($conexion['ruta'], $token, $snapshot, $transporte);
    } catch (Throwable $e) {
        // Un fallo de transporte o respuesta dudosa no demuestra que el envío falló.
        if (!$consultar) {
            $db->prepare("UPDATE emision_documentos SET estado='incierto',respuesta_mensaje=?
                WHERE id=? AND restaurante_id=? AND estado='procesando'")
                ->execute(['No se confirmó el envío. Consulta esta misma serie y número.', $id, $restaurante_id]);
            return 'incierto';
        }
        throw new DomainException('No se obtuvo una respuesta concluyente. Consulta nuevamente el mismo comprobante.');
    }

    $nuevo = $respuesta['estado'];
    if ($d['ambiente'] === 'pruebas' && in_array($nuevo, ['aceptado','aceptado_observado'], true)) $nuevo = 'prueba_aceptada';
    $db->beginTransaction();
    try {
        $st = $db->prepare('SELECT estado FROM emision_documentos WHERE id=? AND restaurante_id=? FOR UPDATE');
        $st->execute([$id,$restaurante_id]);
        $vigente = $st->fetchColumn();
        $terminales = ['aceptado','aceptado_observado','prueba_aceptada','rechazado'];
        if (!in_array($vigente,$terminales,true) || $vigente === $nuevo) {
            $db->prepare('UPDATE emision_documentos SET estado=?,respuesta_codigo=?,respuesta_mensaje=?
                WHERE id=? AND restaurante_id=?')->execute([$nuevo,$respuesta['codigo'],$respuesta['mensaje'],$id,$restaurante_id]);
            $db->prepare('UPDATE emision_nubefact_documentos SET pdf_url=COALESCE(?,pdf_url),
                xml_url=COALESCE(?,xml_url),cdr_url=COALESCE(?,cdr_url) WHERE documento_id=? AND restaurante_id=?')
                ->execute([$respuesta['pdf'],$respuesta['xml'],$respuesta['cdr'],$id,$restaurante_id]);
            $vigente = $nuevo;
        }
        $db->commit();
        return $vigente;
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
}

/** Registra una referencia declarada por el administrador, sin modificar el CPE. */
function nubefact_registrar_referencia(PDO $db, int $restaurante_id, int $id, string $referencia): void {
    if (!preg_match('/\A[\p{L}\p{N}][\p{L}\p{N} .:_\/#-]{2,119}\z/u', $referencia)) {
        throw new DomainException('Indica una referencia externa de 3 a 120 caracteres.');
    }
    $db->beginTransaction();
    try {
        $st = $db->prepare("UPDATE emision_nubefact_documentos n JOIN emision_documentos d
            ON d.id=n.documento_id AND d.restaurante_id=n.restaurante_id
            SET n.referencia_ajuste=? WHERE n.documento_id=? AND n.restaurante_id=?
            AND n.referencia_ajuste IS NULL AND d.ambiente='produccion'
            AND d.estado IN ('aceptado','aceptado_observado')");
        $st->execute([$referencia,$id,$restaurante_id]);
        if (!$st->rowCount()) throw new DomainException('El documento no admite esta referencia o ya tiene una registrada.');
        registrarAuditoria($db,'Registró referencia fiscal externa',"Documento #$id",true);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
}
