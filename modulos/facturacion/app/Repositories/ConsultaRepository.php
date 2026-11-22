<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Consultas de comprobantes: "Consulta y descarga" y "Trazabilidad".
 * Solo lectura: los comprobantes los crea Emisión, su XML lo genera Procesamiento electrónico
 * y los envíos, respuestas y CDR los guarda Envío y respuesta.
 */
final class ConsultaRepository
{
    /** Filas por búsqueda cuando no se indica la página */
    public const LIMITE = 200;

    /**
     * Dato del cliente tal como estaba al emitir (datos_emision del comprobante);
     * si el comprobante no lo tiene, el del maestro de clientes. Uso: sprintf(self::CLIENTE, 'nombre').
     */
    // CONVERT: en MariaDB JSON_UNQUOTE devuelve utf8 (3 bytes); se pasa a utf8mb4 para compararlo con c.nombre
    public const CLIENTE = 'COALESCE(CONVERT(JSON_UNQUOTE(JSON_EXTRACT(f.datos_emision, \'$.cliente.%1$s\')) USING utf8mb4) COLLATE utf8mb4_general_ci, c.%1$s)';

    /** Columnas cliente, tipo_documento y numero_documento del cliente tal como estaba al emitir */
    private static function columnasCliente(): string
    {
        return sprintf(self::CLIENTE, 'nombre') . ' AS cliente, '
            . sprintf(self::CLIENTE, 'tipo_documento') . ' AS tipo_documento, '
            . sprintf(self::CLIENTE, 'numero_documento') . ' AS numero_documento';
    }

    /** Máximo de eventos en la trazabilidad general (la de un solo comprobante muestra todos) */
    public const LIMITE_EVENTOS = 300;

