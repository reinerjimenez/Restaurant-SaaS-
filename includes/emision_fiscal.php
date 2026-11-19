<?php
/** Preparación fiscal aislada por restaurante. No realiza cobros ni envíos reales. */
require_once __DIR__ . '/../config/finanzas.php';
require_once __DIR__ . '/sunat_motor.php';

/** Ensayo de firma, empaquetado y lectura de respuesta, enteramente local. */
function emision_simular_documento(array $snapshot, string $p12, string $clave): array {
    $firmado = sunat_firmar_xml(sunat_generar_invoice($snapshot), $p12, $clave);
    $numero = $snapshot['serie'] . '-' . $snapshot['correlativo'];
    $ruc = $snapshot['emisor']['ruc'];
    $nombre = $ruc . '-' . ($snapshot['tipo'] === 'factura' ? '01' : '03') . '-' . $numero;
    $zip = sunat_crear_zip($nombre . '.xml', $firmado);
    $cdr = '<ApplicationResponse xmlns="urn:oasis:names:specification:ubl:schema:xsd:ApplicationResponse-2"'
        . ' xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"'
        . ' xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">'
        . '<cac:ReceiverParty><cac:PartyIdentification><cbc:ID>' . $ruc . '</cbc:ID></cac:PartyIdentification></cac:ReceiverParty>'
        . '<cac:DocumentResponse><cac:Response><cbc:ResponseCode>0</cbc:ResponseCode>'
        . '<cbc:Description>SIMULADO SIN VALIDEZ TRIBUTARIA</cbc:Description></cac:Response>'
        . '<cac:DocumentReference><cbc:ID>' . $numero . '</cbc:ID></cac:DocumentReference></cac:DocumentResponse></ApplicationResponse>';
    $respuesta = sunat_analizar_cdr_xml($cdr, $numero, $ruc);
    if ($respuesta['estado'] !== 'aceptado') throw new DomainException('La respuesta simulada no pudo conciliarse.');
    return ['xml_firmado'=>$firmado, 'cdr_xml'=>$cdr, 'zip_bytes'=>strlen($zip), 'estado'=>'prueba_aceptada'];
}

function emision_produccion_autorizada(int $restaurante_id): bool {
    return getenv('SUNAT_PRODUCCION_AUTORIZADA_' . $restaurante_id) === '1';
}

function emision_credenciales(int $restaurante_id): array {
    $prefijo = 'SUNAT_' . $restaurante_id . '_';
    $datos = [];
    foreach (['P12_PATH','P12_PASSWORD','USUARIO','CLAVE'] as $campo) {
        $v = getenv($prefijo . $campo);
        if (!is_string($v) || $v === '') throw new DomainException('Faltan credenciales de emisión de este restaurante en el servidor.');
        $datos[strtolower($campo)] = $v;
    }
    $archivo = realpath($datos['p12_path']);
    $raiz = realpath(__DIR__ . '/..');
    $publica = isset($_SERVER['DOCUMENT_ROOT']) && $_SERVER['DOCUMENT_ROOT'] !== '' ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
    $raiz_servidor = getenv('SUNAT_PUBLIC_ROOT');
    $publica_cli = $raiz_servidor ? realpath($raiz_servidor) : dirname($raiz);
    if ($publica_cli === false) throw new DomainException('SUNAT_PUBLIC_ROOT no apunta al directorio público válido del servidor.');
    foreach (array_filter([$raiz, $publica, $publica_cli]) as $directorio) {
        $base = strtolower(rtrim(str_replace('\\', '/', $directorio), '/') . '/');
        if ($archivo !== false && str_starts_with(strtolower(str_replace('\\', '/', $archivo)), $base)) {
            throw new DomainException('El certificado debe guardarse fuera del proyecto y del directorio público.');
        }
    }
    if ($archivo === false || !is_file($archivo) || !is_readable($archivo) || filesize($archivo) > 1024 * 1024) {
        throw new DomainException('Certificado no disponible o demasiado grande.');
    }
    $datos['p12_path'] = $archivo;
    return $datos;
}

function emision_esquema_disponible(PDO $db): bool {
    $st = $db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()
        AND table_name IN ('emision_config', 'emision_documentos')");
    return (int)$st->fetchColumn() === 2;
}

function fiscal_exigir_venta_modificable(PDO $db, int $restaurante_id, int $pedido_id): void {
    if (!emision_esquema_disponible($db)) return;
    $st = $db->prepare('SELECT id FROM emision_documentos WHERE restaurante_id = ? AND pedido_id = ? LIMIT 1');
    $st->execute([$restaurante_id, $pedido_id]);
    if ($st->fetchColumn()) throw new DomainException('Esta venta tiene un documento fiscal de ensayo. No se puede revertir localmente; conserva su evidencia.');
}

