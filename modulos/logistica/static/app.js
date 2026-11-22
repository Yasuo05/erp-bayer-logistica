/* MATT INNOVA | Grupo 7 | ERP Compras, Inventarios y Logística
   Interfaz sin dependencias externas. Toda operación de negocio se valida en el servidor.
*/
(() => {
'use strict';
const state={page:'despachos',catalog:null,warehouse:'',stockQ:'',stockEstado:'todos',
 product:'',movementWarehouse:'',movementType:'todos',movementQ:'',countTab:'conteos',
 detail:null,orderDetails:null,countDetails:null,expandedStock:null,loading:false,renderId:0,
 envioQ:'',envioEstado:'todos',envioGroup:'todos',envioId:null,expediente:null,envioBusy:false,formBusy:false};
const $=sel=>document.querySelector(sel);
const pageConfig=JSON.parse(document.getElementById('g7-page-config')?.textContent||'{}');
const routes={despachos:'envios.php',expediente:'envio.php',inicio:'panel.php',stock:'stock.php',kardex:'kardex.php',conteos:'inventario.php',compras:'compras.php',recepciones:'recepciones.php',traslados:'traslados.php',pagos:'cuentas_por_pagar.php',auditoria:'auditoria.php',...(pageConfig.routes||{})};
const pageKeys=['despachos','inicio','stock','kardex','conteos','compras','recepciones','traslados','pagos','auditoria'];
function pageUrl(page,params={}){
 const route=routes[page]||routes.despachos,query=new URLSearchParams();
 for(const [key,value] of Object.entries(params))if(value!==undefined&&value!==null&&value!=='')query.set(key,String(value));
 const suffix=query.toString();return route+(suffix?(route.includes('?')?'&':'?')+suffix:'');
}
const capabilities=JSON.parse(document.getElementById('g7-capabilities')?.textContent||'{}');
const csrf=document.querySelector('meta[name="g7-csrf"]')?.content||'';
const esc=v=>String(v===null||v===undefined?'':v).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const num=(x,dec=0)=>new Intl.NumberFormat('es-PE',{minimumFractionDigits:dec,maximumFractionDigits:dec}).format(Number(x)||0);
const soles=x=>'S/ '+num(x,2);
const fmtDate=x=>{if(!x)return '—';const s=String(x);const m=s.match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}:\d{2}))?/);return m?`${m[3]}/${m[2]}/${m[1]}${m[4]?' · '+m[4]:''}`:s};
const icon=(name)=>{const paths={search:'<circle cx="11" cy="11" r="8"/><path d="m21 21-4-4"/>',refresh:'<path d="M20 7v5h-5M4 17v-5h5"/><path d="M6 7a7 7 0 0 1 12-2l2 2M4 17l2 2a7 7 0 0 0 12-2"/>',close:'<path d="m6 6 12 12M18 6 6 18"/>',check:'<path d="m5 12 4 4L19 6"/>',chevron:'<path d="m6 9 6 6 6-6"/>',empty:'<path d="M8 3h8l4 4v14H4V3zM8 11h8M8 15h5"/>'};return `<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${paths[name]||paths.empty}</svg>`;};
const statusLabel=value=>({BORRADOR:'En preparación',PREPARACION:'En preparación',PREPARADO:'Preparado',EN_RUTA:'En ruta',ENTREGADO:'Entregado',CERRADO:'Cerrado',CERRADO_CON_INCIDENCIA:'Cerrado con incidencia',CANCELADO:'Cancelado',PARCIAL:'Parcial',RECHAZADO:'Rechazado',RECHAZADA:'Rechazada',FALLIDO:'Fallido',FALLIDA:'Fallida',ACEPTADA:'Aceptada',ABIERTA:'Abierta',RESUELTA:'Resuelta'}[value]||String(value||'—').replaceAll('_',' ').toLocaleLowerCase('es').replace(/^./,c=>c.toLocaleUpperCase('es')));
function toast(msg,type='success'){
 const dialog=$('#modalMount dialog[open]');
 if(type==='error'&&dialog){
  const body=dialog.querySelector('.modal-content');
  body.querySelector('.g7-inline-error')?.remove();
  const message=document.createElement('div');message.className='notice red g7-inline-error';message.setAttribute('role','alert');message.textContent=msg;
  body.appendChild(message);message.scrollIntoView({block:'nearest'});return;
 }
 const div=document.createElement('div');div.className='toast '+(type==='error'?'error':'success');div.textContent=msg;$('#toastMount').appendChild(div);
 setTimeout(()=>div.remove(),4500);
}
async function api(path,method='GET',data){
 const opts={method,headers:{'Accept':'application/json'}};
 if(method!=='GET')opts.headers['X-CSRF-Token']=csrf;
 if(data!==undefined){opts.headers['Content-Type']='application/json';opts.body=JSON.stringify(data)}
 const url = 'api.php?route=' + encodeURIComponent(path);
 let r;try{r=await fetch(url,opts)}catch(e){throw new Error('No se puede conectar al servidor de Logística.')}let j;
 try{j=await r.json()}catch(e){throw new Error('Respuesta no válida del servidor')}
 if(!r.ok||!j.ok){const error=new Error(j.error||`Error HTTP ${r.status}`);error.status=r.status;throw error;}
 return j.data;
}
const warehouseOptions=(selected='',all=false)=>`${all?'<option value="">Todos los almacenes</option>':''}${state.catalog.almacenes.map(w=>`<option value="${w.id}" ${String(w.id)===String(selected)?'selected':''}>${esc(w.nombre)}</option>`).join('')}`;
const destinationWarehouseOptions=(origin,selected='')=>'<option value="">Selecciona el almacén destino</option>'+(state.catalog.almacenes_destino||[]).filter(w=>Number(w.id)!==Number(origin)).map(w=>`<option value="${Number(w.id)}" ${String(w.id)===String(selected)?'selected':''}>${esc(w.nombre)}${w.sucursal?' · '+esc(w.sucursal):''}</option>`).join('');
const productOptions=(selected='',all=false)=>`${all?'<option value="">Todos los artículos</option>':''}${state.catalog.productos.map(p=>`<option value="${p.id}" ${String(p.id)===String(selected)?'selected':''}>${esc(p.codigo)} · ${esc(p.nombre)}</option>`).join('')}`;
const supplierOptions=(selected='')=>state.catalog.proveedores.map(s=>`<option value="${s.id}" ${String(s.id)===String(selected)?'selected':''}>${esc(s.nombre)}</option>`).join('');
function statusBadge(status){let c='badge-gray';const v=String(status||'').toUpperCase();
 if(['CRITICO','MERMA','CANCELADO','VENCIDO','RECHAZADO','RECHAZADA','FALLIDO','FALLIDA'].includes(v))c='badge-red';
 else if(['BAJO','EN_TRANSITO','PARCIAL','PENDIENTE','BORRADOR','PREPARACION','EN_PREPARACION','EN_RESOLUCION','ABIERTA'].includes(v))c='badge-yellow';
 else if(['OPTIMO','RECIBIDA','RECIBIDO','REGULARIZADO','PAGADO','ENTREGADO'].includes(v))c='badge-green';
 else if(['EN_RUTA','COMPRA','VALIDADO','PUBLICADO','PREPARADO','PREPARADA'].includes(v))c='badge-blue';
 else if(['CERRADO','CERRADO_CON_INCIDENCIA','RESUELTA'].includes(v))c='badge-purple';
 return `<span class="badge ${c}">${esc(statusLabel(v))}</span>`;
}
function pageHead(title,subtitle,actions=''){return `<template data-g7-heading><h2>${title}</h2><p>${subtitle}</p></template>${actions?`<div class="page-head"><div class="actions">${actions}</div></div>`:''}`;}
function mountContent(html,focus=false){
 $('#content').innerHTML=html;
 const meta=$('#content template[data-g7-heading]');
 if(meta){
  const title=meta.content.querySelector('h2').textContent,subtitle=meta.content.querySelector('p').textContent;
  const sharedTitle=$('.page-headline .page-title'),sharedSubtitle=$('.page-headline .page-subtitle'),crumb=$('.breadcrumb-trail .current-crumb');
  if(sharedTitle)sharedTitle.textContent=title;if(sharedSubtitle)sharedSubtitle.textContent=subtitle;if(crumb)crumb.textContent=title;
  const group=$('.breadcrumb-trail .group-crumb')?.textContent||'Logística';
  document.title=`${title} · ${group} · Bayer-Pucchún ERP`;meta.remove();
 }
 if(focus)$('#content').focus({preventScroll:true});
}
function btn(label,action,variant='',more=''){
 const navigation={'go-kardex':'kardex','go-stock':'stock','go-compras':'compras','envios-back':'despachos'};
 const classes=`btn ${variant} ${/btn-primary|btn-success/.test(variant)?'btn-primary-action':'btn-sec'}`;
 if(navigation[action])return `<a class="${classes}" href="${esc(pageUrl(navigation[action]))}" ${more}>${label}</a>`;
 const required=action==='modal-minimos'?'editar':action.startsWith('modal-')?'crear':null;
 return `<button type="button" class="${classes}" data-action="${action}" ${required&&!capabilities[required]?'disabled':''} ${more}>${label}</button>`;
}
function stat(label,val,sub='',tone='',extra=''){return `<div class="stat-card ${tone}"><span class="stat-marker"></span><div class="stat-label">${label}</div><div class="stat-value ${extra}">${val}</div><div class="stat-sub">${sub}</div></div>`}
const empty=(title='Sin registros',note='Todavía no hay datos que mostrar.')=>`<div class="empty empty-state-wrap"><div class="empty-icon-box">${icon('empty')}</div><strong>${title}</strong><p>${note}</p></div>`;
function panel(title,subtitle,body,actions=''){return `<section class="panel"><div class="panel-heading"><div><h2>${title}</h2>${subtitle?`<p>${subtitle}</p>`:''}</div>${actions}</div>${body}</section>`}
function table(headers,rows,emptyMessage='No se encontraron registros.'){
 if(!rows.length)return empty('Sin resultados',emptyMessage);
 return `<div class="table-wrap tabla-wrap"><table class="data-table tabla-interactiva"><thead><tr>${headers.map(h=>`<th scope="col" ${h==='Acciones'?'class="right"':''}>${h}</th>`).join('')}</tr></thead><tbody>${rows.join('')}</tbody></table></div>`;
}
function legend(){return `<div class="notice-row">
 <div class="legend"><b>Disponible = Existencia − Reservado</b>La reserva corresponde a pedidos preparados y aún no despachados.</div>
 <div class="legend red"><b>● Rojo · Crítico</b>Disponible igual o inferior al mínimo configurado.</div>
 <div class="legend yellow"><b>● Amarillo · Bajo</b>Disponible superior al mínimo y hasta el 130 % de este.</div>
 <div class="legend green"><b>● Verde · Óptimo</b>Existencias suficientes para la operación.</div>
 </div>`}
