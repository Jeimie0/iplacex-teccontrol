<?php

use function TecControl\Presentacion\escapar;

$porEstado = array_column($datos['indicadores'], 'total', 'codigo');
$total = array_sum($porEstado);
$listas = (int) ($porEstado['lista'] ?? 0);
$entregadas = (int) ($porEstado['entregada'] ?? 0);
?>
<section aria-labelledby="titulo">
    <div class="titulo-pagina"><div><h1 id="titulo">Mis órdenes</h1><p class="tenue"><?= escapar($usuario->nombre) ?></p></div></div>
    <div class="indicadores" aria-label="Resumen de mis órdenes">
        <?php foreach (['Mis órdenes' => $total, 'En curso' => $total - $listas - $entregadas, 'Listas para retiro' => $listas, 'Entregadas' => $entregadas] as $etiqueta => $cantidad): ?>
            <div class="tarjeta indicador"><span><?= escapar($etiqueta) ?></span><strong><?= escapar($cantidad) ?></strong></div>
        <?php endforeach ?>
    </div>
    <section class="tarjeta separacion" aria-label="Filtros de órdenes">
        <?php $rutaBusqueda = '/mis-ordenes'; require __DIR__ . '/filtros.php'; ?>
    </section>
    <?php if ($datos['ordenes'] === []): ?>
        <p class="tarjeta">No hay órdenes que coincidan.</p>
    <?php else: ?>
        <ul class="ordenes">
            <?php foreach ($datos['ordenes'] as $orden): ?>
                <li class="tarjeta orden">
                    <div>
                        <a class="orden__folio" href="/ordenes/<?= escapar($orden['id']) ?>"><?= escapar($orden['folio']) ?></a>
                        <p class="orden__equipo"><?= escapar($orden['tipo'] . ' · ' . $orden['marca'] . ' ' . $orden['modelo']) ?></p>
                    </div>
                    <span class="estado" data-estado="<?= escapar($orden['estado_codigo']) ?>"><?= escapar($orden['estado'] ?? 'Sin estado') ?></span>
                </li>
            <?php endforeach ?>
        </ul>
    <?php endif ?>
</section>
