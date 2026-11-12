<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../config/finanzas.php';

function comprobar_cobro(bool $condicion, string $mensaje): void {
    if (!$condicion) throw new RuntimeException($mensaje);
}

function rechazar_pago(callable $operacion): void {
    try {
        $operacion();
    } catch (DomainException $error) {
        return;
    }
    throw new RuntimeException('Se aceptó un pago inválido.');
}

final class SubtotalDeEnsayo extends PDOStatement {
    public function __construct(private string $subtotal) {}
    public function execute(?array $params = null): bool {
        comprobar_cobro($params === [20], 'Debe consultar los ítems del pedido.');
        return true;
    }
    public function fetchColumn(int $column = 0): mixed {
        return $this->subtotal;
    }
}

final class ConexionCobroDeEnsayo extends PDO {
    public function __construct(private string $subtotal) {}
    public function prepare(string $query, array $options = []): PDOStatement|false {
        comprobar_cobro(str_contains($query, 'SUM(subtotal)') && str_contains($query, 'pedido_items'),
            'El cobro debe consultar el subtotal persistido de los ítems.');
        return new SubtotalDeEnsayo($this->subtotal);
    }
}

$pago = ['metodo' => 'efectivo', 'monto' => '90.00'];
foreach (['-40.00', '0', '0.001', '100000000.00', ['90']] as $invalido) {
    rechazar_pago(fn() => normalizar_pagos([$pago, ['metodo' => 'otro', 'monto' => $invalido]]));
}
rechazar_pago(fn() => normalizar_pagos([['metodo' => 'desconocido', 'monto' => '90.00']]));
rechazar_pago(fn() => normalizar_pagos([['metodo' => 'otro']]));
rechazar_pago(fn() => validar_total_pagos(normalizar_pagos([$pago]), 8999));
rechazar_pago(fn() => validar_total_pagos(normalizar_pagos([$pago]), 9001));

$pedido = ['id' => 20, 'total' => '100.00', 'descuento' => '10.00', 'cargo_extra' => '0.00'];
foreach ([['100.00', '90.00'], ['100.00', '90.00'], ['120.05', '110.05']] as [$subtotal, $cobro]) {
    $pagos = normalizar_pagos([
        ['metodo' => 'efectivo', 'monto' => '60.00'],
        ['metodo' => 'otro', 'monto' => decimal_centimos(importe_centimos($cobro) - 6000)],
    ]);
    [$total] = calcular_cobro(new ConexionCobroDeEnsayo($subtotal), $pedido, [], $pagos);
    comprobar_cobro(decimal_centimos($total) === $cobro, 'Se reaplicó un ajuste sobre el total cobrado.');
    $pedido['total'] = $cobro;
}
$pagos = normalizar_pagos([['metodo' => 'tarjeta', 'monto' => '112.25']]);
comprobar_cobro(calcular_cobro(new ConexionCobroDeEnsayo('120.05'), $pedido, ['cargo_extra' => '2.20'], $pagos)
    === [11225, 1000, 220], 'El cargo debe aplicarse una vez sobre el subtotal.');
echo "OK H-021 y H-023: ciclos de cobro, ajustes y pagos mixtos o inválidos con PDO simulado.\n";
