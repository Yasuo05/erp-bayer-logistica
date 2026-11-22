<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Registro;
use App\Repositories\DocumentoElectronicoRepository;
use App\Repositories\FacturacionRepository;
use PDO;

/** Orquesta intentos, respuesta del proveedor y trazabilidad persistente. */
final class EnvioService
{
    /** Segundos sin respuesta tras los cuales un intento «enviando» se considera interrumpido (el proceso se cortó) */
    public const SEGUNDOS_INTERRUMPIDO = 60;

    private PDO $db;
    public function __construct(
        private DocumentoElectronicoRepository $xmlRepo = new DocumentoElectronicoRepository(),
        private FacturacionRepository $comprobantes = new FacturacionRepository(),
        private ?GatewayEnvio $gateway = null,
    ) { $this->db = Database::conexion(); }

    public function enviar(int $id, string $escenario='aceptado', string $motivo=''): void
    {
        if (!in_array($escenario,['aceptado','observado','rechazado','pendiente','error_transitorio'],true)) throw new \InvalidArgumentException('Escenario de simulación inválido.');
        $comp=$this->comprobantes->comprobante($id) ?? throw new \RuntimeException('No se encontró el comprobante.');
        if (!in_array($comp['estado'],['firmado','pendiente_reintento'],true)) throw new \RuntimeException('Solo se envían comprobantes firmados o habilitados para reintento. Estado: '.estado_comprobante($comp['estado']));
        $doc=$this->xmlRepo->xml($id) ?? throw new \RuntimeException('No existe el XML firmado.');
        if (empty($doc['hash'])) throw new \RuntimeException('El XML no está firmado.');
        $q=$this->db->prepare('SELECT COUNT(*) FROM facturacion_envios WHERE comprobante_id=?');$q->execute([$id]);if((int)$q->fetchColumn()>=5)throw new \RuntimeException('Se alcanzó el máximo de 5 intentos. Revisa el caso y coordina el reenvío con soporte.');
        if ($comp['estado']==='pendiente_reintento' && trim($motivo)==='') throw new \InvalidArgumentException('Describe la corrección o motivo del reintento.');
        // Se elige antes de registrar el intento: cada intento guarda quién responde (simulador o proveedor)
        $gateway=$this->gateway??GatewayFactory::crear();$origen=$gateway->origen();

        $this->db->beginTransaction();
        try {
            $q=$this->db->prepare('SELECT estado FROM facturacion_comprobantes WHERE id=? FOR UPDATE'); $q->execute([$id]); $estado=(string)$q->fetchColumn();
            if (!in_array($estado,['firmado','pendiente_reintento'],true)) throw new \RuntimeException('El estado cambió mientras se preparaba el envío. Actualiza la página.');
            $q=$this->db->prepare("SELECT COUNT(*) FROM facturacion_envios WHERE comprobante_id=? AND estado='enviando'");$q->execute([$id]);if((int)$q->fetchColumn()>0)throw new \RuntimeException('Hay un intento sin respuesta registrada. Si quedó interrumpido, usa «Recuperar intento»: se consulta con la misma clave y no se duplica el envío.');
            $q=$this->db->prepare('SELECT COALESCE(MAX(intento),0)+1 FROM facturacion_envios WHERE comprobante_id=?');$q->execute([$id]);$n=(int)$q->fetchColumn();
            $idem=hash('sha256', $id.'|'.$n.'|'.$doc['hash']);
            $q=$this->db->prepare("INSERT INTO facturacion_envios (comprobante_id,intento,origen,idempotencia,estado,solicitado_en) VALUES (?,?,?,?,'enviando',NOW())");$q->execute([$id,$n,$origen,$idem]);$envio=(int)$this->db->lastInsertId();
            if ($estado==='pendiente_reintento') $this->evento($envio,'reintento','info',null,'Reintento '.$n.' solicitado. Motivo: '.trim($motivo));
            $this->evento($envio,'solicitud','info',null,'Envío registrado para '.$doc['nombre_archivo'].'. Clave de control: '.$idem.'. Respuesta: '.($origen==='simulado'?'simulada, sin validez tributaria':'proveedor externo').'.');
            $this->db->commit();
        } catch (\Throwable $e) { if($this->db->inTransaction())$this->db->rollBack(); throw $e; }

        try { $r=$gateway->enviar($doc['nombre_archivo'],$doc['xml'],$escenario,$idem); }
        catch (\Throwable $e) { $ref=Registro::error($e); $r=['tipo'=>'error_transitorio','codigo'=>'GATEWAY_EXCEPTION','mensaje'=>'Fallo de transporte (referencia '.$ref.'): '.$e->getMessage(),'ticket'=>null,'cdr'=>null]; }
        $r=$this->registrarFallaGateway($r);
        try{$this->registrarRespuesta($id,$envio,$r);}catch(\Throwable $e){$this->marcarFalloLocal($envio,$e);throw $e;}
    }

