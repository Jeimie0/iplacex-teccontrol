<?php

use function TecControl\Presentacion\escapar;
use function TecControl\Presentacion\selector;
?>
<form class="filtros" method="get" action="<?= escapar($rutaBusqueda) ?>">
    <div class="campo">
        <label for="buscar">Buscar</label>
        <input id="buscar" name="buscar" type="search" maxlength="100" value="<?= escapar($datos['busqueda']) ?>">
    </div>
    <?php if ($datos['opciones'] !== []): ?>
        <?php selector('filtro', 'Filtrar por', $datos['opciones'], $datos['filtro'], 'Todos', false); ?>
    <?php endif ?>
    <button class="boton boton--contorno" type="submit">Aplicar</button>
    <?php if ($datos['busqueda'] !== '' || $datos['filtro'] !== ''): ?>
        <a class="enlace-accion" href="<?= escapar($rutaBusqueda) ?>">Limpiar</a>
    <?php endif ?>
</form>