function enterCell(v){return `<span class="${Number(v)>0?'green-text':''}">${Number(v)>0?num(v,3):'—'}</span>`}
function exitCell(v){return `<span class="${Number(v)>0?'danger-text':''}">${Number(v)>0?num(v,3):'—'}</span>`}
function loading(){return `<div class="loading-screen"><div class="loading-spin"></div><p>Cargando información…</p></div>`}
async function init(){
 state.page=pageKeys.includes(pageConfig.page)?pageConfig.page:'despachos';
 const shipmentId=Number(pageConfig.shipmentId);
 state.envioId=Number.isInteger(shipmentId)&&shipmentId>0?shipmentId:null;
 let legacy='';try{legacy=decodeURIComponent(location.hash.slice(1));}catch{}
 if(pageKeys.includes(legacy)){
  if(legacy==='despachos'||(legacy===state.page&&!state.envioId))history.replaceState(null,'',location.pathname+location.search);
  else{location.replace(pageUrl(legacy));return;}
 }
 const product=new URLSearchParams(location.search).get('producto');
 if(state.page==='kardex'&&product&&/^[1-9]\d*$/.test(product))state.product=product;
 try{state.catalog=await api('/api/catalogos');
  await renderCurrentPage();
 }catch(e){mountContent(`${pageHead('Error de conexión','No se pudo cargar la base de datos local.')}<div class="notice red">${esc(e.message)}</div>`)}
}
function navigate(page,params={}){
 if(!pageKeys.includes(page)&&page!=='expediente')return;
 const target=new URL(pageUrl(page,params),location.href);
 if(target.pathname!==location.pathname||target.search!==location.search){location.assign(target.href);return;}
 return refresh();
}
async function renderCurrentPage(){
 state.detail=null;state.orderDetails=null;state.countDetails=null;
 $('#content').innerHTML=loading();
 const token=++state.renderId;
 try{
  const funcs={inicio:renderInicio,stock:renderStock,kardex:renderKardex,conteos:renderConteos,
   compras:renderCompras,recepciones:renderRecepciones,traslados:renderTraslados,
   despachos:renderDespachos,pagos:renderPagos,auditoria:renderAuditoria};
  const html=await funcs[state.page]();
  if(token===state.renderId){mountContent(html,true);window.scrollTo({top:0,behavior:'instant'})}
 }catch(e){if(token===state.renderId){mountContent(`${pageHead('No se pudo cargar esta sección',esc(e.message))}<div class="notice red">Verifica que el servidor esté funcionando correctamente.</div>`);toast(e.message,'error')}}
}
async function refresh(){await renderCurrentPage()}
function chartBars(d){const values=[d.criticos,d.bajos,d.optimos],texts=['Críticos','Bajos','Óptimos'];const max=Math.max(...values,1);
 return `<div class="spark-bars">${values.map((v,i)=>`<div style="--height:${Math.max(10,v/max*94)}%;background:${['#d45a53','#e8b447','#299d77'][i]}"><span>${texts[i]}</span></div>`).join('')}</div>`}