    /**
     * Acción que corresponde a cada situación del comprobante: [qué hacer, por qué].
     * Distingue la falla técnica (se reenvía el mismo XML) de la corrección de contenido (se regenera el XML).
     */
    public static function accionSugerida(string $estado, bool $interrumpido=false, bool $observacionRevisada=false): ?array
    {
        if($interrumpido)return ['Recuperar el intento','El envío se cortó sin guardar la respuesta: se consulta de nuevo con la misma clave, sin duplicarlo.'];
        return match($estado){
            'firmado'=>['Enviar','El XML firmado está listo para enviarse.'],
            'enviado'=>['Consultar el ticket','SUNAT aún está procesando el comprobante.'],
            'pendiente_reintento'=>['Reintentar el envío','Falla técnica: se reenvía el mismo XML firmado, sin cambios.'],
            'rechazado'=>['Corregir y regenerar el XML','Rechazo de contenido: corrige el dato en su origen, regenera y firma el XML, y vuelve a enviarlo.'],
            'observado'=>$observacionRevisada?null:['Revisar la observación','El comprobante es válido y no se reenvía: registra qué se revisó para corregirlo en los siguientes.'],
            default=>null,
        };
    }

    /**
     * Una observación no se corrige reenviando: el comprobante ya es válido. Se registra qué se revisó
     * (y qué se corregirá en los siguientes comprobantes) y deja de figurar como pendiente de atención.
     */
    public function registrarRevisionObservacion(int $id,string $nota): void
    {
        $nota=trim($nota);if($nota===''||mb_strlen($nota)>500)throw new \InvalidArgumentException('Indica qué se revisó (máximo 500 caracteres).');
        $comp=$this->comprobantes->comprobante($id)??throw new \RuntimeException('No se encontró el comprobante.');
        if($comp['estado']!=='observado')throw new \RuntimeException('Solo se revisan observaciones de comprobantes observados.');
        $q=$this->db->prepare("SELECT id FROM facturacion_envios WHERE comprobante_id=? AND tipo_respuesta='observado' ORDER BY intento DESC LIMIT 1");$q->execute([$id]);$envio=$q->fetchColumn();
        if(!$envio)throw new \RuntimeException('No se encontró la respuesta con observación.');
        if($this->observacionRevisada($id))throw new \RuntimeException('La observación ya fue revisada.');
        $this->evento((int)$envio,'observacion_revisada','info',null,'Observación revisada: '.$nota);
    }

    public function observacionRevisada(int $id): bool
    {
        $q=$this->db->prepare("SELECT COUNT(*) FROM facturacion_envio_eventos ev JOIN facturacion_envios e ON e.id=ev.envio_id WHERE e.comprobante_id=? AND ev.etapa='observacion_revisada'");$q->execute([$id]);
        return (int)$q->fetchColumn()>0;
    }

    /** Intento que quedó en «enviando» más de SEGUNDOS_INTERRUMPIDO: el proceso se cortó antes de guardar la respuesta */
    public function intentoInterrumpido(int $id): ?array
    {
        $q=$this->db->prepare("SELECT * FROM facturacion_envios WHERE comprobante_id=? AND estado='enviando' AND solicitado_en < NOW() - INTERVAL ".self::SEGUNDOS_INTERRUMPIDO." SECOND ORDER BY intento DESC LIMIT 1");$q->execute([$id]);
        return $q->fetch()?:null;
    }

