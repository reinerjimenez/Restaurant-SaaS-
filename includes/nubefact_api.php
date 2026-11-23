<?php
/** Adaptador del manual JSON V1 publicado en https://www.nubefact.com/integracion. */
declare(strict_types=1);
require_once __DIR__ . '/sunat_motor.php';

function nubefact_url(string $url): array {
    if (strlen($url) > 500 || preg_match('/[\x00-\x20\x7f\\\\]/', $url)) {
        throw new DomainException('Dirección NubeFacT inválida.');
    }
    $p = parse_url($url);
    if (!$p || ($p['scheme'] ?? '') !== 'https' || !isset($p['host'], $p['path'])
        || array_intersect(['port', 'user', 'pass', 'query', 'fragment'], array_keys($p))) {
        throw new DomainException('Dirección NubeFacT inválida.');
    }
    return [$p['host'], $p['path']];
}

function nubefact_ruta(string $url, string $ambiente): string {
    [$host, $path] = nubefact_url($url);
    $hosts = match ($ambiente) {
        'pruebas' => ['demo.nubefact.com'],
        'produccion' => ['api.nubefact.com'],
        default => [],
    };
    if (!in_array($host, $hosts, true) || !preg_match('~^/api/v1/[A-Za-z0-9-]{8,150}$~D', $path)) {
        throw new DomainException('La Ruta no pertenece al ambiente NubeFacT seleccionado.');
    }
    return 'https://' . $host . $path;
}

function nubefact_enlace(string $url, string $archivo): string {
    [$host, $path] = nubefact_url($url);
    $extensiones = ['pdf'=>'pdf', 'xml'=>'xml', 'cdr'=>'(?:cdr|zip)'];
    if (!isset($extensiones[$archivo]) || !in_array($host, ['www.nubefact.com', 'api.nubefact.com', 'demo.nubefact.com'], true)
        || !preg_match('~^/cpe/[A-Za-z0-9_-]{5,180}\.' . $extensiones[$archivo] . '$~Di', $path)) {
        throw new DomainException('Archivo NubeFacT no disponible en una ubicación autorizada.');
    }
    return 'https://' . $host . $path;
}

function nubefact_identidad(array $snapshot): array {
    $tipo = match ($snapshot['tipo'] ?? '') {
        'factura' => 1,
        'boleta' => 2,
        default => throw new DomainException('Tipo de comprobante no admitido por MESORA.'),
    };
    $serie = (string)($snapshot['serie'] ?? '');
    $numero = filter_var($snapshot['correlativo'] ?? null, FILTER_VALIDATE_INT,
        ['options'=>['min_range'=>1, 'max_range'=>99999999]]);
    if (!preg_match($tipo === 1 ? '/^F[A-Z0-9]{3}$/D' : '/^B[A-Z0-9]{3}$/D', $serie) || !$numero
        || !sunat_ruc_valido((string)($snapshot['emisor']['ruc'] ?? ''))) {
        throw new DomainException('Identidad fiscal inválida para NubeFacT.');
    }
    return ['tipo_de_comprobante'=>$tipo, 'serie'=>$serie, 'numero'=>$numero];
}

