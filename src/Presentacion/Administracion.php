<?php

declare(strict_types=1);

namespace TecControl\Presentacion;

use PDO;
use PDOException;
use DomainException;
use TecControl\Aplicacion\Entrada;
use TecControl\Aplicacion\Gestion;
use TecControl\Aplicacion\Sesion;
use TecControl\Aplicacion\Usuario;
use TecControl\Datos\Catalogos;
use TecControl\Datos\Ordenes;
use TecControl\Datos\Usuarios;

final readonly class Administracion
{
    private const TITULOS = [
        'clientes' => ['Clientes', 'cliente'],
        'equipos' => ['Equipos', 'equipo'],
        'ordenes' => ['Órdenes de servicio', 'orden'],
        'usuarios' => ['Usuarios', 'usuario'],
    ];

    private Catalogos $catalogos;
    private Ordenes $ordenes;
    private Usuarios $usuarios;

    public function __construct(PDO $db, private Sesion $sesion)
    {
        $this->catalogos = new Catalogos($db);
        $this->ordenes = new Ordenes($db);
        $this->usuarios = new Usuarios($db);
    }

    public function atender(string $modulo, string $ficha, bool $escritura, array $consulta, array $entrada, array $datos): void
    {
        if ($modulo === '') {
            $datos['indicadores'] = $this->catalogos->indicadores();
            $datos['estados'] = $this->ordenes->indicadores(null);
            $datos['ordenes'] = $this->ordenes->listarAdministracion('', '', 5);
            vista('administracion', 'Panel de administración', $datos);
            return;
        }

        $datos['modulo'] = $modulo;
        [$titulo, $singular] = self::TITULOS[$modulo];
        $ruta = '/administracion/' . $modulo;
        $datos['ruta'] = $ruta;
        $datos['singular'] = $singular;
        if ($ficha === '') {
            $busqueda = Entrada::busqueda($consulta['buscar'] ?? null);
            $filtro = Entrada::busqueda($consulta['filtro'] ?? null);
            $datos['busqueda'] = $busqueda;
            $datos['filtro'] = $filtro;
            $datos['opciones'] = match ($modulo) {
                'ordenes' => array_column($this->ordenes->estados(), 'nombre', 'codigo'),
                'equipos' => array_column($this->catalogos->clientes(), 'nombre', 'id'),
                'usuarios' => ['administrador' => 'Administrador', 'cliente' => 'Cliente'],
                default => [],
            };
            $datos['filas'] = match ($modulo) {
                'clientes' => $this->catalogos->clientes($busqueda),
                'equipos' => $this->catalogos->equipos($busqueda, Entrada::id($filtro)),
                'usuarios' => $this->usuarios->listar($busqueda, $filtro),
                'ordenes' => $this->ordenes->listarAdministracion($busqueda, $filtro),
            };
            vista('gestion-lista', $titulo, $datos);
            return;
        }

        $id = $ficha === 'nuevo' ? null : Entrada::id($ficha);
        $registro = $id === null ? [] : match ($modulo) {
            'ordenes' => $this->ordenes->buscarParaAdministracion($id),
            'usuarios' => $this->usuarios->buscar($id),
            default => $this->catalogos->buscar($modulo, $id),
        };
        if ($registro === null || ($ficha !== 'nuevo' && $id === null)) {
            errorHttp(404, 'No se encontró el registro solicitado.');
            return;
        }
        if ($modulo === 'usuarios' && $id === $datos['usuario']->id) {
            redirigir('/perfil');
        }

        if ($escritura) {
            try {
                $gestion = new Gestion($this->catalogos, $this->ordenes, $this->usuarios);
                $guardado = $gestion->guardar($modulo, $id, $entrada, $datos['usuario']);
                $this->sesion->confirmar($id === null ? 'Registro creado correctamente.' : 'Cambios guardados.');
                redirigir($modulo === 'ordenes' ? "{$ruta}/{$guardado}" : $ruta);
            } catch (DomainException $error) {
                $datos['error'] = $error->getMessage();
            } catch (PDOException $error) {
                $datos['error'] = errorDeDatos($error);
            }
            $valores = array_filter($entrada, 'is_string');
            unset($valores['clave'], $valores['_csrf']);
            $registro = array_replace($registro, $valores);
            http_response_code(422);
        }

        if ($modulo === 'ordenes' && $id === null && empty($registro['folio'])) {
            $registro['folio'] = 'OT-' . fechaLocal('now')->format('ymd') . '-' . strtoupper(bin2hex(random_bytes(5)));
        }
        $datos['registro'] = $registro;
        $datos['ficha'] = $ficha;
        $datos['nuevo'] = $id === null;
        $datos['clientes'] = in_array($modulo, ['equipos', 'usuarios'], true)
            ? array_column($this->catalogos->clientes(), 'nombre', 'id') : [];
        $datos['equipos'] = $modulo === 'ordenes' && $id === null
            ? $this->catalogos->equipos() : [];
        $datos['estados'] = $modulo === 'ordenes' && $id !== null
            ? array_column($this->ordenes->estados(), 'nombre', 'id') : [];
        $datos['historial'] = $modulo === 'ordenes' && $id !== null
            ? $this->ordenes->historial($id, null) : [];
        $accion = $id === null ? 'Registrar ' : 'Editar ';
        vista('gestion-ficha', $accion . $singular, $datos);
    }
}
