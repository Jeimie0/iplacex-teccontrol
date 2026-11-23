<?php

declare(strict_types=1);

namespace TecControl\Aplicacion;

use DomainException;
use TecControl\Datos\Catalogos;
use TecControl\Datos\Ordenes;
use TecControl\Datos\Usuarios;

final readonly class Gestion
{
    public function __construct(private Catalogos $catalogos, private Ordenes $ordenes, private Usuarios $usuarios)
    {
    }

    public function guardar(string $modulo, ?string $id, array $datos, Usuario $actor): ?string
    {
        if ($actor->rol !== Rol::Administrador) {
            throw new DomainException('Esta acción requiere una cuenta administrativa.');
        }

        return match ($modulo) {
            'clientes' => $this->guardarCliente($id, $datos),
            'equipos' => $this->guardarEquipo($id, $datos),
            'ordenes' => $this->guardarOrden($id, $datos, $actor),
            'usuarios' => $this->guardarUsuario($id, $datos, $actor),
        };
    }

    private function guardarCliente(?string $id, array $datos): ?string
    {
        $this->catalogos->guardar('clientes', $id, [
            'nombre' => Entrada::texto($datos, 'nombre', 150, 'nombre'),
            'correo_contacto' => Entrada::correo($datos, 'correo_contacto', true),
            'telefono' => Entrada::texto($datos, 'telefono', 30, 'teléfono', true),
        ]);
        return $id;
    }

    private function guardarEquipo(?string $id, array $datos): ?string
    {
        $this->catalogos->guardar('equipos', $id, [
            'cliente_id' => $id === null ? Entrada::identificador($datos, 'cliente_id', 'un cliente') : null,
            'tipo' => Entrada::texto($datos, 'tipo', 80, 'tipo'),
            'marca' => Entrada::texto($datos, 'marca', 80, 'marca'),
            'modelo' => Entrada::texto($datos, 'modelo', 100, 'modelo'),
            'numero_serie' => Entrada::texto($datos, 'numero_serie', 100, 'número de serie', true),
        ]);
        return $id;
    }

    private function guardarOrden(?string $id, array $datos, Usuario $actor): string
    {
        $falla = Entrada::texto($datos, 'falla_reportada', 4000, 'falla reportada');
        $diagnostico = Entrada::texto($datos, 'diagnostico', 4000, 'diagnóstico', true);
        if ($id === null) {
            $folio = Entrada::texto($datos, 'folio', 30, 'folio');
            if (preg_match('/^OT-[0-9]{6}-[A-F0-9]{10}$/D', $folio) !== 1) {
                throw new DomainException('Vuelve a abrir el formulario para generar un folio válido.');
            }
            return $this->ordenes->crear(Entrada::identificador($datos, 'equipo_id', 'un equipo'), $folio, $falla, $diagnostico, $actor->id);
        }

        $evento = $datos['evento_id'] ?? null;
        if ($evento !== '' && Entrada::id($evento) === null) {
            throw new DomainException('Recarga la ficha de la orden antes de guardar.');
        }
        $estado = Entrada::identificador($datos, 'estado_id', 'un estado');
        if (strlen($estado) > 5 || (int) $estado > 65535) {
            throw new DomainException('Selecciona un estado del catálogo.');
        }
        $this->ordenes->actualizar($id, $falla, $diagnostico, $estado, $evento, $actor->id);
        return $id;
    }

    private function guardarUsuario(?string $id, array $datos, Usuario $actor): ?string
    {
        $nombre = Entrada::texto($datos, 'nombre', 150, 'nombre');
        $correo = Entrada::correo($datos);
        if ($id === null) {
            $rol = is_string($datos['rol'] ?? null) ? Rol::tryFrom($datos['rol']) : null;
            if ($rol === null) {
                throw new DomainException('Selecciona un rol válido.');
            }
            $cliente = $rol === Rol::Cliente ? Entrada::identificador($datos, 'cliente_id', 'un cliente') : null;
            $this->usuarios->crear($nombre, $correo, password_hash(Entrada::claveNueva($datos['clave'] ?? null), PASSWORD_DEFAULT), $rol->value, $cliente);
            return null;
        }

        if ($id === $actor->id) {
            throw new DomainException('Edita tu propia cuenta desde Mi perfil.');
        }
        $activo = $datos['activo'] ?? null;
        if ($activo !== '0' && $activo !== '1') {
            throw new DomainException('Selecciona el estado de la cuenta.');
        }
        $clave = $datos['clave'] ?? '';
        $hash = $clave === '' ? null : password_hash(Entrada::claveNueva($clave), PASSWORD_DEFAULT);
        $this->usuarios->actualizarGestion($id, $nombre, $correo, $activo === '1', $hash);
        return $id;
    }
}
