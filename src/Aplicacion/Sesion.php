<?php

declare(strict_types=1);

namespace TecControl\Aplicacion;

use RuntimeException;

final readonly class Sesion
{
    private const COOKIE = [
        'path' => '/',
        'domain' => '',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ];

    private int $inactividad;
    private int $duracion;

    public function __construct()
    {
        $this->inactividad = $this->segundos('SESSION_IDLE_SECONDS', 1800);
        $this->duracion = $this->segundos('SESSION_MAX_SECONDS', 28800);
    }

    public function abrir(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_name('TecControlSesion');
        session_cache_limiter('');
        session_set_cookie_params(['lifetime' => 0] + self::COOKIE);

        if (!session_start([
            'use_cookies' => 1,
            'use_only_cookies' => 1,
            'use_strict_mode' => 1,
            'use_trans_sid' => 0,
            'gc_maxlifetime' => $this->duracion,
        ])) {
            throw new RuntimeException('No se pudo abrir la sesión.');
        }

        $ahora = time();
        if (isset($_SESSION['usuario_id']) && (
            !isset($_SESSION['iniciada'], $_SESSION['actividad'])
            || $ahora - $_SESSION['iniciada'] >= $this->duracion
            || $ahora - $_SESSION['actividad'] >= $this->inactividad
        )) {
            $this->cerrar();
        }
    }

    public function idUsuario(): ?string
    {
        return $_SESSION['usuario_id'] ?? null;
    }

    public function iniciarPara(string $usuarioId, string $huella): void
    {
        // La sesión anterior solo contiene el token del formulario público.
        if (!session_regenerate_id(false)) {
            throw new RuntimeException('No se pudo regenerar la sesión.');
        }

        $ahora = time();
        $_SESSION = ['usuario_id' => $usuarioId, 'huella' => $huella, 'iniciada' => $ahora, 'actividad' => $ahora];
    }

    public function coincideClave(string $huella): bool
    {
        return isset($_SESSION['huella']) && hash_equals($_SESSION['huella'], $huella);
    }

    public function confirmar(string $mensaje): void
    {
        $_SESSION['aviso'] = $mensaje;
    }

    public function retirarAviso(): ?string
    {
        $aviso = $_SESSION['aviso'] ?? null;
        unset($_SESSION['aviso']);
        return $aviso;
    }

    public function registrarActividad(): void
    {
        $_SESSION['actividad'] = time();
    }

    public function tokenCsrf(): string
    {
        $this->abrir();

        return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    }

    public function validarCsrf(mixed $enviado): bool
    {
        return is_string($enviado)
            && isset($_SESSION['csrf'])
            && hash_equals($_SESSION['csrf'], $enviado);
    }

    public function cerrar(): void
    {
        $nombre = session_name();
        $_SESSION = [];

        if (!session_destroy()) {
            throw new RuntimeException('No se pudo invalidar la sesión.');
        }

        session_id('');
        unset($_COOKIE[$nombre]);
        setcookie($nombre, '', ['expires' => 1] + self::COOKIE);
    }

    private function segundos(string $variable, int $predeterminado): int
    {
        $valor = getenv($variable);
        if ($valor === false) {
            return $predeterminado;
        }

        $segundos = filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($segundos === false) {
            throw new RuntimeException("El valor de {$variable} debe ser un entero positivo.");
        }

        return $segundos;
    }
}