    /**
     * Recupera un intento interrumpido sin generar un envío duplicado: reutiliza el mismo intento y su clave
     * de idempotencia, de modo que el proveedor reconoce la solicitud original en lugar de registrar otra.
     */
    public function recuperarIntento(int $id,string $escenario='aceptado'): array
    {
        if(!in_array($escenario,['aceptado','observado','rechazado','pendiente','error_transitorio'],true))throw new \InvalidArgumentException('Escenario de simulación inválido.');
        $envio=$this->intentoInterrumpido($id)??throw new \RuntimeException('No hay un intento interrumpido para recuperar.');
        $doc=$this->xmlRepo->xml($id)??throw new \RuntimeException('No existe el XML firmado.');
        // La clave incluye el hash del XML: si el XML cambió, ya no es la misma solicitud
        if(empty($doc['hash'])||!hash_equals((string)$envio['idempotencia'],hash('sha256',$id.'|'.$envio['intento'].'|'.$doc['hash'])))throw new \RuntimeException('El XML cambió desde el intento interrumpido; no se puede recuperar con la misma clave.');
        $gateway=$this->gateway??GatewayFactory::crear();
        if($envio['origen']!==$gateway->origen())throw new \RuntimeException('El intento se hizo con '.($envio['origen']==='simulado'?'el simulador':'el proveedor externo').' y no puede recuperarse con otro origen.');
        // Se «reclama» el intento para que dos personas no lo recuperen a la vez
        $q=$this->db->prepare("UPDATE facturacion_envios SET solicitado_en=NOW() WHERE id=? AND estado='enviando' AND solicitado_en < NOW() - INTERVAL ".self::SEGUNDOS_INTERRUMPIDO." SECOND");$q->execute([$envio['id']]);
        if($q->rowCount()===0)throw new \RuntimeException('Otra persona ya está recuperando este intento. Actualiza la página.');
        $this->evento((int)$envio['id'],'recuperacion','observacion',null,'Intento '.$envio['intento'].' interrumpido sin respuesta registrada. Se consulta de nuevo con la misma clave de control; no se crea un intento nuevo.');
        try { $r=$gateway->enviar($doc['nombre_archivo'],$doc['xml'],$escenario,(string)$envio['idempotencia']); }
        catch (\Throwable $e) { $ref=Registro::error($e); $r=['tipo'=>'error_transitorio','codigo'=>'GATEWAY_EXCEPTION','mensaje'=>'Fallo de transporte (referencia '.$ref.'): '.$e->getMessage(),'ticket'=>null,'cdr'=>null]; }
        $r=$this->registrarFallaGateway($r);
        try{$this->registrarRespuesta($id,(int)$envio['id'],$r);}catch(\Throwable $e){$this->marcarFalloLocal((int)$envio['id'],$e);throw $e;}
        return $r;
    }

