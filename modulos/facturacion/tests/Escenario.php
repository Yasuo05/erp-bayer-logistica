<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Database;
use App\Repositories\FacturacionRepository;
use App\Repositories\SeriesRepository;
use App\Services\DocumentoElectronicoService;
use App\Services\DocumentoIdentidad;
use App\Services\FacturacionService;
use PDO;

/**
 * Datos para las pruebas de integración. Todo lo que se crea lleva la marca MARCA en el nombre del
 * cliente (o en la descripción de la serie) y limpiar() lo borra al terminar.
 * Los comprobantes de prueba se numeran en series reservadas (SeriesRepository::SERIES_PRUEBA),
 * así las series reales no pierden números.
 */
final class Escenario
{
    public const MARCA = 'PRUEBA AUTOMATICA';

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::conexion();
    }

    /**
     * Crea las series reservadas para pruebas y hace que la emisión las use en lugar de las reales.
     * Si una ya existe sin la marca de prueba, se detiene: sería una serie real con ese nombre.
     */
    public function prepararSeries(): void
    {
        $repo = new SeriesRepository();
        $existe = $this->db->prepare('SELECT descripcion FROM facturacion_series WHERE tipo = ? AND serie = ?');
        foreach (SeriesRepository::SERIES_PRUEBA as $tipo => $series) {
            foreach ($series as $serie) {
                $existe->execute([$tipo, $serie]);
                $descripcion = $existe->fetchColumn();
                if ($descripcion === false) {
                    $repo->crear($tipo, $serie, 1, self::MARCA, 'Pruebas automáticas');
                } elseif ($descripcion !== self::MARCA) {
                    throw new \RuntimeException("La serie {$serie} ({$tipo}) ya existe y no es de prueba: renómbrala antes de ejecutar las pruebas.");
                }
            }
        }
        SeriesRepository::usarSeriesDePrueba(true);
    }

    /** Venta aprobada de un cliente de prueba. RUC → factura; DNI → boleta */
    public function venta(string $tipoDocumento = 'RUC', int $lineas = 2): int
    {
        // RUC con su dígito verificador correcto (el registro de ventas lo valida)
        $diez = '20' . str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
        $documento = $tipoDocumento === 'RUC'
            ? $diez . DocumentoIdentidad::digitoVerificadorRuc($diez)
            : '9' . str_pad((string) random_int(0, 9999999), 7, '0', STR_PAD_LEFT);
        $productos = $this->db->query('SELECT id FROM demo_productos WHERE activo = 1 ORDER BY id LIMIT ' . max(1, $lineas))->fetchAll(PDO::FETCH_COLUMN);

        return (new FacturacionRepository())->crearVentaManual([
            'cliente'          => self::MARCA . ' ' . strtoupper(bin2hex(random_bytes(3))),
            'tipo_documento'   => $tipoDocumento,
            'numero_documento' => $documento,
            'producto_id'      => $productos,
            'cantidad'         => array_fill(0, count($productos), '2'),
        ]);
    }

    /** Comprobante emitido desde una venta nueva, con la revisión de importes (queda en «Borrador») */
    public function comprobante(string $tipoDocumento = 'RUC'): int
    {
        $servicio = new FacturacionService();
        $venta = (new FacturacionRepository())->venta($this->venta($tipoDocumento));
        $revision = $servicio->revisarVenta($venta);

        return $servicio->emitirVenta((int) $venta['id'], $revision['tipo'], $revision['importes']['total'], $revision['tasa'], $revision['huella']);
    }

    /** Comprobante con su XML generado, firmado y verificado (queda «XML firmado») */
    public function comprobanteFirmado(string $tipoDocumento = 'RUC'): int
    {
        $id = $this->comprobante($tipoDocumento);
        $documentos = new DocumentoElectronicoService();
        $documentos->generarXml($id);
        $documentos->firmar($id);

        return $id;
    }

    public function estado(int $id): string
    {
        $q = $this->db->prepare('SELECT estado FROM facturacion_comprobantes WHERE id = ?');
        $q->execute([$id]);

        return (string) $q->fetchColumn();
    }

    public function valor(string $sql, array $args = []): mixed
    {
        $q = $this->db->prepare($sql);
        $q->execute($args);

        return $q->fetchColumn();
    }

    /**
     * Deja un intento «interrumpido»: registrado hace 5 minutos y sin respuesta,
     * como si el proceso se hubiera cortado (igual que bin/simular_intento_interrumpido.php).
     */
    public function interrumpirEnvio(int $id): void
    {
        $hash = (string) $this->valor('SELECT hash FROM facturacion_xml WHERE comprobante_id = ?', [$id]);
        $intento = (int) $this->valor('SELECT COALESCE(MAX(intento), 0) + 1 FROM facturacion_envios WHERE comprobante_id = ?', [$id]);
        $this->db->prepare("INSERT INTO facturacion_envios (comprobante_id, intento, origen, idempotencia, estado, solicitado_en)
            VALUES (?, ?, 'simulado', ?, 'enviando', NOW() - INTERVAL 5 MINUTE)")
            ->execute([$id, $intento, hash('sha256', $id . '|' . $intento . '|' . $hash)]);
    }

    /** Borra todo lo creado por las pruebas (series de prueba incluidas). Devuelve cuántos clientes de prueba se eliminaron */
    public function limpiar(): int
    {
        SeriesRepository::usarSeriesDePrueba(false);
        $clientes = $this->db->query("SELECT id FROM demo_clientes WHERE nombre LIKE '" . self::MARCA . "%'")->fetchAll(PDO::FETCH_COLUMN);
        if ($clientes) {
            $lista = implode(',', array_map('intval', $clientes));
            // Las notas (id mayor) se borran antes que su comprobante; detalle, XML, envíos y eventos caen en cascada
            $this->db->exec("DELETE FROM facturacion_comprobantes WHERE cliente_id IN ({$lista}) ORDER BY id DESC");
            $this->db->exec("DELETE d FROM demo_venta_detalles d JOIN demo_ventas v ON v.id = d.venta_id WHERE v.cliente_id IN ({$lista})");
            $this->db->exec("DELETE FROM demo_ventas WHERE cliente_id IN ({$lista})");
            $this->db->exec("DELETE FROM demo_clientes WHERE id IN ({$lista})");
            // Documentos de anulación que quedaron sin comprobantes (sus ítems cayeron en cascada)
            $this->db->exec('DELETE b FROM facturacion_bajas b LEFT JOIN facturacion_baja_items i ON i.baja_id = b.id WHERE i.id IS NULL');
        }
        $series = $this->db->query("SELECT id FROM facturacion_series WHERE descripcion = '" . self::MARCA . "'")->fetchAll(PDO::FETCH_COLUMN);
        if ($series) {
            $lista = implode(',', array_map('intval', $series));
            $this->db->exec("DELETE FROM facturacion_series_cambios WHERE serie_id IN ({$lista})");
            $this->db->exec("DELETE FROM facturacion_series WHERE id IN ({$lista})");
        }

        return count($clientes);
    }
}
