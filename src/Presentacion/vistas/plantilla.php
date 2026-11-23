<?php

declare(strict_types=1);

use TecControl\Aplicacion\Rol;
use function TecControl\Presentacion\escapar;

$usuario = $datos['usuario'] ?? null;
$administrador = $usuario?->rol === Rol::Administrador;
$seccion = $datos['modulo'] ?? ($pagina === 'administracion' ? 'panel' : $pagina);
$menu = $administrador
    ? ['panel' => ['/administracion', 'Panel'], 'clientes' => ['/administracion/clientes', 'Clientes'],
       'equipos' => ['/administracion/equipos', 'Equipos'], 'ordenes' => ['/administracion/ordenes', 'Órdenes'],
       'usuarios' => ['/administracion/usuarios', 'Usuarios'], 'perfil' => ['/perfil', 'Mi perfil']]
    : ['ordenes' => ['/mis-ordenes', 'Mis órdenes'], 'perfil' => ['/perfil', 'Mi perfil']];
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= escapar($titulo) ?> · TecControl</title>
    <link rel="stylesheet" href="/assets/teccontrol.css">
    <script src="/assets/teccontrol.js" defer></script>
</head>
<body class="<?= $administrador ? 'disposicion-admin' : '' ?>">
    <a class="saltar" href="#contenido">Saltar al contenido</a>
    <?php if ($administrador): ?>
        <aside class="lateral">
            <a class="marca" href="/administracion">TecControl</a>
            <nav class="lateral__nav" id="menu-principal" aria-label="Administración">
            <?php foreach ($menu as $clave => [$ruta, $etiqueta]): ?>
                <a href="<?= escapar($ruta) ?>"<?= $seccion === $clave ? ' aria-current="page"' : '' ?>><?= escapar($etiqueta) ?></a>
            <?php endforeach ?>
            </nav>
        </aside>
    <?php endif ?>
    <header class="cabecera">
        <div class="contenedor cabecera__contenido">
            <?php if ($administrador): ?>
                <button class="boton boton--contorno menu-movil" type="button" aria-controls="menu-principal" aria-expanded="false" hidden>Menú</button>
                <span class="cabecera__seccion">Administración</span>
            <?php else: ?>
                <a class="marca" href="<?= escapar($usuario?->inicio() ?? '/') ?>">TecControl</a>
                <?php if ($usuario !== null): ?>
                    <nav class="navegacion-cliente" aria-label="Portal">
                    <?php foreach ($menu as $clave => [$ruta, $etiqueta]): ?>
                        <a href="<?= escapar($ruta) ?>"<?= $seccion === $clave ? ' aria-current="page"' : '' ?>><?= escapar($etiqueta) ?></a>
                    <?php endforeach ?>
                    </nav>
                <?php endif ?>
            <?php endif ?>
            <?php if ($usuario !== null): ?>
                <form action="/salir" method="post">
                    <input type="hidden" name="_csrf" value="<?= escapar($datos['csrf']) ?>">
                    <button class="boton boton--secundario" type="submit">Cerrar sesión</button>
                </form>
            <?php endif ?>
        </div>
    </header>
    <main id="contenido" class="contenedor <?= $pagina === 'ingreso' ? 'contenedor--ingreso' : '' ?>">
        <?php if (isset($datos['aviso'])): ?><p class="mensaje mensaje--exito" role="status"><?= escapar($datos['aviso']) ?></p><?php endif ?>
        <?php require __DIR__ . '/' . $pagina . '.php'; ?>
    </main>
</body>
</html>