    /** Condición SQL (sobre el comprobante f) de lo que requiere atención: rechazos, fallas técnicas, observaciones sin revisar e intentos interrumpidos */
    private static function sqlIncidencia(): string
    {
        return "(f.estado IN ('rechazado','pendiente_reintento')
            OR (f.estado='observado' AND NOT EXISTS (SELECT 1 FROM facturacion_envio_eventos ev JOIN facturacion_envios eo ON eo.id=ev.envio_id WHERE eo.comprobante_id=f.id AND ev.etapa='observacion_revisada'))
            OR EXISTS (SELECT 1 FROM facturacion_envios ei WHERE ei.comprobante_id=f.id AND ei.estado='enviando' AND ei.solicitado_en < NOW() - INTERVAL ".self::SEGUNDOS_INTERRUMPIDO." SECOND))";
    }

    public function consultarTicket(int $id): array
    {
        $comp=$this->comprobantes->comprobante($id)??throw new \RuntimeException('No se encontró el comprobante.');if($comp['estado']!=='enviado')throw new \RuntimeException('Solo se consulta ticket de comprobantes enviados.');
        $doc=$this->xmlRepo->xml($id)??throw new \RuntimeException('No se encontró el XML.');$q=$this->db->prepare("SELECT * FROM facturacion_envios WHERE comprobante_id=? AND estado='pendiente' ORDER BY intento DESC LIMIT 1");$q->execute([$id]);$envio=$q->fetch();if(!$envio||!$envio['ticket'])throw new \RuntimeException('No existe un ticket pendiente para consultar.');
        // Un ticket solo se consulta donde se generó: así una respuesta simulada nunca se mezcla con una real
        $gateway=$this->gateway??GatewayFactory::crear();
        if($envio['origen']!==$gateway->origen())throw new \RuntimeException($envio['origen']==='simulado'?'Este ticket proviene de un envío simulado: no puede consultarse en el proveedor externo.':'Este ticket se generó en el proveedor externo: no puede consultarse con el simulador.');
        try{$r=$gateway->consultar($envio['ticket'],$doc['nombre_archivo']);}
        catch(\Throwable $e){$ref=Registro::error($e);$r=['tipo'=>'error_transitorio','codigo'=>'TICKET_QUERY_FAILED','mensaje'=>'No se pudo consultar el ticket (referencia '.$ref.'): '.mb_substr($e->getMessage(),0,1000),'ticket'=>$envio['ticket'],'cdr'=>null];}
        $r=$this->registrarFallaGateway($r);
        $r['ticket']=$r['ticket']?:$envio['ticket'];
        if($r['tipo']==='error_transitorio'){
            $this->evento((int)$envio['id'],'consulta_ticket','error',$r['codigo']?:null,$r['mensaje'].' Se conserva el ticket para volver a consultar; no se reenvió el comprobante.');
            return $r;
        }
        $this->registrarRespuesta($id,(int)$envio['id'],$r);
        return $r;
    }

    private function registrarRespuesta(int $id,int $envio,array $r): void
    {
        $map=['aceptado'=>'aceptado','observado'=>'observado','rechazado'=>'rechazado','pendiente'=>'enviado','error_transitorio'=>'pendiente_reintento'];
        $nuevo=$map[$r['tipo']]??'pendiente_reintento'; $this->db->beginTransaction();
        try {
            $q=$this->db->prepare('SELECT estado FROM facturacion_comprobantes WHERE id=? FOR UPDATE');$q->execute([$id]);$actual=(string)$q->fetchColumn();
            // Número de intento del comprobante (1, 2, 3…), no el id interno del registro de envío
            $q=$this->db->prepare('SELECT intento FROM facturacion_envios WHERE id=?');$q->execute([$envio]);$intento=(int)$q->fetchColumn();
            $q=$this->db->prepare('UPDATE facturacion_envios SET estado=?,ticket=?,codigo_respuesta=?,mensaje_respuesta=?,tipo_respuesta=?,cdr_nombre=?,cdr_contenido=?,cdr_sha256=?,respondido_en=NOW(),proximo_reintento_en=? WHERE id=?');
            $cdr=$r['cdr'];$cdrNombre=$cdr!==null?'R-'.$id.'-'.$envio.'.zip':null;
            $proximo=$r['tipo']==='error_transitorio'?date('Y-m-d H:i:s',time()+60):null;
            $q->execute([$r['tipo'],$r['ticket'],$r['codigo'],$r['mensaje'],$r['tipo'],$cdrNombre,$cdr,$cdr!==null?hash('sha256',$cdr):null,$proximo,$envio]);
            $nivel=$r['tipo']==='rechazado'||$r['tipo']==='error_transitorio'?'error':($r['tipo']==='observado'?'observacion':'info');
            $this->evento($envio,'respuesta',$nivel,$r['codigo']?:null,$r['mensaje']);
            if($cdr!==null)$this->evento($envio,'cdr','info',null,'CDR guardado: '.$cdrNombre.' (SHA-256 '.hash('sha256',$cdr).').');
            elseif(in_array($r['tipo'],['aceptado','observado','rechazado'],true))$this->evento($envio,'cdr_ausente','observacion',null,'El proveedor devolvió una respuesta final sin CDR. Verifica el portal/proveedor antes de considerar completo el expediente.');
            if(in_array($actual,['firmado','pendiente_reintento'],true)){
                (new EstadoComprobanteService())->validarTransicion($actual,'enviado');
                (new EstadoComprobanteService())->exigirCondiciones($id,'enviado');
                $this->db->prepare('UPDATE facturacion_comprobantes SET estado=? WHERE id=?')->execute(['enviado',$id]);
                $this->db->prepare('INSERT INTO facturacion_eventos (comprobante_id,estado,detalle,metadatos,creado_en) VALUES (?,?,?,?,NOW())')->execute([$id,'enviado','Intento '.$intento.' transmitido al gateway.',ContextoAuditoria::metadatosJson()]);
                $actual='enviado';
            }
            if($actual!==$nuevo){(new EstadoComprobanteService())->validarTransicion($actual,$nuevo);(new EstadoComprobanteService())->exigirCondiciones($id,$nuevo);$this->db->prepare('UPDATE facturacion_comprobantes SET estado=? WHERE id=?')->execute([$nuevo,$id]);$this->db->prepare('INSERT INTO facturacion_eventos (comprobante_id,estado,detalle,metadatos,creado_en) VALUES (?,?,?,?,NOW())')->execute([$id,$nuevo,'Intento '.$intento.': '.$r['mensaje'],ContextoAuditoria::metadatosJson()]);}
            $this->db->commit();
        }catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
    private function evento(int $envio,string $etapa,string $nivel,?string $codigo,string $detalle): void
    { $q=$this->db->prepare('INSERT INTO facturacion_envio_eventos (envio_id,etapa,nivel,codigo,detalle,metadatos,creado_en) VALUES (?,?,?,?,?,?,NOW())');$q->execute([$envio,$etapa,$nivel,$codigo,$detalle,ContextoAuditoria::metadatosJson()]); }
    private function marcarFalloLocal(int $envio,\Throwable $error): void
    { try{$this->db->prepare("UPDATE facturacion_envios SET estado='error_transitorio',tipo_respuesta='error_transitorio',codigo_respuesta='LOCAL_PERSIST_ERROR',mensaje_respuesta=?,respondido_en=NOW(),proximo_reintento_en=NOW() WHERE id=? AND estado='enviando'")->execute([mb_substr('No se pudo persistir la respuesta: '.$error->getMessage(),0,4000),$envio]);$this->evento($envio,'persistencia','error','LOCAL_PERSIST_ERROR','Se conservó el intento como error; verifica la respuesta del proveedor antes de reenviar.');}catch(\Throwable){/* el registro original enviando permanece y bloquea duplicados */} }

    private function registrarFallaGateway(array $r): array
    {
        if (($r['tipo']??'')==='error_transitorio' && !str_contains((string)($r['mensaje']??''),'referencia E-')) {
            $ref=Registro::error(new \RuntimeException((string)($r['mensaje']??'Falla técnica del proveedor.')));
            $r['mensaje']=rtrim((string)($r['mensaje']??'')).' Referencia de log: '.$ref;
        }
        return $r;
    }

    /** Bandeja de envíos; $limite y $offset paginan (sin límite devuelve todo) */
    public function bandeja(?string $estado=null,string $buscar='',?int $limite=null,int $offset=0): array
    {
        $sql='SELECT f.id comprobante_id,f.venta_id,f.comprobante_referenciado_id,f.tipo,f.serie,f.numero,f.estado comprobante_estado,'.sprintf(\App\Repositories\ConsultaRepository::CLIENTE,'nombre').' cliente,r.serie serie_referencia,r.numero numero_referencia,e.id envio_id,e.intento,e.origen origen_respuesta,e.estado envio_estado,e.codigo_respuesta,e.mensaje_respuesta,e.ticket,e.cdr_nombre,e.solicitado_en,e.respondido_en,'
            ."(e.estado='enviando' AND e.solicitado_en < NOW() - INTERVAL ".self::SEGUNDOS_INTERRUMPIDO." SECOND) interrumpido,"
            ."EXISTS(SELECT 1 FROM facturacion_envio_eventos ev JOIN facturacion_envios eo ON eo.id=ev.envio_id WHERE eo.comprobante_id=f.id AND ev.etapa='observacion_revisada') observacion_revisada"
            .' FROM facturacion_comprobantes f JOIN demo_clientes c ON c.id=f.cliente_id LEFT JOIN facturacion_comprobantes r ON r.id=f.comprobante_referenciado_id LEFT JOIN facturacion_envios e ON e.id=(SELECT MAX(e2.id) FROM facturacion_envios e2 WHERE e2.comprobante_id=f.id)';
        [$where,$args]=$this->filtroBandeja($estado,$buscar);
        $sql.=$where.' ORDER BY f.id DESC'.($limite!==null?' LIMIT '.max(1,$limite).' OFFSET '.max(0,$offset):'');$q=$this->db->prepare($sql);$q->execute($args);return $q->fetchAll();
    }

    /** Cuántos comprobantes tiene la bandeja con esos filtros (para la paginación) */
    public function contarBandeja(?string $estado=null,string $buscar=''): int
    {
        [$where,$args]=$this->filtroBandeja($estado,$buscar);
        $q=$this->db->prepare('SELECT COUNT(*) FROM facturacion_comprobantes f JOIN demo_clientes c ON c.id=f.cliente_id'.$where);$q->execute($args);return (int)$q->fetchColumn();
    }

    /** @return array{0:string,1:array} « WHERE …» de la bandeja (sobre f y c) y sus valores */
    private function filtroBandeja(?string $estado,string $buscar): array
    {
        $where=[];$args=[];if($estado==='incidencias')$where[]=self::sqlIncidencia();elseif($estado==='cola')$where[]="f.estado IN ('firmado','enviado')";elseif($estado==='proceso')$where[]="f.estado IN ('firmado','enviado','pendiente_reintento')";elseif($estado!==null&&$estado!==''){$where[]='f.estado=?';$args[]=$estado;}
        if($buscar!==''){$where[]='('.sprintf(\App\Repositories\ConsultaRepository::CLIENTE,'nombre').' LIKE ? OR CONCAT(f.serie,"-",LPAD(f.numero,8,"0")) LIKE ? OR CONCAT(f.serie,"-",f.numero) LIKE ? OR CAST(f.numero AS CHAR) LIKE ?)';$like='%'.$buscar.'%';array_push($args,$like,$like,$like,$like);}
        $where[]='1=1'.sql_sede_comprobante('f'); // sede activa (G4)
        return [' WHERE '.implode(' AND ',$where),$args];
    }
    public function resumen(): array
    { $r=$this->db->query("SELECT SUM(f.estado='firmado') listos,SUM(f.estado='enviado') esperando,SUM(".self::sqlIncidencia().") incidencias,SUM(f.estado='aceptado') aceptados FROM facturacion_comprobantes f")->fetch();return array_map('intval',$r?:[]); }
    public function intentos(int $id): array
    { $q=$this->db->prepare('SELECT * FROM facturacion_envios WHERE comprobante_id=? ORDER BY intento DESC');$q->execute([$id]);$rows=$q->fetchAll();$ev=$this->db->prepare('SELECT * FROM facturacion_envio_eventos WHERE envio_id=? ORDER BY id');foreach($rows as &$row){$ev->execute([$row['id']]);$row['eventos']=$ev->fetchAll();}return $rows; }
    /** CDR con los datos del comprobante e intento al que pertenece, para verificar su asociación antes de entregarlo */
    public function cdr(int $envio): ?array
    { $q=$this->db->prepare('SELECT e.id,e.comprobante_id,e.intento,e.origen,e.cdr_nombre,e.cdr_contenido,e.cdr_sha256,f.serie,f.numero,x.nombre_archivo FROM facturacion_envios e JOIN facturacion_comprobantes f ON f.id=e.comprobante_id LEFT JOIN facturacion_xml x ON x.comprobante_id=e.comprobante_id WHERE e.id=? AND e.cdr_contenido IS NOT NULL');$q->execute([$envio]);return $q->fetch()?:null; }
}