function emision_validar_config(array $input): array {
    $config = [];
    foreach (['ruc' => 11, 'razon_social' => 200, 'direccion' => 300, 'ubigeo' => 6,
        'regimen' => 7, 'modo' => 12, 'serie_boleta' => 4, 'serie_factura' => 4] as $campo => $maximo) {
        $valor = $input[$campo] ?? '';
        if (!is_string($valor) || mb_strlen($valor) > $maximo) throw new DomainException('Configuración inválida.');
        $config[$campo] = trim($valor);
    }
    if (!in_array($config['modo'], ['desactivado', 'pruebas', 'produccion'], true)
        || !in_array($config['regimen'], ['RER', 'RMT', 'GENERAL'], true)
        || !preg_match('/\AB[A-Z0-9]{3}\z/', $config['serie_boleta'])
        || !preg_match('/\AF[A-Z0-9]{3}\z/', $config['serie_factura'])) {
        throw new DomainException('Revisa el modo, régimen y las series de cuatro caracteres.');
    }
    $config['tasa_igv'] = decimal_centimos(importe_centimos($input['tasa_igv'] ?? null));
    if (importe_centimos($config['tasa_igv']) <= 0 || importe_centimos($config['tasa_igv']) > 10000) throw new DomainException('Tasa de IGV inválida. Este flujo requiere operaciones gravadas.');
    if ($config['modo'] !== 'desactivado' && (!sunat_ruc_valido($config['ruc'])
        || !preg_match('/\A\d{6}\z/', $config['ubigeo']) || $config['razon_social'] === '' || $config['direccion'] === '')) {
        throw new DomainException('Completa RUC, razón social, dirección y ubigeo antes de activar pruebas.');
    }
    return $config;
}

