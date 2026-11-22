<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Services\CalculoImportes;
use App\Services\ContextoAuditoria;
use App\Services\DocumentoIdentidad;
use App\Services\EstadoComprobanteService;
use App\Services\SeriesService;
use PDO;

final class FacturacionRepository
{
    /** Estados en los que una factura o boleta puede recibir notas: ya fue aceptada (con o sin observaciones) */
    public const ESTADOS_REFERENCIA_NOTA=['aceptado','observado'];
    /** Notas que ya no tienen efecto y no cuentan para el saldo (una nota rechazada sí cuenta: puede corregirse y reenviarse) */
    public const ESTADOS_SIN_EFECTO=['anulado_local','anulado'];

    private PDO $db;

    public function __construct() { $this->db = Database::conexion(); }

    /**
     * Ventas confirmadas del ERP (Grupo 8) que aún no tienen comprobante. $buscar filtra por cliente,
     * documento o número de venta; $limite y $offset paginan (sin límite devuelve todas).
     */
    public function ventasPendientes(string $buscar='', ?int $limite=null, int $offset=0): array
    {
        [$where,$args]=$this->condicionVentas($buscar);
        $q=$this->db->prepare("SELECT v.id, v.numero, v.fecha, cl.razon_social cliente, cl.tipo_doc tipo_documento, cl.nro_doc numero_documento,
            COUNT(d.id) lineas, SUM(d.subtotal-d.descuento) subtotal
            FROM ventas v JOIN clientes cl ON cl.id=v.cliente_id
            JOIN venta_detalles d ON d.venta_id=v.id LEFT JOIN facturacion_comprobantes f ON f.venta_erp_vigente=v.id
            WHERE v.estado='confirmada' AND f.id IS NULL{$where}
            GROUP BY v.id,v.numero,v.fecha,cl.razon_social,cl.tipo_doc,cl.nro_doc ORDER BY v.id"
            .($limite!==null?' LIMIT '.max(1,$limite).' OFFSET '.max(0,$offset):''));
        $q->execute($args);return $q->fetchAll();
    }

