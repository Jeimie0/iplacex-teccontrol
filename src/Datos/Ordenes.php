<?php

declare(strict_types=1);

namespace TecControl\Datos;

use DomainException;
use PDO;
use Throwable;

final readonly class Ordenes
{
    private const ESTADO_ACTUAL = <<<'SQL'
        LEFT JOIN historial_estados AS ultimo ON ultimo.id = (
            SELECT id FROM historial_estados WHERE orden_id = orden.id ORDER BY id DESC LIMIT 1
        )
        LEFT JOIN estados_orden AS estado ON estado.id = ultimo.estado_id
        SQL;

    public function __construct(private PDO $db)
    {
    }

    public function listarDeCliente(string $clienteId, string $busqueda = '', string $estadoCodigo = ''): array
    {
        return $this->listar($clienteId, $busqueda, $estadoCodigo);
    }

    public function listarAdministracion(string $busqueda, string $estadoCodigo, int $maximo = 0): array
    {
        return $this->listar(null, $busqueda, $estadoCodigo, $maximo);
    }

    private function listar(?string $clienteId, string $busqueda, string $estadoCodigo, int $maximo = 0): array
    {
        $estado = self::ESTADO_ACTUAL;
        $titular = $clienteId === null ? '' : ' AND equipo.cliente_id = ?';
        $limite = $maximo > 0 ? "LIMIT {$maximo}" : '';
        $consulta = $this->db->prepare(<<<SQL
            SELECT orden.id, orden.folio, orden.creada_en, equipo.tipo, equipo.marca, equipo.modelo,
                   cliente.nombre AS cliente, estado.nombre AS estado, estado.codigo AS estado_codigo
            FROM ordenes_servicio AS orden
            JOIN equipos AS equipo ON equipo.id = orden.equipo_id
            JOIN clientes AS cliente ON cliente.id = equipo.cliente_id
            {$estado}
            WHERE CONCAT_WS(' ', orden.folio, equipo.tipo, equipo.marca, equipo.modelo, cliente.nombre) LIKE ?
                  AND (? = '' OR estado.codigo = ?){$titular}
            ORDER BY orden.creada_en DESC, orden.id DESC
            {$limite}
            SQL);
        $parametros = ['%' . $busqueda . '%', $estadoCodigo, $estadoCodigo];
        if ($clienteId !== null) {
            $parametros[] = $clienteId;
        }
        $consulta->execute($parametros);
        return $consulta->fetchAll();
    }

    public function estados(): array
    {
        return $this->db->query('SELECT id, codigo, nombre FROM estados_orden ORDER BY id')->fetchAll();
    }

    public function indicadores(?string $clienteId): array
    {
        $estado = self::ESTADO_ACTUAL;
        $condicion = $clienteId === null ? '' : 'WHERE equipo.cliente_id = ?';
        $consulta = $this->db->prepare(<<<SQL
            SELECT estado.codigo, estado.nombre, COUNT(*) AS total
            FROM ordenes_servicio AS orden
            JOIN equipos AS equipo ON equipo.id = orden.equipo_id
            {$estado}
            {$condicion}
            GROUP BY estado.codigo, estado.nombre
            ORDER BY MIN(estado.id)
            SQL);
        $consulta->execute($clienteId === null ? [] : [$clienteId]);
        return $consulta->fetchAll();
    }

    public function buscarParaAdministracion(string $ordenId): ?array
    {
        return $this->buscarDetalle($ordenId, null);
    }

    public function buscarDeCliente(string $ordenId, string $clienteId): ?array
    {
        return $this->buscarDetalle($ordenId, $clienteId);
    }

    private function buscarDetalle(string $ordenId, ?string $clienteId): ?array
    {
        $estado = self::ESTADO_ACTUAL;
        $filtro = $clienteId === null ? '' : ' AND equipo.cliente_id = ?';
        $consulta = $this->db->prepare(<<<SQL
            SELECT orden.id, orden.equipo_id, orden.folio, orden.creada_en, orden.falla_reportada, orden.diagnostico,
                   equipo.tipo, equipo.marca, equipo.modelo, estado.nombre AS estado, estado.codigo AS estado_codigo,
                   ultimo.id AS evento_id, ultimo.estado_id
            FROM ordenes_servicio AS orden
            JOIN equipos AS equipo ON equipo.id = orden.equipo_id
            {$estado}
            WHERE orden.id = ?{$filtro}
            SQL);
        $consulta->execute($clienteId === null ? [$ordenId] : [$ordenId, $clienteId]);
        return $consulta->fetch() ?: null;
    }

    public function historial(string $ordenId, ?string $clienteId): array
    {
        $filtro = $clienteId === null ? '' : ' AND equipo.cliente_id = ?';
        $consulta = $this->db->prepare(<<<SQL
            SELECT estado.nombre, estado.codigo, evento.registrado_en
            FROM historial_estados AS evento
            JOIN estados_orden AS estado ON estado.id = evento.estado_id
            JOIN ordenes_servicio AS orden ON orden.id = evento.orden_id
            JOIN equipos AS equipo ON equipo.id = orden.equipo_id
            WHERE orden.id = ?{$filtro}
            ORDER BY evento.id DESC
            SQL);
        $consulta->execute($clienteId === null ? [$ordenId] : [$ordenId, $clienteId]);
        return $consulta->fetchAll();
    }

    public function crear(string $equipoId, string $folio, string $falla, ?string $diagnostico, string $actor): string
    {
        $this->db->beginTransaction();
        try {
            $estadoInicial = $this->db->query("SELECT id FROM estados_orden WHERE codigo = 'recibida'")->fetchColumn();
            if ($estadoInicial === false) {
                throw new DomainException('El catálogo requiere el estado inicial Recibida.');
            }
            $consulta = $this->db->prepare(<<<'SQL'
                INSERT INTO ordenes_servicio (equipo_id, folio, falla_reportada, diagnostico) VALUES (?, ?, ?, ?)
                SQL);
            $consulta->execute([$equipoId, $folio, $falla, $diagnostico]);
            $ordenId = $this->db->lastInsertId();
            $this->registrarEstado($ordenId, (string) $estadoInicial, $actor);
            $this->db->commit();
            return $ordenId;
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }
    }

    public function actualizar(
        string $ordenId, string $falla, ?string $diagnostico, string $estadoId, string $eventoAnterior, string $actor,
    ): void {
        $this->db->beginTransaction();
        try {
            $bloqueo = $this->db->prepare('SELECT id FROM ordenes_servicio WHERE id = ? FOR UPDATE');
            $bloqueo->execute([$ordenId]);
            if ($bloqueo->fetchColumn() === false) {
                throw new DomainException('La orden ya no está disponible.');
            }
            $actual = $this->buscarParaAdministracion($ordenId);
            if ((string) $actual['evento_id'] !== $eventoAnterior) {
                throw new DomainException('El estado cambió desde que abriste el formulario. Recarga la orden antes de guardar.');
            }
            $consulta = $this->db->prepare('UPDATE ordenes_servicio SET falla_reportada = ?, diagnostico = ? WHERE id = ?');
            $consulta->execute([$falla, $diagnostico, $ordenId]);
            if ((string) $actual['estado_id'] !== $estadoId) {
                $this->registrarEstado($ordenId, $estadoId, $actor);
            }
            $this->db->commit();
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }
    }

    private function registrarEstado(string $ordenId, string $estadoId, string $actor): void
    {
        $consulta = $this->db->prepare('INSERT INTO historial_estados (orden_id, estado_id, registrado_por) VALUES (?, ?, ?)');
        $consulta->execute([$ordenId, $estadoId, $actor]);
    }
}
