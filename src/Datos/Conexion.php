<?php

declare(strict_types=1);

namespace TecControl\Datos;

use PDO;
use RuntimeException;

function conectar(): PDO
{
    $host = getenv('DB_HOST');
    $puerto = getenv('DB_PORT');
    $base = getenv('DB_NAME');
    $usuario = getenv('DB_USER');
    $clave = getenv('DB_PASSWORD');

    if ($host === false || $puerto === false || $base === false || $usuario === false || $clave === false) {
        throw new RuntimeException('Faltan variables de conexión a MySQL.');
    }

    $dsn = "mysql:host={$host};port={$puerto};dbname={$base};charset=utf8mb4";

    return new PDO($dsn, $usuario, $clave, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+00:00'",
    ]);
}