    public function contarVentasPendientes(string $buscar=''): int
    {
        [$where,$args]=$this->condicionVentas($buscar);
        $q=$this->db->prepare("SELECT COUNT(*) FROM ventas v JOIN clientes cl ON cl.id=v.cliente_id LEFT JOIN facturacion_comprobantes f ON f.venta_erp_vigente=v.id
            WHERE v.estado='confirmada' AND f.id IS NULL AND EXISTS (SELECT 1 FROM venta_detalles d WHERE d.venta_id=v.id){$where}");
        $q->execute($args);return (int)$q->fetchColumn();
    }

    /** Búsqueda en ventas pendientes: razón social, documento o número de venta (VEN001-00000001) */
    private function condicionVentas(string $buscar): array
    {
        // Sede activa (G4): solo las ventas de la sede
        $sede=sede_activa_facturacion();$condSede=$sede!==null?' AND v.sucursal_id='.$sede:'';
        $buscar=trim($buscar);if($buscar==='')return [$condSede,[]];
        $like='%'.addcslashes($buscar,'\\%_').'%';
        return [$condSede.' AND (cl.razon_social LIKE ? OR cl.nro_doc LIKE ? OR v.numero LIKE ?)',[$like,$like,$like]];
    }

    /** Cabecera de la venta del ERP con su cliente. */
    private function sqlVentaErp(string $bloqueo=''): string
    {
        return "SELECT v.id, v.numero, v.fecha, v.estado, v.moneda, v.sucursal_id, v.total,
                cl.id cliente_id, cl.razon_social cliente, cl.tipo_doc tipo_documento, cl.nro_doc numero_documento
            FROM ventas v JOIN clientes cl ON cl.id=v.cliente_id
            WHERE v.id=? AND v.estado='confirmada'{$bloqueo}";
    }

    /** Líneas de la venta del ERP con el código SUNAT de su unidad de medida (catálogo 03, maestro de G5). */
    private function detallesVentaErp(int $ventaId, string $bloqueo=''): array
    {
        $q=$this->db->prepare("SELECT d.codigo_producto, d.descripcion, d.cantidad, u.codigo_sunat unidad_medida, u.nombre unidad_nombre,
                d.precio_unitario, d.descuento, d.tasa_impuesto
            FROM venta_detalles d LEFT JOIN unidades_medida u ON u.id=d.unidad_id WHERE d.venta_id=? ORDER BY d.linea{$bloqueo}");
        $q->execute([$ventaId]);return $q->fetchAll();
    }

    /** Venta confirmada que aún no tiene comprobante (una venta se factura una sola vez) */
    public function venta(int $id): ?array
    {
        $q=$this->db->prepare($this->sqlVentaErp()." AND NOT EXISTS (SELECT 1 FROM facturacion_comprobantes f WHERE f.venta_erp_vigente=v.id)");
        $q->execute([$id]); $venta=$q->fetch();
        if (!$venta) return null;
        $venta['detalles']=$this->detallesVentaErp($id);
        return $venta;
    }

    /** Lee y bloquea la venta dentro de la misma transacción que persiste el comprobante. */
    public function emitirVentaConRevision(int $ventaId, string $tipo, float $tasa, callable $validarRevision): int
    {
        $this->db->beginTransaction();
        try {
            $q = $this->db->prepare($this->sqlVentaErp(' FOR UPDATE'));
            $q->execute([$ventaId]);
            $venta = $q->fetch();
            if (!$venta) throw new \RuntimeException('La venta no existe o ya no está confirmada.');

            $q = $this->db->prepare('SELECT id FROM facturacion_comprobantes WHERE venta_erp_vigente=? LIMIT 1 FOR UPDATE');
            $q->execute([$ventaId]);
            if ($q->fetchColumn() !== false) throw new \RuntimeException('La venta ya fue facturada.');

            $venta['detalles'] = $this->detallesVentaErp($ventaId, ' FOR UPDATE');
            if (!$venta['detalles']) throw new \RuntimeException('La venta no tiene líneas para emitir.');

            $validarRevision($venta);
            $receptor = $this->receptor((string) $venta['tipo_documento'], (string) $venta['numero_documento'], (string) $venta['cliente']);
            $id = $this->insertar($tipo, ['venta_erp_id' => $ventaId, 'cliente_id' => $receptor, 'sucursal_id' => (int) $venta['sucursal_id']], $venta['detalles'], $tasa, null, null);
            // Cobros (G8) de la venta registrados antes de facturar, o vinculados a un comprobante ya anulado: pasan a este comprobante
            $this->db->prepare("UPDATE cobranzas c
                    JOIN cobranza_aplicaciones a ON a.cobranza_id = c.id
                    LEFT JOIN cuenta_cuotas cc ON cc.id = a.cuota_id LEFT JOIN cuentas_por_cobrar cx ON cx.id = cc.cuenta_id
                    LEFT JOIN facturacion_comprobantes previo ON previo.id = c.comprobante_id
                SET c.comprobante_id = ?
                WHERE COALESCE(a.venta_contado_id, cx.venta_id) = ? AND (c.comprobante_id IS NULL OR previo.venta_erp_vigente IS NULL)")
                ->execute([$id, $ventaId]);
            $this->db->commit();

            return $id;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            if ($e instanceof \PDOException && str_contains($e->getMessage(), 'uq_venta_erp_vigente')) {
                throw new \RuntimeException('La venta ya fue facturada.', 0, $e);
            }
            throw $e;
        }
    }

    /**
     * Receptor del comprobante: copia del cliente del ERP (Grupo 5) en el momento de emitir.
     * Se busca por tipo y número de documento; si cambió la razón social se actualiza.
     * Debe llamarse dentro de una transacción.
     */
    private function receptor(string $tipo, string $documento, string $nombre): int
    {
        $q=$this->db->prepare('SELECT id,nombre FROM demo_clientes WHERE tipo_documento=? AND numero_documento=? LIMIT 1 FOR UPDATE');
        $q->execute([$tipo,$documento]);$r=$q->fetch();
        if($r){
            if($r['nombre']!==$nombre)$this->db->prepare('UPDATE demo_clientes SET nombre=? WHERE id=?')->execute([$nombre,$r['id']]);
            return (int)$r['id'];
        }
        $this->db->prepare('INSERT INTO demo_clientes (tipo_documento,numero_documento,nombre) VALUES (?,?,?)')->execute([$tipo,$documento,$nombre]);
        return (int)$this->db->lastInsertId();
    }

    public function comprobantes(?string $tipo=null): array
    {
        $sql='SELECT f.*,c.nombre cliente,c.tipo_documento,c.numero_documento,r.serie serie_referencia,r.numero numero_referencia FROM facturacion_comprobantes f JOIN demo_clientes c ON c.id=f.cliente_id LEFT JOIN facturacion_comprobantes r ON r.id=f.comprobante_referenciado_id';
        $args=[]; $sql.=' WHERE 1=1'.sql_sede_comprobante('f'); if($tipo){$sql.=' AND f.tipo=?';$args[]=$tipo;} $sql.=' ORDER BY f.id DESC';
        $q=$this->db->prepare($sql);$q->execute($args);return array_map(self::conClienteDeEmision(...),$q->fetchAll());
    }

    /**
     * El cliente se muestra tal como estaba al emitir (datos_emision), no como figure hoy en el maestro:
     * así un cambio posterior en el módulo de clientes no altera el documento emitido.
     */
    private static function conClienteDeEmision(array $row): array
    {
        $datos=is_array($row['datos_emision']??null)?$row['datos_emision']:json_decode((string)($row['datos_emision']??''),true);
        if(is_array($datos)&&isset($datos['cliente']['nombre'],$datos['cliente']['tipo_documento'],$datos['cliente']['numero_documento'])){
            $row['cliente']=$datos['cliente']['nombre'];$row['tipo_documento']=$datos['cliente']['tipo_documento'];$row['numero_documento']=$datos['cliente']['numero_documento'];
        }
        return $row;
    }

    public function comprobante(int $id): ?array
    {
        $q=$this->db->prepare('SELECT f.*,c.nombre cliente,c.tipo_documento,c.numero_documento,r.tipo tipo_referencia,r.serie serie_referencia,r.numero numero_referencia FROM facturacion_comprobantes f JOIN demo_clientes c ON c.id=f.cliente_id LEFT JOIN facturacion_comprobantes r ON r.id=f.comprobante_referenciado_id WHERE f.id=?');$q->execute([$id]);$row=$q->fetch();
        if(!$row)return null;
        $row['datos_emision']=isset($row['datos_emision'])?json_decode((string)$row['datos_emision'],true):null;
        $row=self::conClienteDeEmision($row);
        $q=$this->db->prepare('SELECT * FROM facturacion_detalles WHERE comprobante_id=?');$q->execute([$id]);$row['detalles']=$q->fetchAll();
        $q=$this->db->prepare('SELECT * FROM facturacion_eventos WHERE comprobante_id=? ORDER BY id');$q->execute([$id]);$row['eventos']=$q->fetchAll();return $row;
    }

    public function anularBorradorDemo(int $id, string $motivo): void
    {
        $motivo=trim($motivo);
        if($motivo===''||mb_strlen($motivo)>500)throw new \InvalidArgumentException('Indica el motivo de anulación (máximo 500 caracteres).');
        $this->cambiarEstado($id, 'anulado_local', 'Anulación registrada. Motivo: '.$motivo);
    }

    public function cambiarEstado(int $id, string $nuevoEstado, string $detalle): void
    {
        $detalleTexto=trim($detalle);
        if($detalleTexto===''||mb_strlen($detalleTexto)>1000)throw new \InvalidArgumentException('Describe el cambio de estado (máximo 1000 caracteres).');
        $this->db->beginTransaction();
        try{
            $q=$this->db->prepare('SELECT estado FROM facturacion_comprobantes WHERE id=? FOR UPDATE');$q->execute([$id]);$estado=$q->fetchColumn();
            if($estado===false)throw new \RuntimeException('No se encontró el comprobante.');
            (new EstadoComprobanteService())->validarTransicion((string)$estado,$nuevoEstado);
            (new EstadoComprobanteService())->exigirCondiciones($id,$nuevoEstado);
            $this->db->prepare('UPDATE facturacion_comprobantes SET estado=? WHERE id=?')->execute([$nuevoEstado,$id]);
            $evento=$this->db->prepare('INSERT INTO facturacion_eventos (comprobante_id,estado,detalle,metadatos,creado_en) VALUES (?,?,?,?,NOW())');
            $evento->execute([$id,$nuevoEstado,$detalleTexto,ContextoAuditoria::metadatosJson()]);
            $this->db->commit();
        }catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    /**
     * Emisor (Grupo 4) y cliente (Grupo 5) tal como están ahora: se copian al comprobante cuando se emite.
     * Si el comprobante viene de una venta del ERP, el emisor es la empresa de la sucursal de esa venta
     * (RUC, razón social, nombre comercial, dirección, ubigeo del distrito y código de establecimiento SUNAT).
     * Para comprobantes antiguos sin venta del ERP se mantienen los datos de configuración (demo_empresa).
     */
    public function datosEmisionActuales(int $clienteId, ?int $ventaErpId=null): array
    {
        $emisor=$this->db->query('SELECT ruc,razon_social,nombre_comercial,ubigeo,departamento,provincia,distrito,direccion,codigo_establecimiento FROM demo_empresa WHERE id=1')->fetch();
        if(!$emisor)throw new \RuntimeException('Faltan los datos de la empresa emisora (Grupo 4): completa la empresa en Organización › Empresas o importa la base completa del ERP (sql/bayer_pucchun_erp_v2.sql).');
        if($ventaErpId){
            $q=$this->db->prepare('SELECT e.ruc, e.razon_social, e.nombre_comercial, s.direccion, di.nombre distrito, pr.nombre provincia, de.nombre departamento,
                    di.ubigeo, s.codigo_establecimiento, s.nombre sucursal
                FROM ventas v JOIN sucursales s ON s.id=v.sucursal_id JOIN empresas e ON e.id=s.empresa_id
                LEFT JOIN distritos di ON di.id=s.distrito_id LEFT JOIN provincias pr ON pr.id=di.provincia_id LEFT JOIN departamentos de ON de.id=pr.departamento_id
                WHERE v.id=?');
            $q->execute([$ventaErpId]);$erp=$q->fetch();
            if(!$erp)throw new \RuntimeException('No se encontró la empresa emisora de la sucursal de la venta (Grupo 4).');
            if(!DocumentoIdentidad::rucValido((string)$erp['ruc']))throw new \RuntimeException('El RUC de la empresa emisora no es válido. Corrígelo en Organización › Empresas.');
            if(!preg_match('/^\d{6}$/',(string)$erp['ubigeo']))throw new \RuntimeException("La sucursal «{$erp['sucursal']}» no tiene distrito con ubigeo. Complétalo en Organización › Sucursales y Ubicación › Distritos.");
            if(!preg_match('/^\d{4}$/',(string)$erp['codigo_establecimiento']))throw new \RuntimeException("La sucursal «{$erp['sucursal']}» no tiene código de establecimiento SUNAT válido.");
            foreach(['ruc','razon_social','nombre_comercial','direccion','distrito','provincia','departamento','ubigeo','codigo_establecimiento'] as $campo){
                if(trim((string)($erp[$campo]??''))!=='')$emisor[$campo]=in_array($campo,['distrito','provincia','departamento'],true)?mb_strtoupper((string)$erp[$campo]):(string)$erp[$campo];
            }
        }
        $q=$this->db->prepare('SELECT tipo_documento,numero_documento,nombre FROM demo_clientes WHERE id=?');$q->execute([$clienteId]);$cliente=$q->fetch();
        if(!$cliente)throw new \RuntimeException('No se encontró el cliente del comprobante.');
        return ['emisor'=>$emisor,'cliente'=>$cliente];
    }

    public function emitir(string $tipo, array $origen, array $detalle, float $tasa, ?int $referencia=null, ?string $motivo=null): int
    {
        $this->db->beginTransaction();
        try { $id=$this->insertar($tipo,$origen,$detalle,$tasa,$referencia,$motivo);$this->db->commit();return $id; }
        catch(\Throwable $e){
            if($this->db->inTransaction())$this->db->rollBack();
            // Dos emisiones simultáneas de la misma venta: la base lo impide; se avisa con un mensaje claro
            if($e instanceof \PDOException&&str_contains($e->getMessage(),'uq_venta_facturada'))throw new \RuntimeException('La venta ya fue facturada.',0,$e);
            throw $e;
        }
    }

    /**
     * Facturas y boletas que pueden recibir una nota, con lo ya acreditado por notas de crédito
     * y el saldo disponible (base imponible, sin IGV).
     */
    public function referenciasParaNota(): array
    {
        $sinEfecto=implode(',',array_fill(0,count(self::ESTADOS_SIN_EFECTO),'?'));$referencia=implode(',',array_fill(0,count(self::ESTADOS_REFERENCIA_NOTA),'?'));
        $q=$this->db->prepare("SELECT f.*,c.nombre cliente,c.tipo_documento,c.numero_documento,
                COALESCE((SELECT SUM(n.subtotal) FROM facturacion_comprobantes n WHERE n.comprobante_referenciado_id=f.id AND n.tipo='nota_credito' AND n.estado NOT IN ($sinEfecto)),0) acreditado
            FROM facturacion_comprobantes f JOIN demo_clientes c ON c.id=f.cliente_id
            WHERE f.tipo IN ('factura','boleta') AND f.estado IN ($referencia)".sql_sede_comprobante('f')." ORDER BY f.id DESC");
        $q->execute([...self::ESTADOS_SIN_EFECTO,...self::ESTADOS_REFERENCIA_NOTA]);
        return array_map(static function(array $f): array {$f=self::conClienteDeEmision($f);$f['saldo']=round((float)$f['subtotal']-(float)$f['acreditado'],2);return $f;},$q->fetchAll());
    }

    /**
     * Emite una nota con la factura o boleta de referencia bloqueada: así dos notas simultáneas no superan el saldo.
     * La nota usa la tasa de IGV del comprobante original y una serie que empieza con su misma letra (F o B).
     */
    public function emitirNota(int $referenciaId, string $tipo, string $codigoMotivo, string $motivo, float $monto): int
    {
        $this->db->beginTransaction();
        try {
            $q=$this->db->prepare('SELECT id,tipo,serie,numero,estado,cliente_id,subtotal,datos_emision FROM facturacion_comprobantes WHERE id=? FOR UPDATE');$q->execute([$referenciaId]);$ref=$q->fetch();
            if(!$ref||!in_array($ref['tipo'],['factura','boleta'],true))throw new \InvalidArgumentException('Elige una factura o boleta como comprobante de referencia.');
            if(!in_array($ref['estado'],self::ESTADOS_REFERENCIA_NOTA,true))throw new \InvalidArgumentException('Solo se emiten notas sobre comprobantes aceptados por SUNAT. El comprobante elegido está «'.estado_comprobante($ref['estado']).'».');
            if($tipo==='nota_credito'){
                $q=$this->db->prepare("SELECT COALESCE(SUM(subtotal),0) FROM facturacion_comprobantes WHERE comprobante_referenciado_id=? AND tipo='nota_credito' AND estado NOT IN (".implode(',',array_fill(0,count(self::ESTADOS_SIN_EFECTO),'?')).')');$q->execute([$referenciaId,...self::ESTADOS_SIN_EFECTO]);
                $saldo=round((float)$ref['subtotal']-(float)$q->fetchColumn(),2);
                if(round($monto,2)>$saldo)throw new \InvalidArgumentException('El ajuste supera el saldo del comprobante: quedan S/ '.number_format(max(0,$saldo),2).' de base imponible por acreditar.');
            }
            $datos=json_decode((string)$ref['datos_emision'],true);
            if(!is_array($datos)||!isset($datos['emisor'],$datos['cliente'],$datos['tasa_igv']))throw new \RuntimeException('El comprobante de referencia no tiene sus datos de emisión completos.');
            $tasa=(float)$datos['tasa_igv'];
            $detalle=[['descripcion'=>'Ajuste: '.$motivo,'cantidad'=>1,'precio_unitario'=>round($monto,2)]];
            $id=$this->insertar($tipo,['cliente_id'=>$ref['cliente_id']],$detalle,$tasa,$referenciaId,$motivo,$codigoMotivo,substr((string)$ref['serie'],0,1),$datos);
            $this->db->commit();return $id;
        } catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    /** Guarda el comprobante, su detalle y su primer evento. Debe llamarse dentro de una transacción. */
    private function insertar(string $tipo, array $origen, array $detalle, float $tasa, ?int $referencia, ?string $motivo, ?string $codigoMotivo=null, ?string $prefijoSerie=null, ?array $datosEmisionOverride=null): int
    {
        $numeracion=(new SeriesService())->reservarCorrelativo($tipo,null,$prefijoSerie,isset($origen['sucursal_id'])?(int)$origen['sucursal_id']:null);
        $importes=CalculoImportes::calcular($detalle,$tasa);
        $datosEmision=json_encode($datosEmisionOverride??($this->datosEmisionActuales((int)$origen['cliente_id'],isset($origen['venta_erp_id'])?(int)$origen['venta_erp_id']:null)+['tasa_igv'=>$tasa]),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        $q=$this->db->prepare('INSERT INTO facturacion_comprobantes (venta_id,venta_erp_id,comprobante_referenciado_id,tipo,serie,numero,cliente_id,fecha_emision,motivo,codigo_motivo,subtotal,impuesto,total,datos_emision,estado) VALUES (?,?,?,?,?,?,?,NOW(),?,?,?,?,?,?,?)');
        $q->execute([$origen['venta_id']??null,$origen['venta_erp_id']??null,$referencia,$tipo,$numeracion['serie'],$numeracion['numero'],$origen['cliente_id'],$motivo,$codigoMotivo,$importes['subtotal'],$importes['impuesto'],$importes['total'],$datosEmision,'borrador_local']);$id=(int)$this->db->lastInsertId();
        $q=$this->db->prepare('INSERT INTO facturacion_detalles (comprobante_id,codigo_producto,descripcion,cantidad,unidad_medida,precio_unitario,descuento,subtotal,impuesto,total) VALUES (?,?,?,?,?,?,?,?,?,?)');
        foreach($importes['lineas'] as $l)$q->execute([$id,$l['codigo_producto']??null,$l['descripcion'],$l['cantidad'],$l['unidad_medida']??'ZZ',$l['precio_unitario'],$l['descuento']??0,$l['subtotal'],$l['impuesto'],$l['total']]);
        $this->db->prepare('INSERT INTO facturacion_eventos (comprobante_id,estado,detalle,metadatos,creado_en) VALUES (?,?,?,?,NOW())')->execute([$id,'borrador_local','Comprobante calculado; pendiente de envío electrónico.',ContextoAuditoria::metadatosJson()]);
        return $id;
    }
}