function nubefact_payload(array $s): array {
    $id = nubefact_identidad($s);
    $tasa = sunat_centimos((string)$s['tasa_igv']);
    $total = sunat_centimos((string)$s['total']);
    $descuento = sunat_centimos((string)($s['descuento'] ?? '0.00'));
    $cargo = sunat_centimos((string)($s['cargo_extra'] ?? '0.00'));
    $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $s['fecha'] ?? '');
    if (!$fecha || $fecha->format('Y-m-d') !== $s['fecha'] || ($s['moneda'] ?? '') !== 'PEN'
        || $tasa > 10000 || $total <= 0 || empty($s['items'])) {
        throw new DomainException('Fecha, moneda, impuestos o total inválidos para NubeFacT.');
    }
    $c = $s['cliente'];
    $direccion = trim($c['direccion'] ?? '');
    $tipo_cliente = match ($c['tipo_documento'] ?? '') {
        'ruc' => 6,
        'dni' => 1,
        'sin_documento' => '-',
        default => throw new DomainException('Tipo de documento del cliente no admitido.'),
    };
    $doc = (string)($c['numero_documento'] ?? '');
    if (($id['tipo_de_comprobante'] === 1 && ($tipo_cliente !== 6 || $direccion === ''))
        || ($tipo_cliente === 6 && !sunat_ruc_valido($doc))
        || ($tipo_cliente === 1 && !preg_match('/^[0-9]{8}$/D', $doc))
        || ($tipo_cliente === '-' && $total > 70000)) {
        throw new DomainException('Documento del cliente inválido para NubeFacT.');
    }
    $base = static fn(int $monto): int => intdiv($monto * 10000 + intdiv(10000 + $tasa, 2), 10000 + $tasa);
    $cat = ['gravada'=>1,'exonerada'=>8,'inafecta'=>9];
    $resumen = ['gravada'=>0,'igv'=>0,'exonerada'=>0,'inafecta'=>0];
    $items = [];
    $lineas = $s['items'];
    // El cargo de MESORA es gravado. «total_otros_cargos» del API no integra la base IGV.
    if ($cargo > 0) $lineas[] = ['descripcion'=>'Cargo adicional', 'cantidad'=>1,
        'precio_unitario'=>sunat_decimal($cargo),'subtotal'=>sunat_decimal($cargo),'afectacion_igv'=>'gravada'];
    foreach ($lineas as $linea) {
        $afectacion = $linea['afectacion_igv'] ?? 'gravada';
        $cantidad = filter_var($linea['cantidad'], FILTER_VALIDATE_INT,
            ['options'=>['min_range'=>1,'max_range'=>99999999]]);
        $importe = sunat_centimos((string)$linea['subtotal']);
        $precio = sunat_centimos((string)$linea['precio_unitario']);
        if (!isset($cat[$afectacion]) || !$cantidad || $precio <= 0 || $importe !== $precio * $cantidad) {
            throw new DomainException('Detalle incompatible con NubeFacT.');
        }
        $gravada = $afectacion === 'gravada';
        $sin_igv = $gravada ? $base($importe) : $importe;
        $igv = $importe - $sin_igv;
        $resumen[$afectacion] += $sin_igv;
        $resumen['igv'] += $igv;
        $items[] = [
            'unidad_de_medida'=>'NIU', 'descripcion'=>sunat_texto($linea['descripcion'], 250),
            'cantidad'=>$cantidad, 'precio_unitario'=>sunat_decimal($precio),
            'valor_unitario'=>$gravada ? number_format($precio * 100 / (10000 + $tasa), 9, '.', '') : sunat_decimal($precio),
            'subtotal'=>sunat_decimal($sin_igv), 'tipo_de_igv'=>$cat[$afectacion],
            'igv'=>sunat_decimal($igv), 'total'=>sunat_decimal($importe), 'anticipo_regularizacion'=>false,
        ];
    }
    if (($cargo || $descuento) && ($resumen['exonerada'] || $resumen['inafecta'])) {
        throw new DomainException('Los ajustes con operación mixta requieren un desglose adicional.');
    }
    $descuento_base = $base($descuento);
    $resumen['gravada'] -= $descuento_base;
    $resumen['igv'] -= $descuento - $descuento_base;
    if (min($resumen) < 0 || array_sum($resumen) !== $total) {
        throw new DomainException('El detalle NubeFacT no concilia con el total cobrado.');
    }
    foreach (['base_gravada'=>'gravada', 'igv'=>'igv', 'exonerada'=>'exonerada', 'inafecta'=>'inafecta'] as $campo=>$clave) {
        if (!isset($s['resumen_fiscal'][$campo]) || sunat_centimos((string)$s['resumen_fiscal'][$campo]) !== $resumen[$clave]) {
            throw new DomainException('El resumen fiscal no concilia con NubeFacT.');
        }
    }
    return ['operacion'=>'generar_comprobante'] + $id + [
        'sunat_transaction'=>1, 'cliente_tipo_de_documento'=>$tipo_cliente,
        'cliente_numero_de_documento'=>$tipo_cliente === '-' ? '-' : $doc,
        'cliente_denominacion'=>sunat_texto($c['nombre'], 200),
        'cliente_direccion'=>$direccion === '' ? '' : sunat_texto($direccion, 100),
        'fecha_de_emision'=>$fecha->format('d-m-Y'), 'moneda'=>1, 'porcentaje_de_igv'=>sunat_decimal($tasa),
        'descuento_global'=>$descuento_base ? sunat_decimal($descuento_base) : '',
        'total_descuento'=>$descuento_base ? sunat_decimal($descuento_base) : '',
        'total_gravada'=>sunat_decimal($resumen['gravada']), 'total_igv'=>sunat_decimal($resumen['igv']),
        'total_exonerada'=>sunat_decimal($resumen['exonerada']), 'total_inafecta'=>sunat_decimal($resumen['inafecta']),
        'total'=>sunat_decimal($total), 'enviar_automaticamente_a_la_sunat'=>true,
        'enviar_automaticamente_al_cliente'=>false, 'cancelado'=>true, 'items'=>$items,
    ];
}