function emision_guardar_config(PDO $db, int $restaurante_id, array $input): void {
    $c = emision_validar_config($input);
    if ($c['modo'] === 'produccion') {
        if (!emision_produccion_autorizada($restaurante_id)) throw new DomainException('La producción de este restaurante aún no está autorizada en el servidor.');
        emision_credenciales($restaurante_id);
    }
    $db->beginTransaction();
    try {
        bloquear_restaurante($db, $restaurante_id);
        $st = $db->prepare('SELECT ruc, serie_boleta, serie_factura FROM emision_config WHERE restaurante_id = ? FOR UPDATE');
        $st->execute([$restaurante_id]);
        $anterior = $st->fetch();
        if ($anterior && ($anterior['ruc'] !== $c['ruc'] || $anterior['serie_boleta'] !== $c['serie_boleta'] || $anterior['serie_factura'] !== $c['serie_factura'])) {
            $st = $db->prepare('SELECT id FROM emision_documentos WHERE restaurante_id = ? LIMIT 1');
            $st->execute([$restaurante_id]);
            if ($st->fetchColumn()) throw new DomainException('El RUC y las series con documentos registrados no se pueden cambiar.');
        }
        $db->prepare('INSERT INTO emision_config (restaurante_id, modo, ruc, razon_social, direccion, ubigeo, regimen,
            tasa_igv, serie_boleta, serie_factura) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE modo=VALUES(modo), ruc=VALUES(ruc), razon_social=VALUES(razon_social),
            direccion=VALUES(direccion), ubigeo=VALUES(ubigeo), regimen=VALUES(regimen), tasa_igv=VALUES(tasa_igv),
            serie_boleta=VALUES(serie_boleta), serie_factura=VALUES(serie_factura)')
            ->execute([$restaurante_id, $c['modo'], $c['ruc'], $c['razon_social'], $c['direccion'], $c['ubigeo'],
                $c['regimen'], $c['tasa_igv'], $c['serie_boleta'], $c['serie_factura']]);
        registrarAuditoria($db, 'Configuró emisión de ensayo', 'Modo: ' . $c['modo'], true);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
}

function emision_solicitar(PDO $db, int $restaurante_id, int $usuario_id, array $input): int {
    $pedido_id = filter_var($input['pedido_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $tipo = $input['tipo'] ?? '';
    $cliente = [];
    foreach (['numero_documento' => 11, 'nombre' => 200, 'direccion' => 300, 'tipo_documento' => 3] as $campo => $maximo) {
        $v = $input[$campo] ?? '';
        if (!is_string($v) || mb_strlen($v) > $maximo) throw new DomainException('Datos del cliente inválidos.');
        $cliente[$campo] = trim($v);
    }
    if (!$pedido_id || !in_array($tipo, ['boleta', 'factura'], true) || $cliente['nombre'] === ''
        || !in_array($cliente['tipo_documento'], ['dni', 'ruc'], true)
        || !preg_match($cliente['tipo_documento'] === 'ruc' ? '/\A\d{11}\z/' : '/\A\d{8}\z/', $cliente['numero_documento'])
        || ($cliente['tipo_documento'] === 'ruc' && !sunat_ruc_valido($cliente['numero_documento']))
        || ($tipo === 'factura' && ($cliente['tipo_documento'] !== 'ruc' || $cliente['direccion'] === ''))) {
        throw new DomainException('Indica un pedido cobrado y datos válidos del cliente; la factura exige RUC y dirección.');
    }
    $propia = !$db->inTransaction();
    if ($propia) $db->beginTransaction();
    try {
        bloquear_restaurante($db, $restaurante_id);
        $pedido = bloquear_pedido($db, $restaurante_id, $pedido_id, 'cobrado');
        $st = $db->prepare('SELECT * FROM emision_config WHERE restaurante_id = ? FOR UPDATE');
        $st->execute([$restaurante_id]);
        $c = $st->fetch();
        if (!$c || $c['modo'] === 'desactivado') throw new DomainException('Activa y completa primero la configuración de emisión.');
        emision_validar_config($c);
        $ambiente = $c['modo'];
        if ($ambiente === 'produccion') {
            if (!emision_produccion_autorizada($restaurante_id)) throw new DomainException('Producción no autorizada para este restaurante.');
            emision_credenciales($restaurante_id);
        }
        $st = $db->prepare('SELECT id, tipo, snapshot_json FROM emision_documentos WHERE restaurante_id = ? AND pedido_id = ? AND ambiente = ?');
        $st->execute([$restaurante_id, $pedido_id, $ambiente]);
        if ($existente = $st->fetch()) {
            $anterior = json_decode($existente['snapshot_json'], true, 512, JSON_THROW_ON_ERROR);
            if ($existente['tipo'] !== $tipo || $anterior['cliente'] !== $cliente) {
                throw new DomainException('Ya existe un documento de ensayo para este pedido con otros datos. Revisa el historial.');
            }
            if ($propia) $db->commit();
            return (int)$existente['id'];
        }
        if (importe_centimos($pedido['descuento']) || importe_centimos($pedido['cargo_extra'])) {
            throw new DomainException('Los pedidos con descuentos o cargos necesitan el desglose fiscal; este ensayo aún no los admite.');
        }
        $items = array_map(static fn(array $i): array => ['descripcion' => $i['producto_nombre']
            . ($i['opciones_texto'] ? ' · ' . $i['opciones_texto'] : ''), 'cantidad' => (int)$i['cantidad'],
            'precio_unitario' => $i['precio_unitario'], 'subtotal' => $i['subtotal']], items_comprobante($db, $pedido_id));
        if (!$items || subtotal_pedido($db, $pedido_id) !== importe_centimos($pedido['total'])) {
            throw new DomainException('El detalle del pedido no concilia con su total.');
        }
        $columna = $tipo === 'factura' ? 'correlativo_factura' : 'correlativo_boleta';
        if ($ambiente === 'pruebas') $columna .= '_pruebas';
        $serie = $c[$tipo === 'factura' ? 'serie_factura' : 'serie_boleta'];
        $numero = (int)$c[$columna] + 1;
        if ($numero > 99999999) throw new DomainException('La serie de ensayo agotó su correlativo.');
        $snapshot = ['emisor' => array_intersect_key($c, array_flip(['ruc', 'razon_social', 'direccion', 'ubigeo', 'regimen'])),
            'tipo' => $tipo, 'serie' => $serie, 'correlativo' => $numero, 'fecha' => date('Y-m-d'), 'moneda' => 'PEN',
            'tasa_igv' => $c['tasa_igv'], 'cliente' => $cliente, 'items' => $items, 'total' => $pedido['total']];
        $db->prepare("UPDATE emision_config SET $columna = ? WHERE restaurante_id = ?")->execute([$numero, $restaurante_id]);
        $db->prepare('INSERT INTO emision_documentos (restaurante_id,pedido_id,usuario_id,ambiente,tipo,serie,correlativo,snapshot_json)
            VALUES (?,?,?,?,?,?,?,?)')->execute([$restaurante_id, $pedido_id, $usuario_id, $ambiente, $tipo, $serie, $numero,
                json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
        $id = (int)$db->lastInsertId();
        registrarAuditoria($db, 'Solicitó documento de ensayo', "Documento #$id · Pedido #$pedido_id · Sin nuevo cobro", true);
        if ($propia) $db->commit();
        return $id;
    } catch (Throwable $e) {
        if ($propia && $db->inTransaction()) $db->rollBack();
        throw $e;
    }
}
