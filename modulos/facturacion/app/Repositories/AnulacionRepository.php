<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Services\ContextoAuditoria;
use PDO;

/** Documentos de anulación ante SUNAT (comunicación de baja y resumen diario) y los comprobantes que incluyen */
final class AnulacionRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::conexion();
    }

    public function baja(int $id): ?array
    {
        $q = $this->db->prepare('SELECT * FROM facturacion_bajas WHERE id=?');
        $q->execute([$id]);
        $baja = $q->fetch();
        if (!$baja) {
            return null;
        }
        $q = $this->db->prepare('SELECT comprobante_id, motivo FROM facturacion_baja_items WHERE baja_id=? ORDER BY id');
        $q->execute([$id]);
        $baja['items'] = $q->fetchAll();

        return $baja;
    }

    /** Último documento de anulación del comprobante que no fue rechazado (uno rechazado permite generar otro) */
    public function bajaVigente(int $comprobanteId): ?array
    {
        $q = $this->db->prepare(
            "SELECT b.id, b.tipo, b.identificador, b.nombre_archivo, b.hash, b.estado, b.creado_en,
                    b.envio_estado,b.origen_envio,b.ticket,b.codigo_respuesta,b.mensaje_respuesta,b.cdr_contenido,b.cdr_sha256,
                    i.motivo
             FROM facturacion_baja_items i JOIN facturacion_bajas b ON b.id=i.baja_id
             WHERE i.comprobante_id=? AND b.estado<>'rechazado' ORDER BY b.id DESC LIMIT 1"
        );
        $q->execute([$comprobanteId]);

        return $q->fetch() ?: null;
    }

    /**
     * Reserva el siguiente identificador del día (RA-AAAAMMDD-N / RC-AAAAMMDD-N), arma y firma el XML con $construir
     * y lo guarda junto con sus comprobantes y un evento de trazabilidad en cada uno, en una sola transacción.
     *
     * @param array<int, string> $motivos comprobante_id => motivo
     * @param callable(string $identificador): array{nombre: string, xml: string, hash: string} $construir
     * @return array{id: int, identificador: string, nombre: string, hash: string}
     */
    public function guardar(string $tipo, string $prefijo, \DateTimeImmutable $generacion, \DateTimeImmutable $referencia, array $motivos, callable $construir, string $detalleEvento): array
    {
        $this->db->beginTransaction();
        try {
            // Con los comprobantes bloqueados, nadie más puede incluirlos en otra anulación a la vez
            $ids = array_keys($motivos);
            $marcas = implode(',', array_fill(0, count($ids), '?'));
            $q = $this->db->prepare("SELECT id, estado FROM facturacion_comprobantes WHERE id IN ({$marcas}) FOR UPDATE");
            $q->execute($ids);
            $estados = $q->fetchAll(PDO::FETCH_KEY_PAIR);
            foreach ($ids as $id) {
                if (!isset($estados[$id])) {
                    throw new \RuntimeException('No se encontró el comprobante.');
                }
                if ($existente = $this->bajaVigente($id)) {
                    throw new \RuntimeException("El comprobante ya está incluido en {$existente['identificador']}.");
                }
            }

            $q = $this->db->prepare('SELECT COALESCE(MAX(correlativo),0)+1 FROM facturacion_bajas WHERE tipo=? AND fecha_generacion=? FOR UPDATE');
            $q->execute([$tipo, $generacion->format('Y-m-d')]);
            $correlativo = (int) $q->fetchColumn();
            $identificador = $prefijo . '-' . $generacion->format('Ymd') . '-' . $correlativo;

            $archivo = $construir($identificador);

            $q = $this->db->prepare(
                "INSERT INTO facturacion_bajas (tipo,identificador,fecha_referencia,fecha_generacion,correlativo,nombre_archivo,xml,hash,estado,creado_en)
                 VALUES (?,?,?,?,?,?,?,?,'firmado',NOW())"
            );
            $q->execute([$tipo, $identificador, $referencia->format('Y-m-d'), $generacion->format('Y-m-d'), $correlativo, $archivo['nombre'], $archivo['xml'], $archivo['hash']]);
            $bajaId = (int) $this->db->lastInsertId();

            $item = $this->db->prepare('INSERT INTO facturacion_baja_items (baja_id,comprobante_id,motivo) VALUES (?,?,?)');
            // El comprobante conserva su estado hasta que SUNAT acepte la baja; el evento deja constancia del documento
            $evento = $this->db->prepare('INSERT INTO facturacion_eventos (comprobante_id,estado,detalle,metadatos,creado_en) VALUES (?,?,?,?,NOW())');
            foreach ($motivos as $id => $motivo) {
                $item->execute([$bajaId, $id, $motivo]);
                $evento->execute([$id, $estados[$id], str_replace('{identificador}', $identificador, $detalleEvento) . " Hash: {$archivo['hash']}", ContextoAuditoria::metadatosJson()]);
            }

            $this->db->commit();

            return ['id' => $bajaId, 'identificador' => $identificador, 'nombre' => $archivo['nombre'], 'hash' => $archivo['hash']];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }
}
