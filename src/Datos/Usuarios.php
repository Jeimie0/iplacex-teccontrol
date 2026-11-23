<?php

declare(strict_types=1);

namespace TecControl\Datos;

use DomainException;
use PDO;
use Throwable;

final readonly class Usuarios
{
    public function __construct(private PDO $db)
    {
    }

    public function buscarCredenciales(string $correo): ?array
    {
        $consulta = $this->db->prepare('SELECT id, password_hash FROM usuarios WHERE correo = ? AND activo = 1');
        $consulta->execute([$correo]);

        return $consulta->fetch() ?: null;
    }

    public function buscarCuentaActiva(string $id): ?array
    {
        $consulta = $this->db->prepare('SELECT id, nombre, rol, cliente_id, password_hash FROM usuarios WHERE id = ? AND activo = 1');
        $consulta->execute([$id]);

        return $consulta->fetch() ?: null;
    }

    public function actualizarHash(string $id, string $hashAnterior, string $hashNuevo): bool
    {
        $consulta = $this->db->prepare(<<<'SQL'
            UPDATE usuarios
            SET password_hash = ?
            WHERE id = ? AND CAST(password_hash AS BINARY) = ? AND activo = 1
            SQL);
        $consulta->execute([$hashNuevo, $id, $hashAnterior]);

        return $consulta->rowCount() === 1;
    }

    public function listar(string $busqueda, string $rol): array
    {
        $consulta = $this->db->prepare(<<<'SQL'
            SELECT usuario.id, usuario.nombre, usuario.correo, usuario.rol, usuario.activo, cliente.nombre AS cliente
            FROM usuarios AS usuario LEFT JOIN clientes AS cliente ON cliente.id = usuario.cliente_id
            WHERE CONCAT_WS(' ', usuario.nombre, usuario.correo, cliente.nombre) LIKE ? AND (? = '' OR usuario.rol = ?)
            ORDER BY usuario.nombre, usuario.id
            SQL);
        $consulta->execute(['%' . $busqueda . '%', $rol, $rol]);
        return $consulta->fetchAll();
    }

    public function buscar(string $id): ?array
    {
        $consulta = $this->db->prepare('SELECT id, nombre, correo, rol, cliente_id, activo FROM usuarios WHERE id = ?');
        $consulta->execute([$id]);
        return $consulta->fetch() ?: null;
    }

    public function crear(string $nombre, string $correo, string $hash, string $rol, ?string $clienteId): void
    {
        $consulta = $this->db->prepare('INSERT INTO usuarios (nombre, correo, password_hash, rol, cliente_id) VALUES (?, ?, ?, ?, ?)');
        $consulta->execute([$nombre, $correo, $hash, $rol, $clienteId]);
    }

    public function actualizarPerfil(string $id, string $nombre, string $correo): void
    {
        $consulta = $this->db->prepare('UPDATE usuarios SET nombre = ?, correo = ? WHERE id = ? AND activo = 1');
        $consulta->execute([$nombre, $correo, $id]);
    }

    public function actualizarGestion(string $id, string $nombre, string $correo, bool $activo, ?string $hash): void
    {
        $this->db->beginTransaction();
        try {
            $administradores = $this->db->query(<<<'SQL'
                SELECT id FROM usuarios WHERE rol = 'administrador' AND activo = 1 ORDER BY id FOR UPDATE
                SQL)->fetchAll(PDO::FETCH_COLUMN);
            if (!$activo && count($administradores) === 1 && (string) $administradores[0] === $id) {
                throw new DomainException('Debe permanecer al menos una cuenta administrativa activa.');
            }
            $consulta = $this->db->prepare('UPDATE usuarios SET nombre = ?, correo = ?, activo = ?, password_hash = COALESCE(?, password_hash) WHERE id = ?');
            $consulta->execute([$nombre, $correo, (int) $activo, $hash, $id]);
            $this->db->commit();
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }
    }
}
