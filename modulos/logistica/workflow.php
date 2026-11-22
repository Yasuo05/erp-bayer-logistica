<?php
declare(strict_types=1);

/** Flujo G7 sobre los despachos, ventas y saldos oficiales del ERP. */
function g7_permiso_persistido(PDO $pdo, string $permiso): bool {
    $st = $pdo->prepare('SELECT 1 FROM usuarios u JOIN usuario_rol ur ON ur.usuario_id=u.id JOIN roles r ON r.id=ur.rol_id JOIN rol_permiso rp ON rp.rol_id=r.id JOIN permisos p ON p.id=rp.permiso_id WHERE u.id=? AND u.estado=1 AND r.estado=1 AND LOWER(r.nombre)=? AND p.nombre=? LIMIT 1');
    $st->execute([(int)(usuario_actual()['id']??0),mb_strtolower((string)(usuario_actual()['rol'] ?? '')), $permiso]);
    return (bool)$st->fetchColumn();
}

/** Unidades milésimas: no se redondean ni se aceptan exponentes. */
function g7_milesimas($v, string $etiqueta='Cantidad', bool $cero=false): int {
    if ((!is_string($v) && !is_int($v) && !is_float($v)) || !preg_match('/^\d{1,11}(?:\.\d{1,3})?$/D', (string)$v)) {
        throw new ApiError("$etiqueta: usa un decimal positivo de hasta tres decimales, sin redondeo");
    }
    $partes=explode('.',(string)$v,2);
    $n=(int)$partes[0]*1000+(int)str_pad($partes[1]??'',3,'0');
    if (!$cero && $n===0) throw new ApiError("$etiqueta debe ser mayor que cero");
    return $n;
}
function g7_decimal(int $n): string { return intdiv($n,1000).'.'.str_pad((string)($n%1000),3,'0',STR_PAD_LEFT); }
/** DECIMAL(18,4) comercial: quitar sólo ceros sin valor, nunca redondear. */
function g7_milesimas_bd($v,string $etiqueta='Cantidad',bool $cero=false): int {
    if (is_string($v) && str_contains($v,'.')) $v=rtrim(rtrim($v,'0'),'.');
    return g7_milesimas($v,$etiqueta,$cero);
}
function g7_texto($valor,string $nombre,int $max=500,bool $requerido=true): string {
    if (!is_scalar($valor) && $valor!==null) throw new ApiError("$nombre inválido");
    $s=trim((string)$valor);
    if (($requerido && $s==='') || mb_strlen($s)>$max) throw new ApiError("$nombre requerido (máximo $max caracteres)");
    return $s;
}
function g7_id($v,string $nombre='Registro'): int {
    $n=filter_var($v,FILTER_VALIDATE_INT);
    if ($n===false || $n<1) throw new ApiError("$nombre inválido");
    return (int)$n;
}
function g7_envio_balance(array $r): array {
    $salida=g7_milesimas($r['cantidad_salida'],'Salida',true);
    $aceptada=g7_milesimas($r['cantidad_aceptada'],'Aceptada',true);
    $retorno=g7_milesimas($r['cantidad_retornada'],'Retornada',true);
    $perdida=g7_milesimas($r['cantidad_perdida'],'Pérdida',true);
    return ['enviado'=>g7_decimal($salida),'aceptado'=>g7_decimal($aceptada),'retornado'=>g7_decimal($retorno),'perdida'=>g7_decimal($perdida),'pendiente'=>g7_decimal($salida-$aceptada-$retorno-$perdida)];
}
function g7_envio_select(): string {
    return "SELECT d.*, e.estado_logistica,e.version,e.guia_id,e.guia_version,e.cantidad_salida,e.cantidad_aceptada,e.cantidad_retornada,e.cantidad_perdida,e.costo_salida,e.preparado_en,e.cerrado_en,e.legado,e.actualizado_en,
      p.nombre producto,p.codigo codigo_producto,COALESCE(um.abreviatura,um.codigo,'UND') unidad,a.nombre almacen,a.sucursal_id,g.numero guia_numero,g.estado_registro guia_estado,v.estado venta_estado
      FROM g7_despachos d JOIN g7_envios e ON e.despacho_id=d.id JOIN productos p ON p.id=d.producto_id
      LEFT JOIN unidades_medida um ON um.id=p.unidad_base_id JOIN almacenes a ON a.id=d.almacen_id
      LEFT JOIN guias_cabecera g ON g.id=e.guia_id LEFT JOIN ventas v ON v.id=d.venta_id";
}
function g7_envio_cargar(PDO $pdo,int $id,bool $bloquear=false): array {
    $st=$pdo->prepare(g7_envio_select().' WHERE d.id=?'.($bloquear?' FOR UPDATE':'')); $st->execute([$id]); $r=$st->fetch();
    if (!$r) throw new ApiError('Envío no encontrado. Verifica que se aplicó la migración logística.',404);
    g7_exigir_almacen($pdo,(int)$r['almacen_id']);
    return $r;
}
function g7_envio_acciones(PDO $pdo,array $r): array {
    if (!g7_permiso_persistido($pdo,'editar') || in_array($r['estado_logistica'],['CERRADO','CERRADO_CON_INCIDENCIA','CANCELADO'],true)) return [];
    $pre=$r['estado']==='PREPARACION';
    $acciones=$pre?['configurar','preparar','guia','cancelar','incidencia']:['intento','incidencia','cerrar'];
    if ($pre && $r['estado_logistica']==='PREPARADO') $acciones[]='salir';
    if ($pre && $r['estado_logistica']==='PREPARADO' && !$r['guia_id'] && g7_permiso_persistido($pdo,'crear')) $acciones[]='emitir_guia';
    $acciones[]='resolver';
    return $acciones;
}
function g7_envio_presentar(PDO $pdo,array $r): array {
    $r['id']=(int)$r['id'];$r['version']=(int)$r['version'];$r['guia_id']=$r['guia_id']===null?null:(int)$r['guia_id'];
    $r['pendiente']=g7_envio_balance($r)['pendiente'];$r['acciones_permitidas']=g7_envio_acciones($pdo,$r);
    return $r;
}
function g7_envios_listar(PDO $pdo): array {
    $st=$pdo->query(g7_envio_select().' ORDER BY d.id DESC');
    $rows=g7_filtrar_por_sede($pdo,$st->fetchAll());
    return array_map(fn($r)=>g7_envio_presentar($pdo,$r),$rows);
}
function g7_guias_compatibles(PDO $pdo,array $r): array {
    $st=$pdo->prepare("SELECT g.id,g.numero,g.fecha,g.estado_registro FROM guias_cabecera g
      WHERE g.sucursal_id=? AND g.cliente_id=? AND g.estado_registro IN ('VALIDADO','PUBLICADO')
      AND (g.origen IS NULL OR g.origen NOT IN ('ERP_G7','LOGISTICA_G7') OR g.origen_ref=?)
      AND (SELECT COALESCE(SUM(gd.cantidad),0) FROM guias_detalle gd JOIN productos p ON p.id=gd.producto_id WHERE gd.guia_id=g.id AND gd.producto_id=? AND gd.unidad_id=p.unidad_base_id)
        >= ? + (SELECT COALESCE(SUM(d2.cantidad),0) FROM g7_envios e2 JOIN g7_despachos d2 ON d2.id=e2.despacho_id WHERE e2.guia_id=g.id AND d2.producto_id=? AND d2.id<>? AND d2.estado<>'CANCELADO') ORDER BY g.fecha DESC,g.id DESC");
    $st->execute([$r['sucursal_id'],$r['cliente_id'],(string)$r['id'],$r['producto_id'],$r['cantidad'],$r['producto_id'],$r['id']]);
    return $st->fetchAll();
}
function g7_envio_expediente(PDO $pdo,int $id): array {
    $r=g7_envio_cargar($pdo,$id);
    $out=['envio'=>g7_envio_presentar($pdo,$r),'balance'=>g7_envio_balance($r)];
    foreach (['intentos'=>'g7_envio_intentos','incidencias'=>'g7_envio_incidencias','resoluciones'=>'g7_envio_resoluciones','historial'=>'g7_envio_eventos'] as $clave=>$tabla) {
        $st=$pdo->prepare("SELECT x.*,u.nombre usuario FROM $tabla x JOIN usuarios u ON u.id=x.usuario_id WHERE x.despacho_id=? ORDER BY x.id DESC");
        $st->execute([$id]);$out[$clave]=$st->fetchAll();
    }
    $out['guias_disponibles']=$r['estado']==='PREPARACION'?g7_guias_compatibles($pdo,$r):[];
    return $out;
}
function g7_validar_guia(PDO $pdo,array $r,int $guiaId,bool $version=false,bool $permitirBorrador=false): array {
    $st=$pdo->prepare('SELECT * FROM guias_cabecera WHERE id=? FOR UPDATE');$st->execute([$guiaId]);$g=$st->fetch();
    $estados=$permitirBorrador?['BORRADOR','VALIDADO','PUBLICADO']:['VALIDADO','PUBLICADO'];
    if (!$g || !in_array($g['estado_registro'],$estados,true)) throw new ApiError('Asocia una guía existente validada o publicada; no sirve un borrador ni una guía anulada.',409);
    if ((int)$g['sucursal_id']!==(int)$r['sucursal_id'] || (int)$g['cliente_id']!==(int)$r['cliente_id']) throw new ApiError('La guía pertenece a otra sede o cliente.',409);
    if (in_array($g['origen'],['ERP_G7','LOGISTICA_G7'],true) && (string)$g['origen_ref']!==(string)$r['id']) throw new ApiError('La guía está vinculada a otro despacho de origen.',409);
    if ($version && (int)$r['guia_version']!==(int)$g['version']) throw new ApiError('La guía cambió desde su asociación. Revísala y vuelve a asociarla.',409);
    $st=$pdo->prepare('SELECT gd.*,p.unidad_base_id FROM guias_detalle gd JOIN productos p ON p.id=gd.producto_id WHERE gd.guia_id=? FOR UPDATE');$st->execute([$guiaId]);$capacidad=0;
    foreach ($st->fetchAll() as $linea) if ((int)$linea['producto_id']===(int)$r['producto_id']) {
        if ((int)$linea['unidad_id']!==(int)$linea['unidad_base_id']) throw new ApiError('La unidad de la guía no coincide con la unidad base del inventario.',409);
        $capacidad+=g7_milesimas($linea['cantidad']);
    }
    $st=$pdo->prepare("SELECT d.cantidad FROM g7_envios e JOIN g7_despachos d ON d.id=e.despacho_id WHERE e.guia_id=? AND d.producto_id=? AND d.id<>? AND d.estado<>'CANCELADO' FOR UPDATE");$st->execute([$guiaId,$r['producto_id'],$r['id']]);
    $asignado=0;foreach ($st->fetchAll() as $d) $asignado+=g7_milesimas($d['cantidad']);
    if ($capacidad<$asignado+g7_milesimas($r['cantidad'])) throw new ApiError('La guía no cubre la cantidad del producto o ya está asignada a otro envío.',409);
    return $g;
}
function g7_envio_abiertas(PDO $pdo,int $id,bool $soloBloqueantes=false): int {
    $st=$pdo->prepare("SELECT COUNT(*) FROM g7_envio_incidencias WHERE despacho_id=? AND estado='ABIERTA'".($soloBloqueantes?" AND tipo='BLOQUEANTE'":''));$st->execute([$id]);return (int)$st->fetchColumn();
}
function g7_envio_venta_valida(PDO $pdo,array $r): void {
    if (!$r['venta_id'] || $r['venta_estado']!=='confirmada') throw new ApiError('El envío debe proceder de una venta confirmada del ERP.',409);
    $st=$pdo->prepare('SELECT vd.*,v.cliente_id,v.sucursal_id FROM venta_detalles vd JOIN ventas v ON v.id=vd.venta_id WHERE vd.venta_id=? AND vd.producto_id=? ORDER BY vd.linea FOR UPDATE');$st->execute([$r['venta_id'],$r['producto_id']]);$lineas=$st->fetchAll();$cantidad=0;
    foreach ($lineas as $l) {
        if ((int)$l['cliente_id']!==(int)$r['cliente_id'] || (int)$l['sucursal_id']!==(int)$r['sucursal_id']) throw new ApiError('El origen comercial no coincide con el envío.',409);
        $cantidad+=g7_milesimas_bd($l['cantidad']);
    }
    if ($cantidad<g7_milesimas($r['cantidad'])) throw new ApiError('La venta no contiene la cantidad del producto a despachar.',409);
}

/** La autorización precede al replay; misma clave con otra solicitud nunca produce efectos. */
function g7_envio_operar(PDO $pdo,int $id,string $accion,array $p): array {
    if (!g7_permiso_persistido($pdo,'editar')) throw new ApiError('Tu rol no tiene el permiso editar registrado por G4.',403);
    if ($accion==='emitir_guia' && !g7_permiso_persistido($pdo,'crear')) throw new ApiError('Emitir una guía requiere el permiso crear registrado por G4.',403);
    $acciones=['configurar','preparar','guia','emitir_guia','salir','intento','incidencia','resolver','cerrar','cancelar'];
    if (!in_array($accion,$acciones,true)) throw new ApiError('Acción logística desconocida.',404);
    $version=g7_id($p['version']??0,'Versión');
    $clave=g7_texto($p['idempotency_key']??'','Clave de operación',100);
    if (!preg_match('/^[A-Za-z0-9_.:-]{8,100}$/D',$clave)) throw new ApiError('La clave idempotente debe tener entre 8 y 100 letras, números o . _ : -');
    $canonical=function($x) use (&$canonical) { if (!is_array($x)) return $x; if (!array_is_list($x)) ksort($x); foreach ($x as &$v) $v=$canonical($v); return $x; };
    $hash=hash('sha256',json_encode([$accion,$canonical($p)],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
    $own=!$pdo->inTransaction();if ($own) $pdo->beginTransaction();
    try {
        $r=g7_envio_cargar($pdo,$id,true);
        $st=$pdo->prepare('SELECT * FROM g7_envio_operaciones WHERE despacho_id=? AND clave=?');$st->execute([$id,$clave]);$prev=$st->fetch();
        if ($prev) {
            if ($prev['solicitud_hash']!==$hash || (int)$prev['usuario_id']!==(int)usuario_actual()['id']) throw new ApiError('La clave idempotente ya se usó para otra solicitud o usuario.',409);
            $respuesta=json_decode($prev['respuesta'],true,512,JSON_THROW_ON_ERROR);$respuesta['replayed']=true;
            if ($own) $pdo->commit();return $respuesta;
        }
        if ($version!==(int)$r['version']) throw new ApiError('El envío cambió en otra sesión. Actualiza el expediente antes de continuar.',409);
        if (in_array($r['estado_logistica'],['CERRADO','CERRADO_CON_INCIDENCIA','CANCELADO'],true)) throw new ApiError('El envío ya está cerrado o cancelado.',409);
        $pre=$r['estado']==='PREPARACION';$balance=g7_envio_balance($r);$pendiente=g7_milesimas($balance['pendiente'],'Pendiente',true);
        $detalle=[];$mensaje='Operación registrada';$nuevo=$r['estado_logistica'];
        if (in_array($accion,['configurar','preparar','guia','emitir_guia','salir','cancelar'],true) && !$pre) throw new ApiError('Esta acción solo está disponible antes de la salida.',409);
        if (in_array($accion,['intento','cerrar'],true) && $pre) throw new ApiError('Primero debe confirmarse la salida física.',409);
        if ($accion==='configurar') {
            $destino=g7_texto($p['destino']??'','Destino',150);$transporte=g7_texto($p['transporte']??'','Transporte / conductor',120);
            $pdo->prepare('UPDATE g7_despachos SET destino=?,transporte=? WHERE id=?')->execute([$destino,$transporte,$id]);$detalle=compact('destino','transporte');$mensaje='Destino y transporte registrados';
        } elseif ($accion==='preparar') {
            g7_envio_venta_valida($pdo,$r);
            if ($r['estado_logistica']==='PREPARADO') throw new ApiError('El envío ya está preparado.',409);
            if (trim($r['destino'])==='' || trim($r['transporte'])==='') throw new ApiError('Completa destino y transporte antes de preparar.',409);
            if (g7_envio_abiertas($pdo,$id,true)) throw new ApiError('Hay una incidencia bloqueante abierta.',409);
            $b=g7_balance($pdo,(int)$r['producto_id'],(int)$r['almacen_id']);
            if (g7_milesimas($b['reservado'],'Reserva',true)<g7_milesimas($r['cantidad'])) throw new ApiError('La reserva de inventario no cubre el despacho.',409);
            $nuevo='PREPARADO';$pdo->prepare('UPDATE g7_envios SET preparado_en=NOW() WHERE despacho_id=?')->execute([$id]);$mensaje='Preparación física confirmada';
        } elseif ($accion==='guia') {
            $guia=g7_validar_guia($pdo,$r,g7_id($p['guia_id']??0,'Guía'));
            $pdo->prepare('UPDATE g7_envios SET guia_id=?,guia_version=? WHERE despacho_id=?')->execute([$guia['id'],$guia['version'],$id]);$detalle=['guia_id'=>(int)$guia['id'],'guia_numero'=>$guia['numero'],'guia_version'=>(int)$guia['version']];$mensaje='Guía existente asociada';
        } elseif ($accion==='emitir_guia') {
            if ($r['estado_logistica']!=='PREPARADO' || $r['guia_id']) throw new ApiError('La guía operativa se emite para un envío preparado que aún no tiene una guía asociada.',409);
            g7_envio_venta_valida($pdo,$r);
            if (g7_envio_abiertas($pdo,$id,true)) throw new ApiError('Resuelve las incidencias bloqueantes antes de emitir la guía.',409);
            $numero=g7_texto($p['numero']??'','Número de guía operativa',25);
            $fecha=g7_texto($p['fecha']??'','Fecha de guía',10);
            $date=DateTimeImmutable::createFromFormat('!Y-m-d',$fecha);
            if (!$date || $date->format('Y-m-d')!==$fecha || $fecha<'1000-01-01') throw new ApiError('Fecha de guía inválida; usa AAAA-MM-DD.');
            $st=$pdo->prepare("SELECT * FROM guias_cabecera WHERE origen IN ('ERP_G7','LOGISTICA_G7') AND origen_ref=? ORDER BY id FOR UPDATE");$st->execute([(string)$id]);$borradores=$st->fetchAll();
            if (count($borradores)>1) throw new ApiError('Existen varias guías de origen para este despacho. Revisa y asocia la correcta.',409);
            $borrador=$borradores[0]??null;
            if ($borrador && $borrador['estado_registro']!=='BORRADOR') throw new ApiError('El despacho ya tiene una guía de origen fuera de borrador. Revisa y asocia la guía existente.',409);
            $st=$pdo->prepare('SELECT id FROM guias_cabecera WHERE numero=? AND id<>? LIMIT 1');$st->execute([$numero,$borrador['id']??0]);
            if ($st->fetchColumn()) throw new ApiError('Ya existe una guía con ese número.',409);
            $st=$pdo->prepare('SELECT v.vendedor_id,p.unidad_base_id FROM ventas v JOIN vendedores ve ON ve.id=v.vendedor_id JOIN productos p ON p.id=? WHERE v.id=? AND ve.estado=1');$st->execute([$r['producto_id'],$r['venta_id']]);$origen=$st->fetch();
            if (!$origen || !$origen['unidad_base_id']) throw new ApiError('La venta requiere un vendedor activo y el producto una unidad base para emitir la guía.',409);
            $usuario=(int)usuario_actual()['id'];
            if ($borrador) {
                g7_validar_guia($pdo,$r,(int)$borrador['id'],false,true);
                $guiaId=(int)$borrador['id'];$guiaVersion=(int)$borrador['version']+1;
                $pdo->prepare("UPDATE guias_cabecera SET numero=?,fecha=?,vendedor_id=?,estado_registro='VALIDADO',updated_at=NOW(),updated_by=?,validated_at=NOW(),validated_by=?,version=? WHERE id=?")
                    ->execute([$numero,$fecha,$origen['vendedor_id'],$usuario,$usuario,$guiaVersion,$guiaId]);
            } else {
                $pdo->prepare("INSERT INTO guias_cabecera(numero,fecha,cliente_id,vendedor_id,sucursal_id,estado_registro,created_by,updated_at,updated_by,validated_at,validated_by,version,origen,origen_ref,origen_hash) VALUES(?,?,?,?,?,'VALIDADO',?,NOW(),?,NOW(),?,1,'LOGISTICA_G7',?,?)")
                    ->execute([$numero,$fecha,$r['cliente_id'],$origen['vendedor_id'],$r['sucursal_id'],$usuario,$usuario,$usuario,(string)$id,sha1(implode('|',[$id,$r['producto_id'],$r['cantidad'],$fecha,$numero]))]);
                $guiaId=(int)$pdo->lastInsertId();$guiaVersion=1;
                $pdo->prepare('INSERT INTO guias_detalle(guia_id,producto_id,unidad_id,cantidad) VALUES(?,?,?,?)')->execute([$guiaId,$r['producto_id'],$origen['unidad_base_id'],$r['cantidad']]);
            }
            $pdo->prepare('UPDATE g7_envios SET guia_id=?,guia_version=? WHERE despacho_id=?')->execute([$guiaId,$guiaVersion,$id]);
            $detalle=['guia_id'=>$guiaId,'guia_numero'=>$numero,'fecha'=>$fecha,'tipo'=>'OPERATIVA_NO_SUNAT'];$mensaje='Guía operativa creada, validada y asociada (no reemplaza una GRE de SUNAT)';
        } elseif ($accion==='salir') {
            if ($r['estado_logistica']!=='PREPARADO') throw new ApiError('Confirma primero la preparación del envío.',409);
            g7_envio_venta_valida($pdo,$r);
            if (g7_envio_abiertas($pdo,$id,true)) throw new ApiError('Resuelve la incidencia bloqueante antes de salir.',409);
            if (!$r['guia_id']) throw new ApiError('Asocia o emite una guía validada antes de confirmar la salida.',409);
            g7_validar_guia($pdo,$r,(int)$r['guia_id'],true);
            $b=g7_balance($pdo,(int)$r['producto_id'],(int)$r['almacen_id']);$qty=g7_milesimas($r['cantidad']);
            g7_mutate_stock($pdo,(int)$r['producto_id'],(int)$r['almacen_id'],-(float)g7_decimal($qty),'VENTA_DESPACHO',$r['codigo'],$r['cliente'],null,'Salida con guía '.$r['guia_numero'],-(float)g7_decimal($qty));
            $pdo->prepare("UPDATE g7_despachos SET estado='EN_RUTA',fecha_salida=NOW() WHERE id=?")->execute([$id]);
            $pdo->prepare('UPDATE g7_envios SET cantidad_salida=?,costo_salida=? WHERE despacho_id=?')->execute([g7_decimal($qty),$b['costo_promedio'],$id]);$nuevo='EN_RUTA';$detalle=['cantidad'=>g7_decimal($qty),'guia_id'=>(int)$r['guia_id']];$mensaje='Salida física confirmada; reserva y stock descontados una sola vez';
        } elseif ($accion==='intento') {
            if ($pendiente===0) throw new ApiError('No quedan unidades pendientes de entrega.',409);
            if (g7_envio_abiertas($pdo,$id,true)) throw new ApiError('Resuelve la incidencia bloqueante antes del siguiente intento.',409);
            $resultado=strtoupper(g7_texto($p['resultado']??'','Resultado',20));
            if (!in_array($resultado,['ACEPTADA','PARCIAL','RECHAZADA','FALLIDA'],true)) throw new ApiError('Resultado de entrega inválido');
            $cantidad=g7_milesimas($p['cantidad_aceptada']??'0','Cantidad aceptada',true);
            $rechazada=g7_milesimas($p['cantidad_rechazada']??'0','Cantidad rechazada',true);
            if ($cantidad+$rechazada>$pendiente || ($resultado==='ACEPTADA' && ($cantidad<=0 || $rechazada!==0)) || ($resultado==='PARCIAL' && ($cantidad<=0 || $rechazada<=0)) || ($resultado==='RECHAZADA' && ($cantidad!==0 || $rechazada<=0)) || ($resultado==='FALLIDA' && ($cantidad!==0 || $rechazada!==0))) throw new ApiError('Las cantidades aceptada/rechazada no corresponden al resultado ni al saldo pendiente.',409);
            $receptor=g7_texto($p['receptor']??'','Receptor',150,$cantidad>0);$motivo=g7_texto($p['motivo']??'','Motivo',500,$resultado!=='ACEPTADA');$evidencia=g7_texto($p['evidencia']??'','Evidencia',1000);
            $st=$pdo->prepare('SELECT COALESCE(MAX(numero),0)+1 FROM g7_envio_intentos WHERE despacho_id=?');$st->execute([$id]);$numero=(int)$st->fetchColumn();
            $pdo->prepare('INSERT INTO g7_envio_intentos(despacho_id,numero,resultado,cantidad_aceptada,cantidad_rechazada,receptor,motivo,evidencia,usuario_id) VALUES(?,?,?,?,?,?,?,?,?)')->execute([$id,$numero,$resultado,g7_decimal($cantidad),g7_decimal($rechazada),$receptor,$motivo,$evidencia,usuario_actual()['id']]);
            $pdo->prepare('UPDATE g7_envios SET cantidad_aceptada=cantidad_aceptada+? WHERE despacho_id=?')->execute([g7_decimal($cantidad),$id]);
            $nuevo=['ACEPTADA'=>($cantidad===$pendiente?'ENTREGADO':'EN_RUTA'),'PARCIAL'=>'PARCIAL','RECHAZADA'=>'RECHAZADO','FALLIDA'=>'FALLIDO'][$resultado];
            if ($rechazada>0) {
                $pdo->prepare("INSERT INTO g7_envio_incidencias(despacho_id,tipo,motivo,cantidad,evidencia,usuario_id) VALUES(?,'INFORMATIVA',?,?,?,?)")->execute([$id,mb_substr('Rechazo en intento '.$numero.': '.$motivo,0,500),g7_decimal($rechazada),$evidencia,usuario_actual()['id']]);
            }
            if ($cantidad===$pendiente) $pdo->prepare("UPDATE g7_despachos SET estado='ENTREGADO',fecha_entrega=NOW() WHERE id=?")->execute([$id]);
            $detalle=['numero'=>$numero,'resultado'=>$resultado,'cantidad_aceptada'=>g7_decimal($cantidad),'cantidad_rechazada'=>g7_decimal($rechazada),'receptor'=>$receptor,'motivo'=>$motivo,'evidencia'=>$evidencia];$mensaje='Intento de entrega registrado';
        } elseif ($accion==='incidencia') {
            $tipo=strtoupper(g7_texto($p['tipo']??'','Tipo de incidencia',20));if (!in_array($tipo,['INFORMATIVA','BLOQUEANTE'],true)) throw new ApiError('Tipo de incidencia inválido');
            $motivo=g7_texto($p['motivo']??'','Motivo',500);$evidencia=g7_texto($p['evidencia']??'','Evidencia',1000);
            $pdo->prepare('INSERT INTO g7_envio_incidencias(despacho_id,tipo,motivo,evidencia,usuario_id) VALUES(?,?,?,?,?)')->execute([$id,$tipo,$motivo,$evidencia,usuario_actual()['id']]);$detalle=['incidencia_id'=>(int)$pdo->lastInsertId(),'tipo'=>$tipo,'motivo'=>$motivo,'evidencia'=>$evidencia];$mensaje='Incidencia registrada sin alterar inventario';
        } elseif ($accion==='resolver') {
            $incidenciaId=g7_id($p['incidencia_id']??0,'Incidencia');$st=$pdo->prepare('SELECT * FROM g7_envio_incidencias WHERE id=? AND despacho_id=? FOR UPDATE');$st->execute([$incidenciaId,$id]);$inc=$st->fetch();
            if (!$inc || $inc['estado']!=='ABIERTA') throw new ApiError('La incidencia no existe en este envío o ya fue resuelta.',409);
            $tipo=strtoupper(g7_texto($p['tipo']??'','Resolución',20));if (!in_array($tipo,['CIERRE','RETORNO','PERDIDA','DISPOSICION'],true)) throw new ApiError('Tipo de resolución inválido');
            $cantidad=g7_milesimas($p['cantidad']??'0','Cantidad resuelta',true);$evidencia=g7_texto($p['evidencia']??'','Evidencia de resolución',1000);$documento=g7_texto($p['documento']??'','Documento de respaldo',150,$tipo!=='CIERRE');
            if (($tipo==='CIERRE' && $cantidad!==0) || ($tipo!=='CIERRE' && ($pre || $cantidad<=0 || $cantidad>$pendiente))) throw new ApiError('La resolución no corresponde al saldo físico pendiente.',409);
            if ($tipo==='RETORNO') {
                if (!g7_permiso_persistido($pdo,'editar')) throw new ApiError('Se requiere permiso de inventario para confirmar el retorno físico.',403);
                g7_mutate_stock($pdo,(int)$r['producto_id'],(int)$r['almacen_id'],(float)g7_decimal($cantidad),'RETORNO_ENVIO',$r['codigo'],$r['cliente'],(float)$r['costo_salida'],'Retorno físico confirmado: '.$documento);
                $pdo->prepare('UPDATE g7_envios SET cantidad_retornada=cantidad_retornada+? WHERE despacho_id=?')->execute([g7_decimal($cantidad),$id]);
            } elseif (in_array($tipo,['PERDIDA','DISPOSICION'],true)) {
                $pdo->prepare('UPDATE g7_envios SET cantidad_perdida=cantidad_perdida+? WHERE despacho_id=?')->execute([g7_decimal($cantidad),$id]);
            }
            $pdo->prepare('INSERT INTO g7_envio_resoluciones(despacho_id,incidencia_id,tipo,cantidad,documento,evidencia,usuario_id) VALUES(?,?,?,?,?,?,?)')->execute([$id,$incidenciaId,$tipo,g7_decimal($cantidad),$documento,$evidencia,usuario_actual()['id']]);
            $pdo->prepare("UPDATE g7_envio_incidencias SET estado='RESUELTA',resuelta_en=NOW() WHERE id=?")->execute([$incidenciaId]);
            $detalle=['incidencia_id'=>$incidenciaId,'tipo'=>$tipo,'cantidad'=>g7_decimal($cantidad),'documento'=>$documento,'evidencia'=>$evidencia];$mensaje=$tipo==='RETORNO'?'Retorno físico confirmado con entrada en Kardex':'Resolución documentada';
        } elseif ($accion==='cerrar') {
            if ($pendiente!==0) throw new ApiError('El envío tiene unidades pendientes de aceptar, retornar o disponer.',409);
            if (g7_envio_abiertas($pdo,$id,true)) throw new ApiError('Resuelve las incidencias bloqueantes abiertas antes de cerrar.',409);
            $nuevo=(g7_milesimas($r['cantidad_retornada'],'Retornada',true)>0 || g7_milesimas($r['cantidad_perdida'],'Pérdida',true)>0)?'CERRADO_CON_INCIDENCIA':'CERRADO';
            $pdo->prepare('UPDATE g7_envios SET cerrado_en=NOW() WHERE despacho_id=?')->execute([$id]);$mensaje='Envío cerrado con balance físico en cero';
        } elseif ($accion==='cancelar') {
            if (g7_envio_abiertas($pdo,$id)) throw new ApiError('Resuelve las incidencias abiertas antes de cancelar.',409);
            $motivo=g7_texto($p['motivo']??'','Motivo de cancelación',500);
            g7_mutate_stock($pdo,(int)$r['producto_id'],(int)$r['almacen_id'],0.0,'RESERVA_ANULADA',$r['codigo'],'',null,$motivo,-(float)$r['cantidad']);
            $pdo->prepare("UPDATE g7_despachos SET estado='CANCELADO' WHERE id=?")->execute([$id]);$nuevo='CANCELADO';$detalle=['motivo'=>$motivo];$mensaje='Envío cancelado antes de salir; reserva liberada';
        }
        $nuevaVersion=$version+1;
        $pdo->prepare('UPDATE g7_envios SET estado_logistica=?,version=?,actualizado_en=NOW() WHERE despacho_id=?')->execute([$nuevo,$nuevaVersion,$id]);
        $pdo->prepare('INSERT INTO g7_envio_eventos(despacho_id,version,accion,detalle,usuario_id) VALUES(?,?,?,?,?)')->execute([$id,$nuevaVersion,strtoupper($accion),json_encode($detalle,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),usuario_actual()['id']]);
        g7_audit($pdo,'ENVIO_'.strtoupper($accion),['despacho_id'=>$id,'version'=>$nuevaVersion]+$detalle);
        $respuesta=['envio_id'=>$id,'version'=>$nuevaVersion,'mensaje'=>$mensaje,'replayed'=>false];
        $pdo->prepare('INSERT INTO g7_envio_operaciones(despacho_id,clave,accion,solicitud_hash,respuesta,usuario_id) VALUES(?,?,?,?,?,?)')->execute([$id,$clave,$accion,$hash,json_encode($respuesta,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),usuario_actual()['id']]);
        if ($own) $pdo->commit();return $respuesta;
    } catch (Throwable $e) { if ($own && $pdo->inTransaction()) $pdo->rollBack();throw $e; }
}
