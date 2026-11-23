<?php

declare(strict_types=1);

namespace TecControl\Presentacion;

use DomainException;
use PDO;
use PDOException;
use TecControl\Aplicacion\AccesoOrdenes;
use TecControl\Aplicacion\Autenticacion;
use TecControl\Aplicacion\Entrada;
use TecControl\Aplicacion\Perfil;
use TecControl\Aplicacion\Rol;
use TecControl\Aplicacion\Sesion;
use TecControl\Datos\Ordenes;
use TecControl\Datos\Usuarios;

final readonly class AplicacionWeb
{
    private Usuarios $usuarios;
    private Ordenes $ordenes;

    public function __construct(private PDO $db, private Sesion $sesion)
    {
        $this->usuarios = new Usuarios($db);
        $this->ordenes = new Ordenes($db);
    }

    public function atender(string $uri, string $metodo, array $consulta, array $entrada): void
    {
        $ruta = parse_url($uri, PHP_URL_PATH);
        if (!is_string($ruta)) {
            errorHttp(404, 'No se encontró la página solicitada.');
            return;
        }
        $administracion = preg_match(
            '#^/administracion(?:/(clientes|equipos|ordenes|usuarios)(?:/(nuevo|[1-9][0-9]{0,19}))?)?$#D', $ruta, $admin,
        ) === 1;
        $ordenId = preg_match('#^/ordenes/([1-9][0-9]{0,19})(/historial)?$#D', $ruta, $orden) === 1
            ? Entrada::id($orden[1]) : null;
        $ingreso = $ruta === '/' || $ruta === '/ingresar';
        $salida = $ruta === '/salir';
        $escritura = $metodo === 'POST';
        if (!$administracion && !$ingreso && !$salida && $ordenId === null && !in_array($ruta, ['/perfil', '/mis-ordenes'], true)) {
            errorHttp(404, 'No se encontró la página solicitada.');
            return;
        }
        $metodos = match (true) {
            $salida => ['POST'],
            $ruta === '/ingresar', $ruta === '/perfil', isset($admin[2]) => ['GET', 'HEAD', 'POST'],
            default => ['GET', 'HEAD'],
        };
        if (!in_array($metodo, $metodos, true)) {
            header('Allow: ' . implode(', ', $metodos));
            errorHttp(405, 'Este método no está disponible para la página solicitada.');
            return;
        }

        $this->sesion->abrir();
        $autenticacion = new Autenticacion($this->usuarios, $this->sesion);
        $usuario = $autenticacion->actual();
        if ($usuario === null && !$ingreso) {
            redirigir('/ingresar');
        }
        $rolRequerido = match (true) {
            $administracion => Rol::Administrador,
            $ruta === '/mis-ordenes' => Rol::Cliente,
            default => null,
        };
        if ($rolRequerido !== null && $usuario->rol !== $rolRequerido) {
            errorHttp(403, 'Tu cuenta no tiene permiso para acceder a esta página.');
            return;
        }
        if ($escritura && !$this->sesion->validarCsrf($entrada['_csrf'] ?? null)) {
            errorHttp(403, 'El formulario venció. Vuelve a abrir la página e inténtalo de nuevo.');
            return;
        }

        if ($ingreso) {
            if ($usuario !== null) {
                redirigir($usuario->inicio());
            }

            $datos = ['csrf' => $this->sesion->tokenCsrf(), 'aviso' => $this->sesion->retirarAviso()];
            if ($escritura) {
                $credencial = $entrada['correo'] ?? null;
                $cuenta = $autenticacion->iniciar($credencial, $entrada['clave'] ?? null);
                if ($cuenta !== null) {
                    redirigir($cuenta->inicio());
                }
                http_response_code(422);
                $datos['error'] = 'El correo o la contraseña son incorrectos.';
                $datos['correo'] = is_string($credencial) ? substr($credencial, 0, 254) : '';
            }
            vista('ingreso', 'Iniciar sesión', $datos);
            return;
        }
        if ($salida) {
            $this->sesion->cerrar();
            redirigir('/ingresar');
        }

        $datos = [
            'usuario' => $usuario,
            'csrf' => $this->sesion->tokenCsrf(),
            'aviso' => $this->sesion->retirarAviso(),
        ];
        if ($administracion) {
            (new Administracion($this->db, $this->sesion))->atender(
                $admin[1] ?? '', $admin[2] ?? '', $escritura, $consulta, $entrada, $datos,
            );
            return;
        }
        if ($ruta === '/perfil') {
            $this->perfil($escritura, $entrada, $datos);
            return;
        }

        $acceso = new AccesoOrdenes($this->ordenes);
        if ($ruta === '/mis-ordenes') {
            $busqueda = Entrada::busqueda($consulta['buscar'] ?? null);
            $filtro = Entrada::busqueda($consulta['filtro'] ?? null);
            $datos['busqueda'] = $busqueda;
            $datos['filtro'] = $filtro;
            $datos['opciones'] = array_column($this->ordenes->estados(), 'nombre', 'codigo');
            $datos['indicadores'] = $acceso->indicadores($usuario);
            $datos['ordenes'] = $acceso->listarPropias($usuario, $busqueda, $filtro);
            vista('ordenes', 'Mis órdenes', $datos);
            return;
        }

        $detalle = $acceso->buscarDetalle($usuario, $ordenId);
        if ($detalle === null) {
            errorHttp(404, 'No se encontró la orden solicitada.');
            return;
        }
        $datos['orden'] = $detalle;
        $datos['historial'] = $acceso->historial($usuario, $ordenId);
        $pagina = isset($orden[2]) ? 'historial' : 'detalle';
        $titulo = $pagina === 'historial' ? 'Historial de ' . $detalle['folio'] : 'Orden ' . $detalle['folio'];
        vista($pagina, $titulo, $datos);
    }

    private function perfil(bool $escritura, array $entrada, array $datos): void
    {
        $usuario = $datos['usuario'];
        $registro = $this->usuarios->buscar($usuario->id);
        if ($registro === null) {
            redirigir('/ingresar');
        }
        if ($escritura) {
            try {
                $perfil = new Perfil($this->usuarios);
                if (($entrada['accion'] ?? null) === 'contrasena') {
                    $perfil->cambiarClave($usuario, $entrada);
                    $this->sesion->cerrar();
                    $this->sesion->abrir();
                    $this->sesion->confirmar('Contraseña actualizada. Ingresa con tu nueva contraseña.');
                    redirigir('/ingresar');
                }
                if (($entrada['accion'] ?? null) !== 'datos') {
                    throw new DomainException('Selecciona una acción de perfil válida.');
                }
                $perfil->actualizar($usuario, $entrada);
                $this->sesion->confirmar('Perfil actualizado.');
                redirigir('/perfil');
            } catch (DomainException $error) {
                $datos['error'] = $error->getMessage();
            } catch (PDOException $error) {
                $datos['error'] = errorDeDatos($error);
            }
            http_response_code(422);
        }
        $datos['registro'] = $registro;
        vista('perfil', 'Mi perfil', $datos);
    }
}
