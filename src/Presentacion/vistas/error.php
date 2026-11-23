<?php

use function TecControl\Presentacion\escapar;
?>
<section class="tarjeta" aria-labelledby="titulo">
    <h1 id="titulo"><?= escapar($titulo) ?></h1>
    <p class="tenue"><?= escapar($datos['mensaje']) ?></p>
    <a class="volver" href="/">Volver al inicio</a>
</section>
