<?php

use function TecControl\Presentacion\campo;
use function TecControl\Presentacion\escapar;
?>
<h1>Mi perfil</h1>
<?php if (isset($datos['error'])): ?><p class="mensaje" role="alert"><?= escapar($datos['error']) ?></p><?php endif ?>
<div class="perfil">
    <section class="tarjeta">
        <h2>Datos de la cuenta</h2>
        <form method="post" action="/perfil" class="formulario">
            <input type="hidden" name="_csrf" value="<?= escapar($datos['csrf']) ?>">
            <input type="hidden" name="accion" value="datos">
            <?php campo($datos['registro'], 'nombre', 'Nombre', 150); ?>
            <?php campo($datos['registro'], 'correo', 'Correo electrónico', 254, 'email'); ?>
            <button class="boton" type="submit">Guardar perfil</button>
        </form>
    </section>
    <section class="tarjeta">
        <h2>Cambiar contraseña</h2>
        <form method="post" action="/perfil" class="formulario">
            <input type="hidden" name="_csrf" value="<?= escapar($datos['csrf']) ?>">
            <input type="hidden" name="accion" value="contrasena">
            <?php campo([], 'clave_actual', 'Contraseña actual', 72, 'password'); ?>
            <?php campo([], 'clave_nueva', 'Nueva contraseña', 72, 'password'); ?>
            <?php campo([], 'confirmacion', 'Confirma la contraseña', 72, 'password'); ?>
            <p class="tenue">Usa entre 12 y 72 bytes. Al guardar, vuelve a ingresar con tu nueva contraseña.</p>
            <button class="boton" type="submit">Actualizar contraseña</button>
        </form>
    </section>
</div>
