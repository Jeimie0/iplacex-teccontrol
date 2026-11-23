<?php

declare(strict_types=1);

namespace TecControl\Aplicacion;

use DomainException;

final class Entrada
{
    public static function texto(array $datos, string $campo, int $maximo, string $nombre, bool $opcional = false): ?string
    {
        $valor = $datos[$campo] ?? '';
        if (is_string($valor)) {
            $valor = trim($valor);
            if ($opcional && $valor === '') {
                return null;
            }
            if ($valor !== '' && mb_check_encoding($valor, 'UTF-8') && mb_strlen($valor) <= $maximo) {
                return $valor;
            }
        }

        throw new DomainException("Revisa el campo {$nombre}. Su longitud máxima es {$maximo} caracteres.");
    }

    public static function correo(array $datos, string $campo = 'correo', bool $opcional = false): ?string
    {
        $correo = self::texto($datos, $campo, 254, 'correo electrónico', $opcional);
        if ($correo !== null && (strlen($correo) > 254 || filter_var($correo, FILTER_VALIDATE_EMAIL) === false)) {
            throw new DomainException('Ingresa un correo electrónico válido.');
        }

        return $correo;
    }

    public static function id(mixed $valor): ?string
    {
        if (!is_string($valor) || preg_match('/^[1-9][0-9]{0,19}$/D', $valor) !== 1
            || (strlen($valor) === 20 && strcmp($valor, '18446744073709551615') > 0)) {
            return null;
        }

        return $valor;
    }

    public static function identificador(array $datos, string $campo, string $nombre): string
    {
        return self::id($datos[$campo] ?? null) ?? throw new DomainException("Selecciona {$nombre}.");
    }

    public static function claveNueva(mixed $clave): string
    {
        if (!is_string($clave) || strlen($clave) < 12 || strlen($clave) > 72 || str_contains($clave, "\0")) {
            throw new DomainException('Usa una contraseña de entre 12 y 72 bytes.');
        }

        return $clave;
    }

    public static function busqueda(mixed $valor): string
    {
        return is_string($valor) && mb_check_encoding($valor, 'UTF-8') ? mb_substr(trim($valor), 0, 100) : '';
    }
}
