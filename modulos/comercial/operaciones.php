<?php
require_once __DIR__ . '/../../core/crud_base.php';
require_once __DIR__ . '/../logistica/stock_lib.php';
require_once __DIR__ . '/cxc_lib.php';
requerir_login();
if (!puede('ver')) { http_response_code(403); exit('Sin acceso.'); }

$modulo = $comercialModulo ?? '';
if (!in_array($modulo, ['pedidos', 'ventas'], true)) { http_response_code(404); exit('Módulo no disponible.'); }
$cfg = $modulo === 'pedidos'
    ? ['titulo'=>'Pedidos','origen'=>'cotizaciones','origen_det'=>'cotizacion_detalles','origen_estado'=>'aceptada','tabla'=>'pedidos','detalle'=>'pedido_detalles','documento'=>'pedido','prefijo'=>'PED','estado_siguiente'=>'confirmado','etiqueta_origen'=>'Cotización']
    : ['titulo'=>'Ventas','origen'=>'pedidos','origen_det'=>'pedido_detalles','origen_estado'=>'confirmado','tabla'=>'ventas','detalle'=>'venta_detalles','documento'=>'venta','prefijo'=>'VEN','estado_siguiente'=>'confirmada','etiqueta_origen'=>'Pedido'];

function op_url(string $modulo, array $params=[]): string { $u=BASE_URL.'/modulos/comercial/'.$modulo.'.php'; return $params?$u.'?'.http_build_query($params):$u; }
function op_audit(PDO $pdo,int $u,string $entidad,int $id,string $accion,?string $antes,?string $despues): void { $pdo->prepare('INSERT INTO comercial_auditoria(usuario_id,entidad,entidad_id,accion,estado_anterior,estado_nuevo) VALUES(?,?,?,?,?,?)')->execute([$u,$entidad,$id,$accion,$antes,$despues]); }
function op_numero(PDO $pdo,int $sucursal,string $documento,string $prefijo): string {
    $st=$pdo->prepare('SELECT id,serie,ultimo_numero FROM comercial_correlativos WHERE sucursal_id=? AND documento=? ORDER BY id LIMIT 1 FOR UPDATE');$st->execute([$sucursal,$documento]);$r=$st->fetch();
    if(!$r){$serie=$prefijo.str_pad((string)$sucursal,3,'0',STR_PAD_LEFT);$pdo->prepare('INSERT INTO comercial_correlativos(sucursal_id,documento,serie,ultimo_numero) VALUES(?,?,?,1)')->execute([$sucursal,$documento,$serie]);return $serie.'-00000001';}
    $n=(int)$r['ultimo_numero']+1;$pdo->prepare('UPDATE comercial_correlativos SET ultimo_numero=? WHERE id=?')->execute([$n,$r['id']]);return $r['serie'].'-'.str_pad((string)$n,8,'0',STR_PAD_LEFT);
}
function op_flash(string $t,string $m):void {$_SESSION['operacion_flash']=[$t,$m];}
$usuario=(int)usuario_actual()['id'];

