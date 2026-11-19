<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/emision_fiscal.php';
function fiscal_comprobar(bool $ok, string $caso): void { if (!$ok) throw new RuntimeException($caso); }
function fiscal_rechazar(callable $f): void {
    try { $f(); } catch (DomainException $e) { return; }
    throw new RuntimeException('Se aceptó una configuración inválida.');
}
$config = ['modo'=>'pruebas','ruc'=>'20100070970','razon_social'=>'Ensayo <texto>', 'direccion'=>'Dirección',
    'ubigeo'=>'150101','regimen'=>'RER','tasa_igv'=>'10.50','serie_boleta'=>'B001','serie_factura'=>'F001'];
fiscal_comprobar(emision_validar_config($config)['tasa_igv'] === '10.50', 'Debe conservar la tasa elegida, sin inferirla del RER.');
foreach ([['modo'=>'activado'], ['regimen'=>'NRUS'], ['serie_boleta'=>'B1'], ['serie_factura'=>'B001'],
    ['ruc'=>'no válido'], ['ubigeo'=>'15010'], ['tasa_igv'=>'100.01'], ['tasa_igv'=>'0'], ['razon_social'=>['x']]] as $cambio) {
    fiscal_rechazar(fn()=>emision_validar_config(array_replace($config,$cambio)));
}
final class ConsultaFiscalPrueba extends PDOStatement {
    public function __construct(private array $paso) {}
    public function execute(?array $params=null): bool {
        fiscal_comprobar($params === $this->paso['params'], 'Parámetros tenant incorrectos.'); return true;
    }
    public function fetchColumn(int $column=0): mixed { return $this->paso['columna'] ?? false; }
    public function fetch(int $mode=PDO::FETCH_DEFAULT, int $cursorOrientation=PDO::FETCH_ORI_NEXT, int $cursorOffset=0): mixed { return $this->paso['fila'] ?? false; }
}
final class ConexionFiscalPrueba extends PDO {
    public bool $transaccion=true;
    public function __construct(public array $pasos) {}
    public function query(string $query, ?int $fetchMode=null, mixed ...$fetchModeArgs): PDOStatement|false {
        fiscal_comprobar(str_contains($query,'information_schema.tables'), 'Solo se permite descubrir el esquema.');
        return $this->prepare($query);
    }
    public function prepare(string $query, array $options=[]): PDOStatement|false {
        $paso=array_shift($this->pasos);
        fiscal_comprobar($paso !== null && str_contains($query,$paso['sql']), 'Consulta fiscal inesperada.');
        return new ConsultaFiscalPrueba($paso);
    }
    public function inTransaction(): bool { return $this->transaccion; }
    public function beginTransaction(): bool { throw new RuntimeException('No debe iniciar otra transacción.'); }
    public function commit(): bool { throw new RuntimeException('No debe confirmar el cobro del llamador.'); }
    public function rollBack(): bool { throw new RuntimeException('No debe revertir la transacción del llamador.'); }
}
$sin_esquema=new ConexionFiscalPrueba([['sql'=>'information_schema.tables','columna'=>0]]);
fiscal_exigir_venta_modificable($sin_esquema,1,7);
$con_documento=new ConexionFiscalPrueba([['sql'=>'information_schema.tables','columna'=>2],
    ['sql'=>'restaurante_id = ? AND pedido_id = ?','params'=>[1,7],'columna'=>9]]);
fiscal_rechazar(fn()=>fiscal_exigir_venta_modificable($con_documento,1,7));
$cliente=['numero_documento'=>'12345678','nombre'=>'Ensayo','direccion'=>'','tipo_documento'=>'dni'];
$input=array_merge($cliente,['pedido_id'=>7,'tipo'=>'boleta']);
$pasos=[['sql'=>'restaurantes','params'=>[1],'columna'=>1],
    ['sql'=>'pedidos WHERE id = ? AND restaurante_id = ?','params'=>[7,1], 'fila'=>['id'=>7,'estado'=>'cobrado']],
    ['sql'=>'emision_config WHERE restaurante_id = ? FOR UPDATE','params'=>[1],'fila'=>$config],
    ['sql'=>'emision_documentos WHERE restaurante_id = ? AND pedido_id = ? AND ambiente = ?', 'params'=>[1,7,'pruebas'],
        'fila'=>['id'=>9,'tipo'=>'boleta','snapshot_json'=>json_encode(['cliente'=>$cliente])]]];
$idempotente=new ConexionFiscalPrueba($pasos);
fiscal_comprobar(emision_solicitar($idempotente,1,2,$input)===9 && !$idempotente->pasos, 'El reintento debe devolver el mismo documento sin cobro ni correlativo.');
$conflicto=new ConexionFiscalPrueba($pasos);
fiscal_rechazar(fn()=>emision_solicitar($conflicto,1,2,array_replace($input,['nombre'=>'Otro cliente'])));
$ajeno=new ConexionFiscalPrueba([['sql'=>'restaurantes','params'=>[1],'columna'=>1],
    ['sql'=>'pedidos WHERE id = ? AND restaurante_id = ?','params'=>[7,1],'fila'=>false]]);
fiscal_rechazar(fn()=>emision_solicitar($ajeno,1,2,$input));
$opciones=['private_key_bits'=>2048,'private_key_type'=>OPENSSL_KEYTYPE_RSA,
    'config'=>getenv('OPENSSL_CONF') ?: (PHP_OS_FAMILY === 'Windows' ? 'C:/xampp/apache/conf/openssl.cnf' : '/etc/ssl/openssl.cnf')];
$llave=openssl_pkey_new($opciones);
$csr=openssl_csr_new(['commonName'=>'ENSAYO','organizationalUnitName'=>'20100070970','countryName'=>'PE'],$llave,$opciones);
$cert=openssl_csr_sign($csr,null,$llave,1,$opciones);
if (!$cert || !openssl_pkcs12_export($cert,$p12,$llave,'ensayo')) throw new RuntimeException('No se pudo generar certificado de ensayo efímero.');
$snapshot=['emisor'=>['ruc'=>'20100070970','razon_social'=>'ENSAYO','direccion'=>'Dirección','ubigeo'=>'150101'],
    'tipo'=>'boleta','serie'=>'B001','correlativo'=>1,'fecha'=>'2026-11-19','moneda'=>'PEN','tasa_igv'=>'10.50',
    'cliente'=>$cliente,'items'=>[['descripcion'=>'Ensayo','cantidad'=>1,'precio_unitario'=>'110.50','subtotal'=>'110.50']], 'total'=>'110.50'];
$ensayo=emision_simular_documento($snapshot,$p12,'ensayo');
fiscal_comprobar($ensayo['estado']==='prueba_aceptada' && str_contains($ensayo['xml_firmado'],'SignatureValue')
    && str_contains($ensayo['cdr_xml'],'SIMULADO SIN VALIDEZ TRIBUTARIA') && $ensayo['zip_bytes']>0,
    'El ensayo debe firmar, empaquetar y conciliar una CDR solo simulada.');
fiscal_rechazar(fn()=>emision_simular_documento($snapshot,$p12,'clave incorrecta'));
echo "OK emisión fiscal: configuración, tasa explícita, producción bloqueada, esquema ausente, aislamiento e idempotencia con PDO simulado.\n";
