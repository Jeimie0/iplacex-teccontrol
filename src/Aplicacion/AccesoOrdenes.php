<?php

declare(strict_types=1);

namespace TecControl\Aplicacion;

use TecControl\Datos\Ordenes;

final readonly class AccesoOrdenes
{
    public function __construct(private Ordenes $ordenes)
    {
    }

    public function listarPropias(Usuario $usuario, string $busqueda = '', string $estado = ''): array
    {
        if ($usuario->rol !== Rol::Cliente || $usuario->clienteId === null) {
            return [];
        }

        return $this->ordenes->listarDeCliente($usuario->clienteId, $busqueda, $estado);
    }

    public function buscarDetalle(Usuario $usuario, string $ordenId): ?array
    {
        if ($usuario->rol === Rol::Administrador) {
            return $this->ordenes->buscarParaAdministracion($ordenId);
        }

        if ($usuario->clienteId === null) {
            return null;
        }

        return $this->ordenes->buscarDeCliente($ordenId, $usuario->clienteId);
    }

    public function historial(Usuario $usuario, string $ordenId): array
    {
        if ($usuario->rol === Rol::Administrador) {
            return $this->ordenes->historial($ordenId, null);
        }

        return $usuario->clienteId === null ? [] : $this->ordenes->historial($ordenId, $usuario->clienteId);
    }

    public function indicadores(Usuario $usuario): array
    {
        return $usuario->rol === Rol::Cliente && $usuario->clienteId !== null
            ? $this->ordenes->indicadores($usuario->clienteId)
            : [];
    }
}
