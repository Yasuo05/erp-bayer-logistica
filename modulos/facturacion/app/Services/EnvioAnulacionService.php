<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Registro;
use App\Repositories\AnulacionRepository;
use App\Repositories\FacturacionRepository;
use PDO;

/** Envío y consulta del ticket para una comunicación de baja o resumen diario. */
final class EnvioAnulacionService
{
    private PDO $db;

    public function __construct(
        private AnulacionRepository $repo = new AnulacionRepository(),
        private ?GatewayEnvio $gateway = null,
        private FacturacionRepository $comprobantes = new FacturacionRepository(),
    )
    { $this->db = Database::conexion(); }

    public function enviar(int $id, string $escenario = 'pendiente'): array
    {
        $baja = $this->repo->baja($id) ?? throw new \RuntimeException('No se encontró el documento de anulación.');
        if (!in_array($baja['envio_estado'], ['firmado','error_transitorio'], true) || ($baja['envio_estado']==='error_transitorio' && $baja['ticket'])) throw new \RuntimeException('La anulación ya tiene un envío registrado o conserva un ticket pendiente.');
        $gateway = $this->gateway ?? GatewayFactory::crear();
        $idem = hash('sha256', 'baja|' . $id . '|' . $baja['hash']);
        $q=$this->db->prepare("UPDATE facturacion_bajas SET envio_estado='enviando',origen_envio=?,solicitado_en=NOW() WHERE id=? AND envio_estado IN ('firmado','error_transitorio')"); $q->execute([$gateway->origen(), $id]);
        if($q->rowCount()===0) throw new \RuntimeException('Otra persona ya está enviando esta anulación. Actualiza la página.');
        try { $r = $gateway->enviar($baja['nombre_archivo'], $baja['xml'], $escenario, $idem); }
        catch (\Throwable $e) { $r = $this->fallo($e, 'GATEWAY_EXCEPTION'); }
        return $this->guardarRespuesta($baja, $r);
    }

    public function consultar(int $id): array
    {
        $baja = $this->repo->baja($id) ?? throw new \RuntimeException('No se encontró el documento de anulación.');
        if (!in_array($baja['envio_estado'], ['pendiente','error_transitorio'], true) || !$baja['ticket']) throw new \RuntimeException('No hay un ticket pendiente para consultar.');
        $gateway = $this->gateway ?? GatewayFactory::crear();
        if ($baja['origen_envio'] !== $gateway->origen()) throw new \RuntimeException('El ticket debe consultarse con el mismo proveedor que lo generó.');
        try { $r = $gateway->consultar($baja['ticket'], $baja['nombre_archivo']); }
        catch (\Throwable $e) { $r = $this->fallo($e, 'TICKET_QUERY_FAILED'); }
        $r['ticket'] = $r['ticket'] ?: $baja['ticket'];
        return $this->guardarRespuesta($baja, $r);
    }

    private function fallo(\Throwable $e, string $codigo): array
    {
        $ref = Registro::error($e);
        return ['tipo'=>'error_transitorio','codigo'=>$codigo,'mensaje'=>'Falla técnica (referencia '.$ref.'): '.$e->getMessage(),'ticket'=>null,'cdr'=>null];
    }

    private function guardarRespuesta(array $baja, array $r): array
    {
        if (($r['tipo'] ?? '') === 'error_transitorio') {
            $e = new \RuntimeException((string)($r['mensaje'] ?? 'Falla técnica del proveedor.'));
            if (!str_contains((string)$r['mensaje'],'referencia E-')) { $ref = Registro::error($e); $r['mensaje'] .= ' Referencia de log: '.$ref; }
        }
        $estados = ['pendiente'=>'pendiente','aceptado'=>'aceptado','observado'=>'observado','rechazado'=>'rechazado','error_transitorio'=>'error_transitorio'];
        $estado = $estados[$r['tipo']] ?? 'error_transitorio';
        $cdr = $r['cdr'] ?? null;
        try {
            $this->db->beginTransaction();
            $q = $this->db->prepare('UPDATE facturacion_bajas SET envio_estado=?,estado=?,ticket=?,codigo_respuesta=?,mensaje_respuesta=?,cdr_contenido=?,cdr_sha256=?,respondido_en=NOW() WHERE id=?');
            $q->execute([$estado, $estado === 'aceptado' ? 'aceptado' : ($estado === 'rechazado' ? 'rechazado' : 'enviado'), $r['ticket'] ?? null, $r['codigo'] ?? null, $r['mensaje'] ?? '', $cdr, $cdr !== null ? hash('sha256',$cdr) : null, $baja['id']]);
            if ($q->rowCount() !== 1) throw new \RuntimeException('No se pudo guardar la respuesta del proveedor para la anulación.');
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            $this->marcarFalloLocal((int)$baja['id'], $e);
            throw new \RuntimeException('No se pudo guardar la respuesta de la anulación; se registró la falla para permitir su recuperación.', 0, $e);
        }
        if ($estado === 'aceptado') {
            foreach ($baja['items'] as $item) {
                $this->comprobantes->cambiarEstado(
                    (int)$item['comprobante_id'],
                    'anulado',
                    "La anulación {$baja['identificador']} fue aceptada por el proveedor ({$r['codigo']})."
                );
            }
        }
        return $r;
    }

    /** Deja recuperable el documento si falla la persistencia después de que el proveedor respondió. */
    private function marcarFalloLocal(int $bajaId, \Throwable $error): void
    {
        $referencia = Registro::error($error);
        try {
            $q = $this->db->prepare("UPDATE facturacion_bajas SET envio_estado='error_transitorio',estado='enviado',codigo_respuesta='LOCAL_PERSIST_ERROR',mensaje_respuesta=?,respondido_en=NOW() WHERE id=? AND envio_estado='enviando'");
            $q->execute(['No se pudo persistir la respuesta del proveedor. Código de referencia: '.$referencia, $bajaId]);
        } catch (\Throwable $persistencia) {
            Registro::error($persistencia);
        }
    }
}