function nubefact_validar_emision(array $snapshot): void {
    nubefact_payload($snapshot);
}

function nubefact_ip_publica(string $ip): bool {
    if (defined('FILTER_FLAG_GLOBAL_RANGE')) {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE) !== false;
    }
    // PHP 8.1 no distingue todos los rangos de IPv4 con uso especial.
    $flags = FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
    if (!filter_var($ip, FILTER_VALIDATE_IP, $flags)) return false;
    [$a, $b, $c] = array_map('intval', explode('.', $ip));
    return $a < 224
        && !($a === 100 && $b >= 64 && $b <= 127)
        && !($a === 192 && ($b === 0 || ($b === 88 && $c === 99)))
        && !($a === 198 && ($b === 18 || $b === 19 || ($b === 51 && $c === 100)))
        && !($a === 203 && $b === 0 && $c === 113);
}

/** No se siguen redirecciones; se fija una IP pública para evitar una segunda resolución. */
function nubefact_http(string $url, ?string $token, ?array $payload): string {
    [$host] = nubefact_url($url);
    $dns = dns_get_record($host, defined('FILTER_FLAG_GLOBAL_RANGE') ? DNS_A | DNS_AAAA : DNS_A);
    $ip = null;
    foreach ($dns ?: [] as $registro) {
        $actual = $registro['ip'] ?? $registro['ipv6'] ?? null;
        if (!$actual) continue;
        if (!nubefact_ip_publica($actual)) {
            throw new RuntimeException('Destino NubeFacT fuera de la red pública.');
        }
        // Se prefiere IPv4 cuando el alojamiento no tiene conectividad IPv6.
        if ($ip === null || isset($registro['ip'])) $ip = $actual;
    }
    if ($ip === null) throw new RuntimeException('No se pudo resolver NubeFacT.');
    $limite = 4 * 1024 * 1024;
    $contenido = '';
    $ch = curl_init($url);
    try {
        curl_setopt_array($ch, [
            CURLOPT_CONNECTTIMEOUT=>4, CURLOPT_TIMEOUT=>10, CURLOPT_FOLLOWLOCATION=>false,
            CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS, CURLOPT_SSL_VERIFYPEER=>true, CURLOPT_SSL_VERIFYHOST=>2,
            CURLOPT_PROXY=>'', CURLOPT_RESOLVE=>[$host . ':443:' . (str_contains($ip, ':') ? "[$ip]" : $ip)],
            CURLOPT_WRITEFUNCTION=>static function ($ch, string $trozo) use (&$contenido, $limite): int {
                if (strlen($contenido) + strlen($trozo) > $limite) return 0;
                $contenido .= $trozo;
                return strlen($trozo);
            },
        ]);
        if ($payload !== null) {
            curl_setopt_array($ch, [
                CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                CURLOPT_HTTPHEADER=>['Content-Type: application/json', 'Accept: application/json', 'Authorization: Token token='.$token],
            ]);
        }
        if (!curl_exec($ch) || (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE) !== 200) {
            throw new RuntimeException('NubeFacT no confirmó la operación. Consulta el mismo comprobante.');
        }
        return $contenido;
    } finally {
        curl_close($ch);
    }
}

