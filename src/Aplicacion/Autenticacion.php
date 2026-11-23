<?php

declare(strict_types=1);

namespace TecControl\Aplicacion;

use TecControl\Datos\Usuarios;

final readonly class Autenticacion
{
    private const HASH_DE_COMPROBACION = '$2y$10$Wrq8h72jmqmGtmOV5wkwW.oAwJe5gLNUtuqAeHBCDjrFrYEZjdqJy';

    public function __construct(private Usuarios $usuarios, private Sesion $sesion)
    {
    }

    public function iniciar(mixed $correo, mixed $clave): ?Usuario
    {
        if (!is_string($correo) || !is_string($clave) || $clave === ''
            || strlen($clave) > 72 || str_contains($clave, "\0")) {
            return null;
        }

        $correo = trim($correo);
        if (strlen($correo) > 254 || filter_var($correo, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        $credenciales = $this->usuarios->buscarCredenciales($correo);
        $hash = $credenciales['password_hash'] ?? self::HASH_DE_COMPROBACION;
        if (!password_verify($clave, $hash) || $credenciales === null) {
            return null;
        }

        $usuarioId = (string) $credenciales['id'];
        if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            $nuevoHash = password_hash($clave, PASSWORD_DEFAULT);
            if (!$this->usuarios->actualizarHash($usuarioId, $hash, $nuevoHash)) {
                return null;
            }
            $hash = $nuevoHash;
        }

        $usuario = $this->leerUsuario($usuarioId);
        if ($usuario === null || !hash_equals($usuario->huellaClave, hash('sha256', $hash))) {
            return null;
        }

        $this->sesion->iniciarPara($usuario->id, $usuario->huellaClave);
        return $usuario;
    }

    public function actual(): ?Usuario
    {
        $id = $this->sesion->idUsuario();
        if ($id === null) {
            return null;
        }

        $usuario = $this->leerUsuario($id);
        if ($usuario === null || !$this->sesion->coincideClave($usuario->huellaClave)) {
            $this->sesion->cerrar();
            return null;
        }

        $this->sesion->registrarActividad();

        return $usuario;
    }

    private function leerUsuario(string $id): ?Usuario
    {
        $fila = $this->usuarios->buscarCuentaActiva($id);
        if ($fila === null) {
            return null;
        }

        $rol = Rol::tryFrom($fila['rol']);
        $clienteId = $fila['cliente_id'] === null ? null : (string) $fila['cliente_id'];
        if ($rol === null || ($rol === Rol::Cliente) !== ($clienteId !== null)) {
            return null;
        }

        return new Usuario($id, $fila['nombre'], $rol, $clienteId, hash('sha256', $fila['password_hash']));
    }
}
