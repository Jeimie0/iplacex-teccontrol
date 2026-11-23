<?php

use function TecControl\Presentacion\escapar;
?>
<a class="volver" href="/ordenes/<?= escapar($datos['orden']['id']) ?>">Volver a la orden</a>
<section class="tarjeta">
    <h1><?= escapar($titulo) ?></h1>
    <?php require __DIR__ . '/eventos.php'; ?>
</section>