async function renderInicio(){
 const [d,sedes]=await Promise.all([api('/api/resumen'),api('/api/sedes')]);
 const activity=d.actividad.slice(0,6).map(r=>`<li><span class="activity-dot"></span><div><b>${esc(r.producto)}</b><small>${esc(r.tipo.replaceAll('_',' '))} · ${esc(r.almacen)} · ${fmtDate(r.fecha)}</small></div></li>`).join('');
 const alerts=d.alertas.slice(0,7).map(r=>`<tr><td><strong>${esc(r.nombre)}</strong><span class="meta-line">${esc(r.codigo)}</span></td><td class="center">${num(r.disponible,3)}</td><td class="center">${num(r.minimo,3)}</td><td>${statusBadge(r.estado)}</td><td class="right"><a class="link-button" href="${esc(pageUrl('stock'))}">Revisar stock</a></td></tr>`);
 return `${pageHead('Panel de control','Gestión integral del abastecimiento, existencias y movimiento físico de la mercadería.',
 btn('Ver movimientos','go-kardex','btn-primary'))}
 <div class="welcome-banner"><div><h2>Centro de operaciones · Grupo 7</h2><p>Controla qué ingresa, dónde está y cómo se mueve la mercadería en cada sede.</p></div>${btn('Revisar alertas de stock','go-stock','')}</div>
 <div class="cards">${stat('Valor del inventario',soles(d.valor_inventario),'Valorización de existencias','blue','smaller')}
 ${stat('Stock crítico',num(d.criticos),'Requieren reposición','danger')}
 ${stat('Stock bajo',num(d.bajos),'Artículos bajo seguimiento','warning')}
 ${stat('Stock óptimo',num(d.optimos),'Operación con cobertura','success')}
 ${stat('Mercancía en tránsito',num(d.en_transito,3),'Entre almacenes','blue')}</div>
 <div class="grid-two">${panel('Alertas de reposición','Artículos próximos o por debajo del stock mínimo.',
 table(['Artículo','Disponible','Mínimo','Situación','Acciones'],alerts,'No hay alertas de inventario.'))}
 ${panel('Resumen operativo','Indicadores principales del módulo Grupo 7.',
 `<div class="panel-body">${[
 ['Artículos catalogados',num(d.articulos)],['Pedidos de compra pendientes',num(d.oc_pendientes)],
 ['Unidades reservadas',num(d.reservado_unidades,3)],['Despachos activos',num(d.despachos_pendientes)],
 ['Cuentas por pagar',soles(d.cxp_pendiente)]].map(x=>`<div class="kv"><span>${x[0]}</span><b>${x[1]}</b></div>`).join('')}</div>`)}</div>
 <div class="grid-two">${panel('Existencias por sede','Consolidado físico de los almacenes registrados.',
 table(['Sede','Artículos activos','Unidades','Valor de stock'],sedes.map(s=>`<tr><td><strong>${esc(s.nombre)}</strong><span class="meta-line">${esc(s.codigo)}</span></td><td>${s.productos}</td><td>${num(s.unidades,3)}</td><td class="money">${soles(s.valor)}</td></tr>`)))}
 ${panel('Actividad reciente','Últimos movimientos registrados en Kardex.',`<div class="panel-body"><ul class="activity-list">${activity||'<li>Sin movimientos aún.</li>'}</ul></div>`)}</div>
 <div class="notice"><strong>Integración ERP:</strong> Los grupos 4 y 5 entregan sedes, usuarios, productos y proveedores. El Grupo 7 entrega disponibilidad a ventas (G8) y resultados logísticos a analítica (G9). Esta instancia funciona con datos locales de demostración.</div>`;
}
async function renderStock(){
 const p=new URLSearchParams();if(state.warehouse)p.set('almacen',state.warehouse);if(state.stockQ)p.set('q',state.stockQ);
 const [rows,d]=await Promise.all([api('/api/stock?'+p),api('/api/resumen')]);
 const counts={todos:rows.length,CRITICO:rows.filter(x=>x.estado==='CRITICO').length,BAJO:rows.filter(x=>x.estado==='BAJO').length,OPTIMO:rows.filter(x=>x.estado==='OPTIMO').length};
 const shown=state.stockEstado==='todos'?rows:rows.filter(x=>x.estado===state.stockEstado);
 const tabs=Object.entries(counts).map(([key,n])=>`<button class="${state.stockEstado===key?'active':''}" data-stock-state="${key}">${key==='todos'?'Todos':key==='CRITICO'?'Críticos':key==='BAJO'?'Bajos':'Óptimos'} (${n})</button>`).join('');
 const trs=shown.map(r=>{let percentage=r.minimo>0?Math.min(100,r.disponible/(r.minimo*1.6)*100):100;
  return `<tr class="${r.estado==='CRITICO'?'critical-row':r.estado==='BAJO'?'low-row':''}"><td><strong>${esc(r.nombre)}</strong><span class="meta-line">${esc(r.codigo)} · ${esc(r.unidad)}</span></td>
  <td><span class="label-small">${r.sedes} sedes</span><span class="meta-line" title="${esc(r.ubicaciones)}">${esc(r.ubicaciones.slice(0,45))}${r.ubicaciones.length>45?'…':''}</span>${r.en_transito>0?`<span class="meta-line" style="color:#2563eb">+ ${num(r.en_transito,3)} en tránsito</span>`:''}</td>
  <td class="center">${num(r.existencia,3)}</td><td class="center">${num(r.reservado,3)}</td>
  <td><strong class="right">${num(r.disponible,3)}</strong><div class="progress-track"><div class="progress-bar ${r.estado==='CRITICO'?'danger':r.estado==='BAJO'?'warning':''}" style="width:${percentage}%"></div></div></td>
  <td class="center">${num(r.minimo,3)}</td><td>${statusBadge(r.estado)}</td>
  <td class="right"><div class="row-actions"><button class="link-button" data-stock-detail="${r.id}">Detalle</button><a class="link-button" href="${esc(pageUrl('kardex',{producto:r.id}))}">Kardex</a></div></td></tr>`;
 });
 return `${pageHead('Stock por sedes','Existencias, reservas, disponibilidad y ubicación de cada artículo en los almacenes.',
 btn('Exportar Excel','export-stock')+btn('Configurar mínimos','modal-minimos')+btn('Registrar movimiento','modal-movimiento','btn-primary'))}
 <div class="cards">${stat('Valor del inventario',soles(d.valor_inventario),'Todos los almacenes','blue','smaller')}
 ${stat('Crítico',d.criticos,'Disponible bajo mínimo','danger')}${stat('Bajo',d.bajos,'Requiere seguimiento','warning')}
 ${stat('Óptimo',d.optimos,'Stock suficiente','success')}${stat('Con pedidos reservados',d.reservas,'Artículos con reservas','blue')}</div>
 ${panel('Consultar inventario','Filtra por código, artículo, almacén y estado.',
 `<div class="panel-body"><div class="filter-bar"><div class="filter-block grow"><label>Buscar artículo</label><input class="filter" id="stockQ" placeholder="Código de barra, ID o descripción" value="${esc(state.stockQ)}" /></div>
 <div class="filter-block"><label>Almacén / Sede</label><select class="filter" id="stockWarehouse">${warehouseOptions(state.warehouse,true)}</select></div>
 <div class="filter-block"><label>Mostrar</label><div class="segmented">${tabs}</div></div></div></div>`)}
 ${panel('Existencias disponibles',`${shown.length} artículo(s) encontrados. Las cifras se actualizan después de cada operación.`,
 `${table(['Artículo','Ubicación','Existencia','Reservado','Disponible','Mínimo','Estado','Acciones'],trs,'Prueba otros filtros o registra existencias.')}
 <div class="table-footer"><span>Los saldos se calculan desde la base de datos local.</span><span>${shown.length} registros</span></div>`)}
 <div id="stockDetailMount"></div>${legend()}`;
}
async function showStockDetail(id){
 const p=state.catalog.productos.find(x=>x.id===id);if(!p)return;
 const data=await Promise.all(state.catalog.almacenes.map(w=>api(`/api/stock?almacen=${w.id}&q=${encodeURIComponent(p.codigo)}`)));
 const rows=data.map((list,i)=>({...list.find(x=>x.id===id),warehouse:state.catalog.almacenes[i]})).filter(r=>r.id);
 $('#stockDetailMount').innerHTML=panel(`Detalle por almacén: ${esc(p.nombre)}`,'Saldo y umbral mínimo de cada ubicación.',
 `<div class="panel-body"><div class="details-grid">${rows.map(r=>`<div class="details-item"><strong>${esc(r.warehouse.nombre)}</strong><p>${esc(r.ubicaciones)}</p><b>${num(r.disponible,3)} ${esc(p.unidad)}</b><div class="kv"><span>Existencia</span><b>${num(r.existencia,3)}</b></div><div class="kv"><span>Reservado</span><b>${num(r.reservado,3)}</b></div><div class="kv"><span>Mínimo</span><b>${num(r.minimo,3)}</b></div>${statusBadge(r.estado)}</div>`).join('')}</div></div>`);
 $('#stockDetailMount').scrollIntoView({behavior:'smooth',block:'nearest'});
}
async function renderKardex(){
 const params=new URLSearchParams();if(state.product)params.set('producto',state.product);if(state.movementWarehouse)params.set('almacen',state.movementWarehouse);if(state.movementType!=='todos')params.set('tipo',state.movementType);if(state.movementQ)params.set('q',state.movementQ);
 const rows=await api('/api/movimientos?'+params);
 const selected=state.product?state.catalog.productos.find(p=>p.id===Number(state.product)):null;
 const status=selected?await api('/api/stock?q='+encodeURIComponent(selected.codigo)):[];
 const summary=selected?status.find(x=>x.id===selected.id):null;
 const concepts=[...new Set(rows.map(x=>x.tipo))];
 const trs=rows.map(r=>`<tr><td class="nowrap">${fmtDate(r.fecha)}</td><td><strong>${esc(r.producto)}</strong><span class="meta-line">${esc(r.almacen)}</span></td>
 <td><span class="badge ${Number(r.entrada)>0?'badge-green':Number(r.salida)>0?'badge-red':'badge-blue'}">${esc(r.tipo.replaceAll('_',' '))}</span></td><td><span class="nowrap">${esc(r.documento||'—')}</span><span class="meta-line">${esc(r.tercero)}</span></td>
 <td class="right">${enterCell(r.entrada)}</td><td class="right">${exitCell(r.salida)}</td><td class="right"><strong>${num(r.saldo,3)}</strong></td>
 <td class="right">${soles(r.costo_unitario)}</td><td class="right">${soles(r.valor_saldo)}</td></tr>`);
 return `${pageHead('Movimientos (Kardex)','Entradas, salidas, compras, ventas, transferencias, mermas y ajustes en orden cronológico.',
 btn('Exportar Kardex','export-kardex')+btn('Nuevo movimiento','modal-movimiento','btn-primary'))}
 ${panel('Consulta de movimientos','Selecciona un artículo o una sede para auditar el historial.',
 `<div class="panel-body"><div class="filter-bar"><div class="filter-block grow"><label>Código / producto / documento</label><input id="movementQ" class="filter" placeholder="Buscar movimiento" value="${esc(state.movementQ)}"></div>
 <div class="filter-block grow"><label>Artículo</label><select id="movementProduct" class="filter">${productOptions(state.product,true)}</select></div>
 <div class="filter-block"><label>Almacén / Sede</label><select id="movementWarehouse" class="filter">${warehouseOptions(state.movementWarehouse,true)}</select></div>
 <div class="filter-block narrow"><label>Concepto</label><select id="movementType" class="filter"><option value="todos">Todos</option>${concepts.map(x=>`<option value="${esc(x)}" ${state.movementType===x?'selected':''}>${esc(x.replaceAll('_',' '))}</option>`).join('')}</select></div></div></div>`)}
 ${selected&&summary?panel(`Kardex: ${esc(selected.nombre)}`,'Resumen actual del artículo en todas las sedes.',
 `<div class="metric-strip">${[['Existencia',summary.existencia],['Reservado',summary.reservado],['Disponible',summary.disponible],['Stock mínimo',summary.minimo],['Valor del saldo',soles(summary.valor)]].map(([k,v])=>`<div class="metric-tile"><label>${k}</label><b>${typeof v==='number'?num(v,3):v}</b></div>`).join('')}</div>`):''}
 ${panel('Historial de operaciones',`${rows.length} movimientos · Saldo por producto y almacén tras cada operación.`,
 `${table(['Fecha / hora','Artículo / sede','Concepto','Documento / tercero','Entradas','Salidas','Saldo','Costo unit.','Valor saldo'],trs,'No existen movimientos con los filtros actuales.')}
 <div class="table-footer"><span>Los saldos son posteriores a cada movimiento.</span><span>Máximo 500 registros en pantalla · Excel exporta el historial disponible.</span></div>`)}
 <div class="notice"><strong>Importante:</strong> el saldo del Kardex se calcula por producto y almacén. Los movimientos iniciales de demostración se identifican como SALDO INICIAL; no representan compras reales.</div>`;
}
async function renderConteos(){
 const [counts,adjustments]=await Promise.all([api('/api/conteos'),api('/api/ajustes')]);
 const tabs=`<div class="tabbar"><button class="${state.countTab==='conteos'?'active':''}" data-count-tab="conteos">Conteo físico</button><button class="${state.countTab==='ajustes'?'active':''}" data-count-tab="ajustes">Mermas y ajustes</button></div>`;
 const pending=counts.filter(x=>x.estado==='PENDIENTE').length;
 const diff=counts.reduce((s,x)=>s+x.detalles.filter(z=>Number(z.diferencia)!==0).length,0);
 const neg=counts.reduce((s,x)=>s+x.detalles.filter(z=>Number(z.diferencia)<0).reduce((a,l)=>a+Number(l.valor_diferencia),0),0);
 const countRows=counts.map(x=>`<tr><td><strong>${esc(x.codigo)}</strong><span class="meta-line">${fmtDate(x.fecha)}</span></td><td>${esc(x.almacen)}</td><td>${esc(x.responsable)}</td><td>${x.detalles.length}</td>
 <td><span class="${x.detalles.some(l=>Number(l.diferencia)!==0)?'danger-text':''}">${x.detalles.filter(l=>Number(l.diferencia)!==0).length}</span></td><td>${statusBadge(x.estado)}</td><td class="right"><div class="row-actions"><button class="link-button" data-count-detail="${x.id}">Ver detalle</button>${x.estado==='PENDIENTE'?`<button class="btn btn-small btn-primary" data-count-apply="${x.id}">Regularizar</button>`:''}</div></td></tr>`);
 const adjustRows=adjustments.map(r=>`<tr><td><strong>${esc(r.codigo)}</strong><span class="meta-line">${fmtDate(r.fecha)}</span></td><td><strong>${esc(r.producto)}</strong><span class="meta-line">${esc(r.almacen)}</span></td>
 <td>${statusBadge(r.tipo)}</td><td>${num(r.cantidad,3)}</td><td>${esc(r.motivo)}</td><td>${esc(r.observacion||'—')}</td></tr>`);
 return `${pageHead('Inventario físico / Ajustes','Conteos físicos, identificación de diferencias y regularizaciones con trazabilidad automática.',
 btn('Registrar merma / ajuste','modal-ajuste')+btn('Iniciar conteo físico','modal-conteo','btn-primary'))}
 <div class="cards cards-4">${stat('Conteos registrados',counts.length,'Histórico de toma física','blue')}
 ${stat('Conteos pendientes',pending,'Aún sin regularizar','warning')}
 ${stat('Artículos con diferencia',diff,'En conteos registrados','danger')}
 ${stat('Faltantes valorizados',soles(Math.abs(neg)),'Diferencias negativas','danger','smaller')}</div>
 ${tabs}
 ${state.countTab==='conteos'?panel('Control de inventario físico','Los conteos se guardan primero como pendientes. Regularizar genera ajustes en el Kardex.',
 table(['Toma física','Almacén','Responsable','Artículos','Diferencias','Estado','Acciones'],countRows,'Todavía no has iniciado ningún conteo físico.'))
 :panel('Mermas y regularizaciones','Historial de bajas por daño, vencimiento, robo y otros ajustes.',
 table(['Registro','Artículo / sede','Operación','Cantidad','Motivo','Observación'],adjustRows,'No se han registrado mermas ni ajustes.'))}
 <div id="countDetailMount"></div>
 <div class="notice warn"><strong>Control recomendado:</strong> un conteo permanece pendiente hasta que se regulariza. Si el inventario cambia entre el conteo y la regularización, el sistema evita aplicar un ajuste desactualizado.</div>`;
}
function showCountDetail(id){
 api('/api/conteos').then(rows=>{
  const x=rows.find(r=>r.id===id);if(!x)return;
  const trs=x.detalles.map(l=>`<tr><td><strong>${esc(l.producto)}</strong></td><td class="right">${num(l.cantidad_sistema,3)}</td><td class="right">${num(l.cantidad_contada,3)}</td><td class="right"><span class="${Number(l.diferencia)<0?'danger-text':Number(l.diferencia)>0?'green-text':''}">${num(l.diferencia,3)}</span></td><td class="right">${soles(l.valor_diferencia)}</td></tr>`);
  $('#countDetailMount').innerHTML=panel(`Detalle del conteo ${esc(x.codigo)}`,
   `${esc(x.almacen)} · ${esc(x.responsable)}`,
   `${table(['Artículo','Sistema','Contado','Diferencia','Valor diferencia'],trs)}<div class="panel-body">${statusBadge(x.estado)}</div>`);
  $('#countDetailMount').scrollIntoView({behavior:'smooth',block:'nearest'});
 }).catch(e=>toast(e.message,'error'));
}
async function renderCompras(){
 const rows=await api('/api/ordenes');
 const pending=rows.filter(r=>r.estado==='PENDIENTE'||r.estado==='PARCIAL');
 const trs=rows.map(x=>`<tr><td><strong>${esc(x.codigo)}</strong><span class="meta-line">${fmtDate(x.fecha)}</span></td>
 <td><strong>${esc(x.proveedor)}</strong><span class="meta-line">${esc(x.almacen)}</span></td><td>${x.lineas}</td><td>${statusBadge(x.estado)}</td>
 <td class="money">${soles(x.total)}</td><td class="right"><div class="row-actions"><button class="link-button" data-order-detail="${x.id}">Detalle</button>
 ${x.estado==='PENDIENTE'||x.estado==='PARCIAL'?`<button class="btn btn-small btn-success" data-order-receive="${x.id}">Recibir compra</button>`:''}</div></td></tr>`);
 return `${pageHead('Órdenes de compra','Solicitud formal de artículos al proveedor; registra los pedidos antes de su recepción.',
 btn('Exportar órdenes','export-ordenes')+btn('Nueva orden de compra','modal-compra','btn-primary'))}
 <div class="cards cards-4">${stat('Órdenes de compra',rows.length,'Registradas en el sistema','blue')}
 ${stat('Pendientes de recibir',pending.length,'En espera de mercancía','warning')}
 ${stat('Órdenes recibidas',rows.filter(x=>x.estado==='RECIBIDA').length,'Actualizadas en stock','success')}
 ${stat('Valor total de pedidos',soles(rows.reduce((a,x)=>a+Number(x.total||0),0)),'Acumulado referencial','blue','smaller')}</div>
 ${panel('Órdenes registradas','Selecciona una orden para consultar sus líneas o recibir la mercadería.',
 table(['Orden / fecha','Proveedor / sede','Líneas','Estado','Total','Acciones'],trs,'Todavía no hay órdenes de compra.'))}
 <div id="orderDetailMount"></div>
 <div class="notice"><strong>Flujo:</strong> se registra la orden de compra → se confirma la recepción → aumenta el stock en el almacén → se genera el registro de cuenta por pagar. Los pagos son de demostración, no transacciones bancarias.</div>`;
}
function showOrderDetail(id){
 api('/api/ordenes').then(list=>{
  let o=list.find(x=>x.id===id);if(!o)return;
  $('#orderDetailMount').innerHTML=panel(`Detalle de ${esc(o.codigo)}`,
   `${esc(o.proveedor)} · ${esc(o.almacen)} · ${fmtDate(o.fecha)}`,
   `${table(['Producto','Solicitado','Recibido','Pendiente','Costo unitario','Subtotal'],o.detalles.map(l=>`<tr><td><strong>${esc(l.producto)}</strong></td><td>${num(l.cantidad,3)}</td><td>${num(l.recibido,3)}</td><td>${num(l.cantidad-l.recibido,3)}</td><td>${soles(l.costo_unitario)}</td><td class="money">${soles(l.cantidad*l.costo_unitario)}</td></tr>`))}<div class="panel-body">${statusBadge(o.estado)} <span class="helper"> · Total: ${soles(o.total)}</span></div>`);
  $('#orderDetailMount').scrollIntoView({behavior:'smooth',block:'nearest'});
 }).catch(e=>toast(e.message,'error'));
}
async function renderRecepciones(){
 const rows=await api('/api/recepciones');
 const trs=rows.map(x=>`<tr><td><strong>${esc(x.codigo)}</strong><span class="meta-line">${fmtDate(x.fecha)}</span></td>
 <td><strong>${esc(x.producto)}</strong><span class="meta-line">${esc(x.proveedor)}</span></td><td>${esc(x.almacen)}</td>
 <td class="right">${num(x.cantidad,3)} ${esc(x.unidad)}</td><td class="right">${soles(x.costo_unitario)}</td><td class="right money">${soles(x.costo_unitario*x.cantidad)}</td>
 <td>${x.orden_id?`OC #${x.orden_id}`:'Recepción directa'}</td></tr>`);
 return `${pageHead('Recepción de mercadería','Control documental y físico de artículos que ingresan desde proveedores a los almacenes.',
 btn('Nueva recepción directa','modal-recepcion','btn-primary'))}
 <div class="cards cards-4">${stat('Recepciones registradas',rows.length,'Comprobantes de entrada','blue')}
 ${stat('Unidades recibidas',num(rows.reduce((v,x)=>v+Number(x.cantidad),0),3),'Acumulado histórico','success')}
 ${stat('Importe recibido',soles(rows.reduce((v,x)=>v+Number(x.cantidad)*Number(x.costo_unitario),0)),'Valorización referencial','blue','smaller')}
 ${stat('Recepciones con orden',rows.filter(x=>x.orden_id).length,'Vinculadas a compras','success')}</div>
 ${panel('Historial de recepciones','Cada recepción real incrementa las existencias y genera un movimiento COMPRA en el Kardex.',
 table(['Recepción / fecha','Producto / proveedor','Almacén','Cantidad','Costo unit.','Total','Origen'],trs,'No existen recepciones.'))}
 <div class="notice warn"><strong>Advertencia:</strong> la recepción RC-2026-0001 forma parte del saldo inicial demostrativo, para evitar contabilizarla dos veces. Las recepciones nuevas sí incrementan inmediatamente el stock.</div>`;
}
async function renderTraslados(){
 const rows=await api('/api/transferencias');
 const transit=rows.filter(r=>r.estado==='EN_TRANSITO');
 const trs=rows.map(r=>`<tr><td><strong>${esc(r.codigo)}</strong><span class="meta-line">${fmtDate(r.fecha_salida)}</span></td>
 <td><strong>${esc(r.producto)}</strong><span class="meta-line">${esc(r.unidad)}</span></td><td>${esc(r.origen)}</td><td>${esc(r.destino)}</td>
 <td class="right">${num(r.cantidad,3)}</td><td>${statusBadge(r.estado)}</td>
 <td class="right">${r.estado==='EN_TRANSITO'?(state.catalog.almacenes.some(w=>Number(w.id)===Number(r.destino_id))?`<button class="btn btn-small btn-success" data-transfer-receive="${r.id}" ${capabilities.editar?'':'disabled'}>Confirmar recepción</button>`:'<span class="label-small">Recepción en la sede destino</span>'):fmtDate(r.fecha_recepcion)}</td></tr>`);
 return `${pageHead('Traslados entre sedes','Movimiento físico de productos: salida del almacén origen, tránsito y recepción en destino.',
 btn('Nuevo traslado','modal-traslado','btn-primary'))}
 <div class="cards cards-4">${stat('Traslados registrados',rows.length,'Operaciones históricas','blue')}
 ${stat('Traslados en tránsito',transit.length,'Por confirmar recepción','warning')}
 ${stat('Unidades en tránsito',num(transit.reduce((a,x)=>a+Number(x.cantidad),0),3),'Mercadería fuera de almacén','blue')}
 ${stat('Traslados recibidos',rows.length-transit.length,'Completados','success')}</div>
 ${panel('Seguimiento de mercancía entre sedes','La recepción en el destino debe confirmarse para ingresar las unidades al nuevo almacén.',
 table(['Código / fecha','Producto','Origen','Destino','Cantidad','Estado','Acción'],trs,'Todavía no se han registrado traslados.'))}
 <div class="notice"><strong>Control de tránsito:</strong> al registrar un traslado, se descuenta del origen. Al confirmar la recepción, se añade al destino. Mientras tanto figura como mercancía en tránsito y no está disponible para vender.</div>`;
}
async function renderDespachos(){
 if(state.envioId){
  state.expediente=await api(`/api/envios/${state.envioId}`);
  return renderExpediente(state.expediente);
 }
 const rows=await api('/api/envios');
 const groups=[['todos','Todos',null],['preparacion','Por despachar',['BORRADOR','PREPARACION','PREPARADO']],['seguimiento','En seguimiento',['EN_RUTA','ENTREGADO']],['incidencias','Con incidencias',['PARCIAL','RECHAZADO','FALLIDO']],['cerrados','Cerrados',['CERRADO','CERRADO_CON_INCIDENCIA']],['cancelados','Cancelados',['CANCELADO']]];
 const groupStates=groups.find(g=>g[0]===state.envioGroup)?.[2];
 const shown=rows.filter(r=>(!groupStates||groupStates.includes(r.estado_logistica))&&(state.envioEstado==='todos'||r.estado_logistica===state.envioEstado)&&
  [r.codigo,r.producto,r.cliente,r.destino,r.documento_venta,r.almacen].join(' ').toLocaleLowerCase('es').includes(state.envioQ.toLocaleLowerCase('es')));
 const estados=[...new Set(rows.map(r=>r.estado_logistica))];
 const trs=shown.map(r=>`<tr><td><strong>${esc(r.codigo)}</strong><span class="meta-line">${fmtDate(r.fecha)} · ${esc(r.documento_venta||'Venta confirmada')}</span></td>
 <td><strong>${esc(r.cliente)}</strong><span class="meta-line">${esc(r.destino||'Destino pendiente')}</span></td>
 <td><strong>${esc(r.producto)}</strong><span class="meta-line">${esc(r.almacen)} · ${esc(r.unidad)}</span></td>
 <td class="right">${num(r.cantidad,3)}<span class="meta-line">${esc(r.unidad)}</span></td><td>${statusBadge(r.estado_logistica)}<span class="meta-line">${r.estado==='PREPARACION'?'Stock reservado':`${num(r.pendiente,3)} pendientes`}</span></td>
 <td class="right"><a class="btn btn-sec btn-sm btn-small" href="${esc(pageUrl('expediente',{id:r.id}))}" aria-label="Abrir expediente ${esc(r.codigo)}">Ver expediente</a></td></tr>`);
 return `${pageHead('Envíos y entregas','Prepara la mercadería y consulta el seguimiento de cada entrega.')}
 <div class="barra g7-list-toolbar">
  <div class="barra-izq">
   <div class="buscador"><div class="search-input-wrap">${icon('search').replace('<svg','<svg class="search-icon"')}<input id="envioQ" type="search" class="custom-input filter" aria-label="Envío, cliente, producto o venta" value="${esc(state.envioQ)}" placeholder="Buscar por envío, cliente, producto o venta…" autocomplete="off"></div></div>
   <div class="g7-state-filter"><label for="envioEstado">Estado</label><select id="envioEstado" class="custom-select filter"><option value="todos">Todos los estados</option>${estados.map(v=>`<option value="${esc(v)}" ${v===state.envioEstado?'selected':''}>${esc(statusLabel(v))}</option>`).join('')}</select></div>
  </div>
  <div class="actions">${btn(icon('refresh')+'Actualizar','envios-refresh')}<a class="btn btn-primary-action" href="../comercial/ventas.php">Consultar ventas</a></div>
 </div>
 <div class="filter-pills-bar" aria-label="Filtrar envíos por seguimiento">${groups.map(([key,label,states])=>`<button type="button" class="pill-item ${state.envioGroup===key?'pill-active':''}" data-envio-group="${key}" aria-pressed="${state.envioGroup===key}">${label}<span class="pill-count">${states?rows.filter(r=>states.includes(r.estado_logistica)).length:rows.length}</span></button>`).join('')}</div>
 <div class="g7-list-count" role="status">${shown.length} ${shown.length===1?'envío':'envíos'}${shown.length!==rows.length?` de ${rows.length}`:''} en la sede seleccionada</div>
 ${table(['Envío / venta','Cliente / destino','Producto / almacén','Cantidad','Estado','Acciones'],trs,'No se encontraron envíos con los filtros seleccionados.')}
 ${!shown.length&&(state.envioQ||state.envioEstado!=='todos'||state.envioGroup!=='todos')?`<div class="g7-empty-reset">${btn('Limpiar filtros','envios-clear')}</div>`:''}
 <p class="g7-section-note">Los envíos se generan al confirmar una venta. Abre el expediente para completar la preparación, registrar la salida y dar seguimiento a la entrega.</p>`;
}
function envioAction(action,label,variant='',extra=''){
 const allowed=state.expediente?.envio?.acciones_permitidas||[];
 return `<button type="button" class="btn ${variant} ${/btn-primary|btn-success/.test(variant)?'btn-primary-action':'btn-sec'}" data-envio-action="${action}" ${allowed.includes(action)&&capabilities.editar?'':'disabled'} ${extra}>${label}</button>`;
}
function envioHistoryDetail(value,unidad){
 let detail=value;
 try{if(typeof detail==='string')detail=JSON.parse(detail);}catch{return String(value||'');}
 if(!detail||typeof detail!=='object')return String(detail||'');
 const parts=[];
 if(detail.numero!==undefined)parts.push(`Intento ${detail.numero}`);
 if(detail.resultado)parts.push(String(detail.resultado).replaceAll('_',' '));
 if(detail.cantidad!==undefined)parts.push(`Cantidad: ${num(detail.cantidad,3)} ${unidad}`);
 if(detail.cantidad_aceptada!==undefined)parts.push(`Aceptada: ${num(detail.cantidad_aceptada,3)} ${unidad}`);
 if(detail.cantidad_rechazada!==undefined)parts.push(`Rechazada: ${num(detail.cantidad_rechazada,3)} ${unidad}`);
 if(detail.tipo&&detail.tipo!=='OPERATIVA_NO_SUNAT')parts.push(`Tipo: ${String(detail.tipo).replaceAll('_',' ')}`);
 for(const [key,label] of [['guia_numero','Guía'],['destino','Destino'],['transporte','Transporte'],['receptor','Receptor'],['motivo','Motivo'],['documento','Documento'],['evidencia','Evidencia']]){
  if(detail[key])parts.push(`${label}: ${detail[key]}`);
 }
 return parts.join(' · ');
}
function renderExpediente(data){
 const {envio:e,balance:b,intentos=[],incidencias=[],resoluciones=[],historial=[]}=data;
 const abiertas=incidencias.filter(i=>i.estado==='ABIERTA');
 const intentoRows=intentos.map(i=>`<tr><td><strong>Intento ${esc(i.numero)}</strong><span class="meta-line">${fmtDate(i.fecha)}</span></td><td>${statusBadge(i.resultado)}</td><td class="right">${num(i.cantidad_aceptada,3)}</td><td class="right">${num(i.cantidad_rechazada,3)}</td><td>${esc(i.receptor||'—')}<span class="meta-line">${esc(i.usuario||'')}</span></td><td><span class="envio-wrap">${esc(i.motivo||'—')}</span><span class="meta-line envio-wrap">${esc(i.evidencia)}</span></td></tr>`);
 const incidentRows=incidencias.map(i=>`<tr><td><strong>#${Number(i.id)} · ${esc(i.tipo)}</strong><span class="meta-line">${fmtDate(i.fecha)}</span></td><td><span class="envio-wrap">${esc(i.motivo)}</span><span class="meta-line envio-wrap">${esc(i.evidencia)}</span></td><td>${statusBadge(i.estado)}</td><td class="right">${i.estado==='ABIERTA'?envioAction('resolver','Resolver','btn-small',`data-incidencia-id="${Number(i.id)}"`):'—'}</td></tr>`);
 const resolutionRows=resoluciones.map(r=>`<tr><td>${fmtDate(r.fecha)}</td><td><strong>${esc(String(r.tipo).replaceAll('_',' '))}</strong><span class="meta-line">Incidencia #${Number(r.incidencia_id)}</span></td><td class="right">${num(r.cantidad,3)}</td><td><span class="envio-wrap">${esc(r.documento||'—')}</span><span class="meta-line envio-wrap">${esc(r.evidencia)}</span></td></tr>`);
 const historyLabels={CONFIGURAR:'Datos de entrega actualizados',PREPARAR:'Mercadería preparada',GUIA:'Guía asociada',EMITIR_GUIA:'Guía operativa emitida',SALIR:'Salida confirmada',INTENTO:'Intento de entrega registrado',INCIDENCIA:'Incidencia registrada',RESOLVER:'Resolución registrada',CERRAR:'Expediente cerrado',CANCELAR:'Preparación cancelada'};
 const history=historial.map(h=>`<li><span class="activity-dot"></span><div><b>${esc(historyLabels[h.accion]||String(h.accion).replaceAll('_',' '))}</b><small>${fmtDate(h.fecha)} · ${esc(h.usuario||'Sistema')}</small><p class="envio-wrap">${esc(envioHistoryDetail(h.detalle,e.unidad))}</p></div></li>`).join('');
 const current=e.estado_logistica;
 const stageIndex=['BORRADOR','PREPARACION'].includes(current)?0:current==='PREPARADO'?1:['EN_RUTA','PARCIAL','RECHAZADO','FALLIDO','ENTREGADO'].includes(current)?2:['CERRADO','CERRADO_CON_INCIDENCIA'].includes(current)?3:-1;
 return `${pageHead(esc(e.codigo),`${esc(e.cliente)} · ${esc(e.documento_venta||'Venta confirmada')}`,
 btn('Volver a envíos','envios-back')+btn('Actualizar','envios-refresh')+`<button class="btn" data-print-guide="${Number(e.id)}">Imprimir constancia interna</button>`)}
 <div class="envio-status">${statusBadge(current)}<span class="label-small">${esc(e.almacen)} · ${fmtDate(e.fecha)}</span></div>
 <ol class="envio-stages" aria-label="Avance del envío">${['Preparación','Mercadería preparada','Entrega y seguimiento','Cierre'].map((label,i)=>`<li class="${i<stageIndex?'done':i===stageIndex?'current':''}"><span>${i<stageIndex?'✓':i+1}</span>${label}</li>`).join('')}</ol>
 ${panel('Mercadería del envío',`${esc(e.producto)} · ${esc(e.unidad)}`,`<div class="metric-strip envio-balance">${[['Cantidad preparada',e.cantidad],['Enviada',b.enviado],['Aceptada',b.aceptado],['Retornada',b.retornado],['Pérdida / disposición',b.perdida],['Pendiente',b.pendiente]].map(([label,value])=>`<div class="metric-tile"><label>${label}</label><b>${num(value,3)}</b></div>`).join('')}</div>`)}
 <div class="grid-two envio-grid">${panel('Preparación y despacho','Datos operativos y guía del envío.',`<div class="panel-body">
 <dl class="envio-data"><div><dt>Destino</dt><dd>${esc(e.destino||'Pendiente de completar')}</dd></div><div><dt>Transporte / conductor</dt><dd>${esc(e.transporte||'Pendiente de completar')}</dd></div><div><dt>Guía asociada</dt><dd>${esc(e.guia_numero||'Sin guía asociada')}</dd></div><div><dt>Salida del almacén</dt><dd>${fmtDate(e.fecha_salida)}</dd></div></dl>
 <div class="envio-actions">${envioAction('configurar','Completar datos')}${envioAction('preparar','Marcar preparada','btn-primary')}${envioAction('emitir_guia','Emitir guía operativa')}${envioAction('guia','Asociar guía existente')}${envioAction('salir','Confirmar salida','btn-success')}</div>
 <p class="helper envio-help"><a href="../datahub/public/guias" target="_blank" rel="noopener noreferrer">Consultar guías de remisión</a></p></div>`)}
 ${panel('Entrega y seguimiento','Registra lo recibido y resuelve la mercadería pendiente.',`<div class="panel-body"><p class="form-note">${num(b.pendiente,3)} ${esc(e.unidad)} pendientes · ${abiertas.length} incidencias abiertas</p>
 <div class="envio-actions">${envioAction('intento',intentos.length?'Registrar nuevo intento':'Registrar entrega','btn-primary')}${envioAction('incidencia','Registrar incidencia')}${envioAction('cerrar','Cerrar expediente','btn-success')}${envioAction('cancelar','Cancelar preparación','btn-danger')}</div>
 <p class="helper envio-help">El cierre requiere saldo pendiente cero y ninguna incidencia bloqueante abierta. Las incidencias informativas quedan visibles en el expediente.</p>${current==='CERRADO_CON_INCIDENCIA'?'<div class="notice warn">Envío cerrado con retorno, pérdida o disposición confirmada. El expediente conserva sus evidencias e historial.</div>':''}${!capabilities.editar?'<div class="notice">Tu acceso permite consultar los expedientes.</div>':''}</div>`)}</div>
 ${panel('Intentos de entrega','Las unidades rechazadas siguen pendientes hasta su aceptación o resolución física.',table(['Intento / fecha','Resultado','Aceptada','Rechazada','Receptor','Observación / evidencia'],intentoRows,'Aún no se registraron intentos de entrega.'))}
 ${panel('Incidencias','Seguimiento de observaciones y situaciones que requieren resolución.',table(['Incidencia','Motivo / evidencia','Estado','Acciones'],incidentRows,'Este envío no tiene incidencias registradas.'))}
 ${resolutionRows.length?panel('Resoluciones registradas','Retornos, pérdidas, disposición y cierres de incidencias.',table(['Fecha','Resolución','Cantidad','Documento / evidencia'],resolutionRows)):''}
 ${panel('Historial del expediente','Registro de operaciones realizadas.',`<div class="panel-body"><ul class="activity-list envio-history">${history||'<li>Sin eventos adicionales.</li>'}</ul></div>`)}`;
}
function randomOperationKey(){return window.crypto?.randomUUID?.()||Array.from(window.crypto.getRandomValues(new Uint8Array(16)),n=>n.toString(16).padStart(2,'0')).join('')}
function openEnvioModal(action,incidentId){
 const data=state.expediente,e=data?.envio;if(!e)return;
 if(!capabilities.editar||!e.acciones_permitidas?.includes(action)){toast('No tienes acceso a esta acción o el estado del envío cambió.','error');return;}
 const note=()=>field('Evidencia / referencia','evidencia','Indica la constancia o referencia verificable','textarea','required maxlength="1000"');
 const qty=(label,name,value='')=>field(label,name,'0.000','number',`min="0.001" max="${Number(data.balance.pendiente)}" step="0.001" required ${value?`value="${esc(value)}"`:''}`);
 let title,body,submit='Guardar';
 if(action==='configurar'){
  title='Completar preparación';submit='Guardar datos';
  body=`<div class="form-grid"><div class="full">${field('Lugar de entrega','destino','Dirección del destinatario','text',`required maxlength="150" value="${esc(e.destino)}"`)}</div><div class="full">${field('Transporte / conductor','transporte','Unidad, placa y responsable del transporte','text',`required maxlength="120" value="${esc(e.transporte)}"`)}</div></div>`;
 }else if(action==='emitir_guia'){
  title='Emitir guía operativa';submit='Emitir y asociar guía';
  const fecha=new Intl.DateTimeFormat('sv-SE',{timeZone:'America/Lima',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date());
  body=`<div class="form-grid">${field('Número de guía','numero','Ej. T001-000001','text','required maxlength="25"')}${field('Fecha','fecha','','date',`required value="${fecha}"`)}</div>
  <p class="form-note">La guía incluirá el cliente, la sede y ${num(e.cantidad,3)} ${esc(e.unidad)} de ${esc(e.producto)} de este envío. Quedará validada y asociada al expediente.</p>
  <div class="notice warn">Esta guía es un documento operativo interno. No constituye una guía electrónica emitida ante SUNAT.</div>`;
 }else if(action==='guia'){
  title='Asociar guía de remisión';submit='Asociar guía';
  const guides=data.guias_disponibles||[];
  body=guides.length?`<div class="form-grid"><div class="full">${field('Guía compatible','guia_id','<option value="">Selecciona una guía</option>'+guides.map(g=>`<option value="${Number(g.id)}">${esc(g.numero)} · ${fmtDate(g.fecha)} · ${esc(g.estado_registro)}</option>`).join(''),'select','required')}</div></div><p class="form-note">Se verificará la sede, el cliente, el producto y la cantidad antes de asociar la guía.</p>`:'<div class="notice warn">No hay guías compatibles disponibles. Completa la preparación del envío y usa «Emitir guía operativa» desde el expediente.</div>';
  body+='<p class="form-note envio-help"><a href="../datahub/public/guias" target="_blank" rel="noopener noreferrer">Consultar guías de remisión</a></p>';
 }else if(action==='intento'){
  title='Registrar intento de entrega';submit='Registrar intento';
  body=`<p class="form-note">Disponible para entregar: <strong>${num(data.balance.pendiente,3)} ${esc(e.unidad)}</strong>.</p><div class="form-grid">
  <div class="full">${field('Resultado','resultado','<option value="ACEPTADA">Aceptada · sin rechazo</option><option value="PARCIAL">Parcial · acepta y rechaza</option><option value="RECHAZADA">Rechazada · sin aceptación</option><option value="FALLIDA">Fallida · no se concretó</option>','select','id="envioResultado"')}</div>
  ${qty('Cantidad aceptada','cantidad_aceptada',data.balance.pendiente)}
  ${qty('Cantidad rechazada','cantidad_rechazada')}
  <div class="full">${field('Nombre del receptor','receptor','Persona que recibe la mercadería','text','required maxlength="150"')}</div>
  <div class="full">${field('Motivo / observación','motivo','Describe lo ocurrido en este intento','textarea','maxlength="500"')}</div><div class="full">${note()}</div></div>`;
 }else if(action==='incidencia'){
  title='Registrar incidencia';submit='Guardar incidencia';
  body=`<div class="form-grid">${field('Tipo','tipo','<option value="INFORMATIVA">Informativa</option><option value="BLOQUEANTE">Bloqueante · requiere resolver antes del cierre</option>','select')}
  <div class="full">${field('Motivo','motivo','Describe la incidencia','textarea','required maxlength="500"')}</div><div class="full">${note()}</div></div>`;
 }else if(action==='resolver'){
  title='Resolver incidencia';submit='Confirmar resolución';
  const open=data.incidencias.filter(i=>i.estado==='ABIERTA');
  body=`<div class="form-grid"><div class="full">${field('Incidencia','incidencia_id',open.map(i=>`<option value="${Number(i.id)}" ${Number(i.id)===Number(incidentId)?'selected':''}>#${Number(i.id)} · ${esc(i.motivo)}</option>`).join(''),'select','required')}</div>
  <div class="full">${field('Resolución','tipo','<option value="CIERRE">Observación resuelta · sin movimiento de unidades</option><option value="RETORNO">Retorno físico confirmado al almacén</option><option value="PERDIDA">Pérdida confirmada</option><option value="DISPOSICION">Disposición final confirmada</option>','select','id="envioResolucion"')}</div>
  ${qty('Cantidad resuelta','cantidad')}${field('Documento / constancia','documento','Número de documento','text','maxlength="150"')}
  <div class="full">${note()}</div></div><div class="notice warn">Confirma un retorno únicamente cuando la mercadería haya regresado físicamente al almacén. Esta resolución registrará la entrada de inventario.</div>`;
 }else if(action==='cancelar'){
  title='Cancelar preparación';submit='Cancelar preparación';body=`${field('Motivo de cancelación','motivo','Indica por qué se cancela el envío','textarea','required maxlength="500"')}<p class="form-note envio-help">Se liberará la reserva de mercadería. No se puede cancelar un envío que ya salió del almacén.</p>`;
 }else if(action==='preparar'){
  title='Marcar mercadería preparada';submit='Confirmar preparación';body=`<p>Confirma que preparaste <strong>${num(e.cantidad,3)} ${esc(e.unidad)}</strong> de ${esc(e.producto)} para este envío.</p><p class="form-note">La mercadería seguirá reservada hasta confirmar su salida.</p>`;
 }else if(action==='salir'){
  title='Confirmar salida del almacén';submit='Confirmar salida';body=`<p>Se registrará la salida de <strong>${num(e.cantidad,3)} ${esc(e.unidad)}</strong> de ${esc(e.almacen)} con la guía <strong>${esc(e.guia_numero)}</strong>.</p><div class="notice warn">Confirma esta operación cuando la mercadería haya salido físicamente del almacén.</div>`;
 }else if(action==='cerrar'){
  title='Cerrar expediente';submit='Confirmar cierre';body='<p>El sistema comprobará el balance de mercadería y las incidencias antes de cerrar este envío.</p>';
 }else return;
 modal(title,esc(e.codigo),body,'envio-'+action,{submit});
 const form=$('#modalForm');form.dataset.envioId=e.id;form.dataset.version=e.version;form.dataset.command=action;
 if(action==='guia'&&!data.guias_disponibles?.length)$('#submitModal').disabled=true;
 syncEnvioForm();
}
function syncEnvioForm(){
 const form=$('#modalForm');if(!form||!form.dataset.command)return;
 if(form.dataset.command==='intento'){
  const result=form.elements.resultado.value,accepted=['ACEPTADA','PARCIAL'].includes(result),rejected=['PARCIAL','RECHAZADA'].includes(result),quantity=form.elements.cantidad_aceptada,rejectQty=form.elements.cantidad_rechazada;
  quantity.readOnly=!accepted;quantity.min=accepted?'0.001':'0';
  if(!accepted)quantity.value='0';
  if(accepted&&Number(quantity.value)===0)quantity.value='';
  rejectQty.readOnly=!rejected;rejectQty.min=rejected?'0.001':'0';
  if(!rejected)rejectQty.value='0';
  if(rejected&&Number(rejectQty.value)===0)rejectQty.value=result==='RECHAZADA'?state.expediente.balance.pendiente:'';
  form.elements.receptor.required=accepted;form.elements.receptor.disabled=!accepted;
  form.elements.motivo.required=result!=='ACEPTADA';
 }
 if(form.dataset.command==='resolver'){
  const physical=form.elements.tipo.value!=='CIERRE';
  form.elements.cantidad.disabled=!physical;form.elements.cantidad.required=physical;
  form.elements.documento.disabled=!physical;form.elements.documento.required=physical;
 }
}
async function submitEnvio(form){
 if(state.envioBusy)return;
 const raw=Object.fromEntries(new FormData(form));
 const signature=JSON.stringify(raw);
 if(form.dataset.signature!==signature){form.dataset.signature=signature;form.dataset.idempotencyKey=randomOperationKey();}
 const payload={...raw,version:Number(form.dataset.version),idempotency_key:form.dataset.idempotencyKey};
 const button=$('#submitModal'),label=button.textContent;
 state.envioBusy=true;button.disabled=true;button.textContent='Guardando…';form.setAttribute('aria-busy','true');
 form.querySelector('.envio-form-error')?.remove();
 try{
  const result=await api(`/api/envios/${form.dataset.envioId}/${form.dataset.command}`,'POST',payload);
  state.envioBusy=false;closeModal();toast(result.mensaje||'Operación registrada');await refresh();
 }catch(error){
  const message=document.createElement('div');message.className='notice red envio-form-error';message.setAttribute('role','alert');message.textContent=error.message;
  if(error.status===409){const reload=document.createElement('button');reload.type='button';reload.className='link-button';reload.dataset.action='envio-reload';reload.textContent='Actualizar expediente';message.appendChild(reload);}
  form.querySelector('.modal-content').appendChild(message);message.scrollIntoView({block:'nearest'});
 }finally{state.envioBusy=false;if(button.isConnected){button.disabled=false;button.textContent=label;form.removeAttribute('aria-busy');}}
}
async function renderPagos(){
 const rows=await api('/api/cuentas-pagar');
 const trs=rows.map(r=>`<tr><td><strong>${esc(r.codigo)}</strong><span class="meta-line">${esc(r.recepcion)}</span></td><td>${esc(r.proveedor)}</td><td>${fmtDate(r.vencimiento)}</td>
 <td class="right">${soles(r.importe)}</td><td class="right">${soles(r.pagado)}</td><td class="right money">${soles(r.pendiente)}</td><td>${statusBadge(r.estado)}</td>
 <td class="right">${r.pendiente>0.001?`<button class="btn btn-small btn-primary" data-payable="${r.id}">Registrar pago</button>`:'—'}</td></tr>`);
 return `${pageHead('Cuentas por pagar','Obligaciones originadas por la recepción de compras; seguimiento referencial de vencimientos y pagos.',
 btn('Ver órdenes de compra','go-compras','btn-primary'))}
 <div class="cards cards-4">${stat('Obligaciones registradas',rows.length,'Cuentas vinculadas a recepciones','blue')}
 ${stat('Saldo pendiente',soles(rows.reduce((a,x)=>a+Number(x.pendiente),0)),'Deuda referencial','warning','smaller')}
 ${stat('Pagado / registrado',soles(rows.reduce((a,x)=>a+Number(x.pagado),0)),'Pagos simulados','success','smaller')}
 ${stat('Cuentas vencidas',rows.filter(x=>x.estado==='VENCIDO').length,'Requieren revisión','danger')}</div>
 ${panel('Obligaciones de proveedores','Cada recepción genera una cuenta por pagar. El pago es solo un registro académico.',
 table(['Obligación / recepción','Proveedor','Vencimiento','Importe','Pagado','Saldo','Estado','Acciones'],trs,'Aún no existen obligaciones.'))}
 <div class="notice warn"><strong>Alcance:</strong> la pantalla registra pagos de demostración; no implementa tesorería real, transferencia bancaria, conciliación ni autorización financiera. Al unir el ERP, debe integrarse con el grupo responsable de finanzas.</div>`;
}
async function renderAuditoria(){
 const rows=await api('/api/auditoria');
 const trs=rows.map(r=>`<tr><td>${fmtDate(r.fecha)}</td><td><strong>${esc(r.accion.replaceAll('_',' '))}</strong></td><td><span class="helper">${esc(r.detalle)}</span></td></tr>`);
 return `${pageHead('Auditoría de operaciones','Rastrea los cambios y las operaciones que afectan al módulo de compras, inventarios y logística.')}
 ${panel('Bitácora del sistema','Últimos 100 eventos relevantes registrados en la base local.',
 table(['Fecha y hora','Operación','Detalle del evento'],trs,'Aún no existe actividad registrada.'))}
 <div class="notice">Las operaciones quedan registradas con fecha y usuario en la base de datos del ERP.</div>`;
}
/* Formularios transaccionales validados por la API del ERP. */
let fieldSequence=0;
function field(label,name,input,type='text',other=''){
 const id=other.match(/\bid="([^"]+)"/)?.[1]||`g7-field-${name}-${++fieldSequence}`;
 const attrs=other.match(/\bid="/) ? other : `id="${id}" ${other}`;
 const control=type==='select'?`<select class="custom-select filter" name="${name}" ${attrs}>${input}</select><div class="select-chevron">${icon('chevron')}</div>`:
 type==='textarea'?`<textarea class="custom-textarea" name="${name}" rows="3" ${attrs} placeholder="${esc(input)}"></textarea>`:
 `<input class="custom-input" name="${name}" type="${type}" placeholder="${esc(input)}" ${attrs} />`;
 return `<div class="field form-group"><label class="field-label" for="${id}">${label}${/\brequired\b/.test(other)?' <span class="req-star" aria-hidden="true">*</span>':''}</label><div class="input-wrapper">${control}</div></div>`;
}
let lastModalFocus=null;
function modal(title,subtitle,body,op,{large=false,submit='Guardar registro'}={}){
 if(state.envioBusy||state.formBusy)return;
 lastModalFocus=document.activeElement;
 const area=$('.breadcrumb-trail .group-crumb')?.textContent||'Logística';
 $('#modalMount').innerHTML=`<dialog class="fusion-modal modal ${large?'large':''}" aria-labelledby="modalTitle" aria-describedby="g7-modal-subtitle"><div class="fusion-modal-card"><div class="fusion-modal-header modal-header"><div class="modal-header-left"><span class="modal-header-tag">${op.startsWith('envio-')?'Envíos y entregas':esc(area)}</span><h2 class="modal-title" id="modalTitle">${title}</h2><p class="modal-subtitle" id="g7-modal-subtitle">${subtitle}</p></div><button class="modal-close-btn close-btn" type="button" data-close-modal aria-label="Cerrar">${icon('close')}</button></div><form id="modalForm" class="fusion-modal-form" data-operation="${op}"><div class="fusion-modal-body modal-content">${body}</div><div class="fusion-modal-footer modal-footer"><div class="footer-hint"><span class="kbd-hint">Esc</span><span>para cancelar</span></div><div class="footer-actions"><button type="button" class="btn btn-sec" data-close-modal>Cancelar</button><button type="submit" class="btn btn-primary-action btn-primary btn-save" id="submitModal">${submit}</button></div></div></form></div></dialog>`;
 const dialog=$('#modalMount dialog');
 dialog.addEventListener('cancel',event=>{event.preventDefault();closeModal()});
 dialog.addEventListener('click',event=>{if(event.target===dialog)closeModal()});
 dialog.showModal();
 const first=$('#modalForm input:not([type=hidden]),#modalForm select');if(first)first.focus();
}
function openModal(name){
 const opts={product:productOptions(),warehouse:warehouseOptions(),supplier:supplierOptions()};
 if(name==='movimiento'){
  modal('Registrar movimiento manual','La operación actualizará el stock y quedará registrada en el Kardex.',
   `<div class="form-grid">${field('Tipo de movimiento','tipo',`<option value="ENTRADA">Entrada de mercancía</option><option value="SALIDA">Salida de mercancía</option><option value="DEVOLUCION">Devolución / ingreso</option>`,'select')}
   ${field('Almacén / Sede','almacen_id',opts.warehouse,'select')}
   <div class="full">${field('Producto','producto_id',opts.product,'select')}</div>
   ${field('Cantidad','cantidad','Ej. 10','number','required min="0.001" step="0.001"')}
   ${field('Documento de referencia','documento','Ej. MOV-0001','text','maxlength="50"')}
   ${field('Tercero / responsable','tercero','Ej. Operador de almacén','text','maxlength="100"')}
   <div class="full">${field('Observación','observacion','Detalle de la operación (opcional)','textarea','maxlength="300"')}</div></div>
   <div class="notice warn">Las salidas no pueden consumir unidades que están reservadas para otros despachos.</div>`,name,{submit:'Registrar movimiento'});
 }else if(name==='minimos'){
  modal('Configurar stock mínimo','El nivel es independiente para cada producto y almacén.',
   `<div class="form-grid"><div class="full">${field('Artículo','producto_id',opts.product,'select')}</div>
   ${field('Almacén','almacen_id',opts.warehouse,'select')}${field('Stock mínimo','minimo','Ej. 20','number','min="0" step="0.001" required')}</div>`,name,{submit:'Actualizar mínimo'});
 }else if(name==='compra'){
  modal('Nueva orden de compra','Registra una orden con una o varias líneas de productos.',
   `<div class="form-grid">${field('Proveedor','proveedor_id',opts.supplier,'select')}${field('Almacén destino','almacen_id',opts.warehouse,'select')}
   ${field('Fecha estimada de entrega','fecha_entrega','','date')}
   ${field('Observación','observacion','Ej. Reposición de insumos agrícolas','text','maxlength="300"')}</div>
   <div class="section-label">Artículos solicitados</div>
   <div id="purchaseLines">${purchaseLine()}</div>
   <button type="button" class="btn btn-ghost" id="addPurchaseLine">+ Agregar artículo</button>
   <div class="notice" style="margin-top:14px">La orden se crea como pendiente. El stock aumenta solo cuando confirmas la recepción.</div>`,name,{large:true,submit:'Guardar orden de compra'});
 }else if(name==='recepcion'){
  modal('Recepción directa de mercancía','Registro de ingreso de un producto no vinculado a una orden previa.',
   `<div class="form-grid">${field('Proveedor','proveedor_id',opts.supplier,'select')}${field('Almacén destino','almacen_id',opts.warehouse,'select')}
   <div class="full">${field('Producto recibido','producto_id',opts.product,'select')}</div>
   ${field('Cantidad recibida','cantidad','Ej. 25','number','min="0.001" step="0.001" required')}
   ${field('Costo unitario S/','costo_unitario','Ej. 45.50','number','min="0" step="0.01" required')}
   <div class="full">${field('Observación','observacion','Ej. Recepción con guía de remisión','textarea','maxlength="300"')}</div></div>`,name,{submit:'Confirmar recepción'});
 }else if(name==='traslado'){
  modal('Nuevo traslado entre sedes','El sistema descontará del origen y mantendrá las unidades en tránsito.',
   `<div class="form-grid"><div class="full">${field('Artículo','producto_id',opts.product,'select')}</div>
   ${field('Almacén origen','origen_id',opts.warehouse,'select','id="trasladoOrigen" required')}${field('Almacén destino','destino_id',destinationWarehouseOptions(state.catalog.almacenes[0]?.id),'select','id="trasladoDestino" required')}
   ${field('Cantidad','cantidad','Ej. 12','number','min="0.001" step="0.001" required')}
   ${field('Observación / motivo','observacion','Ej. Reposición de sede B','text','maxlength="300"')}</div>
   <div class="notice">La recepción la confirma un operador con acceso a la sede destino cuando llegue la mercadería.</div>`,name,{submit:'Iniciar traslado'});
 }else if(name==='ajuste'){
  modal('Registrar merma o ajuste','Baja por daño, vencimiento, robo o corrección autorizada.',
   `<div class="form-grid">${field('Tipo de ajuste','tipo',`<option value="MERMA">Merma / pérdida</option><option value="AJUSTE_NEGATIVO">Ajuste negativo</option><option value="AJUSTE_POSITIVO">Ajuste positivo</option>`,'select')}
   ${field('Almacén','almacen_id',opts.warehouse,'select')}
   <div class="full">${field('Artículo','producto_id',opts.product,'select')}</div>
   ${field('Cantidad','cantidad','Ej. 2','number','min="0.001" step="0.001" required')}
   ${field('Motivo','motivo',`<option value="Daño">Daño</option><option value="Vencimiento">Vencimiento</option><option value="Robo">Robo</option><option value="Diferencia detectada">Diferencia detectada</option><option value="Corrección autorizada">Corrección autorizada</option><option value="Otro">Otro</option>`,'select')}
   <div class="full">${field('Observación','observacion','Describa lo ocurrido','textarea','maxlength="300"')}</div></div>`,name,{submit:'Registrar ajuste'});
 }else if(name==='conteo'){
  modal('Nuevo conteo de inventario','Registra cantidades físicas y revisa las diferencias antes de regularizar.',
   `<div class="form-grid">${field('Almacén a contar','almacen_id',opts.warehouse,'select','id="countWarehouse"')}
   ${field('Responsable del conteo','responsable','Nombre del responsable','text','required value="Operador de almacén" maxlength="100"')}
   <div class="full">${field('Observación','observacion','Ej. Toma física del turno mañana','text','maxlength="300"')}</div></div>
   <div class="section-label">Cantidades físicas por producto</div><div id="countProducts" class="count-list"><p>Consultando existencias…</p></div>
   <div class="notice warn" style="margin-top:14px">Revisa las cantidades antes de guardar. La regularización se realizará en el siguiente paso.</div>`,name,{large:true,submit:'Guardar conteo físico'});
  loadCountProducts();
 }else if(name==='pago'){
  const pay=state.payable; if(!pay)return;
  modal(`Registrar pago · ${esc(pay.codigo)}`,'Registro financiero de demostración, sin conexión a una entidad bancaria.',
   `<div class="panel-body"><div class="kv"><span>Proveedor</span><b>${esc(pay.proveedor)}</b></div><div class="kv"><span>Importe total</span><b>${soles(pay.importe)}</b></div><div class="kv"><span>Saldo pendiente</span><b>${soles(pay.pendiente)}</b></div></div>
   <div class="form-grid"><div>${field('Importe a registrar S/','importe','Ej. 100.00','number',`required min="0.01" step="0.01" max="${pay.pendiente}"`)}</div>
   <div>${field('Medio de pago','medio_pago','<option value="transferencia">Transferencia</option><option value="efectivo">Efectivo (sale de la caja abierta de la sede)</option><option value="deposito">Depósito</option>','select','required')}</div></div>`,name,{submit:'Registrar pago'});
 }
}
function purchaseLine(){return `<div class="inline-fields purchase-line">
 ${field('Producto','producto_id',productOptions(),'select')}
 ${field('Cantidad','cantidad','Ej. 12','number','min="0.001" step="0.001" required')}
 ${field('Costo S/','costo_unitario','Ej. 39.20','number','min="0" step="0.01" required')}
 <button type="button" class="btn btn-ghost btn-small" data-remove-line title="Eliminar línea">×</button></div>`}
async function loadCountProducts(){
 const sel=$('#countWarehouse');if(!sel)return;
 const wid=sel.value;
 try{
  const rows=await api('/api/stock?almacen='+encodeURIComponent(wid));
  if(!$('#countProducts')||$('#countWarehouse').value!==wid)return;
  $('#modalForm .g7-inline-error')?.remove();
  $('#countProducts').innerHTML=rows.filter(x=>x.existencia>0).map(r=>`<div class="count-entry"><div><strong>${esc(r.nombre)}</strong><small>Sistema: ${num(r.existencia,3)} ${esc(r.unidad)}</small></div>
  <div class="field"><label>Contado</label><input class="count-qty" type="number" min="0" step="0.001" data-product-id="${r.id}" value="${r.existencia}" required></div></div>`).join('')||empty('Sin existencias','No hay productos con stock en este almacén.');
 }catch(e){toast(e.message,'error')}
}
function closeModal(){if(state.envioBusy||state.formBusy)return;const dialog=$('#modalMount dialog');if(dialog?.open)dialog.close();$('#modalMount').innerHTML='';if(lastModalFocus?.isConnected)lastModalFocus.focus();}
async function submitModal(form){
 if(state.envioBusy||state.formBusy)return;
 if(form.dataset.command)return submitEnvio(form);
 form.querySelector('.g7-inline-error')?.remove();
 const operation=form.dataset.operation,raw=Object.fromEntries(new FormData(form));
 let endpoint,payload;
 if(operation==='compra'){
  let lines=[...form.querySelectorAll('.purchase-line')];
  if(!lines.length)throw new Error('Debe agregar al menos un artículo');
  payload={...raw,items:lines.map(row=>({producto_id:row.querySelector('[name=producto_id]').value,cantidad:row.querySelector('[name=cantidad]').value,costo_unitario:row.querySelector('[name=costo_unitario]').value}))};
  endpoint='/api/ordenes';
 }else if(operation==='conteo'){
  payload={...raw,items:[...form.querySelectorAll('.count-qty')].map(inp=>({producto_id:inp.dataset.productId,cantidad_contada:inp.value}))};
  if(!payload.items.length)throw new Error('La sede seleccionada no contiene artículos para contar');
  endpoint='/api/conteos';
 }else{
  payload=raw;
  endpoint=({movimiento:'/api/movimientos',minimos:'/api/minimos',recepcion:'/api/recepciones',
   traslado:'/api/transferencias',ajuste:'/api/ajustes',
   pago:`/api/cuentas-pagar/${state.payable?.id}/pagar`})[operation];
 }
 if(!endpoint)throw new Error('Formulario no reconocido');
 const button=$('#submitModal'),label=button.textContent;state.formBusy=true;button.disabled=true;button.textContent='Guardando…';form.setAttribute('aria-busy','true');
 try{let result=await api(endpoint,'POST',payload);state.formBusy=false;closeModal();toast(result.mensaje||`Registro ${result.codigo||''} guardado correctamente`);await refresh()}
 finally{state.formBusy=false;form.removeAttribute('aria-busy');if(button?.isConnected){button.disabled=false;button.textContent=label}}
}
function confirmAction(text){return window.confirm(text)}
async function transactionalAction(path,confirmText){
 if(confirmText&&!confirmAction(confirmText))return;
 try{const r=await api(path,'POST',{});toast(r.mensaje||'Operación registrada');await refresh()}
 catch(e){toast(e.message,'error')}
}
function download(type){const a=document.createElement('a');a.href='api.php?route='+encodeURIComponent('/api/exportar?tipo='+encodeURIComponent(type));a.download='PUCCHUN_G7_'+type.toUpperCase()+'.xlsx';document.body.appendChild(a);a.click();a.remove();toast('Se inició la exportación a Excel')}
function syncFilterRender(keepId){
 const el=keepId?document.getElementById(keepId):null;
 const cursor=el&&['text','search'].includes(el.type)?[el.selectionStart,el.selectionEnd]:null;
 const page=state.page;
 const token=++state.renderId;
 const f={stock:renderStock,kardex:renderKardex,despachos:renderDespachos}[page];if(!f)return;
 f().then(html=>{if(token!==state.renderId)return;mountContent(html);
  if(keepId){let next=document.getElementById(keepId);if(next){next.focus();if(cursor&&cursor[0]!==null)next.setSelectionRange(...cursor)}}
 }).catch(e=>toast(e.message,'error'));
}
let debounce=null;
document.addEventListener('input',e=>{
 const id=e.target.id;
 if(id==='stockQ'||id==='movementQ'||id==='envioQ'){
  const val=e.target.value;
  if(id==='stockQ')state.stockQ=val;else if(id==='envioQ')state.envioQ=val;else state.movementQ=val;
  clearTimeout(debounce);debounce=setTimeout(()=>syncFilterRender(id),380);
 }
});
document.addEventListener('change',e=>{
 const id=e.target.id, val=e.target.value;
 if(id==='stockWarehouse'){state.warehouse=val;syncFilterRender()}
 if(id==='movementProduct'){state.product=val;syncFilterRender()}
 if(id==='movementWarehouse'){state.movementWarehouse=val;syncFilterRender()}
 if(id==='movementType'){state.movementType=val;syncFilterRender()}
 if(id==='countWarehouse'){loadCountProducts()}
 if(id==='envioEstado'){state.envioEstado=val;syncFilterRender()}
 if(id==='envioResultado'||id==='envioResolucion'){syncEnvioForm()}
 if(id==='trasladoOrigen'){const destination=$('#trasladoDestino');destination.innerHTML=destinationWarehouseOptions(val,destination.value);}
});
document.addEventListener('click',async e=>{
 const node=e.target.closest('.g7-ui button');
 if(!node)return;
 if(node.disabled)return;
 if(node.dataset.closeModal!==undefined){closeModal();return}
 if(node.dataset.envioAction){openEnvioModal(node.dataset.envioAction,node.dataset.incidenciaId);return;}
 if(node.dataset.envioGroup){state.envioGroup=node.dataset.envioGroup;state.envioEstado='todos';syncFilterRender();return;}
 if(node.dataset.stockState){state.stockEstado=node.dataset.stockState;syncFilterRender();return}
 if(node.dataset.countTab){state.countTab=node.dataset.countTab;refresh();return}
 if(node.dataset.stockDetail){showStockDetail(Number(node.dataset.stockDetail)).catch(e=>toast(e.message,'error'));return}
 if(node.dataset.orderDetail){showOrderDetail(Number(node.dataset.orderDetail));return}
 if(node.dataset.countDetail){showCountDetail(Number(node.dataset.countDetail));return}
 if(node.dataset.orderReceive){transactionalAction(`/api/ordenes/${node.dataset.orderReceive}/recibir`,'¿Confirmas la recepción completa de la orden? Se incrementará el stock y se generará la cuenta por pagar.');return}
 if(node.dataset.transferReceive){transactionalAction(`/api/transferencias/${node.dataset.transferReceive}/recibir`,'¿Confirmas que la mercadería llegó al almacén destino?');return}
 if(node.dataset.countApply){transactionalAction(`/api/conteos/${node.dataset.countApply}/regularizar`,'¿Regularizar las diferencias? Esta acción actualiza el stock y genera movimientos de ajuste en el Kardex.');return}
 if(node.dataset.printGuide){window.open('api.php?route='+encodeURIComponent('/guias/'+node.dataset.printGuide),'_blank','noopener,noreferrer');return}
 if(node.dataset.payable){try{let all=await api('/api/cuentas-pagar');state.payable=all.find(p=>p.id===Number(node.dataset.payable));openModal('pago')}catch(err){toast(err.message,'error')}return}
 if(node.dataset.removeLine!==undefined){if(document.querySelectorAll('.purchase-line').length<=1){toast('La orden debe tener al menos una línea','error');return}node.closest('.purchase-line').remove();return}
 if(node.id==='addPurchaseLine'){const target=document.createElement('div');target.innerHTML=purchaseLine();$('#purchaseLines').appendChild(target.firstElementChild);return}
 const action=node.dataset.action;if(!action)return;
 const actionMap={'envios-refresh':()=>refresh(),
 'envios-clear':()=>{state.envioQ='';state.envioEstado='todos';state.envioGroup='todos';syncFilterRender()},
 'envio-reload':()=>{closeModal();refresh()},
 'export-stock':()=>download('stock'),'export-kardex':()=>download('kardex'),'export-ordenes':()=>download('ordenes'),
 'modal-movimiento':()=>openModal('movimiento'),'modal-minimos':()=>openModal('minimos'),
 'modal-compra':()=>openModal('compra'),'modal-recepcion':()=>openModal('recepcion'),
 'modal-traslado':()=>openModal('traslado'),'modal-ajuste':()=>openModal('ajuste'),
 'modal-conteo':()=>openModal('conteo')};
 if(actionMap[action])actionMap[action]();
});
document.addEventListener('submit',e=>{
 if(e.target.id==='modalForm'){e.preventDefault();submitModal(e.target).catch(err=>toast(err.message,'error'))}
});
init();
})();
