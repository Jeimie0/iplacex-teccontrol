<?php

use function TecControl\Presentacion\escapar;
use function TecControl\Presentacion\fechaLocal;

$orden = $datos['orden'];
?>
<a class="volver" href="<?= escapar($usuario->inicio()) ?>">Volver a mi inicio</a>
<section class="tarjeta" aria-labelledby="titulo">
    <h1 id="titulo">Orden <?= escapar($orden['folio']) ?></h1>
    <dl class="detalle">
        <div><dt>Estado</dt><dd><span class="estado" data-estado="<?= escapar($orden['estado_codigo']) ?>"><?= escapar($orden['estado'] ?? 'Sin estado') ?></span></dd></div>
        <div><dt>Equipo</dt><dd><?= escapar($orden['tipo'] . ' · ' . $orden['marca'] . ' ' . $orden['modelo']) ?></dd></div>
        <div><dt>Ingreso</dt><dd><?= escapar(fechaLocal($orden['creada_en'])->format('d-m-Y H:i')) ?></dd></div>
        <div><dt>Falla reportada</dt><dd><?= escapar($orden['falla_reportada']) ?></dd></div>
        <?php if ($orden['diagnostico'] !== null): ?>
            <div><dt>Diagnóstico</dt><dd><?= escapar($orden['diagnostico']) ?></dd></div>
        <?php endif ?>
    </dl>
</section>
<section class="tarjeta separacion">
    <div class="titulo-pagina titulo-pagina--secundario">
        <h2>Historial de estados</h2>
        <a class="enlace-accion" href="/ordenes/<?= escapar($orden['id']) ?>/historial">Abrir historial</a>
    </div>
    <?php require __DIR__ . '/eventos.php'; ?>
</section>
