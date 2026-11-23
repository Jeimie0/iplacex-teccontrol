<?php

use function TecControl\Presentacion\campo;
use function TecControl\Presentacion\escapar;
use function TecControl\Presentacion\selector;

$registro = $datos['registro'];
$nuevo = $datos['nuevo'];
$modulo = $datos['modulo'];
?>
<a class="volver" href="<?= escapar($datos['ruta']) ?>">Volver al listado</a>
<div class="titulo-pagina">
    <h1><?= escapar($titulo) ?></h1>
    <?php if ($modulo === 'ordenes' && !$nuevo): ?>
        <a class="boton boton--contorno" href="/ordenes/<?= escapar($datos['ficha']) ?>">Ver detalle</a>
    <?php endif ?>
</div>
<section class="tarjeta formulario-tarjeta">
    <?php if (isset($datos['error'])): ?><p class="mensaje" role="alert"><?= escapar($datos['error']) ?></p><?php endif ?>
    <form method="post" action="<?= escapar($datos['ruta']) ?>/<?= escapar($datos['ficha']) ?>" class="formulario">
        <input type="hidden" name="_csrf" value="<?= escapar($datos['csrf']) ?>">
        <?php if ($modulo === 'clientes'): ?>
            <?php campo($registro, 'nombre', 'Nombre', 150); ?>
            <?php campo($registro, 'correo_contacto', 'Correo de contacto', 254, 'email', false); ?>
            <?php campo($registro, 'telefono', 'Teléfono', 30, 'tel', false); ?>
        <?php elseif ($modulo === 'equipos'): ?>
            <?php if ($nuevo): ?>
                <?php selector('cliente_id', 'Cliente', $datos['clientes'], $registro['cliente_id'] ?? null); ?>
            <?php else: ?>
                <p class="lectura"><strong>Cliente</strong> <?= escapar($datos['clientes'][$registro['cliente_id']] ?? '—') ?></p>
            <?php endif ?>
            <div class="formulario__rejilla">
                <?php campo($registro, 'tipo', 'Tipo de equipo', 80); ?>
                <?php campo($registro, 'marca', 'Marca', 80); ?>
                <?php campo($registro, 'modelo', 'Modelo', 100); ?>
                <?php campo($registro, 'numero_serie', 'Número de serie', 100, 'text', false); ?>
            </div>
        <?php elseif ($modulo === 'ordenes'): ?>
            <?php if ($nuevo): ?>
                <div class="campo">
                    <label for="folio">Folio</label>
                    <input id="folio" name="folio" value="<?= escapar($registro['folio']) ?>" readonly>
                </div>
                <?php $equipos = []; foreach ($datos['equipos'] as $equipo) {
                    $equipos[$equipo['id']] = $equipo['cliente'] . ' · ' . $equipo['tipo'] . ' ' . $equipo['marca'] . ' ' . $equipo['modelo'];
                } ?>
                <?php selector('equipo_id', 'Equipo', $equipos, $registro['equipo_id'] ?? null); ?>
                <p class="lectura"><strong>Estado inicial</strong> Recibida</p>
            <?php else: ?>
                <p class="lectura"><strong>Folio</strong> <?= escapar($registro['folio']) ?></p>
                <p class="lectura"><strong>Equipo</strong> <?= escapar($registro['tipo'] . ' · ' . $registro['marca'] . ' ' . $registro['modelo']) ?></p>
                <input type="hidden" name="evento_id" value="<?= escapar($registro['evento_id']) ?>">
                <?php selector('estado_id', 'Estado', $datos['estados'], $registro['estado_id']); ?>
            <?php endif ?>
            <?php campo($registro, 'falla_reportada', 'Falla reportada', 4000, 'textarea'); ?>
            <?php campo($registro, 'diagnostico', 'Diagnóstico', 4000, 'textarea', false); ?>
        <?php else: ?>
            <?php campo($registro, 'nombre', 'Nombre', 150); ?>
            <?php campo($registro, 'correo', 'Correo electrónico', 254, 'email'); ?>
            <?php if ($nuevo): ?>
                <?php selector('rol', 'Rol', ['administrador' => 'Administrador', 'cliente' => 'Cliente'], $registro['rol'] ?? null); ?>
                <div data-campo-cliente>
                    <?php selector('cliente_id', 'Cliente asociado', $datos['clientes'], $registro['cliente_id'] ?? null, 'Selecciona un cliente', false); ?>
                </div>
            <?php else: ?>
                <p class="lectura"><strong>Rol</strong> <?= escapar(ucfirst($registro['rol'])) ?></p>
                <?php selector('activo', 'Estado de la cuenta', [1 => 'Activa', 0 => 'Inactiva'], $registro['activo']); ?>
            <?php endif ?>
            <?php campo([], 'clave', $nuevo ? 'Contraseña inicial' : 'Nueva contraseña', 72, 'password', $nuevo); ?>
            <p class="tenue"><?= $nuevo ? 'Usa una contraseña de al menos 12 bytes.' : 'Deja en blanco para conservar la contraseña actual.' ?></p>
        <?php endif ?>
        <div class="acciones">
            <button class="boton" type="submit"><?= $nuevo ? 'Guardar registro' : 'Guardar cambios' ?></button>
            <a class="enlace-accion" href="<?= escapar($datos['ruta']) ?>">Cancelar</a>
        </div>
    </form>
</section>
<?php if ($modulo === 'ordenes' && !$nuevo): ?>
    <section class="tarjeta separacion" aria-labelledby="historial"><h2 id="historial">Historial de estados</h2>
        <?php require __DIR__ . '/eventos.php'; ?>
    </section>
<?php endif ?>
