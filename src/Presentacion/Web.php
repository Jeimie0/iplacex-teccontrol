<?php

declare(strict_types=1);

namespace TecControl\Presentacion;

function escapar(string|int|null $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function fechaLocal(string $fecha): \DateTimeImmutable
{
    return (new \DateTimeImmutable($fecha, new \DateTimeZone('UTC')))
        ->setTimezone(new \DateTimeZone('America/Santiago'));
}

function vista(string $pagina, string $titulo, array $datos = []): void
{
    require __DIR__ . '/vistas/plantilla.php';
}

function errorHttp(int $estado, string $mensaje): void
{
    http_response_code($estado);
    $titulo = match ($estado) {
        403 => 'Acceso denegado',
        404 => 'Contenido no disponible',
        default => 'No se pudo completar la solicitud',
    };
    vista('error', $titulo, ['mensaje' => $mensaje]);
}

function redirigir(string $ruta): never
{
    header('Location: ' . $ruta, true, 303);
    exit;
}

function errorDeDatos(\PDOException $error): string
{
    return match ((int) ($error->errorInfo[1] ?? 0)) {
        1062 => 'Ya existe un registro con ese correo o folio.',
        1452 => 'El registro seleccionado ya no está disponible. Revisa los datos.',
        1205, 1213 => 'Otro usuario está modificando el registro. Vuelve a intentar.',
        default => throw $error,
    };
}

function campo(array $registro, string $nombre, string $etiqueta, int $maximo, string $tipo = 'text', bool $obligatorio = true): void
{
    $requerido = $obligatorio ? ' required' : '';
    $valor = $tipo === 'password' ? '' : escapar($registro[$nombre] ?? '');
    $autocompletar = match ($nombre) {
        'clave_actual' => ' autocomplete="current-password"',
        'clave', 'clave_nueva', 'confirmacion' => ' autocomplete="new-password"',
        default => '',
    };
    ?>
    <div class="campo">
        <label for="<?= escapar($nombre) ?>"><?= escapar($etiqueta) ?></label>
        <?php if ($tipo === 'textarea'): ?>
            <textarea id="<?= escapar($nombre) ?>" name="<?= escapar($nombre) ?>" rows="4" maxlength="<?= $maximo ?>"<?= $requerido ?>><?= $valor ?></textarea>
        <?php else: ?>
            <input id="<?= escapar($nombre) ?>" name="<?= escapar($nombre) ?>" type="<?= escapar($tipo) ?>" value="<?= $valor ?>" maxlength="<?= $maximo ?>"<?= $requerido . $autocompletar ?>>
        <?php endif ?>
    </div>
    <?php
}

function selector(string $nombre, string $etiqueta, array $opciones, string|int|null $valor, string $inicial = 'Selecciona', bool $obligatorio = true): void
{
    ?>
    <div class="campo">
        <label for="<?= escapar($nombre) ?>"><?= escapar($etiqueta) ?></label>
        <select id="<?= escapar($nombre) ?>" name="<?= escapar($nombre) ?>"<?= $obligatorio ? ' required' : '' ?>>
            <option value=""><?= escapar($inicial) ?></option>
            <?php foreach ($opciones as $clave => $texto): ?>
                <option value="<?= escapar($clave) ?>"<?= (string) $clave === (string) $valor ? ' selected' : '' ?>><?= escapar($texto) ?></option>
            <?php endforeach ?>
        </select>
    </div>
    <?php
}
