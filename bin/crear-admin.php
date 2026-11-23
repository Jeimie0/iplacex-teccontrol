<?php

declare(strict_types=1);

use TecControl\Aplicacion\Entrada;
use TecControl\Aplicacion\Rol;
use TecControl\Datos\Usuarios;
use function TecControl\Datos\conectar;

require __DIR__ . '/../src/inicio.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if ($argc !== 3) {
    fwrite(STDERR, "Uso: php bin/crear-admin.php <nombre> <correo>. Lee la contraseña por entrada estándar.\n");
    exit(2);
}

try {
    $entrada = fgets(STDIN);
    $clave = Entrada::claveNueva($entrada === false ? null : rtrim($entrada, "\r\n"));
    $nombre = Entrada::texto(['nombre' => $argv[1]], 'nombre', 150, 'nombre');
    $correo = Entrada::correo(['correo' => $argv[2]]);
    (new Usuarios(conectar()))->crear($nombre, $correo, password_hash($clave, PASSWORD_DEFAULT), Rol::Administrador->value, null);
    echo "Cuenta administrativa creada.\n";
} catch (DomainException $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
} catch (PDOException $error) {
    fwrite(STDERR, ((int) ($error->errorInfo[1] ?? 0) === 1062
        ? 'Ya existe una cuenta con ese correo.' : 'No se pudo crear la cuenta administrativa.') . PHP_EOL);
    exit(1);
}
