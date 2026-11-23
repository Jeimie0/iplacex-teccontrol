<?php

declare(strict_types=1);

namespace TecControl\Datos;

use PDO;

final readonly class Catalogos
{
    private const COLUMNAS = [
        'clientes' => ['nombre', 'correo_contacto', 'telefono'],
        'equipos' => ['cliente_id', 'tipo', 'marca', 'modelo', 'numero_serie'],
    ];

    public function __construct(private PDO $db)
    {
    }

    public function clientes(string $busqueda = ''): array
    {
        $consulta = $this->db->prepare(<<<'SQL'
            SELECT id, nombre, correo_contacto, telefono
            FROM clientes
            WHERE CONCAT_WS(' ', nombre, correo_contacto, telefono) LIKE ?
            ORDER BY nombre, id
            SQL);
        $consulta->execute(['%' . $busqueda . '%']);
        return $consulta->fetchAll();
    }

    public function equipos(string $busqueda = '', ?string $clienteId = null): array
    {
        $titular = $clienteId === null ? '' : ' AND equipo.cliente_id = ?';
        $consulta = $this->db->prepare(<<<SQL
            SELECT equipo.*, cliente.nombre AS cliente
            FROM equipos AS equipo JOIN clientes AS cliente ON cliente.id = equipo.cliente_id
            WHERE CONCAT_WS(' ', equipo.tipo, equipo.marca, equipo.modelo, equipo.numero_serie, cliente.nombre) LIKE ?{$titular}
            ORDER BY cliente.nombre, equipo.tipo, equipo.marca, equipo.id
            SQL);
        $patron = '%' . $busqueda . '%';
        $consulta->execute($clienteId === null ? [$patron] : [$patron, $clienteId]);
        return $consulta->fetchAll();
    }

    public function buscar(string $tabla, string $id): ?array
    {
        $columnas = implode(', ', self::COLUMNAS[$tabla]);
        $consulta = $this->db->prepare("SELECT id, {$columnas} FROM {$tabla} WHERE id = ?");
        $consulta->execute([$id]);
        return $consulta->fetch() ?: null;
    }

    public function guardar(string $tabla, ?string $id, array $datos): void
    {
        $columnas = self::COLUMNAS[$tabla];
        if ($id === null) {
            $campos = implode(', ', $columnas);
            $marcas = implode(', ', array_fill(0, count($columnas), '?'));
            $consulta = $this->db->prepare("INSERT INTO {$tabla} ({$campos}) VALUES ({$marcas})");
            $consulta->execute(array_map(static fn ($campo) => $datos[$campo], $columnas));
            return;
        }

        // El vínculo del equipo con el cliente es permanente en el esquema provisional.
        $columnas = array_diff($columnas, ['cliente_id']);
        $asignaciones = implode(', ', array_map(static fn ($campo) => "{$campo} = ?", $columnas));
        $consulta = $this->db->prepare("UPDATE {$tabla} SET {$asignaciones} WHERE id = ?");
        $consulta->execute([...array_map(static fn ($campo) => $datos[$campo], $columnas), $id]);
    }

    public function indicadores(): array
    {
        return $this->db->query(<<<'SQL'
            SELECT (SELECT COUNT(*) FROM clientes) AS clientes,
                   (SELECT COUNT(*) FROM equipos) AS equipos,
                   (SELECT COUNT(*) FROM ordenes_servicio) AS ordenes,
                   (SELECT COUNT(*) FROM usuarios WHERE activo = 1) AS usuarios
            SQL)->fetch();
    }
}
