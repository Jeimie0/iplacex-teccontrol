<?php

use function TecControl\Presentacion\escapar;
?>
<section class="tarjeta acceso" aria-labelledby="titulo">
    <h1 id="titulo">Iniciar sesión</h1>
    <p class="tenue">Ingresa con el correo asociado a tu cuenta.</p>
    <?php if (isset($datos['error'])): ?>
        <p class="mensaje" role="alert"><?= escapar($datos['error']) ?></p>
    <?php endif ?>
    <form method="post" action="/ingresar" class="formulario">
        <input type="hidden" name="_csrf" value="<?= escapar($datos['csrf']) ?>">
        <div class="campo">
            <label for="correo">Correo electrónico</label>
            <input id="correo" name="correo" type="email" autocomplete="username" maxlength="254" value="<?= escapar($datos['correo'] ?? '') ?>" required>
        </div>
        <div class="campo">
            <label for="clave">Contraseña</label>
            <input id="clave" name="clave" type="password" autocomplete="current-password" required>
        </div>
        <button class="boton" type="submit">Ingresar</button>
    </form>
</section>