    /**
     * Orden lógico de las etapas. Desempata eventos registrados en el mismo segundo
     * (por ejemplo, emisión, XML y firma hechos seguidos).
     */
    private const ORDEN_ETAPAS = [
        'borrador_local', 'generado_xml', 'firmado',
        'reintento_habilitado', 'reintento', 'solicitud', 'recuperacion', 'enviado',
        'consulta_ticket', 'respuesta', 'persistencia', 'cdr', 'cdr_ausente',
        'aceptado', 'observado', 'observacion_revisada', 'rechazado', 'pendiente_reintento',
        'anulado_local', 'anulado',
    ];

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::conexion();
    }

    /** Cuántos comprobantes cumplen los filtros (para la paginación) */
    public function contar(array $filtros): int
    {
        [$where, $args] = $this->condiciones($filtros);
        $q = $this->db->prepare('SELECT COUNT(*) FROM facturacion_comprobantes f JOIN demo_clientes c ON c.id = f.cliente_id WHERE 1 = 1' . $where);
        $q->execute($args);

        return (int) $q->fetchColumn();
    }

    /**
     * @param array{tipo:string, numero:string, cliente:string, estado:string, desde:string, hasta:string} $filtros
     *        Valores ya validados por el controlador; '' significa "sin filtro".
     * @param int $limite  filas por página
     * @param int $offset  filas que se saltan (páginas anteriores)
     */
    public function buscar(array $filtros, int $limite = self::LIMITE, int $offset = 0): array
    {
        [$where, $args] = $this->condiciones($filtros);
        $sql = 'SELECT f.id, f.tipo, f.serie, f.numero, f.fecha_emision, f.subtotal, f.impuesto, f.total, f.estado,
                       ' . self::columnasCliente() . ',
                       x.nombre_archivo AS xml_archivo, x.hash AS xml_hash,
                       -- Último envío con CDR (lo guarda Envío y respuesta en facturacion_envios)
                       (SELECT e.id FROM facturacion_envios e
                        WHERE e.comprobante_id = f.id AND e.cdr_contenido IS NOT NULL
                        ORDER BY e.intento DESC LIMIT 1) AS cdr_envio_id,
                       -- Quién respondió el último intento: simulado o proveedor (NULL si aún no se envió)
                       (SELECT e.origen FROM facturacion_envios e
                        WHERE e.comprobante_id = f.id
                        ORDER BY e.intento DESC LIMIT 1) AS origen_respuesta
                FROM facturacion_comprobantes f
                JOIN demo_clientes c ON c.id = f.cliente_id
                LEFT JOIN facturacion_xml x ON x.comprobante_id = f.id
                WHERE 1 = 1' . $where
            . ' ORDER BY f.fecha_emision DESC, f.id DESC LIMIT ' . max(1, $limite) . ' OFFSET ' . max(0, $offset);

        $q = $this->db->prepare($sql);
        $q->execute($args);

        return $q->fetchAll();
    }

    /** @return array{0: string, 1: array} condiciones SQL (sobre f y c) y sus valores */
    private function condiciones(array $filtros): array
    {
        $sql = sql_sede_comprobante('f'); // sede activa (G4)
        $args = [];

        if ($filtros['tipo'] !== '') {
            $sql .= ' AND f.tipo = ?';
            $args[] = $filtros['tipo'];
        }

        if ($filtros['estado'] !== '') {
            $sql .= ' AND f.estado = ?';
            $args[] = $filtros['estado'];
        }

        if ($filtros['desde'] !== '') {
            $sql .= ' AND f.fecha_emision >= ?';
            $args[] = $filtros['desde'] . ' 00:00:00';
        }

        if ($filtros['hasta'] !== '') {
            $sql .= ' AND f.fecha_emision <= ?';
            $args[] = $filtros['hasta'] . ' 23:59:59';
        }

        if ($filtros['cliente'] !== '') {
            // Nombre o número de documento; % y _ se buscan como texto literal
            $like = '%' . addcslashes($filtros['cliente'], '\\%_') . '%';
            $sql .= ' AND (' . sprintf(self::CLIENTE, 'nombre') . ' LIKE ? OR ' . sprintf(self::CLIENTE, 'numero_documento') . ' LIKE ?)';
            array_push($args, $like, $like);
        }

        if ($filtros['numero'] !== '') {
            [$condicion, $valores] = $this->condicionNumero($filtros['numero']);
            $sql .= $condicion;
            array_push($args, ...$valores);
        }

        return [$sql, $args];
    }

    /** Datos principales de un comprobante para el encabezado de su trazabilidad */
    public function resumen(int $id): ?array
    {
        $q = $this->db->prepare(
            'SELECT f.id, f.tipo, f.serie, f.numero, f.fecha_emision, f.total, f.estado,
                    ' . self::columnasCliente() . ',
                    (SELECT e.origen FROM facturacion_envios e
                     WHERE e.comprobante_id = f.id
                     ORDER BY e.intento DESC LIMIT 1) AS origen_respuesta
             FROM facturacion_comprobantes f
             JOIN demo_clientes c ON c.id = f.cliente_id
             WHERE f.id = ?'
        );
        $q->execute([$id]);

        return $q->fetch() ?: null;
    }

    /**
     * Línea de tiempo: une los eventos del comprobante (facturacion_eventos: emisión, XML, firma, estados)
     * con los del envío (facturacion_envio_eventos: solicitudes, respuestas, CDR, reintentos).
     *
     * @param array{id:int, numero:string, etapas:string[], desde:string, hasta:string, incidencias:bool} $filtros
     *        Con id > 0 se devuelve el historial completo de ese comprobante, del más antiguo al más reciente;
     *        sin id, los eventos más recientes primero.
     * @param int|null $limite eventos por página (sin id); null = los últimos LIMITE_EVENTOS
     */
    public function eventos(array $filtros, ?int $limite = null, int $offset = 0): array
    {
        [$desde, $args] = $this->consultaEventos($filtros);
        $direccion = $filtros['id'] > 0 ? 'ASC' : 'DESC';
        $etapas = implode(', ', array_fill(0, count(self::ORDEN_ETAPAS), '?'));
        $sql = "SELECT t.*, f.tipo, f.serie, f.numero, " . sprintf(self::CLIENTE, 'nombre') . " AS cliente {$desde}"
            . " ORDER BY t.creado_en {$direccion}, FIELD(t.etapa, {$etapas}) {$direccion}, t.id {$direccion}";
        array_push($args, ...self::ORDEN_ETAPAS);

        if ($filtros['id'] === 0) {
            $sql .= ' LIMIT ' . max(1, $limite ?? self::LIMITE_EVENTOS) . ' OFFSET ' . max(0, $offset);
        }

        $q = $this->db->prepare($sql);
        $q->execute($args);

        return $q->fetchAll();
    }

    /** Cuántos eventos cumplen los filtros (para la paginación) */
    public function contarEventos(array $filtros): int
    {
        [$desde, $args] = $this->consultaEventos($filtros);
        $q = $this->db->prepare("SELECT COUNT(*) {$desde}");
        $q->execute($args);

        return (int) $q->fetchColumn();
    }

    /** @return array{0: string, 1: array} «FROM … WHERE …» de la línea de tiempo y sus valores */
    private function consultaEventos(array $filtros): array
    {
        $sql = "FROM (
                    SELECT 'comprobante' AS fuente, ev.id, ev.comprobante_id, ev.estado AS etapa,
                           CASE WHEN ev.estado = 'rechazado' THEN 'error'
                                WHEN ev.estado IN ('observado', 'pendiente_reintento') THEN 'observacion'
                                ELSE 'info' END AS nivel,
                           NULL AS codigo, ev.detalle, ev.creado_en, NULL AS intento, NULL AS origen,
                           JSON_UNQUOTE(JSON_EXTRACT(ev.metadatos, '$.usuario_id')) AS usuario_id,
                           JSON_UNQUOTE(JSON_EXTRACT(ev.metadatos, '$.usuario_nombre')) AS usuario_nombre,
                           JSON_UNQUOTE(JSON_EXTRACT(ev.metadatos, '$.sede_id')) AS sede_id,
                           JSON_UNQUOTE(JSON_EXTRACT(ev.metadatos, '$.sede_nombre')) AS sede_nombre
                    FROM facturacion_eventos ev
                    UNION ALL
                    SELECT 'envio', ee.id, e.comprobante_id, ee.etapa, ee.nivel,
                           ee.codigo, ee.detalle, ee.creado_en, e.intento, e.origen,
                           JSON_UNQUOTE(JSON_EXTRACT(ee.metadatos, '$.usuario_id')),
                           JSON_UNQUOTE(JSON_EXTRACT(ee.metadatos, '$.usuario_nombre')),
                           JSON_UNQUOTE(JSON_EXTRACT(ee.metadatos, '$.sede_id')),
                           JSON_UNQUOTE(JSON_EXTRACT(ee.metadatos, '$.sede_nombre'))
                    FROM facturacion_envio_eventos ee
                    JOIN facturacion_envios e ON e.id = ee.envio_id
                ) t
                JOIN facturacion_comprobantes f ON f.id = t.comprobante_id
                JOIN demo_clientes c ON c.id = f.cliente_id
                WHERE 1 = 1";
        $args = [];

        if ($filtros['id'] > 0) {
            $sql .= ' AND t.comprobante_id = ?';
            $args[] = $filtros['id'];
        }

        if ($filtros['numero'] !== '') {
            [$condicion, $valores] = $this->condicionNumero($filtros['numero']);
            $sql .= $condicion;
            array_push($args, ...$valores);
        }

        if ($filtros['etapas']) {
            $sql .= ' AND t.etapa IN (' . implode(', ', array_fill(0, count($filtros['etapas']), '?')) . ')';
            array_push($args, ...$filtros['etapas']);
        }

        if ($filtros['incidencias']) {
            $sql .= " AND t.nivel IN ('observacion', 'error')";
        }

        if ($filtros['desde'] !== '') {
            $sql .= ' AND t.creado_en >= ?';
            $args[] = $filtros['desde'] . ' 00:00:00';
        }

        if ($filtros['hasta'] !== '') {
            $sql .= ' AND t.creado_en <= ?';
            $args[] = $filtros['hasta'] . ' 23:59:59';
        }

        return [$sql, $args];
    }

    /**
     * Origen de la respuesta del último intento de envío: 'simulado', 'proveedor' o null si aún no se envió.
     * Un "aceptado" simulado no es una aceptación real de SUNAT.
     */
    public function origenRespuesta(int $comprobanteId): ?string
    {
        $q = $this->db->prepare('SELECT origen FROM facturacion_envios WHERE comprobante_id = ? ORDER BY intento DESC LIMIT 1');
        $q->execute([$comprobanteId]);
        $origen = $q->fetchColumn();

        return $origen === false ? null : (string) $origen;
    }

    /**
     * Acepta "B001-12", "B001-00000012", solo la serie ("B001") o solo el número ("12").
     *
     * @return array{0: string, 1: array}
     */
    private function condicionNumero(string $texto): array
    {
        if (preg_match('/^([A-Za-z0-9]{1,4})\s*-\s*0*(\d+)$/', $texto, $m)) {
            return [' AND f.serie = ? AND f.numero = ?', [strtoupper($m[1]), (int) $m[2]]];
        }

        if (ctype_digit($texto)) {
            return [' AND f.numero = ?', [(int) $texto]];
        }

        return [' AND f.serie = ?', [strtoupper($texto)]];
    }
}
