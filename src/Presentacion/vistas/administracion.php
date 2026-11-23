<?php

use function TecControl\Presentacion\escapar;

$indicadores = [
    'ordenes' => ['Órdenes de servicio', '/administracion/ordenes'],
    'clientes' => ['Clientes registrados', '/administracion/clientes'],
    'equipos' => ['Equipos registrados', '/administracion/equipos'],
    'usuarios' => ['Cuentas activas', '/administracion/usuarios'],
];
?>
<div class="titulo-pagina">
    <div>
        <h1>Panel de administración</h1>
        <p class="tenue">Sesión iniciada como <?= escapar($usuario->nombre) ?>.</p>
    </div>
    <a class="boton" href="/administracion/ordenes/nuevo">Nueva orden</a>
</div>
<section class="indicadores" aria-label="Indicadores">
    <?php foreach ($indicadores as $clave => [$etiqueta, $ruta]): ?>
        <a class="tarjeta indicador" href="<?= escapar($ruta) ?>"><span><?= escapar($etiqueta) ?></span><strong><?= escapar($datos['indicadores'][$clave]) ?></strong></a>
    <?php endforeach ?>
</section>
<div class="tablero">
    <section class="tarjeta">
        <div class="titulo-pagina titulo-pagina--secundario"><h2>Órdenes recientes</h2><a class="enlace-accion" href="/administracion/ordenes">Ver todas</a></div>
        <?php if ($datos['ordenes'] === []): ?><p class="tenue">Aún no hay órdenes registradas.</p><?php endif ?>
        <ul class="orden-reciente">
            <?php foreach ($datos['ordenes'] as $orden): ?>
                <li><div><a class="enlace-accion" href="/administracion/ordenes/<?= escapar($orden['id']) ?>"><?= escapar($orden['folio']) ?></a><p class="tenue"><?= escapar($orden['cliente']) ?> · <?= escapar($orden['modelo']) ?></p></div><span class="estado" data-estado="<?= escapar($orden['estado_codigo']) ?>"><?= escapar($orden['estado'] ?? 'Sin estado') ?></span></li>
            <?php endforeach ?>
        </ul>
    </section>
    <section class="tarjeta">
        <h2>Órdenes por estado</h2>
        <?php if ($datos['estados'] === []): ?><p class="tenue">Aún no hay órdenes registradas.</p><?php endif ?>
        <ul class="distribucion">
            <?php foreach ($datos['estados'] as $estado): ?>
                <li><div><span><?= escapar($estado['nombre'] ?? 'Sin estado') ?></span><strong><?= escapar($estado['total']) ?></strong></div>
                    <progress value="<?= escapar($estado['total']) ?>" max="<?= max(1, (int) $datos['indicadores']['ordenes']) ?>" aria-label="<?= escapar($estado['nombre'] ?? 'Sin estado') ?>"></progress>
                </li>
            <?php endforeach ?>
        </ul>
    </section>
</div>
