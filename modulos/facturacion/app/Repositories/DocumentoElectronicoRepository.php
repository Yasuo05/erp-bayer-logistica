<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Services\ContextoAuditoria;
use App\Services\EstadoComprobanteService;
use PDO;

/** Datos del emisor y XML (sin firmar y firmado) de cada comprobante */
final class DocumentoElectronicoRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::conexion();
    }

    /** Empresa emisora. Hoy es el registro de demostración; luego vendrá del Grupo 4. */
    public function emisor(): ?array
    {
        return $this->db->query('SELECT * FROM demo_empresa WHERE id=1')->fetch() ?: null;
    }

    public function xml(int $comprobanteId): ?array
    {
        $q = $this->db->prepare('SELECT * FROM facturacion_xml WHERE comprobante_id=?');
        $q->execute([$comprobanteId]);

        return $q->fetch() ?: null;
    }

    public function historial(int $comprobanteId): array
    {
        $q = $this->db->prepare('SELECT id,nombre_archivo,hash,generado_en,firmado_en,reemplazado_en,motivo FROM facturacion_xml_historial WHERE comprobante_id=? ORDER BY id DESC');
        $q->execute([$comprobanteId]);

        return $q->fetchAll();
    }

    /** Guarda el XML recién generado y pasa el comprobante a "XML generado". */
    public function guardarGenerado(int $comprobanteId, string $nombreArchivo, string $xml, string $detalle): void
    {
        $this->enTransicion($comprobanteId, 'generado_xml', $detalle, function () use ($comprobanteId, $nombreArchivo, $xml): void {
            $this->guardarXml($comprobanteId, $nombreArchivo, $xml);
        });
    }

    /**
     * Corrección tras un rechazo: conserva el XML anterior en el historial, actualiza los datos de emisión
     * y guarda el XML regenerado (rechazado → generado_xml), todo en una sola transacción.
     */
    public function guardarCorreccion(int $comprobanteId, string $nombreArchivo, string $xml, array $datosEmision, string $motivo, string $detalle): void
    {
        $this->enTransicion($comprobanteId, 'generado_xml', $detalle, function () use ($comprobanteId, $nombreArchivo, $xml, $datosEmision, $motivo): void {
            $q = $this->db->prepare(
                'INSERT INTO facturacion_xml_historial (comprobante_id,nombre_archivo,xml,hash,generado_en,firmado_en,reemplazado_en,motivo)
                 SELECT comprobante_id,nombre_archivo,xml,hash,generado_en,firmado_en,NOW(),? FROM facturacion_xml WHERE comprobante_id=?'
            );
            $q->execute([$motivo, $comprobanteId]);
            $this->db->prepare('UPDATE facturacion_comprobantes SET datos_emision=? WHERE id=?')
                ->execute([json_encode($datosEmision, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $comprobanteId]);
            $this->guardarXml($comprobanteId, $nombreArchivo, $xml);
        });
    }

    private function guardarXml(int $comprobanteId, string $nombreArchivo, string $xml): void
    {
        $q = $this->db->prepare(
            'INSERT INTO facturacion_xml (comprobante_id,nombre_archivo,xml,hash,generado_en,firmado_en) VALUES (?,?,?,NULL,NOW(),NULL)
             ON DUPLICATE KEY UPDATE nombre_archivo=VALUES(nombre_archivo), xml=VALUES(xml), hash=NULL, generado_en=NOW(), firmado_en=NULL'
        );
        $q->execute([$comprobanteId, $nombreArchivo, $xml]);
    }

    /** Reemplaza el XML por su versión firmada y pasa el comprobante a "Firmado". */
    public function guardarFirmado(int $comprobanteId, string $xml, string $hash, string $detalle): void
    {
        $this->enTransicion($comprobanteId, 'firmado', $detalle, function () use ($comprobanteId, $xml, $hash): void {
            $q = $this->db->prepare('UPDATE facturacion_xml SET xml=?, hash=?, firmado_en=NOW() WHERE comprobante_id=?');
            $q->execute([$xml, $hash, $comprobanteId]);
        });
    }

    /** Valida el cambio de estado con el comprobante bloqueado y guarda todo junto con su evento de trazabilidad. */
    private function enTransicion(int $id, string $nuevoEstado, string $detalle, callable $guardar): void
    {
        $this->db->beginTransaction();
        try {
            $q = $this->db->prepare('SELECT estado FROM facturacion_comprobantes WHERE id=? FOR UPDATE');
            $q->execute([$id]);
            $estado = $q->fetchColumn();
            if ($estado === false) {
                throw new \RuntimeException('No se encontró el comprobante.');
            }
            (new EstadoComprobanteService())->validarTransicion((string) $estado, $nuevoEstado);

            $guardar();
            (new EstadoComprobanteService())->exigirCondiciones($id, $nuevoEstado);
            $this->db->prepare('UPDATE facturacion_comprobantes SET estado=? WHERE id=?')->execute([$nuevoEstado, $id]);
            $this->db->prepare('INSERT INTO facturacion_eventos (comprobante_id,estado,detalle,metadatos,creado_en) VALUES (?,?,?,?,NOW())')
                ->execute([$id, $nuevoEstado, $detalle, ContextoAuditoria::metadatosJson()]);
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }
}
