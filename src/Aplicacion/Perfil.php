<?php

declare(strict_types=1);

namespace TecControl\Aplicacion;

use DomainException;
use TecControl\Datos\Usuarios;

final readonly class Perfil
{
    public function __construct(private Usuarios $usuarios)
    {
    }

    public function actualizar(Usuario $usuario, array $datos): void
    {
        $this->usuarios->actualizarPerfil(
            $usuario->id,
            Entrada::texto($datos, 'nombre', 150, 'nombre'),
            Entrada::correo($datos),
        );
    }

    public function cambiarClave(Usuario $usuario, array $datos): void
    {
        $nueva = Entrada::claveNueva($datos['clave_nueva'] ?? null);
        if ($nueva !== ($datos['confirmacion'] ?? null)) {
            throw new DomainException('La confirmación de la contraseña no coincide.');
        }
        $fila = $this->usuarios->buscarCuentaActiva($usuario->id);
        $actual = $datos['clave_actual'] ?? null;
        if ($fila === null || !is_string($actual) || strlen($actual) > 72 || str_contains($actual, "\0")
            || !password_verify($actual, $fila['password_hash'])) {
            throw new DomainException('La contraseña actual es incorrecta.');
        }
        if ($nueva === $actual) {
            throw new DomainException('Elige una contraseña distinta de la actual.');
        }
        if (!$this->usuarios->actualizarHash($usuario->id, $fila['password_hash'], password_hash($nueva, PASSWORD_DEFAULT))) {
            throw new DomainException('La cuenta cambió. Vuelve a ingresar para continuar.');
        }
    }
}
