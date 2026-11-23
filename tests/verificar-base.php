<?php

declare(strict_types=1);

use function TecControl\Datos\conectar;

require __DIR__ . '/../src/Datos/Conexion.php';

function comprobar(bool $resultado, string $mensaje): void
{
    if (!$resultado) {
        throw new RuntimeException($mensaje);
    }

    echo 'OK ', $mensaje, PHP_EOL;
}

function comprobarErrorMysql(int $codigo, string $caso, callable $operacion): void
{
    try {
        $operacion();
    } catch (PDOException $error) {
        if ((int) ($error->errorInfo[1] ?? 0) !== $codigo) {
            throw $error;
        }

        echo "OK {$caso} (MySQL {$codigo})", PHP_EOL;
        return;
    }

    throw new RuntimeException("Se esperaba el error {$codigo} para {$caso}.");
}

try {
    $db = conectar();
    $version = (string) $db->query('SELECT VERSION()')->fetchColumn();
    comprobar(preg_match('/^8\.4\.\d+(?:[-.][a-z0-9.-]+)?$/iD', $version) === 1, "SELECT VERSION() = {$version}");
    comprobar(version_compare(PHP_VERSION, '8.3.0', '>='), 'PHP ' . PHP_VERSION . ' con PDO MySQL');
    comprobar((int) $db->query('SELECT @@SESSION.foreign_key_checks')->fetchColumn() === 1, 'Claves foráneas activas');

    $tablas = $db->query(<<<'SQL'
        SELECT TABLE_NAME, ENGINE, TABLE_COLLATION
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'
        ORDER BY TABLE_NAME
        SQL)->fetchAll();
    comprobar(count($tablas) >= 6, count($tablas) . ' tablas de aplicación');
    foreach ($tablas as $tabla) {
        comprobar(
            $tabla['ENGINE'] === 'InnoDB' && str_starts_with($tabla['TABLE_COLLATION'], 'utf8mb4_'),
            "{$tabla['TABLE_NAME']} | {$tabla['ENGINE']} | {$tabla['TABLE_COLLATION']}",
        );
    }

    $relaciones = $db->query(<<<'SQL'
        SELECT TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL
        ORDER BY TABLE_NAME, COLUMN_NAME
        SQL)->fetchAll(PDO::FETCH_NUM);
    comprobar(count($relaciones) >= 6, count($relaciones) . ' relaciones con clave foránea');
    foreach ($relaciones as [$tabla, $columna, $referencia, $clave]) {
        echo "  {$tabla}.{$columna} -> {$referencia}.{$clave}", PHP_EOL;
    }

    $nombreEstado = $db->query("SELECT nombre FROM estados_orden WHERE codigo = 'diagnostico'")->fetchColumn();
    comprobar($nombreEstado === 'En diagnóstico', 'Texto del catálogo conservado en UTF-8');

    $ordenes = $db->query(<<<'SQL'
        SELECT orden.folio, equipo.id AS equipo_id, equipo.cliente_id, cliente.nombre, estado.nombre AS estado
        FROM ordenes_servicio AS orden
        JOIN equipos AS equipo ON equipo.id = orden.equipo_id
        JOIN clientes AS cliente ON cliente.id = equipo.cliente_id
        LEFT JOIN historial_estados AS evento ON evento.id = (
            SELECT id FROM historial_estados WHERE orden_id = orden.id ORDER BY id DESC LIMIT 1
        )
        LEFT JOIN estados_orden AS estado ON estado.id = evento.estado_id
        WHERE orden.folio REGEXP '^DEMO-[0-9]{3}$'
        ORDER BY orden.folio
        SQL)->fetchAll();
    $clientesDemo = count(array_unique(array_column($ordenes, 'cliente_id')));
    comprobar(count($ordenes) >= 20 && $clientesDemo >= 2, count($ordenes) . " órdenes ficticias de {$clientesDemo} clientes");
    foreach ($ordenes as $orden) {
        if ($orden['estado'] === null) {
            throw new RuntimeException("Orden ficticia sin historial {$orden['folio']}.");
        }

        echo "  {$orden['folio']} | {$orden['nombre']} | {$orden['estado']}", PHP_EOL;
    }

    $cuentas = [
        ['admin@teccontrol.example', 'DemoTec-Admin-2026!', 'administrador'],
        ['cliente-a@teccontrol.example', 'DemoTec-Cliente-A-2026!', 'cliente'],
        ['cliente-b@teccontrol.example', 'DemoTec-Cliente-B-2026!', 'cliente'],
    ];
    $consultarCuenta = $db->prepare('SELECT password_hash, rol, activo FROM usuarios WHERE correo = ?');
    foreach ($cuentas as [$correo, $clave, $rol]) {
        $consultarCuenta->execute([$correo]);
        $cuenta = $consultarCuenta->fetch();
        comprobar(
            $cuenta !== false && $cuenta['rol'] === $rol && (int) $cuenta['activo'] === 1
                && password_verify($clave, $cuenta['password_hash']),
            "Cuenta ficticia {$correo} con rol {$rol} y hash verificado",
        );
    }

    $nombrePrueba = 'Verificación ' . bin2hex(random_bytes(6));
    $folioPrueba = 'PRUEBA-' . bin2hex(random_bytes(6));
    $buscarCliente = $db->prepare('SELECT COUNT(*) FROM clientes WHERE nombre = ?');
    $buscarEquipo = $db->prepare('SELECT COUNT(*) FROM equipos WHERE id = ?');
    $buscarOrden = $db->prepare('SELECT COUNT(*) FROM ordenes_servicio WHERE folio = ?');

    $db->beginTransaction();
    try {
        $crearCliente = $db->prepare('INSERT INTO clientes (nombre) VALUES (?)');
        $crearCliente->execute([$nombrePrueba]);
        $clienteId = $db->lastInsertId();
        $buscarCliente->execute([$nombrePrueba]);
        comprobar((int) $buscarCliente->fetchColumn() === 1, 'Inserción válida de cliente');

        $crearEquipo = $db->prepare("INSERT INTO equipos (cliente_id, tipo, marca, modelo) VALUES (?, 'Prueba', 'Demo', 'T1')");
        comprobarErrorMysql(1452, 'Rechazo de un cliente inexistente en equipos', fn () => $crearEquipo->execute([0]));
        $crearEquipo->execute([$clienteId]);
        $equipoId = $db->lastInsertId();

        comprobarErrorMysql(1062, 'Rechazo de código de estado duplicado', fn () => $db->exec(
            "INSERT INTO estados_orden (codigo, nombre) VALUES ('recibida', 'Prueba duplicada')",
        ));
        comprobarErrorMysql(1048, 'Rechazo de nombre obligatorio ausente', fn () => $crearCliente->execute([null]));

        $crearUsuario = $db->prepare(<<<'SQL'
            INSERT INTO usuarios (nombre, correo, password_hash, rol)
            VALUES ('Prueba de restricción', ?, ?, 'cliente')
            SQL);
        comprobarErrorMysql(3819, 'Rechazo de cuenta cliente sin titular', fn () => $crearUsuario->execute([
            $folioPrueba . '@teccontrol.example',
            password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
        ]));

        $crearOrden = $db->prepare("INSERT INTO ordenes_servicio (equipo_id, folio, falla_reportada) VALUES (?, ?, 'Prueba')");
        $crearOrden->execute([$equipoId, $folioPrueba]);
        $ordenId = $db->lastInsertId();
        $buscarOrden->execute([$folioPrueba]);
        comprobar((int) $buscarOrden->fetchColumn() === 1, 'Inserción válida de orden dentro de una transacción');

        $otroCliente = $ordenes[0]['cliente_id'];
        $cambiarCliente = $db->prepare('UPDATE equipos SET cliente_id = ? WHERE id = ?');
        comprobarErrorMysql(1644, 'Rechazo de reasignar el titular de un equipo', fn () => $cambiarCliente->execute([
            $otroCliente, $equipoId,
        ]));

        $cambiarEquipo = $db->prepare('UPDATE ordenes_servicio SET equipo_id = ? WHERE id = ?');
        comprobarErrorMysql(1644, 'Rechazo de reasignar el equipo de una orden', fn () => $cambiarEquipo->execute([
            $ordenes[0]['equipo_id'], $ordenId,
        ]));

        $registrarEstado = $db->prepare(<<<'SQL'
            INSERT INTO historial_estados (orden_id, estado_id, registrado_por)
            SELECT ?, 0, id FROM usuarios WHERE correo = 'admin@teccontrol.example'
            SQL);
        comprobarErrorMysql(1452, 'Rechazo del historial con estado inexistente', fn () => $registrarEstado->execute([$ordenId]));
    } finally {
        $db->rollBack();
    }

    $buscarOrden->execute([$folioPrueba]);
    $buscarEquipo->execute([$equipoId]);
    $buscarCliente->execute([$nombrePrueba]);
    comprobar(
        (int) $buscarOrden->fetchColumn() === 0 && (int) $buscarEquipo->fetchColumn() === 0
            && (int) $buscarCliente->fetchColumn() === 0,
        'Reversión de orden, equipo y cliente tras fallar el registro del estado',
    );
} catch (Throwable $error) {
    fwrite(STDERR, 'FALLO: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