if($_SERVER['REQUEST_METHOD']==='POST'){
    csrf_validar();
    try{
        if(!puede('crear') && ($_POST['accion']??'')==='convertir') throw new RuntimeException('Sin permiso para crear.');
        if(($_POST['accion']??'')==='convertir'){
            $origenId=(int)($_POST['origen_id']??0);$condicion=$_POST['condicion_pago']??'contado';if(!in_array($condicion,['contado','credito'],true)) throw new RuntimeException('Condición de pago no válida.');
            $pdo->beginTransaction();
            $st=$pdo->prepare("SELECT * FROM `{$cfg['origen']}` WHERE id=? FOR UPDATE");$st->execute([$origenId]);$o=$st->fetch();
            if(!$o || $o['estado']!==$cfg['origen_estado']) throw new RuntimeException('El documento de origen no está disponible para conversión.');
            sede_exigir((int)$o['sucursal_id']);
            $fk=$modulo==='pedidos'?'cotizacion_id':'pedido_id';$st=$pdo->prepare("SELECT id FROM `{$cfg['tabla']}` WHERE `$fk`=?");$st->execute([$origenId]);if($st->fetch())throw new RuntimeException('El documento de origen ya fue convertido.');
            $numero=op_numero($pdo,(int)$o['sucursal_id'],$cfg['documento'],$cfg['prefijo']);
            if($modulo==='pedidos'){$sql='INSERT INTO pedidos(sucursal_id,cliente_id,vendedor_id,numero,fecha,moneda,estado,subtotal,descuento,impuesto,total,observaciones,creado_por,cotizacion_id,condicion_pago) VALUES(?,?,?,?,CURDATE(),?,"borrador",?,?,?,?,?,?,?,?)';$params=[$o['sucursal_id'],$o['cliente_id'],$o['vendedor_id'],$numero,$o['moneda'],$o['subtotal'],$o['descuento'],$o['impuesto'],$o['total'],$o['observaciones'],$usuario,$origenId,$condicion];}
            else {$vencimiento=$condicion==='credito'?(trim($_POST['fecha_vencimiento']??'')?:null):null;
                if($condicion==='credito'){
                    // Condiciones de venta del cliente (G5): límite de crédito y días de crédito
                    $st=$pdo->prepare('SELECT razon_social,limite_credito,dias_credito FROM clientes WHERE id=?');$st->execute([$o['cliente_id']]);$cli=$st->fetch();
                    if($cli['limite_credito']===null||(float)$cli['limite_credito']<=0)throw new RuntimeException('El cliente '.$cli['razon_social'].' no tiene línea de crédito. Asígnale un límite en Clientes o vende al contado.');
                    $st=$pdo->prepare('SELECT COALESCE(SUM('.cxc_sql_saldo_venta('v').'),0) FROM ventas v WHERE v.cliente_id=? AND v.estado="confirmada" AND v.condicion_pago="credito"');$st->execute([$o['cliente_id']]);$deuda=max(0,(float)$st->fetchColumn());
                    if($deuda+(float)$o['total']>(float)$cli['limite_credito']+0.005)throw new RuntimeException('La venta supera la línea de crédito del cliente: límite '.number_format((float)$cli['limite_credito'],2).', deuda vigente '.number_format($deuda,2).', esta venta '.number_format((float)$o['total'],2).'.');
                    if(!$vencimiento&&$cli['dias_credito']!==null)$vencimiento=date('Y-m-d',strtotime('+'.(int)$cli['dias_credito'].' days'));
                }
                if($condicion==='credito'&&!$vencimiento)throw new RuntimeException('Indica la fecha de vencimiento del crédito (el cliente no tiene días de crédito definidos).');$sql='INSERT INTO ventas(sucursal_id,cliente_id,vendedor_id,numero,fecha,moneda,estado,subtotal,descuento,impuesto,total,observaciones,creado_por,pedido_id,condicion_pago,fecha_vencimiento) VALUES(?,?,?,?,CURDATE(),?,"borrador",?,?,?,?,?,?,?,?,?)';$params=[$o['sucursal_id'],$o['cliente_id'],$o['vendedor_id'],$numero,$o['moneda'],$o['subtotal'],$o['descuento'],$o['impuesto'],$o['total'],$o['observaciones'],$usuario,$origenId,$condicion,$vencimiento];}
            $pdo->prepare($sql)->execute($params);$nuevo=(int)$pdo->lastInsertId();
            $d=$pdo->prepare("SELECT * FROM `{$cfg['origen_det']}` WHERE `".($modulo==='pedidos'?'cotizacion_id':'pedido_id')."`=? ORDER BY linea");$d->execute([$origenId]);$ins=$pdo->prepare("INSERT INTO `{$cfg['detalle']}`( ".($modulo==='pedidos'?'pedido_id':'venta_id').",linea,producto_id,unidad_id,codigo_producto,descripcion,cantidad,precio_unitario,descuento,subtotal,tasa_impuesto,impuesto,total) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)");foreach($d as $x)$ins->execute([$nuevo,$x['linea'],$x['producto_id'],$x['unidad_id'],$x['codigo_producto'],$x['descripcion'],$x['cantidad'],$x['precio_unitario'],$x['descuento'],$x['subtotal'],$x['tasa_impuesto'],$x['impuesto'],$x['total']]);
            op_audit($pdo,$usuario,$modulo==='pedidos'?'pedido':'venta',$nuevo,'creado',null,'borrador');$pdo->commit();op_flash('ok',($modulo==='pedidos'?'Pedido ':'Venta ')."$numero ".($modulo==='pedidos'?'creado':'creada').' correctamente.');
        }elseif(($_POST['accion']??'')==='confirmar'){
            if(!puede('editar'))throw new RuntimeException('Sin permiso para confirmar.');$id=(int)($_POST['id']??0);$pdo->beginTransaction();$st=$pdo->prepare("SELECT * FROM `{$cfg['tabla']}` WHERE id=? FOR UPDATE");$st->execute([$id]);$r=$st->fetch();if(!$r||$r['estado']!=='borrador')throw new RuntimeException('Solo se pueden confirmar borradores.');
            sede_exigir((int)$r['sucursal_id']);
            $pdo->prepare("UPDATE `{$cfg['tabla']}` SET estado=? WHERE id=?")->execute([$cfg['estado_siguiente'],$id]);
            // Venta confirmada → Logística (G7): reserva el stock y prepara los despachos en la misma transacción
            $despachos=$modulo==='ventas'?g7_reservar_venta($pdo,$id):[];
            // Pedido (G8) ← stock (G7): se confirma igual, pero se avisa si la sede no tiene stock suficiente (se reserva recién en la venta)
            $aviso='';
            if($modulo==='pedidos'){$st=$pdo->prepare('SELECT d.descripcion, SUM(d.cantidad) requerido, COALESCE((SELECT SUM(s.cantidad-s.reservado) FROM g7_saldos s JOIN almacenes a ON a.id=s.almacen_id WHERE a.sucursal_id=? AND a.estado=1 AND s.producto_id=d.producto_id),0) disponible FROM pedido_detalles d WHERE d.pedido_id=? GROUP BY d.producto_id, d.descripcion HAVING requerido > disponible');$st->execute([$r['sucursal_id'],$id]);$faltan=array_map(fn($x)=>$x['descripcion'].' (disponible '.(float)$x['disponible'].', requerido '.(float)$x['requerido'].')',$st->fetchAll());if($faltan)$aviso=' Atención: stock insuficiente en la sede para '.implode('; ',$faltan).'. La venta no podrá confirmarse hasta reponerlo.';}
            if($modulo==='ventas'&&$r['condicion_pago']==='credito'){$pdo->prepare('INSERT INTO cuentas_por_cobrar(venta_id,importe_original,estado,creado_por) VALUES(?, ?, "activa", ?)')->execute([$id,$r['total'],$usuario]);$cuenta=(int)$pdo->lastInsertId();$pdo->prepare('INSERT INTO cuenta_cuotas(cuenta_id,numero_cuota,vencimiento,importe) VALUES(?,?,?,?)')->execute([$cuenta,1,$r['fecha_vencimiento'],$r['total']]);}
            op_audit($pdo,$usuario,$modulo==='pedidos'?'pedido':'venta',$id,'confirmado','borrador',$cfg['estado_siguiente']);$pdo->commit();op_flash($aviso?'warn':'ok',($modulo==='pedidos'?'Pedido confirmado.':'Venta confirmada.').($despachos?' Stock reservado; despachos en preparación: '.implode(', ',$despachos).'.':'').$aviso);
        }elseif(($_POST['accion']??'')==='anular'&&$modulo==='ventas'){
            // Anular venta: deshace lo que generó la confirmación, solo si no hay comprobante, cobros ni mercadería en salida
            if(!puede('editar'))throw new RuntimeException('Sin permiso para anular ventas.');
            $id=(int)($_POST['id']??0);$motivo=trim($_POST['motivo']??'');
            if($motivo===''||mb_strlen($motivo)>255)throw new RuntimeException('Indica el motivo de la anulación (máx. 255 caracteres).');
            $pdo->beginTransaction();
            $st=$pdo->prepare('SELECT * FROM ventas WHERE id=? FOR UPDATE');$st->execute([$id]);$r=$st->fetch();
            if(!$r||$r['estado']==='anulada')throw new RuntimeException('La venta no existe o ya está anulada.');
            sede_exigir((int)$r['sucursal_id']);
            $st=$pdo->prepare("SELECT CONCAT(serie,'-',LPAD(numero,8,'0')) FROM facturacion_comprobantes WHERE venta_erp_id=? AND estado NOT IN ('anulado','anulado_local') LIMIT 1");$st->execute([$id]);
            if($comp=$st->fetchColumn())throw new RuntimeException("La venta ya tiene el comprobante $comp: anúlalo o emite una nota de crédito en Facturación; la venta no se anula desde aquí.");
            $st=$pdo->prepare("SELECT GROUP_CONCAT(DISTINCT c.numero) FROM cobranza_aplicaciones a JOIN cobranzas c ON c.id=a.cobranza_id
                LEFT JOIN cuenta_cuotas cc ON cc.id=a.cuota_id LEFT JOIN cuentas_por_cobrar cx ON cx.id=cc.cuenta_id
                WHERE c.estado<>'anulada' AND (a.venta_contado_id=? OR cx.venta_id=?)");$st->execute([$id,$id]);
            if($cobros=$st->fetchColumn())throw new RuntimeException("La venta tiene cobranzas vigentes ($cobros): anúlalas primero en Cobranzas.");
            $cancelados=$r['estado']==='confirmada'?g7_cancelar_despachos_venta($pdo,$id):[];
            $pdo->prepare("UPDATE cuentas_por_cobrar SET estado='anulada' WHERE venta_id=?")->execute([$id]);
            $pdo->prepare('UPDATE ventas SET estado="anulada", anulado_por=?, anulado_en=NOW(), motivo_anulacion=? WHERE id=?')->execute([$usuario,$motivo,$id]);
            $pdo->prepare('INSERT INTO comercial_auditoria(usuario_id,entidad,entidad_id,accion,estado_anterior,estado_nuevo,motivo) VALUES(?,"venta",?,"anulada",?,"anulada",?)')->execute([$usuario,$id,$r['estado'],$motivo]);
            $pdo->commit();
            op_flash('ok',"Venta {$r['numero']} anulada.".($cancelados?' Despachos cancelados y stock liberado: '.implode(', ',$cancelados).'.':'').' Para volver a vender, crea una nueva cotización.');
        }else throw new RuntimeException('Acción no válida.');
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();op_flash('err',$e instanceof PDOException?'No se pudo completar la operación.':$e->getMessage());}
    header('Location: '.op_url($modulo));exit;
}
$flash=$_SESSION['operacion_flash']??null;unset($_SESSION['operacion_flash']);$titulo=$cfg['titulo'];require __DIR__.'/../../core/layout_header.php';
if($flash):?><div id="toast-container" class="toast-container"></div><script>document.addEventListener('DOMContentLoaded',()=>mostrarToast(<?=json_encode($flash[1])?>,<?=json_encode($flash[0])?>));</script><?php endif;
[$sedeSql,$sedeArg]=sede_filtro('o.sucursal_id'); // sede activa (G4)
$origenes=$pdo->prepare("SELECT o.id,o.numero,o.fecha,o.total,o.moneda,cl.razon_social FROM `{$cfg['origen']}` o INNER JOIN clientes cl ON cl.id=o.cliente_id LEFT JOIN `{$cfg['tabla']}` n ON n.".($modulo==='pedidos'?'cotizacion_id':'pedido_id')."=o.id WHERE o.estado='{$cfg['origen_estado']}' AND n.id IS NULL{$sedeSql} ORDER BY o.id DESC");$origenes->execute($sedeArg);$origenes=$origenes->fetchAll();
// En ventas se muestran también el comprobante de Facturación (G1) y el estado de sus despachos (G7)
$extra=$modulo==='ventas'?",(SELECT CONCAT(fc.serie,'-',LPAD(fc.numero,8,'0'),' · ',fc.estado) FROM facturacion_comprobantes fc WHERE fc.venta_erp_id=n.id ORDER BY fc.venta_erp_vigente IS NULL, fc.id DESC LIMIT 1) comprobante,".cxc_sql_notas_venta('n')." notas,".cxc_sql_saldo_venta('n')." saldo,(SELECT GROUP_CONCAT(DISTINCT gd.estado ORDER BY gd.estado SEPARATOR ', ') FROM g7_despachos gd WHERE gd.venta_id=n.id) despacho,EXISTS(SELECT 1 FROM facturacion_comprobantes fv WHERE fv.venta_erp_id=n.id AND fv.estado NOT IN ('anulado','anulado_local')) comp_vigente":'';
[$sedeSqlN,$sedeArgN]=sede_filtro('n.sucursal_id');
$filas=$pdo->prepare("SELECT n.*,cl.razon_social cliente,s.nombre sucursal,o.numero origen{$extra} FROM `{$cfg['tabla']}` n INNER JOIN clientes cl ON cl.id=n.cliente_id INNER JOIN sucursales s ON s.id=n.sucursal_id LEFT JOIN `{$cfg['origen']}` o ON o.id=n.".($modulo==='pedidos'?'cotizacion_id':'pedido_id')." WHERE 1=1{$sedeSqlN} ORDER BY n.id DESC");$filas->execute($sedeArgN);$filas=$filas->fetchAll();
?>
<div class="comercial-actions" style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:20px"><div><h2 style="margin:0;color:#10384f">Registro de <?=h($cfg['titulo'])?></h2><p class="small-note">Se generan desde <?=strtolower(h($cfg['etiqueta_origen']))?>s aprobadas para conservar el detalle e importes.</p></div></div>
<div class="comercial-card" style="background:#fff;border:1px solid #e2e8f0;border-radius:16px;margin-bottom:20px"><div class="comercial-card-head" style="padding:18px 22px;border-bottom:1px solid #e2e8f0"><h2 style="margin:0;font-size:16px">Crear desde <?=h($cfg['etiqueta_origen'])?></h2></div><div class="comercial-card-body" style="padding:22px"><?php if(!$origenes):?><p class="small-note">No hay <?=strtolower(h($cfg['etiqueta_origen']))?>s disponibles para convertir.</p><?php else:?><form method="post" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="accion" value="convertir"><label class="form-group" style="min-width:300px"><span class="field-label"><?=h($cfg['etiqueta_origen'])?> *</span><select name="origen_id" class="custom-select" required><?php foreach($origenes as $o):?><option value="<?=h($o['id'])?>"><?=h($o['numero'].' · '.$o['razon_social'].' · '.$o['moneda'].' '.number_format($o['total'],2))?></option><?php endforeach;?></select></label><label class="form-group"><span class="field-label">Condición</span><select name="condicion_pago" class="custom-select"><option value="contado">Contado</option><option value="credito">Crédito</option></select></label><?php if($modulo==='ventas'):?><label class="form-group"><span class="field-label">Vence crédito</span><input class="custom-input" name="fecha_vencimiento" type="date"></label><?php endif;?><?php if(puede('crear')):?><button class="btn btn-primary-action" type="submit">Crear <?=rtrim(h($cfg['titulo']),'s')?></button><?php endif;?></form><?php endif;?></div></div>
<div class="comercial-card" style="background:#fff;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden"><div class="comercial-card-head" style="padding:18px 22px;border-bottom:1px solid #e2e8f0"><h2 style="margin:0;font-size:16px"><?=count($filas)?> <?=strtolower(h($cfg['titulo']))?></h2></div><div style="overflow:auto"><table class="detalle-tabla" style="width:100%;min-width:760px;border-collapse:collapse"><thead><tr><th>Número</th><th>Fecha</th><th>Cliente</th><th><?=h($cfg['etiqueta_origen'])?></th><th>Condición</th><th>Estado</th><th>Total</th><th></th></tr></thead><tbody><?php foreach($filas as $f):?><tr><td><strong><?=h($f['numero'])?></strong></td><td><?=h($f['fecha'])?></td><td><?=h($f['cliente'])?></td><td><?=h($f['origen'])?></td><td><?=h($f['condicion_pago'])?></td><td><?=h($f['estado'])?><?php if($modulo==='ventas'&&$f['estado']==='confirmada'):?><div class="small-note">Comprobante: <?=h($f['comprobante']??'pendiente de facturar')?></div><div class="small-note">Despacho: <?=h($f['despacho']??'—')?></div><?php if(abs((float)$f['notas'])>0.004):?><div class="small-note"><?=(float)$f['notas']>0?'Nota de crédito':'Nota de débito'?>: <?=number_format(abs((float)$f['notas']),2)?></div><?php endif;?><div class="small-note"><?=(float)$f['saldo']<-0.004?'Saldo a favor del cliente: '.number_format(-(float)$f['saldo'],2):'Saldo por cobrar: '.number_format(max(0,(float)$f['saldo']),2)?></div><?php endif;?></td><td><?=h($f['moneda'])?> <?=number_format($f['total'],2)?></td><td><?php if($f['estado']==='borrador'&&puede('editar')):?><form method="post"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="accion" value="confirmar"><input type="hidden" name="id" value="<?=h($f['id'])?>"><button class="btn btn-sec" type="submit">Confirmar</button></form><?php endif;?><?php if($modulo==='ventas'&&$f['estado']!=='anulada'&&!empty($f['comp_vigente'])):?><div class="small-note" style="margin-top:6px">Facturada: para revertirla, anula el comprobante o emite una nota de crédito en Facturación.</div><?php endif;?><?php if($modulo==='ventas'&&$f['estado']!=='anulada'&&empty($f['comp_vigente'])&&puede('editar')):?><form method="post" style="display:flex;gap:5px;margin-top:6px" onsubmit="return confirm('¿Anular la venta <?=h($f['numero'])?>? Se cancelan sus despachos y su cuenta por cobrar.')"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="accion" value="anular"><input type="hidden" name="id" value="<?=h($f['id'])?>"><input class="custom-input" style="width:140px" name="motivo" placeholder="Motivo" maxlength="255" required><button class="btn btn-muted" type="submit">Anular</button></form><?php endif;?><?php if(($f['estado']??'')==='anulada'):?><div class="small-note">Motivo: <?=h($f['motivo_anulacion']??'')?></div><?php endif;?></td></tr><?php endforeach;if(!$filas):?><tr><td colspan="8" style="text-align:center;padding:30px;color:#64748b">Aún no hay registros.</td></tr><?php endif;?></tbody></table></div></div>
<?php require __DIR__.'/../../core/layout_footer.php';
