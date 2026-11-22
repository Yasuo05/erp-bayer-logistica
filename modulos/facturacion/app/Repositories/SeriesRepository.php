<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class SeriesRepository
{
    /**
     * Series reservadas para las pruebas automáticas (por tipo; las notas, una con F y otra con B).
     * Así las pruebas no gastan números de las series reales ni dejan saltos en su numeración.
     */
    public const SERIES_PRUEBA = [
        'factura'      => ['FZ99'],
        'boleta'       => ['BZ99'],
        'nota_credito' => ['FZ98', 'BZ98'],
        'nota_debito'  => ['FZ97', 'BZ97'],
    ];

    /** Solo lo activa tests/ejecutar.php: al emitir se usan las series de prueba en lugar de las reales */
    private static bool $seriesDePrueba = false;

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::conexion();
    }

    public function listar(): array
    {
        return $this->db->query(
            'SELECT s.*, su.nombre AS sede, COUNT(c.id) AS comprobantes, COALESCE(MAX(c.numero), 0) AS ultimo_emitido
             FROM facturacion_series s
             LEFT JOIN sucursales su ON su.id=s.sucursal_id
             LEFT JOIN facturacion_comprobantes c ON c.tipo=s.tipo AND c.serie=s.serie
             GROUP BY s.id
             ORDER BY FIELD(s.tipo,"factura","boleta","nota_credito","nota_debito"), s.serie'
        )->fetchAll();
    }

    /** Últimos cambios manuales de correlativos (creación de series y ajustes del siguiente número) */
    public function cambios(int $limite = 50): array
    {
        $q = $this->db->prepare(
            'SELECT h.*, s.tipo, s.serie
             FROM facturacion_series_cambios h
             JOIN facturacion_series s ON s.id = h.serie_id
             ORDER BY h.id DESC
             LIMIT ' . max(1, $limite)
        );
        $q->execute();

        return $q->fetchAll();
    }

    /** Crea la serie y deja registrado su correlativo inicial en el historial */
    public function crear(string $tipo, string $serie, int $siguiente, ?string $descripcion, string $usuario, ?int $sucursalId = null): int
    {
        $this->db->beginTransaction();
        try {
            $q = $this->db->prepare(
                'INSERT INTO facturacion_series (tipo,serie,sucursal_id,descripcion,siguiente,activo) VALUES (?,?,?,?,?,1)'
            );
            $q->execute([$tipo, $serie, $sucursalId, $descripcion, $siguiente]);
            $id = (int) $this->db->lastInsertId();
            $this->registrarCambio($id, null, $siguiente, 'Creación de la serie.', $usuario);
            $this->db->commit();

            return $id;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Actualiza la descripción y el siguiente correlativo. Si el correlativo cambia,
     * exige un motivo y registra valor anterior, nuevo, fecha y usuario.
     */
    public function actualizar(int $id, int $siguiente, ?string $descripcion, string $motivo, string $usuario): void
    {
        $this->db->beginTransaction();
        try {
            $q = $this->db->prepare('SELECT tipo,serie,siguiente FROM facturacion_series WHERE id=? FOR UPDATE');
            $q->execute([$id]);
            $row = $q->fetch();
            if (!$row) {
                throw new \RuntimeException('No se encontró la serie.');
            }

            $q = $this->db->prepare('SELECT COALESCE(MAX(numero),0) FROM facturacion_comprobantes WHERE tipo=? AND serie=?');
            $q->execute([$row['tipo'], $row['serie']]);
            $minimo = (int) $q->fetchColumn() + 1;
            if ($siguiente < $minimo) {
                throw new \InvalidArgumentException("El siguiente correlativo no puede ser menor que {$minimo}.");
            }

            $anterior = (int) $row['siguiente'];
            if ($siguiente !== $anterior) {
                if ($motivo === '') {
                    throw new \InvalidArgumentException("Indica el motivo del cambio del correlativo de {$row['serie']} ({$anterior} → {$siguiente}).");
                }
                $this->registrarCambio($id, $anterior, $siguiente, $motivo, $usuario);
            }

            $q = $this->db->prepare('UPDATE facturacion_series SET descripcion=?,siguiente=? WHERE id=?');
            $q->execute([$descripcion, $siguiente, $id]);
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function cambiarActivo(int $id, bool $activo): void
    {
        $q = $this->db->prepare('UPDATE facturacion_series SET activo=? WHERE id=?');
        $q->execute([$activo ? 1 : 0, $id]);
        if ($q->rowCount() === 0) {
            $existe = $this->db->prepare('SELECT COUNT(*) FROM facturacion_series WHERE id=?');
            $existe->execute([$id]);
            if (!(bool) $existe->fetchColumn()) {
                throw new \RuntimeException('No se encontró la serie.');
            }
        }
    }

    /**
     * Serie que se usaría al emitir y su siguiente número, sin reservarlo (para la revisión previa).
     * @return array{serie:string,siguiente:int}|null
     */
    public function previsualizar(string $tipo, ?string $prefijo = null, ?int $sucursalId = null): ?array
    {
        [$sql, $args] = $this->consultaSerieActiva($tipo, null, $prefijo, $sucursalId);
        $q = $this->db->prepare($sql);
        $q->execute($args);
        $row = $q->fetch();

        return $row ? ['serie' => (string) $row['serie'], 'siguiente' => (int) $row['siguiente']] : null;
    }

    /**
     * Reserva un número dentro de la transacción iniciada por quien crea el comprobante.
     * Fuera de una transacción se rechaza: el número podría quedar reservado sin comprobante, o repetirse.
     * $prefijo limita a las series que empiezan con esa letra (las notas usan F o B según el comprobante que modifican).
     * @return array{serie:string,numero:int}
     */
    public function reservar(string $tipo, ?string $serie = null, ?string $prefijo = null, ?int $sucursalId = null): array
    {
        if (!$this->db->inTransaction()) {
            throw new \LogicException('La reserva del correlativo debe ejecutarse dentro de la transacción que crea el comprobante.');
        }

        [$sql, $args] = $this->consultaSerieActiva($tipo, $serie, $prefijo, $sucursalId);
        $q = $this->db->prepare($sql . ' FOR UPDATE');
        $q->execute($args);
        $row = $q->fetch();
        if (!$row) {
            throw new \RuntimeException($prefijo !== null
                ? "No hay una serie activa de este tipo que empiece con «{$prefijo}». Créala en Series y correlativos."
                : 'No existe una serie activa para este tipo de comprobante.');
        }

        $numero = (int) $row['siguiente'];
        $this->db->prepare('UPDATE facturacion_series SET siguiente=siguiente+1 WHERE id=?')->execute([$row['id']]);

        return ['serie' => (string) $row['serie'], 'numero' => $numero];
    }

    public static function usarSeriesDePrueba(bool $activar): void
    {
        self::$seriesDePrueba = $activar;
    }

    public static function esSeriePrueba(string $tipo, string $serie): bool
    {
        return in_array(strtoupper($serie), self::SERIES_PRUEBA[$tipo] ?? [], true);
    }

    /**
     * @return array{0:string,1:array} primera serie activa del tipo (opcionalmente una serie o un prefijo).
     * Sin serie indicada, las de prueba solo se eligen durante las pruebas, y entonces solo ellas.
     */
    private function consultaSerieActiva(string $tipo, ?string $serie, ?string $prefijo, ?int $sucursalId = null): array
    {
        $sql = 'SELECT id,serie,siguiente FROM facturacion_series WHERE tipo=? AND activo=1';
        $args = [$tipo];
        if ($serie !== null) {
            $sql .= ' AND serie=?';
            $args[] = $serie;
        } elseif (isset(self::SERIES_PRUEBA[$tipo])) {
            $reservadas = self::SERIES_PRUEBA[$tipo];
            $sql .= ' AND serie ' . (self::$seriesDePrueba ? 'IN' : 'NOT IN') . ' (' . implode(',', array_fill(0, count($reservadas), '?')) . ')';
            array_push($args, ...$reservadas);
        }
        if ($prefijo !== null) {
            $sql .= ' AND serie LIKE ?';
            $args[] = $prefijo . '%';
        }

        // Serie por sede (G4): primero la serie asignada a la sede del comprobante; si no tiene, la serie general (sin sede)
        if ($sucursalId !== null) {
            $sql .= ' AND (sucursal_id = ? OR sucursal_id IS NULL)';
            $args[] = $sucursalId;
            return [$sql . ' ORDER BY sucursal_id IS NULL, id LIMIT 1', $args];
        }
        return [$sql . ' ORDER BY sucursal_id IS NOT NULL, id LIMIT 1', $args];
    }

    private function registrarCambio(int $serieId, ?int $anterior, int $nuevo, string $motivo, string $usuario): void
    {
        $q = $this->db->prepare(
            'INSERT INTO facturacion_series_cambios (serie_id,valor_anterior,valor_nuevo,motivo,usuario,creado_en) VALUES (?,?,?,?,?,NOW())'
        );
        $q->execute([$serieId, $anterior, $nuevo, $motivo, $usuario]);
    }
}
