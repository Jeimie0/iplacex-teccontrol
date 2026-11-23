<?php

declare(strict_types=1);

use TecControl\Aplicacion\Sesion;
use TecControl\Presentacion\AplicacionWeb;

use function TecControl\Datos\conectar;
use function TecControl\Presentacion\errorHttp;

require __DIR__ . '/../src/inicio.php';

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; style-src 'self'; img-src 'self'; script-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");

if (!filter_var($_SERVER['HTTPS'] ?? '', FILTER_VALIDATE_BOOLEAN)) {
    errorHttp(400, 'Esta solicitud requiere una conexión HTTPS.');
    return;
}

try {
    (new AplicacionWeb(conectar(), new Sesion()))->atender(
        $_SERVER['REQUEST_URI'] ?? '/', $_SERVER['REQUEST_METHOD'] ?? 'GET', $_GET, $_POST,
    );
} catch (Throwable $error) {
    error_log('TecControl: ' . $error::class . ' en ' . basename($error->getFile()) . ':' . $error->getLine());
    errorHttp(500, 'El servicio no pudo completar la solicitud. Inténtalo de nuevo más tarde.');
}
