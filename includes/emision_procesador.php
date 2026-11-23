<?php
/** Procesamiento fiscal: transacciones breves y transporte fuera del bloqueo de caja. */
require_once __DIR__ . '/emision_fiscal.php';
require_once __DIR__ . '/sunat_motor.php';

function emision_identidad(array $snapshot): string {
    return $snapshot['emisor']['ruc'] . '-' . ($snapshot['tipo'] === 'factura' ? '01' : '03')
        . '-' . $snapshot['serie'] . '-' . $snapshot['correlativo'];
}

/** El transporte es inyectable en pruebas; nunca se activa un simulador por configuración. */
function emision_procesar_real(PDO $db, int $restaurante_id, int $id, bool $consultar, ?callable $transporte = null): array {
    if (!emision_produccion_autorizada($restaurante_id)) throw new DomainException('La producción de este restaurante no está autorizada.');
    $credenciales = emision_credenciales($restaurante_id);
    $db->beginTransaction();
    try {
        bloquear_restaurante($db, $restaurante_id);
        $st = $db->prepare("SELECT d.*,c.ruc AS ruc_config FROM emision_documentos d JOIN emision_config c ON c.restaurante_id=d.restaurante_id
            WHERE d.id=? AND d.restaurante_id=? AND d.ambiente='produccion' AND c.modo='produccion' FOR UPDATE");
        $st->execute([$id, $restaurante_id]);
        $d = $st->fetch();
        if (!$d) throw new DomainException('Documento no disponible para este restaurante en producción.');
        $snapshot = json_decode($d['snapshot_json'], true, 512, JSON_THROW_ON_ERROR);
        if (($snapshot['proveedor'] ?? 'directo') !== 'directo') {
            throw new DomainException('Consulta este documento con el proveedor que lo emitió.');
        }
        if (in_array($d['estado'], ['aceptado','aceptado_observado','rechazado'], true)) {
            $db->commit();
            return ['estado'=>$d['estado'], 'mensaje'=>'Documento ya conciliado; no se repitió el envío.'];
        }
        if (!$consultar && $d['estado'] !== 'pendiente') {
            throw new DomainException('El documento tiene un envío iniciado o incierto. Consulta su CDR; no se reenviará automáticamente.');
        }
        if ($consultar && !in_array($d['estado'], ['procesando','incierto','pendiente_ticket'], true)) {
            throw new DomainException('Solo se consultan documentos con envío iniciado o incierto.');
        }
        if (substr($credenciales['usuario'],0,11) !== $snapshot['emisor']['ruc'] || $d['ruc_config'] !== $snapshot['emisor']['ruc']) {
            throw new DomainException('El RUC del documento no coincide con su configuración o usuario SOL.');
        }
        $identidad = emision_identidad($snapshot);
        $es_boleta = $snapshot['tipo'] === 'boleta';
        if ($es_boleta) {
            if (empty($d['resumen_identidad'])) {
                $prefijo = $snapshot['emisor']['ruc'] . '-RC-' . date('Ymd') . '-';
                $stResumen=$db->prepare("SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(resumen_identidad,'-',-1) AS UNSIGNED)),0)+1
                    FROM emision_documentos WHERE restaurante_id=? AND ambiente='produccion' AND resumen_identidad LIKE ?");
                $stResumen->execute([$restaurante_id,$prefijo.'%']);
                $secuencia=(int)$stResumen->fetchColumn();
                if ($secuencia>99999) throw new DomainException('Se agotaron los resúmenes disponibles para hoy.');
                $identidad=$prefijo.$secuencia;
            } else $identidad=$d['resumen_identidad'];
            $resumen_id=substr($identidad,12);
        }
        $xml = $d['xml_firmado'];
        if (!$consultar) {
            if (!$xml) $xml = sunat_firmar_xml(sunat_generar_invoice($snapshot), file_get_contents($credenciales['p12_path']), $credenciales['p12_password']);
            $xml_envio = $xml;
            if ($es_boleta) $xml_envio = $d['resumen_xml_firmado'] ?: sunat_firmar_xml(sunat_generar_resumen($snapshot, $resumen_id, date('Y-m-d')),
                file_get_contents($credenciales['p12_path']), $credenciales['p12_password']);
            $zip = sunat_crear_zip($identidad . '.xml', $xml_envio);
            $db->prepare("UPDATE emision_documentos SET xml_firmado=?, estado='procesando', intentos=intentos+1,
                respuesta_mensaje='Envío iniciado; pendiente de conciliación' WHERE id=? AND restaurante_id=?")
                ->execute([$xml, $id, $restaurante_id]);
            if ($es_boleta) $db->prepare('UPDATE emision_documentos SET resumen_identidad=?,resumen_xml_firmado=? WHERE id=? AND restaurante_id=?')
                ->execute([$identidad, $xml_envio, $id, $restaurante_id]);
        } elseif (!$xml) {
            throw new DomainException('No hay XML firmado persistido para conciliar este documento.');
        }
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
    // Desde aquí el envío puede haber llegado al receptor: cualquier fallo exige consulta.
    try {
        if ($transporte) {
            $cdr_zip = $transporte($consultar, $identidad, $xml_envio ?? $xml, $credenciales);
        } elseif ($es_boleta && !$consultar) {
            $cdr_zip = ['ticket'=>sunat_enviar_resumen($zip, $identidad . '.zip', $credenciales['usuario'], $credenciales['clave'], true)];
        } elseif ($es_boleta) {
            if (empty($d['ticket'])) throw new DomainException('No se recibió ticket. Conciliar manualmente; no reenviar el resumen.');
            $cdr_zip = sunat_consultar_ticket($d['ticket'], $credenciales['usuario'], $credenciales['clave'], true);
        } elseif ($consultar) {
            $consulta = sunat_consultar_cdr($identidad, $credenciales['usuario'], $credenciales['clave'], true);
            if (!$consulta['cdr']) throw new RuntimeException('La consulta aún no devuelve una CDR.');
            $cdr_zip = $consulta['cdr'];
        } else {
            $cdr_zip = sunat_enviar_bill($zip, $identidad . '.zip',
                $credenciales['usuario'], $credenciales['clave'], true);
        }
        if ($es_boleta && is_array($cdr_zip)) {
            if (!$consultar && isset($cdr_zip['ticket'])) {
                $db->prepare("UPDATE emision_documentos SET ticket=?,estado='pendiente_ticket',respuesta_mensaje='Resumen recibido; aceptación pendiente'
                    WHERE id=? AND restaurante_id=? AND estado='procesando'")->execute([$cdr_zip['ticket'], $id, $restaurante_id]);
                return ['estado'=>'pendiente_ticket','mensaje'=>'Boleta enviada en resumen; pendiente de consultar ticket.'];
            }
            if (($cdr_zip['codigo'] ?? '') === '98') return ['estado'=>'pendiente_ticket','mensaje'=>'SUNAT aún procesa el resumen; consultar después.'];
            if (($cdr_zip['codigo'] ?? '') === '99' && empty($cdr_zip['cdr'])) {
                $db->prepare("UPDATE emision_documentos SET estado='rechazado',respuesta_codigo='99',respuesta_mensaje='Resumen procesado con errores; revisar SUNAT'
                    WHERE id=? AND restaurante_id=?")->execute([$id,$restaurante_id]);
                return ['estado'=>'rechazado','mensaje'=>'Resumen procesado con errores. Revisar SUNAT.'];
            }
            if (empty($cdr_zip['cdr'])) throw new RuntimeException('Consulta sin CDR válida.');
            $cdr_zip = $cdr_zip['cdr'];
        }
        $resultado = sunat_leer_cdr($cdr_zip, $identidad);
        $st = $db->prepare("UPDATE emision_documentos SET estado=?,cdr_xml=?,respuesta_codigo=?,respuesta_mensaje=?
            WHERE id=? AND restaurante_id=? AND ambiente='produccion' AND estado IN ('procesando','incierto','pendiente_ticket')");
        $st->execute([$resultado['estado'], $resultado['xml'], $resultado['codigo'], mb_substr($resultado['mensaje'],0,500), $id, $restaurante_id]);
        return ['estado'=>$resultado['estado'], 'mensaje'=>$resultado['mensaje']];
    } catch (Throwable $e) {
        $db->prepare("UPDATE emision_documentos SET estado='incierto',respuesta_mensaje=?
            WHERE id=? AND restaurante_id=? AND ambiente='produccion' AND estado IN ('procesando','incierto','pendiente_ticket')")
            ->execute(['No se confirmó una CDR válida. Consultar antes de reintentar; no volver a numerar.', $id, $restaurante_id]);
        error_log('Emisión fiscal sin confirmación para documento #' . $id . '.');
        return ['estado'=>'incierto', 'mensaje'=>'No se confirmó la emisión. Consulta el estado antes de reintentar.'];
    }
}
