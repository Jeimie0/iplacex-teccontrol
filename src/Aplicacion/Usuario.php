<?php

declare(strict_types=1);

namespace TecControl\Aplicacion;

final readonly class Usuario
{
    public function __construct(
        public string $id,
        public string $nombre,
        public Rol $rol,
        public ?string $clienteId,
        public string $huellaClave,
    ) {
    }

    public function inicio(): string
    {
        return match ($this->rol) {
            Rol::Administrador => '/administracion',
            Rol::Cliente => '/mis-ordenes',
        };
    }
}
