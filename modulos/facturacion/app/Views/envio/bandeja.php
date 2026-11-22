<div class="page envio-page">
    <header class="page-header envio-header">
        <div class="page-title">
            <div class="page-icon"><?= icono('send',22) ?></div>
            <div>
                <div class="envio-title-line"><h1><?= e($titulo) ?></h1><span class="envio-mode <?= $modoDemo?'is-demo':'is-live' ?>"><?= $modoDemo?'SIMULACIÓN':'GATEWAY ACTIVO' ?></span></div>
                <p><?= $filtro==='incidencias'?'Revisa observaciones, rechazos y errores que requieren atención.':'Consulta el estado de envío y la última respuesta de cada comprobante.' ?></p>
            </div>
        </div>
    </header>

    <?php if(!empty($_SESSION['flash'])): ?><div class="notice notice-ok"><?= e($_SESSION['flash']) ?></div><?php unset($_SESSION['flash']);endif ?>
    <?php if(!empty($_SESSION['flash_error'])): ?><div class="notice notice-error"><?= e($_SESSION['flash_error']) ?></div><?php unset($_SESSION['flash_error']);endif ?>
    <?php if($error): ?><div class="notice notice-error"><?= e($error) ?>. Importa la base completa del ERP (sql/bayer_pucchun_erp_v2.sql).</div><?php endif ?>

    <div class="envio-context <?= $modoDemo?'demo-context':'live-context' ?>">
        <span class="context-icon"><?= icono($modoDemo?'info':'check',17) ?></span>
        <span><?= $modoDemo?'<strong>Envío simulado.</strong> Las respuestas se generan en el sistema; no se transmite a SUNAT.':'<strong>Gateway externo configurado.</strong> Las solicitudes se procesan con el servicio definido en el entorno.' ?></span>
        <?php if($modoDemo): ?><span class="context-hint">Sin validez tributaria.</span><?php endif ?>
    </div>

    <?php if($modoDemo): ?><details class="envio-help"><summary>¿Qué significa cada resultado?</summary><div class="help-grid"><p><strong>Aceptado:</strong> registra una respuesta favorable y permite descargar el CDR simulado.</p><p><strong>Observado:</strong> el comprobante es válido con observaciones; no se reenvía: se registra la revisión.</p><p><strong>Rechazado:</strong> rechazo de contenido; se corrige el dato, se regenera y firma el XML y se vuelve a enviar.</p><p><strong>Pendiente con ticket:</strong> simula una recepción asíncrona; al consultar el ticket, el simulador devuelve aceptación y un CDR, sin contactar un proveedor.</p><p><strong>Error temporal:</strong> falla técnica de comunicación; el comprobante queda pendiente de reintento y se reenvía el mismo XML.</p></div></details><?php endif ?>

    <section class="envio-metrics" aria-label="Resumen de comprobantes">
        <a class="envio-metric metric-process" href="<?= url('/envio/estado') ?>?estado=firmado"><span class="metric-label">Listos para enviar</span><strong><?= (int)$resumen['listos'] ?></strong><span class="metric-foot">XML firmado, aún sin enviar</span></a>
        <a class="envio-metric" href="<?= url('/envio/estado') ?>?estado=enviado"><span class="metric-label">Esperando respuesta</span><strong><?= (int)$resumen['esperando'] ?></strong><span class="metric-foot">Envío recibido o ticket pendiente</span></a>
        <a class="envio-metric metric-incident" href="<?= url('/envio/rechazos') ?>"><span class="metric-label">Requieren atención</span><strong><?= (int)$resumen['incidencias'] ?></strong><span class="metric-foot">Rechazos, fallas técnicas y observaciones sin revisar</span></a>
        <a class="envio-metric metric-success" href="<?= url('/envio/estado') ?>?estado=aceptado"><span class="metric-label">Aceptados</span><strong><?= (int)$resumen['aceptados'] ?></strong><span class="metric-foot">Con respuesta favorable</span></a>
    </section>

    <?php if($detalle): ?>
    <section class="card envio-detail" id="detalle">
        <div class="card-header envio-detail-header">
            <div><span class="metric-label">Detalle del comprobante</span><h2 class="card-title"><?= e(strtoupper($detalle['tipo']).' '.$detalle['serie'].'-'.str_pad((string)$detalle['numero'],8,'0',STR_PAD_LEFT)) ?></h2><span class="muted"><?= e($detalle['cliente']) ?></span></div>
            <div class="detail-header-actions"><span class="badge <?= e(clase_estado_comprobante($detalle['estado'])) ?>"><?= e(estado_comprobante($detalle['estado'])) ?></span><?php if(($intentos[0]['origen']??null)==='simulado'): ?><span class="badge badge-simulado" title="La última respuesta la generó el simulador: no fue enviado a SUNAT">Simulado</span><?php endif ?><a class="btn" href="<?= url($filtro==='incidencias'?'/envio/rechazos':'/envio/estado') ?>">Cerrar detalle</a></div>
        </div>
        <div class="card-body envio-detail-body">
            <?php $escenarios = $modoDemo ? '<label class="field">Resultado simulado<select name="escenario"><option value="aceptado">Aceptado</option><option value="observado">Observado</option><option value="rechazado">Rechazado</option><option value="pendiente">Pendiente con ticket</option><option value="error_transitorio">Error temporal</option></select></label>' : ''; ?>
            <?php if($interrumpido): ?>
                <!-- El proceso se cortó con el intento en «enviando»: se recupera con la misma clave, sin crear otro intento -->
                <div class="action-panel"><div><strong>Intento interrumpido</strong><p class="muted">El último envío se cortó antes de guardar la respuesta. Al recuperarlo se consulta de nuevo con la misma clave de control: no se crea otro intento ni se duplica el envío.</p></div>
                <form method="post" action="<?= url('/envio/recuperar') ?>" class="envio-action-form" data-confirmar="¿Recuperar el intento? Se consultará de nuevo con la misma clave, sin crear otro intento."><input type="hidden" name="id" value="<?= (int)$detalle['id'] ?>"><?= $escenarios ?>
                    <button class="btn btn-primary" type="submit">Recuperar intento</button>
                </form></div>
            <?php elseif($detalle['estado']==='firmado'): ?>
                <div class="action-panel"><div><strong>Listo para enviar</strong><p class="muted">Se usará el XML firmado asociado a este comprobante.</p></div>
                <form method="post" action="<?= url('/envio/enviar') ?>" class="envio-action-form" data-confirmar="¿Enviar el comprobante? Después del envío ya no podrá anularse localmente."><input type="hidden" name="id" value="<?= (int)$detalle['id'] ?>"><?= $escenarios ?>
                    <button class="btn btn-primary" type="submit">Enviar comprobante</button>
                </form></div>
            <?php elseif($detalle['estado']==='pendiente_reintento'): ?>
                <!-- Falla técnica: se reenvía el mismo XML; no corrige datos -->
                <div class="action-panel"><div><strong>Reintento técnico</strong><p class="muted">El intento anterior falló por un problema técnico, sin respuesta válida del proveedor. Se reenviará el mismo XML firmado, sin cambios.</p></div>
                <form method="post" action="<?= url('/envio/enviar') ?>" class="envio-action-form" data-confirmar="¿Reintentar el envío con el mismo XML firmado?"><input type="hidden" name="id" value="<?= (int)$detalle['id'] ?>"><?= $escenarios ?>
                    <label class="field">Motivo del reintento<input name="motivo" maxlength="500" required placeholder="Ej.: se restableció la conexión con el proveedor"></label>
                    <button class="btn btn-primary" type="submit">Reintentar envío</button>
                </form></div>
            <?php elseif($detalle['estado']==='rechazado'): ?>
                <!-- Rechazo de contenido: se corrige y se regenera el XML (mismo número); reenviar el mismo XML volvería a ser rechazado -->
                <div class="action-panel"><div><strong>Corregir el contenido</strong><p class="muted">Un rechazo no se resuelve reenviando el mismo XML. Corrige el dato en su módulo de origen (empresa o cliente) y regenera el XML con el mismo número; luego fírmalo y vuelve a enviarlo. El XML rechazado queda en el historial.</p></div>
                <form method="post" action="<?= url('/comprobantes/xml/corregir') ?>" class="retry-form" data-confirmar="¿Corregir y regenerar el XML? El XML actual pasará al historial y habrá que firmarlo de nuevo."><input type="hidden" name="id" value="<?= (int)$detalle['id'] ?>">
                    <label class="field">Qué se corrigió<textarea name="motivo" maxlength="500" required placeholder="Ej.: se corrigió la razón social del cliente según el rechazo"></textarea></label>
                    <button class="btn btn-primary" type="submit">Corregir y regenerar XML</button>
                </form></div>
            <?php elseif($detalle['estado']==='observado' && $observacionRevisada): ?>
                <div class="action-panel"><div><strong>Observación revisada</strong><p class="muted">El comprobante es válido ante SUNAT. La revisión quedó registrada en el historial de abajo.</p></div></div>
            <?php elseif($detalle['estado']==='observado'): ?>
                <!-- Observado = válido: no se reenvía; se registra la revisión -->
                <div class="action-panel"><div><strong>Revisar la observación</strong><p class="muted">El comprobante es válido ante SUNAT y no se reenvía. Registra qué se revisó y qué se corregirá en los siguientes comprobantes.</p></div>
                <form method="post" action="<?= url('/envio/observacion/revisar') ?>" class="retry-form"><input type="hidden" name="id" value="<?= (int)$detalle['id'] ?>">
                    <label class="field">Qué se revisó<textarea name="nota" maxlength="500" required placeholder="Ej.: se actualizará la dirección del cliente para los próximos comprobantes"></textarea></label>
                    <button class="btn btn-primary" type="submit">Registrar revisión</button>
                </form></div>
            <?php elseif($detalle['estado']==='enviado'): ?><div class="action-panel"><div><strong>En espera de respuesta</strong><p class="muted"><?= $modoDemo?'La consulta es simulada; no hay conexión a un proveedor externo.':'Se consultará el ticket en el gateway configurado.' ?></p></div><form method="post" action="<?= url('/envio/consultar') ?>"><input type="hidden" name="id" value="<?= (int)$detalle['id'] ?>"><button class="btn btn-primary" type="submit"><?= $modoDemo?'Simular consulta':'Consultar respuesta' ?></button></form></div>
            <?php endif ?>

            <h3 class="envio-section-title">Historial de procesamiento <span><?= count($intentos) ?> <?= count($intentos)===1?'intento':'intentos' ?></span></h3>
            <?php if($intentos): ?><div class="attempt-list">
                <?php foreach($intentos as $intento): ?><article class="attempt-card"><div class="attempt-heading"><div><span class="attempt-number">Intento <?= (int)$intento['intento'] ?></span><?php if($intento['estado']==='enviando'&&strtotime($intento['solicitado_en'])<time()-\App\Services\EnvioService::SEGUNDOS_INTERRUMPIDO): ?><span class="badge badge-warn" title="Se cortó antes de guardar la respuesta">Interrumpido</span><?php else: ?><span class="badge <?= in_array($intento['estado'],['aceptado'],true)?'badge-ok':(in_array($intento['estado'],['rechazado','error_transitorio'],true)?'badge-error':(in_array($intento['estado'],['observado','pendiente'],true)?'badge-warn':'badge-info')) ?>"><?= e(ucfirst(str_replace('_',' ',$intento['estado']))) ?></span><?php endif ?><?php if($intento['origen']==='simulado'): ?><span class="badge badge-simulado" title="Respuesta generada por el simulador: no fue enviado a SUNAT">Simulado</span><?php endif ?></div><?php if($intento['cdr_nombre']): ?><a class="btn btn-small" href="<?= url('/envio/cdr') ?>?envio=<?= (int)$intento['id'] ?>"><?= icono('file-down',15) ?> Descargar XML del CDR<?= $intento['origen']==='simulado'?' simulado':'' ?></a><?php endif ?></div>
                    <p class="attempt-response"><?php if($intento['codigo_respuesta']): ?><code><?= e($intento['codigo_respuesta']) ?></code><?php endif ?> <?= e($intento['mensaje_respuesta']??'Sin respuesta registrada') ?></p>
                    <?php if($intento['ticket']): ?><p class="attempt-ticket">Ticket de consulta: <code><?= e($intento['ticket']) ?></code></p><?php endif ?>
                    <div class="attempt-events"><?php foreach($intento['eventos'] as $ev): ?><div class="attempt-event"><span class="event-dot <?= e($ev['nivel']) ?>"></span><span><strong><?= e(ucfirst(str_replace('_',' ',$ev['etapa']))) ?></strong> · <?= e($ev['detalle']) ?><small><?= e($ev['creado_en']) ?></small></span></div><?php endforeach ?></div>
                </article><?php endforeach ?>
            </div><?php else: ?><div class="empty"><strong>Aún no hay intentos</strong><p>Cuando envíes este comprobante, aquí aparecerán las respuestas y eventos.</p></div><?php endif ?>
        </div>
    </section>
    <?php endif ?>

    <section class="card envio-inbox">
        <div class="card-header inbox-header"><div><h2 class="card-title"><?= $filtro==='incidencias'?'Incidencias por resolver':'Bandeja de procesamiento' ?></h2><p class="muted"><?= (int)$paginacion->total ?> registro<?= $paginacion->total===1?'':'s' ?></p></div><nav class="inbox-tabs"><a class="<?= $filtro!=='incidencias'?'active':'' ?>" href="<?= url('/envio/estado') ?>">Todos</a><a class="<?= $filtro==='incidencias'?'active':'' ?>" href="<?= url('/envio/rechazos') ?>">Requieren atención</a></nav></div>
        <form method="get" id="envio-filters" class="envio-filters" action="<?= url($filtro==='incidencias'?'/envio/rechazos':'/envio/estado') ?>">
            <label class="field search-field"><span>Buscar comprobante o cliente</span><input type="search" name="buscar" value="<?= e($buscar) ?>" placeholder="Ej. F001-00000001 o nombre del cliente" autocomplete="off"></label>
            <label class="field"><span>Estado</span><select name="estado"><?php foreach(($filtro==='incidencias'?[''=>'Todos los estados','observado'=>'Observado','rechazado'=>'Rechazado','pendiente_reintento'=>'Pendiente de reintento']:[''=>'Todos los estados','proceso'=>'En proceso','firmado'=>'Firmado','enviado'=>'Enviado','aceptado'=>'Aceptado','observado'=>'Observado','rechazado'=>'Rechazado','pendiente_reintento'=>'Pendiente de reintento']) as $key=>$label): ?><option value="<?= e($key) ?>" <?= $estadoFiltro===$key?'selected':'' ?>><?= e($label) ?></option><?php endforeach ?></select></label>
            <?php if($buscar!==''||$estadoFiltro!==''): ?><div class="filter-actions"><a class="btn" href="<?= url($filtro==='incidencias'?'/envio/rechazos':'/envio/estado') ?>">Limpiar filtros</a></div><?php endif ?>
        </form>
        <div class="table-wrap"><table class="envio-table"><thead><tr><th>Comprobante</th><th>Origen ERP</th><th>Cliente</th><th>Estado</th><th>Último intento</th><th>Respuesta</th><th></th></tr></thead><tbody>
        <?php foreach($filas as $f): ?><tr><td><strong><?= e(strtoupper($f['tipo']).' '.$f['serie'].'-'.str_pad((string)$f['numero'],8,'0',STR_PAD_LEFT)) ?></strong><?php if($f['solicitado_en']): ?><small><?= e(date('d/m/Y',strtotime($f['solicitado_en']))) ?></small><?php endif ?></td><td><?php if($f['venta_id']): ?><span class="origin-tag">Venta #<?= (int)$f['venta_id'] ?></span><small>Comercial · ERP</small><?php elseif($f['serie_referencia']): ?><span class="origin-tag">Ajuste relacionado</span><small><?= e($f['serie_referencia'].'-'.str_pad((string)$f['numero_referencia'],8,'0',STR_PAD_LEFT)) ?></small><?php else: ?><span class="muted">Registro manual</span><?php endif ?></td><td><?= e($f['cliente']) ?></td><td><div class="estado-celda"><span class="badge <?= e(clase_estado_comprobante($f['comprobante_estado'])) ?>"><?= e(estado_comprobante($f['comprobante_estado'])) ?></span><?php if($f['origen_respuesta']==='simulado'): ?><span class="badge badge-simulado" title="La respuesta la generó el simulador: no fue enviado a SUNAT">Simulado</span><?php endif ?></div></td><td><?php if($f['intento']): ?><strong>#<?= (int)$f['intento'] ?></strong><small><?= e(ucfirst(str_replace('_',' ',$f['envio_estado']))) ?><?= $f['respondido_en']?' · '.e(date('d/m H:i',strtotime($f['respondido_en']))):'' ?></small><?php else: ?><span class="muted">Aún sin envío</span><?php endif ?></td><td class="response-cell"><?php if($f['codigo_respuesta']): ?><code><?= e($f['codigo_respuesta']) ?></code><?php endif ?> <?= e(match($f['envio_estado']??''){ 'aceptado'=>'Aceptado', 'observado'=>'Aceptado con observación', 'rechazado'=>'Rechazado', 'pendiente'=>'Pendiente de respuesta', 'error_transitorio'=>'Error temporal', default=>$f['mensaje_respuesta']??'Sin respuesta todavía' }) ?><?php if($f['ticket']&&$f['envio_estado']==='pendiente'): ?><small>Ticket pendiente</small><?php endif ?><?php $accion=\App\Services\EnvioService::accionSugerida($f['comprobante_estado'],(bool)$f['interrumpido'],(bool)$f['observacion_revisada']);if($accion): ?><small class="accion-sugerida" title="<?= e($accion[1]) ?>">→ <?= e($accion[0]) ?></small><?php endif ?></td><td><a class="btn btn-small" href="<?= url($filtro==='incidencias'?'/envio/rechazos':'/envio/estado') ?>?id=<?= (int)$f['comprobante_id'] ?><?= $buscar!==''?'&buscar='.rawurlencode($buscar):'' ?>">Ver detalle</a></td></tr><?php endforeach ?>
        <?php if(!$filas): ?><tr><td colspan="6"><div class="empty"><strong><?= $buscar!==''?'No encontramos coincidencias':'No hay comprobantes en esta bandeja' ?></strong><p><?= $buscar!==''?'Prueba con otra serie, número o cliente.':'Cuando haya envíos, aparecerán aquí con su estado y respuesta.' ?></p></div></td></tr><?php endif ?></tbody></table></div>
        <?php require BASE_PATH . '/app/Views/parciales/paginacion.php'; ?>
    </section>
</div>
<script src="<?= asset('js/envio.js') ?>" defer></script>
