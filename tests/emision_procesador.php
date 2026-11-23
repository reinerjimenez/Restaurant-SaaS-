<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/emision_procesador.php';
function comprobar_procesador(bool $ok): void { if (!$ok) throw new RuntimeException('Falló contrato del procesador.'); }
final class ConsultaProcesador extends PDOStatement {
    public function __construct(private ConexionProcesador $db, private string $sql) {}
    public function execute(?array $params = null): bool {
        if (str_starts_with($this->sql, 'UPDATE emision_documentos')) {
            if (str_contains($this->sql, 'SET ticket=')) { $this->db->fila['estado']='pendiente_ticket'; $this->db->fila['ticket']=$params[0]; }
            elseif (str_contains($this->sql, "SET xml_firmado=")) {
                $this->db->fila['xml_firmado']=$params[0]; $this->db->fila['estado']='procesando';
            } elseif (str_contains($this->sql, "estado='incierto'")) $this->db->fila['estado']='incierto';
            elseif (str_contains($this->sql, 'SET resumen_identidad=')) { $this->db->fila['resumen_identidad']=$params[0]; $this->db->fila['resumen_xml_firmado']=$params[1]; }
            else $this->db->fila['estado']=$params[0];
        }
        return true;
    }
    public function fetch(int $mode=PDO::FETCH_DEFAULT, int $cursorOrientation=PDO::FETCH_ORI_NEXT, int $cursorOffset=0): mixed {
        return str_contains($this->sql, 'SELECT d.*') ? $this->db->fila : ['id'=>1];
    }
    public function fetchColumn(int $column=0): mixed { return 1; }
}
final class ConexionProcesador extends PDO {
    private bool $transaccion=false;
    public function __construct(public array $fila) {}
    public function beginTransaction(): bool { $this->transaccion=true; return true; }
    public function inTransaction(): bool { return $this->transaccion; }
    public function commit(): bool { $this->transaccion=false; return true; }
    public function rollBack(): bool { $this->transaccion=false; return true; }
    public function prepare(string $query,array $options=[]): PDOStatement|false { return new ConsultaProcesador($this,$query); }
}
$archivo=tempnam(sys_get_temp_dir(),'mesora_p12_prueba_');
file_put_contents($archivo,'ensayo no usado: XML persistido en el doble');
try {
    foreach (['SUNAT_PRODUCCION_AUTORIZADA_1'=>'1','SUNAT_1_P12_PATH'=>$archivo,'SUNAT_1_P12_PASSWORD'=>'ensayo','SUNAT_1_USUARIO'=>'20123456789ENSAYO','SUNAT_1_CLAVE'=>'ensayo'] as $k=>$v) putenv($k.'='.$v);
    $snapshot=['emisor'=>['ruc'=>'20100066603','razon_social'=>'ENSAYO','direccion'=>'Ensayo','ubigeo'=>'150101'],
        'tipo'=>'factura','serie'=>'F001','correlativo'=>1,'fecha'=>date('Y-m-d'),'moneda'=>'PEN','tasa_igv'=>'18.00',
        'cliente'=>['tipo_documento'=>'ruc','numero_documento'=>'20100066603','nombre'=>'ENSAYO'],
        'items'=>[['descripcion'=>'Ensayo','cantidad'=>1,'precio_unitario'=>'118.00','subtotal'=>'118.00']],'total'=>'118.00'];
    putenv('SUNAT_1_USUARIO=20100066603ENSAYO');
    $db=new ConexionProcesador(['estado'=>'pendiente','ruc_config'=>'20100066603','snapshot_json'=>json_encode($snapshot),'xml_firmado'=>sunat_generar_invoice($snapshot)]);
    $envios=0;
    $db->fila['snapshot_json'] = json_encode($snapshot + ['proveedor'=>'nubefact'], JSON_THROW_ON_ERROR);
    try {
        emision_procesar_real($db, 1, 1, false, function () use (&$envios) { $envios++; });
        throw new RuntimeException('El envío directo aceptó un documento de NubeFacT.');
    } catch (DomainException $e) {
        comprobar_procesador(str_contains($e->getMessage(), 'proveedor') && $envios === 0 && !$db->inTransaction());
    }
    $db->fila['snapshot_json'] = json_encode($snapshot, JSON_THROW_ON_ERROR);
    $r=emision_procesar_real($db,1,1,false,function()use(&$envios,$db){
        comprobar_procesador(!$db->inTransaction() && $db->fila['estado']==='procesando');
        $envios++; throw new RuntimeException('timeout de ensayo');
    });
    comprobar_procesador($r['estado']==='incierto' && $envios===1);
    try { emision_procesar_real($db,1,1,false,fn()=>throw new RuntimeException('No debe enviar')); throw new RuntimeException('Aceptó reenvío ciego'); }
    catch(DomainException $e) { comprobar_procesador($envios===1); }
    $db->fila['estado']='aceptado';
    $r=emision_procesar_real($db,1,1,false,fn()=>throw new RuntimeException('No debe enviar'));
    comprobar_procesador($r['estado']==='aceptado');
    $snapshot['tipo']='boleta'; $snapshot['serie']='B001';
    $db->fila=['estado'=>'pendiente','ruc_config'=>'20100066603','snapshot_json'=>json_encode($snapshot),'xml_firmado'=>sunat_generar_invoice($snapshot),
        'resumen_xml_firmado'=>sunat_generar_resumen($snapshot,'RC-'.date('Ymd').'-1',date('Y-m-d')),'resumen_identidad'=>null];
    $r=emision_procesar_real($db,1,2,false,function($consulta,$identidad)use($db){
        comprobar_procesador(!$consulta && $identidad==='20100066603-RC-'.date('Ymd').'-1' && !$db->inTransaction()
            && str_contains($db->fila['xml_firmado'],'<Invoice ') && str_contains($db->fila['resumen_xml_firmado'],'<SummaryDocuments '));
        return ['ticket'=>'TICKET-ENSAYO'];
    });
    comprobar_procesador($r['estado']==='pendiente_ticket' && $db->fila['ticket']==='TICKET-ENSAYO');
    $r=emision_procesar_real($db,1,2,true,fn()=>['codigo'=>'98','cdr'=>null]);
    comprobar_procesador($r['estado']==='pendiente_ticket');
    $r=emision_procesar_real($db,1,2,true,fn()=>['codigo'=>'99','cdr'=>null]);
    comprobar_procesador($r['estado']==='rechazado');
    putenv('SUNAT_PRODUCCION_AUTORIZADA_1=0');
    try { emision_procesar_real($db,1,1,true); throw new RuntimeException('Saltó autorización'); }
    catch(DomainException $e) { comprobar_procesador(str_contains($e->getMessage(),'autorizada')); }
    echo "OK: persistencia antes de transporte, transporte sin transacción, timeout incierto, bloqueo reenvío, idempotencia, ticket RC boleta y gate. Sin red ni BD.\n";
} finally { unlink($archivo); }
