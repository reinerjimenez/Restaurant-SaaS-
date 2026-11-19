<?php
// Datos desechables para la prueba HTTP de aislamiento. Ejecutar solo por CLI.
if (PHP_SAPI !== 'cli' || ($argv[1] ?? '') !== '--sembrar') exit(1);
$nombre_base = 'mesora_aislamiento_test';
$db = new PDO('mysql:host=127.0.0.1;port=3306;dbname=' . $nombre_base . ';charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
if ($db->query('SELECT DATABASE()')->fetchColumn() !== $nombre_base) exit(1);
if ((int)$db->query('SELECT COUNT(*) FROM restaurantes')->fetchColumn() !== 0) exit(1);
$clave = password_hash('Test!aislamiento-2026', PASSWORD_BCRYPT);
$db->beginTransaction();
try {
    $db->prepare("UPDATE usuarios SET email = ?, password = ? WHERE rol = 'superadmin'")
        ->execute(['super@aislamiento.test', $clave]);
    $ids = [];
    foreach (['A', 'B'] as $letra) {
        $db->prepare('INSERT INTO restaurantes (nombre) VALUES (?)')->execute(["Restaurante $letra — TEST"]);
        $restaurante = (int)$db->lastInsertId();
        $ids[$letra] = ['restaurante' => $restaurante, 'usuarios' => [], 'mesas' => [], 'productos' => [], 'opciones' => []];
        foreach (['admin', 'encargado', 'atencion', 'cocina'] as $rol) {
            $db->prepare('INSERT INTO usuarios (restaurante_id, nombre, email, password, rol) VALUES (?, ?, ?, ?, ?)')
                ->execute([$restaurante, "$rol $letra TEST", "$rol.$letra@aislamiento.test", $clave, $rol]);
            $ids[$letra]['usuarios'][$rol] = (int)$db->lastInsertId();
        }
        foreach ([1, 2] as $numero) {
            $db->prepare('INSERT INTO mesas (restaurante_id, numero) VALUES (?, ?)')->execute([$restaurante, $numero]);
            $ids[$letra]['mesas'][] = (int)$db->lastInsertId();
        }
        $db->prepare('INSERT INTO categorias (restaurante_id, nombre) VALUES (?, ?)')->execute([$restaurante, "Categoría $letra TEST"]);
        $categoria = (int)$db->lastInsertId();
        $ids[$letra]['categoria'] = $categoria;
        foreach ([1, 2] as $numero) {
            $db->prepare('INSERT INTO productos (restaurante_id, categoria_id, nombre, precio, tiene_opciones) VALUES (?, ?, ?, ?, 1)')
                ->execute([$restaurante, $categoria, "Producto $letra $numero TEST", '10.00']);
            $producto = (int)$db->lastInsertId();
            $ids[$letra]['productos'][] = $producto;
            $db->prepare('INSERT INTO opciones_grupo (producto_id, nombre) VALUES (?, ?)')->execute([$producto, "Grupo $letra $numero TEST"]);
            $grupo = (int)$db->lastInsertId();
            $db->prepare('INSERT INTO opciones_valor (grupo_id, valor) VALUES (?, ?)')->execute([$grupo, "Valor $letra $numero TEST"]);
            $ids[$letra]['opciones'][] = ['grupo' => $grupo, 'valor' => (int)$db->lastInsertId()];
        }
        $db->prepare('INSERT INTO turnos (restaurante_id, usuario_id, inicio) VALUES (?, ?, NOW())')
            ->execute([$restaurante, $ids[$letra]['usuarios']['admin']]);
        $ids[$letra]['turno'] = (int)$db->lastInsertId();
    }
    $db->commit();
    echo json_encode($ids, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;
} catch (Throwable $error) {
    if ($db->inTransaction()) $db->rollBack();
    throw $error;
}
