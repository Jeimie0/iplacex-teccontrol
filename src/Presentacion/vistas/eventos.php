<?php

use function TecControl\Presentacion\escapar;
use function TecControl\Presentacion\fechaLocal;
?>
<?php if ($datos['historial'] === []): ?>
    <p class="tenue">Aún no hay cambios registrados.</p>
<?php else: ?>
    <ol class="eventos">
        <?php foreach ($datos['historial'] as $evento): ?>
            <?php $fecha = fechaLocal($evento['registrado_en']); ?>
            <li>
                <span class="estado" data-estado="<?= escapar($evento['codigo']) ?>"><?= escapar($evento['nombre']) ?></span>
                <time datetime="<?= escapar($fecha->format(DATE_ATOM)) ?>"><?= escapar($fecha->format('d-m-Y H:i')) ?></time>
            </li>
        <?php endforeach ?>
    </ol>
<?php endif ?>
