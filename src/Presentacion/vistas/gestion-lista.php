<?php

use function TecControl\Presentacion\escapar;
use function TecControl\Presentacion\fechaLocal;

$modulo = $datos['modulo'];
$columnas = match ($modulo) {
    'clientes' => ['nombre' => 'Nombre', 'correo_contacto' => 'Correo de contacto', 'telefono' => 'Teléfono'],
    'equipos' => ['tipo' => 'Tipo', 'marca' => 'Marca', 'modelo' => 'Modelo', 'numero_serie' => 'Serie', 'cliente' => 'Cliente'],
    'ordenes' => ['folio' => 'Folio', 'cliente' => 'Cliente', 'modelo' => 'Equipo', 'estado' => 'Estado', 'creada_en' => 'Ingreso'],
    'usuarios' => ['nombre' => 'Nombre', 'correo' => 'Correo', 'rol' => 'Rol', 'cliente' => 'Cliente', 'activo' => 'Acceso'],
};
?>
<div class="titulo-pagina">
    <h1><?= escapar($titulo) ?></h1>
    <a class="boton" href="<?= escapar($datos['ruta']) ?>/nuevo">
        <?= $modulo === 'ordenes' ? 'Nueva orden' : 'Nuevo ' . escapar($datos['singular']) ?>
    </a>
</div>
<section class="tarjeta" aria-label="Resultados">
    <?php $rutaBusqueda = $datos['ruta']; require __DIR__ . '/filtros.php'; ?>
    <?php if ($datos['filas'] === []): ?>
        <p class="vacio">No hay registros que coincidan.</p>
    <?php else: ?>
        <div class="tabla-scroll" role="region" aria-label="<?= escapar($titulo) ?>" tabindex="0">
            <table role="table">
                <thead role="rowgroup"><tr role="row"><?php foreach ($columnas as $columna): ?><th role="columnheader" scope="col"><?= escapar($columna) ?></th><?php endforeach ?><th role="columnheader" scope="col">Acción</th></tr></thead>
                <tbody role="rowgroup">
                <?php foreach ($datos['filas'] as $fila): ?>
                    <tr role="row">
                        <?php foreach ($columnas as $campo => $columna): ?>
                            <td role="cell">
                                <span class="etiqueta-tabla" aria-hidden="true"><?= escapar($columna) ?></span>
                                <?php if ($campo === 'estado'): ?>
                                    <span class="estado" data-estado="<?= escapar($fila['estado_codigo']) ?>"><?= escapar($fila[$campo] ?? 'Sin estado') ?></span>
                                <?php elseif ($campo === 'activo'): ?>
                                    <span class="estado" data-estado="<?= (int) $fila[$campo] === 1 ? 'lista' : 'recibida' ?>"><?= (int) $fila[$campo] === 1 ? 'Activo' : 'Inactivo' ?></span>
                                <?php elseif ($campo === 'creada_en'): ?>
                                    <?= escapar(fechaLocal($fila[$campo])->format('d-m-Y H:i')) ?>
                                <?php else: ?>
                                    <?= escapar($fila[$campo] ?? '—') ?>
                                <?php endif ?>
                            </td>
                        <?php endforeach ?>
                        <td role="cell"><span class="etiqueta-tabla" aria-hidden="true">Acción</span><a class="enlace-accion" href="<?= escapar($datos['ruta']) ?>/<?= escapar($fila['id']) ?>">Editar<span class="solo-lectores"> <?= escapar($fila['folio'] ?? $fila['nombre'] ?? $fila['modelo']) ?></span></a></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php endif ?>
</section>
