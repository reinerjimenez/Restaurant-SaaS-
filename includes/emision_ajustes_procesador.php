<?php
/** Envío y conciliación de notas de factura y bajas de boleta. */
declare(strict_types=1);
require_once __DIR__ . '/emision_ajustes.php';

/** Transporte inyectable solo en pruebas; los comandos de producción no lo exponen. */
function emision_procesar_ajuste_real(PDO $db, int $restaurante_id, int $id, bool $consultar,
    ?callable $transporte = null): array {
    if (!emision_produccion_autorizada($restaurante_id)) throw new DomainException('Producción no autorizada.');
    $credenciales = emision_credenciales($restaurante_id);
    $db->beginTransaction();
    try {
        bloquear_restaurante($db, $restaurante_id);
        $st = $db->prepare("SELECT a.*,c.ruc,d.tipo AS tipo_original,d.estado AS estado_original FROM emision_ajustes a
            JOIN emision_config c ON c.restaurante_id=a.restaurante_id
            JOIN emision_documentos d ON d.id=a.documento_id AND d.restaurante_id=a.restaurante_id
            WHERE a.id=? AND a.restaurante_id=? AND a.ambiente='produccion' AND c.modo='produccion' FOR UPDATE");
        $st->execute([$id,$restaurante_id]);
        $a = $st->fetch();
        if (!$a || !in_array($a['estado_original'], ['aceptado','aceptado_observado'], true)) {
            throw new DomainException('Ajuste u original no disponible para este restaurante.');
        }
        if (in_array($a['estado'], ['aceptado','aceptado_observado','rechazado'], true)) {
            $db->commit();
            return ['estado'=>$a['estado'],'mensaje'=>'Ajuste ya conciliado; no se repitió el envío.'];
        }
        if ((!$consultar && $a['estado'] !== 'pendiente') || ($consultar && !in_array($a['estado'],
            ['procesando','incierto','pendiente_ticket'], true))) throw new DomainException('Consulta el ajuste antes de reintentar; no se reenviará automáticamente.');
        $s = json_decode($a['snapshot_json'], true, 512, JSON_THROW_ON_ERROR);
        $o = $s['origen'];
        if ($a['ruc'] !== $o['emisor']['ruc'] || substr($credenciales['usuario'],0,11) !== $a['ruc']) {
            throw new DomainException('RUC de ajuste o credenciales no coincide.');
        }
        $baja = $a['tipo'] === 'baja_boleta';
        if ($baja && $a['tipo_original'] !== 'boleta') throw new DomainException('Baja solo para boletas.');
        if (!$baja && $a['tipo_original'] !== 'factura') throw new DomainException('Nota individual solo para factura.');
        if ($baja) {
            if ($a['resumen_identidad']) $identidad = $a['resumen_identidad'];
            else {
                $prefijo = $a['ruc'] . '-RC-' . date('Ymd') . '-';
                $st = $db->prepare("SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(resumen_identidad,'-',-1) AS UNSIGNED)),0)
                    FROM emision_documentos WHERE restaurante_id=? AND ambiente='produccion' AND resumen_identidad LIKE ?");
                $st->execute([$restaurante_id,$prefijo.'%']);
                $maximo = (int)$st->fetchColumn();
                $st = $db->prepare("SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(resumen_identidad,'-',-1) AS UNSIGNED)),0)
                    FROM emision_ajustes WHERE restaurante_id=? AND ambiente='produccion' AND resumen_identidad LIKE ?");
                $st->execute([$restaurante_id,$prefijo.'%']);
                $n = max($maximo,(int)$st->fetchColumn()) + 1;
                if ($n > 99999) throw new DomainException('Se agotaron los resúmenes de hoy.');
                $identidad = $prefijo . $n;
            }
        } else $identidad = $a['ruc'] . '-07-' . $a['serie'] . '-' . $a['correlativo'];
        $xml = $a['xml_firmado'];
        if (!$consultar) {
            if (!$xml) {
                $sin_firma = $baja
                    ? sunat_generar_resumen($o, substr($identidad,12), date('Y-m-d'), 3)
                    : sunat_generar_nota_credito($o, $a['serie'], (int)$a['correlativo'], $s['fecha'], $s['motivo_texto']);
                $certificado = file_get_contents($credenciales['p12_path']);
                if ($certificado === false) throw new DomainException('Certificado no disponible.');
                $xml = sunat_firmar_xml($sin_firma, $certificado, $credenciales['p12_password']);
            }
            $zip = sunat_crear_zip($identidad . '.xml', $xml);
            $db->prepare("UPDATE emision_ajustes SET xml_firmado=?,resumen_identidad=?,estado='procesando',
                intentos=intentos+1,respuesta_mensaje='Envío iniciado; conciliación pendiente'
                WHERE id=? AND restaurante_id=?")
                ->execute([$xml,$baja ? $identidad : null,$id,$restaurante_id]);
        } elseif (!$xml) throw new DomainException('Ajuste sin XML persistido para consulta.');
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
    try {
        if ($transporte) $respuesta = $transporte($consultar,$identidad,$xml,$credenciales);
        elseif ($baja && !$consultar) $respuesta = ['ticket'=>sunat_enviar_resumen($zip,$identidad . '.zip',
            $credenciales['usuario'],$credenciales['clave'],true)];
        elseif ($baja) {
            if (!$a['ticket']) throw new DomainException('No hay ticket; conciliación manual necesaria.');
            $respuesta = sunat_consultar_ticket($a['ticket'],$credenciales['usuario'],$credenciales['clave'],true);
        } elseif ($consultar) {
            $consulta = sunat_consultar_cdr($identidad,$credenciales['usuario'],$credenciales['clave'],true);
            if (empty($consulta['cdr'])) throw new RuntimeException('La consulta aún no devuelve la CDR de la nota.');
            $respuesta = $consulta['cdr'];
        }
        else $respuesta = sunat_enviar_bill($zip,$identidad . '.zip',$credenciales['usuario'],$credenciales['clave'],true);
        if ($baja && is_array($respuesta)) {
            if (!$consultar && isset($respuesta['ticket'])) {
                $db->prepare("UPDATE emision_ajustes SET ticket=?,estado='pendiente_ticket',
                    respuesta_mensaje='Baja enviada; esperar CDR del resumen' WHERE id=? AND restaurante_id=? AND estado='procesando'")
                    ->execute([$respuesta['ticket'],$id,$restaurante_id]);
                return ['estado'=>'pendiente_ticket','mensaje'=>'Baja enviada; pendiente de ticket.'];
            }
            if (($respuesta['codigo'] ?? '') === '98') return ['estado'=>'pendiente_ticket','mensaje'=>'SUNAT sigue procesando la baja.'];
            if (($respuesta['codigo'] ?? '') === '99' && empty($respuesta['cdr'])) {
                $db->prepare("UPDATE emision_ajustes SET estado='rechazado',respuesta_codigo='99',
                    respuesta_mensaje='Resumen de baja procesado con errores' WHERE id=? AND restaurante_id=?")
                    ->execute([$id,$restaurante_id]);
                return ['estado'=>'rechazado','mensaje'=>'Baja rechazada por resumen.'];
            }
            if (empty($respuesta['cdr'])) throw new RuntimeException('Baja sin CDR.');
            $respuesta = $respuesta['cdr'];
        }
        $resultado = sunat_leer_cdr($respuesta,$identidad);
        $db->prepare("UPDATE emision_ajustes SET estado=?,cdr_xml=?,respuesta_codigo=?,respuesta_mensaje=?
            WHERE id=? AND restaurante_id=? AND estado IN ('procesando','incierto','pendiente_ticket')")
            ->execute([$resultado['estado'],$resultado['xml'],$resultado['codigo'],mb_substr($resultado['mensaje'],0,500),$id,$restaurante_id]);
        return ['estado'=>$resultado['estado'],'mensaje'=>$resultado['mensaje']];
    } catch (Throwable $e) {
        $db->prepare("UPDATE emision_ajustes SET estado='incierto',respuesta_mensaje='Sin CDR confirmada; conciliar antes de reintentar'
            WHERE id=? AND restaurante_id=? AND estado IN ('procesando','incierto','pendiente_ticket')")
            ->execute([$id,$restaurante_id]);
        error_log('Ajuste fiscal sin confirmación #' . $id . '.');
        return ['estado'=>'incierto','mensaje'=>'Sin CDR confirmada. Conciliar sin reenvío.'];
    }
}
