<?php
/**
 * Datos ficticios para pruebas HTTP de SUNAT. Ejecutar solo con DB_NAME=mesora_sunat_test.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli' || getenv('DB_NAME') !== 'mesora_sunat_test') {
    fwrite(STDERR, "Base de ensayo no autorizada.\n");
    exit(1);
}

$db = new PDO(
    'mysql:host=127.0.0.1;port=3306;dbname=mesora_sunat_test;charset=utf8mb4',
    getenv('DB_USER') ?: 'root',
    getenv('DB_PASS') ?: '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
     PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
     PDO::ATTR_EMULATE_PREPARES => false]
);
if ($db->query('SELECT DATABASE()')->fetchColumn() !== 'mesora_sunat_test') {
    throw new RuntimeException('La conexión no apunta a la base de ensayo.');
}
foreach (['restaurantes', 'emision_config', 'emision_documentos', 'pedidos', 'pedido_items', 'turnos'] as $tabla) {
    $st = $db->query('SELECT COUNT(*) FROM ' . $tabla);
    if ($st->fetchColumn() === false) throw new RuntimeException('Falta la tabla ' . $tabla);
}
if ((int)$db->query('SELECT COUNT(*) FROM restaurantes')->fetchColumn() !== 0) {
    throw new RuntimeException('La base de ensayo ya tiene restaurantes; no repetir el sembrado.');
}

$clave = 'SunatEnsayo-2026!';
$hash = password_hash($clave, PASSWORD_DEFAULT);
$fixture = ['clave' => $clave, 'base_url' => 'http://127.0.0.1:8100/MESORA',
    'cliente_ruc' => '20500000008'];

$db->beginTransaction();
try {
    foreach (['A' => ['ruc' => '20123456786', 'tasa' => '10.50'],
              'B' => ['ruc' => '20600000005', 'tasa' => '18.00']] as $nombre => $datos) {
        $db->prepare('INSERT INTO restaurantes (nombre, activo) VALUES (?, 1)')
            ->execute(['Restaurante ficticio ' . $nombre]);
        $restaurante = (int)$db->lastInsertId();
        $fixture[$nombre] = ['restaurante' => $restaurante, 'ruc' => $datos['ruc'],
            'tasa_igv' => $datos['tasa'], 'usuarios' => [], 'pedidos' => [], 'totales' => []];

        foreach (['admin', 'atencion', 'cocina'] as $rol) {
            $email = strtolower("sunat.$nombre.$rol@example.test");
            $db->prepare('INSERT INTO usuarios (restaurante_id,nombre,email,password,rol,activo)
                VALUES (?,?,?,?,?,1)')->execute([$restaurante, "Usuario $nombre $rol", $email, $hash, $rol]);
            $fixture[$nombre]['usuarios'][$rol] = ['id' => (int)$db->lastInsertId(), 'email' => $email];
        }
        $atencion = $fixture[$nombre]['usuarios']['atencion']['id'];
        $db->prepare('INSERT INTO turnos (restaurante_id,usuario_id) VALUES (?,?)')
            ->execute([$restaurante, $atencion]);
        $fixture[$nombre]['turno'] = (int)$db->lastInsertId();

        $db->prepare('INSERT INTO categorias (restaurante_id,nombre,activo) VALUES (?, ?, 1)')
            ->execute([$restaurante, 'Comidas de prueba']);
        $categoria = (int)$db->lastInsertId();
        $db->prepare('INSERT INTO productos (restaurante_id,categoria_id,nombre,precio,activo)
            VALUES (?,?,?,?,1)')->execute([$restaurante, $categoria, 'Producto de ensayo', '10.00']);
        $producto = (int)$db->lastInsertId();

        $db->prepare('INSERT INTO emision_config (restaurante_id,modo,ruc,razon_social,direccion,ubigeo,
            regimen,tasa_igv,serie_boleta,serie_factura) VALUES (?,\'pruebas\',?,?,?,\'150101\',\'RER\',?,\'B001\',\'F001\')')
            ->execute([$restaurante, $datos['ruc'], 'Empresa ficticia ' . $nombre,
                'Avenida de prueba 123, Lima', $datos['tasa']]);

        $nombres = $nombre === 'A' ? ['fiscal', 'simple', 'descuento', 'cargo'] : ['fiscal'];
        foreach ($nombres as $etiqueta) {
            $db->prepare('INSERT INTO pedidos (restaurante_id, usuario_id, tipo, estado, total)
                VALUES (?, ?, \'llevar\', \'activo\', 10.00)')->execute([$restaurante, $atencion]);
            $pedido = (int)$db->lastInsertId();
            $db->prepare('INSERT INTO pedido_items (pedido_id,producto_id,nombre_producto,cantidad,
                precio_unitario,subtotal) VALUES (?,?,?,1,10.00,10.00)')
                ->execute([$pedido, $producto, 'Producto de ensayo']);
            $fixture[$nombre]['pedidos'][$etiqueta] = $pedido;
            $fixture[$nombre]['totales'][$etiqueta] = '10.00';
        }
    }
    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    throw $e;
}

$ruta = __DIR__ . '/sunat_fixture.json';
file_put_contents($ruta, json_encode($fixture, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
echo "Datos ficticios creados en mesora_sunat_test: $ruta\n";
