<?php
/** Prueba del ciclo de envío con PDO doble y transporte sin red. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/nubefact_procesador.php';

function verificar_procesador_nubefact(bool $ok): void {
    if (!$ok) throw new RuntimeException('Falló el contrato del procesador NubeFacT.');
}

final class ConsultaNubefactPrueba extends PDOStatement {
    public function __construct(private ConexionNubefactPrueba $db, private string $sql) {}
    public function execute(?array $params = null): bool {
        if (str_contains($this->sql, 'WHERE d.id=? AND d.restaurante_id=?')) {
            verificar_procesador_nubefact($params === [15, 7]);
        }
        if (str_contains($this->sql, "SET estado='procesando'")) {
            verificar_procesador_nubefact($this->db->inTransaction());
            $this->db->doc['estado'] = 'procesando';
        }
        if (str_contains($this->sql, "SET estado='incierto'")) {
            verificar_procesador_nubefact(!$this->db->inTransaction());
            $this->db->doc['estado'] = 'incierto';
        }
        if (str_contains($this->sql, 'SET estado=?,respuesta_codigo=')) {
            verificar_procesador_nubefact($this->db->inTransaction());
            $this->db->doc['estado'] = $params[0];
        }
        return true;
    }
    public function fetch(int $mode=PDO::FETCH_DEFAULT, int $cursorOrientation=PDO::FETCH_ORI_NEXT, int $cursorOffset=0): mixed {
        return str_contains($this->sql,'FROM emision_nubefact_config') ? $this->db->config : $this->db->doc;
    }
    public function fetchColumn(int $column=0): mixed {
        return str_contains($this->sql, 'SELECT estado FROM emision_documentos') ? $this->db->doc['estado'] : 2;
    }
}

final class ConexionNubefactPrueba extends PDO {
    private bool $transaccion = false;
    public function __construct(public array $config, public array $doc) {}
    public function query(string $query, ?int $fetchMode=null, mixed ...$args): PDOStatement|false {
        return $this->prepare($query);
    }
    public function prepare(string $query, array $options=[]): PDOStatement|false {
        return new ConsultaNubefactPrueba($this, $query);
    }
    public function beginTransaction(): bool { $this->transaccion = true; return true; }
    public function inTransaction(): bool { return $this->transaccion; }
    public function commit(): bool { $this->transaccion = false; return true; }
    public function rollBack(): bool { $this->transaccion = false; return true; }
}

putenv('MESORA_NUBEFACT_KEY=' . base64_encode(random_bytes(32)));
$c = ['restaurante_id'=>7,'ambiente'=>'pruebas','ruc'=>'20100070970',
    'ruta'=>'https://demo.nubefact.com/api/v1/11111111-2222-4333-8444-555555555555','habilitado'=>1];
$c['token_cifrado'] = nubefact_cifrar_token('token-ficticio-ensayo',$c);
$snapshot = ['proveedor'=>'nubefact','emisor'=>['ruc'=>$c['ruc']], 'tipo'=>'factura','serie'=>'F001',
    'correlativo'=>1,'fecha'=>date('Y-m-d'),'moneda'=>'PEN','tasa_igv'=>'18.00',
    'cliente'=>['tipo_documento'=>'ruc','numero_documento'=>'20123456786','nombre'=>'Comprador ficticio','direccion'=>'Av. Ensayo 1'],
    'items'=>[['descripcion'=>'Plato','cantidad'=>1,'precio_unitario'=>'118.00','subtotal'=>'118.00','afectacion_igv'=>'gravada']],
    'descuento'=>'0.00','cargo_extra'=>'0.00','total'=>'118.00',
    'resumen_fiscal'=>['base_gravada'=>'100.00','igv'=>'18.00','exonerada'=>'0.00','inafecta'=>'0.00']];
$db = new ConexionNubefactPrueba($c, ['estado'=>'pendiente','ambiente'=>'pruebas','serie'=>'F001','correlativo'=>1,
    'tipo'=>'factura','segundos'=>0,'snapshot_json'=>json_encode($snapshot,JSON_THROW_ON_ERROR)]);
$llamadas = 0;
$timeout = function () use ($db,&$llamadas): never {
    verificar_procesador_nubefact(!$db->inTransaction() && $db->doc['estado'] === 'procesando');
    $segundo = static fn () => throw new LogicException('Una segunda solicitud no debe tocar el transporte.');
    verificar_procesador_nubefact(nubefact_procesar($db,7,15,false,$segundo) === 'procesando');
    verificar_procesador_nubefact(nubefact_procesar($db,7,15,true,$segundo) === 'procesando');
    $llamadas++;
    throw new RuntimeException('Timeout de ensayo.');
};
verificar_procesador_nubefact(nubefact_procesar($db,7,15,false,$timeout) === 'incierto');
verificar_procesador_nubefact(nubefact_procesar($db,7,15,false,$timeout) === 'incierto' && $llamadas === 1);
$consultar = function (string $ruta, string $token, array $payload) use ($db): array {
    verificar_procesador_nubefact(!$db->inTransaction());
    verificar_procesador_nubefact($payload === ['operacion'=>'consultar_comprobante','tipo_de_comprobante'=>1,'serie'=>'F001','numero'=>1]);
    return ['tipo_de_comprobante'=>1,'serie'=>'F001','numero'=>1,'aceptada_por_sunat'=>true,
        'sunat_responsecode'=>'0','cadena_para_codigo_qr'=>'20100070970|01|F001|1|18.00|118.00'];
};
verificar_procesador_nubefact(nubefact_procesar($db,7,15,true,$consultar) === 'prueba_aceptada');
verificar_procesador_nubefact(nubefact_procesar($db,7,15,false,$timeout) === 'prueba_aceptada' && $llamadas === 1);
echo "OK: envío fuera de transacción, timeout incierto, reintento sin reenvío y consulta por la misma identidad. Sin BD ni red.\n";
