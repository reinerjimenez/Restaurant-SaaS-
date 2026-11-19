<?php
// Ejecuta la validación real del endpoint con dobles; no conecta ninguna base.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

final class RespuestaFrontera extends RuntimeException {
    public function __construct(public bool $success, public string $mensaje) { parent::__construct($mensaje); }
}
function requireRole(array $roles): void {}
function jsonResponse(bool $success, mixed $data = null, string $message = ''): never {
    throw new RespuestaFrontera($success, $message);
}
function getDB(): never { throw new LogicException('Acceso a BD'); }

$codigo = file_get_contents(__DIR__ . '/../api/guardar_comprobante.php');
$codigo = preg_replace('/^require_once[^;]+;\s*$/m', '', $codigo);
$codigo = preg_replace('/^<\?php\s*/', '', $codigo);
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SESSION = ['restaurante_id' => 1, 'usuario_id' => 1];
foreach (['boleta', 'factura', 'simple', 'inventado'] as $tipo) {
    http_response_code(200);
    $GLOBALS['_RAW_INPUT'] = json_encode(['pedido_id' => 1, 'tipo' => $tipo]);
    try {
        eval($codigo);
        throw new RuntimeException('El endpoint continuó sin un resultado verificable.');
    } catch (RespuestaFrontera $r) {
        if ($tipo === 'simple' || $r->success) throw new RuntimeException('Resultado inesperado.');
        if (in_array($tipo, ['boleta', 'factura'], true)
            && (http_response_code() !== 409 || !str_contains($r->mensaje, 'no fue cobrado'))) {
            throw new RuntimeException('No se rechazó la emisión fiscal antes del cobro.');
        }
    } catch (LogicException $e) {
        if ($tipo !== 'simple' || $e->getMessage() !== 'Acceso a BD') throw $e;
    }
}
echo "OK: boleta/factura rechazadas antes de BD; simple conserva el flujo y tipo inválido rechazado.\n";