function nubefact_respuesta(array $respuesta, array $snapshot): array {
    $id = nubefact_identidad($snapshot);
    foreach ($id as $campo=>$valor) {
        $actual = $respuesta[$campo] ?? null;
        if ((!is_string($actual) && !is_int($actual)) || (string)$actual !== (string)$valor) {
            throw new RuntimeException('NubeFacT no confirmó la identidad del comprobante.');
        }
    }
    $aceptada = $respuesta['aceptada_por_sunat'] ?? null;
    $codigo = $respuesta['sunat_responsecode'] ?? '';
    if (!is_bool($aceptada) || (!is_string($codigo) && !is_int($codigo))
        || !preg_match('/^[0-9]{0,4}$/D', (string)$codigo)) {
        throw new RuntimeException('Estado fiscal devuelto por NubeFacT no verificable.');
    }
    $codigo = (string)$codigo;
    $qr = $respuesta['cadena_para_codigo_qr'] ?? '';
    if ($aceptada || $qr !== '') {
        $campos = is_string($qr) ? array_map('trim', explode('|', $qr, 8)) : [];
        if (count($campos) < 4 || $campos[0] !== $snapshot['emisor']['ruc']
            || $campos[1] !== ($id['tipo_de_comprobante'] === 1 ? '01' : '03') || $campos[2] !== $id['serie']
            || !ctype_digit($campos[3]) || (int)$campos[3] !== $id['numero']) {
            throw new RuntimeException('NubeFacT no confirmó el RUC emisor del comprobante.');
        }
    }
    if ($aceptada && $codigo !== '0') {
        throw new RuntimeException('Los datos de aceptación de NubeFacT son contradictorios.');
    }
    $observacion = $respuesta['sunat_note'] ?? null;
    $estado = $aceptada ? (!empty($observacion) ? 'aceptado_observado' : 'aceptado')
        : ((int)$codigo >= 2000 && (int)$codigo <= 3999 ? 'rechazado' : 'pendiente_ticket');
    $mensajes = ['aceptado'=>'Comprobante aceptado según NubeFacT.',
        'aceptado_observado'=>'Comprobante aceptado con observaciones según NubeFacT.',
        'rechazado'=>'Comprobante rechazado. Consulta el código fiscal en NubeFacT.',
        'pendiente_ticket'=>'Sin confirmación fiscal. Consulta este mismo comprobante.'];
    $normalizada = ['estado'=>$estado, 'codigo'=>$codigo, 'mensaje'=>$mensajes[$estado]];
    foreach (['pdf','xml','cdr'] as $archivo) {
        $enlace = $respuesta['enlace_del_'.$archivo] ?? null;
        // El manual también documenta enlace .pdf derivado del enlace base.
        if (($enlace === null || $enlace === '') && $archivo === 'pdf' && is_string($respuesta['enlace'] ?? null)) {
            $enlace = $respuesta['enlace'] . '.pdf';
        }
        $normalizada[$archivo] = null;
        if (is_string($enlace) && $enlace !== '') {
            try { $normalizada[$archivo] = nubefact_enlace($enlace, $archivo); } catch (DomainException $e) { /* Archivo no autorizado. */ }
        }
    }
    return $normalizada;
}

function nubefact_validar_token(string $token): void {
    if (strlen($token) < 8 || strlen($token) > 512 || preg_match('/[^A-Za-z0-9._~+\/=-]/', $token)) {
        throw new DomainException('El Token NubeFacT no tiene un formato válido.');
    }
}

function nubefact_solicitar(string $ruta, string $token, array $snapshot, array $payload, ?callable $transporte): array {
    [$host] = nubefact_url($ruta);
    nubefact_ruta($ruta, $host === 'demo.nubefact.com' ? 'pruebas' : 'produccion');
    nubefact_validar_token($token);
    $raw = ($transporte ?? 'nubefact_http')($ruta, $token, $payload);
    $respuesta = is_array($raw) ? $raw : json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($respuesta) || isset($respuesta['errors'])) {
        throw new RuntimeException('NubeFacT no confirmó el comprobante. Consulta antes de reintentar.');
    }
    return nubefact_respuesta($respuesta, $snapshot);
}

function nubefact_emitir(string $ruta, string $token, array $snapshot, ?callable $transporte = null): array {
    return nubefact_solicitar($ruta, $token, $snapshot, nubefact_payload($snapshot), $transporte);
}

function nubefact_consultar(string $ruta, string $token, array $snapshot, ?callable $transporte = null): array {
    return nubefact_solicitar($ruta, $token, $snapshot,
        ['operacion'=>'consultar_comprobante'] + nubefact_identidad($snapshot), $transporte);
}

function nubefact_descargar(string $url, string $archivo): string {
    $contenido = nubefact_http(nubefact_enlace($url, $archivo), null, null);
    if ($archivo === 'pdf' && str_starts_with($contenido, '%PDF-')) return $contenido;
    if ($archivo === 'cdr' && str_starts_with($contenido, "PK\x03\x04")) return $contenido;
    if (in_array($archivo, ['xml','cdr'], true)) {
        $xml = sunat_cargar_xml($contenido);
        if ($xml->documentElement?->localName === ($archivo === 'cdr' ? 'ApplicationResponse' : 'Invoice')) return $contenido;
    }
    throw new RuntimeException('El proveedor no devolvió un archivo fiscal válido.');
}
