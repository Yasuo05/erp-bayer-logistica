/* Ejecuta las funciones reales de la interfaz con respuestas DECIMAL del API.
   No necesita navegador, servidor, base de datos ni dependencias externas. */
'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const vm=require('node:vm');
const {webcrypto}=require('node:crypto');

const file=path.join(__dirname,'..','modulos','logistica','static','app.js');
const source=fs.readFileSync(file,'utf8');
const instrumented=source.replace(/init\(\);\s*\}\)\(\);\s*$/,
 'globalThis.ui={state,renderKardex,renderConteos,renderTraslados,renderPagos,renderRecepciones,renderCompras,submitEnvio,submitModal,purchaseLine,field,modal,closeModal,setRefresh:fn=>refresh=fn};\n})();');
assert.notEqual(instrumented,source,'La prueba debe cargar el archivo real y sustituir sólo el arranque.');
const responses=new Map(),requests=[];
const noOp=()=>{};
const submitButton={textContent:'Registrar intento',disabled:false,isConnected:false};
const mounts={innerHTML:'',appendChild:noOp};
const sandbox={
 document:{
  getElementById:id=>({textContent:JSON.stringify(id==='g7-page-config'?{page:'despachos'}:{ver:true,crear:true,editar:true})}),
  querySelector:selector=>selector==='meta[name="g7-csrf"]'?{content:'csrf-qa'}:selector==='#submitModal'?submitButton:selector==='#modalMount'||selector==='#toastMount'?mounts:null,
  addEventListener:noOp,
  createElement:()=>({className:'',textContent:'',appendChild:noOp,remove:noOp})
 },
 window:{crypto:webcrypto,scrollTo:noOp},
 Intl,URL,URLSearchParams,console,
 setTimeout:()=>0,clearTimeout:noOp,
 FormData:class { constructor(form){this.entries=Object.entries(form.values);} [Symbol.iterator](){return this.entries[Symbol.iterator]();} },
 fetch:async(url,options={})=>{
  const route=new URL(url,'http://qa.local/modulos/logistica/').searchParams.get('route');
  requests.push({route,method:options.method||'GET',payload:options.body?JSON.parse(options.body):null,headers:options.headers});
  const data=responses.get(route.split('?')[0])??[];
  return {ok:true,status:200,json:async()=>({ok:true,data})};
 }
};
vm.createContext(sandbox);vm.runInContext(instrumented,sandbox,{filename:file});
const {ui}=sandbox;
ui.state.catalog={productos:[],proveedores:[],almacenes:[{id:1,nombre:'Almacén QA'}]};
const format=(value,decimals)=>new Intl.NumberFormat('es-PE',{minimumFractionDigits:decimals,maximumFractionDigits:decimals}).format(value);
function statValue(html,label){
 const escaped=label.replace(/[.*+?^${}()|[\]\\]/g,'\\$&');
 const value=html.match(new RegExp(`<div class="stat-label">${escaped}</div><div class="stat-value[^\"]*">([^<]+)</div>`));
 assert(value,`Debe existir el indicador «${label}».`);return value[1];
}
let assertions=0;
function same(actual,expected,message){assert.equal(actual,expected,message);assertions++;}

(async()=>{
 responses.set('/api/transferencias',[
  {id:1,estado:'EN_TRANSITO',cantidad:'5.125',destino_id:1},
  {id:2,estado:'EN_TRANSITO',cantidad:'10.000',destino_id:1},
  {id:3,estado:'RECIBIDO',cantidad:'999.000',destino_id:1}
 ]);
 same(statValue(await ui.renderTraslados(),'Unidades en tránsito'),format(15.125,3),'Suma dos cantidades DECIMAL; excluye el traslado recibido.');

 responses.set('/api/cuentas-pagar',[
  {id:1,importe:'110.60',pendiente:'100.10',pagado:'10.50',estado:'PENDIENTE'},
  {id:2,importe:'22.45',pendiente:'20.20',pagado:'2.25',estado:'PENDIENTE'}
 ]);
 const payments=await ui.renderPagos();
 same(statValue(payments,'Saldo pendiente'),'S/ '+format(120.30,2),'Suma saldos DECIMAL en lugar de concatenarlos.');
 same(statValue(payments,'Pagado / registrado'),'S/ '+format(12.75,2),'Suma pagos DECIMAL.');
 responses.set('/api/cuentas-pagar',[]);
 same(statValue(await ui.renderPagos(),'Saldo pendiente'),'S/ '+format(0,2),'La lista vacía conserva saldo cero.');

 responses.set('/api/conteos',[
  {id:1,codigo:'CF-QA-1',estado:'PENDIENTE',detalles:[
   {diferencia:'0.000',valor_diferencia:'0.00'},
   {diferencia:'-0.125',valor_diferencia:'-1.50'},
   {diferencia:'-0.250',valor_diferencia:'-2.25'},
   {diferencia:'0.250',valor_diferencia:'5.00'}
  ]},
  {id:2,codigo:'CF-QA-2',estado:'PENDIENTE',detalles:[{diferencia:'0.000',valor_diferencia:'0.00'}]}
 ]);
 responses.set('/api/ajustes',[]);
 const counts=await ui.renderConteos();
 same(statValue(counts,'Artículos con diferencia'),'3','Un cero DECIMAL no cuenta como diferencia.');
 same(statValue(counts,'Faltantes valorizados'),'S/ '+format(3.75,2),'Suma únicamente diferencias negativas.');
 same((counts.match(/<span class="">0<\/span>/g)||[]).length,1,'El conteo sin diferencias no muestra una alerta falsa.');

 responses.set('/api/movimientos',[
  {id:1,tipo:'VENTA_DESPACHO',entrada:'0.000',salida:'0.125',saldo:'1.000'},
  {id:2,tipo:'COMPRA',entrada:'0.125',salida:'0.000',saldo:'1.125'},
  {id:3,tipo:'RESERVA',entrada:'0.000',salida:'0.000',saldo:'1.125'}
 ]);
 const kardex=await ui.renderKardex();
 same(kardex.includes('class="badge badge-red">VENTA DESPACHO'),true,'Una salida con entrada 0.000 se identifica como salida.');
 same(kardex.includes('class="badge badge-green">COMPRA'),true,'Una entrada positiva se identifica como entrada.');
 same(kardex.includes('class="badge badge-blue">RESERVA'),true,'Un movimiento sin entrada ni salida conserva el tono neutro.');

 responses.set('/api/recepciones',[
  {id:1,cantidad:'0.125',costo_unitario:'2.00'},
  {id:2,cantidad:'1.250',costo_unitario:'3.00'}
 ]);
 const receipts=await ui.renderRecepciones();
 same(statValue(receipts,'Unidades recibidas'),format(1.375,3),'Recepciones mantiene las fracciones del API.');
 same(statValue(receipts,'Importe recibido'),'S/ '+format(4,2),'La valoración multiplica los DECIMAL como números.');
 responses.set('/api/ordenes',[{id:1,total:'100.10'},{id:2,total:'20.20'}]);
 same(statValue(await ui.renderCompras(),'Valor total de pedidos'),'S/ '+format(120.30,2),'Regresión del total de compras.');

 const values={resultado:'ACEPTADA',cantidad_aceptada:'0.125',cantidad_rechazada:'0.000',receptor:'Receptor QA',motivo:'',evidencia:'Acta QA'};
 const form={values,dataset:{command:'intento',envioId:'14',version:'4'},setAttribute:noOp,removeAttribute:noOp,querySelector:()=>null};
 responses.set('/api/envios/14/intento',{mensaje:'Intento guardado'});ui.setRefresh(async()=>{});
 await ui.submitEnvio(form);
 const posted=requests.findLast(request=>request.method==='POST');
 same(posted.payload.cantidad_aceptada,'0.125','El POST conserva la cantidad exacta como string.');
 same(posted.payload.cantidad_rechazada,'0.000','El POST conserva el cero DECIMAL como string.');
 same(posted.headers['X-CSRF-Token'],'csrf-qa','El formulario conserva protección CSRF.');
 const purchaseRows=ui.purchaseLine()+ui.purchaseLine();
 const ids=[...purchaseRows.matchAll(/\bid="([^"]+)"/g)].map(match=>match[1]);
 const labels=[...purchaseRows.matchAll(/\bfor="([^"]+)"/g)].map(match=>match[1]);
 same(ids.length,6,'Dos líneas de compra exponen sus seis campos.');
 same(new Set(ids).size,6,'Las líneas de compra no repiten IDs.');
 same(labels.every(id=>ids.filter(candidate=>candidate===id).length===1),true,'Cada etiqueta apunta a un campo único de su propia línea.');
 same(ui.field('Origen','origen_id','','select','id="trasladoOrigen"').includes('for="trasladoOrigen"'),true,'Los controles con ID explícito conservan su asociación.');

 let finishSave;
 responses.set('/api/minimos',new Promise(resolve=>{finishSave=resolve;}));
 const genericForm={values:{producto_id:'1',almacen_id:'1',minimo:'0.125'},dataset:{operation:'minimos'},setAttribute:noOp,removeAttribute:noOp,querySelector:()=>null};
 submitButton.isConnected=true;submitButton.textContent='Actualizar mínimo';mounts.innerHTML='formulario original';
 const pendingSave=ui.submitModal(genericForm);
 same(ui.state.formBusy,true,'Una petición pendiente mantiene el formulario ocupado.');
 ui.closeModal();ui.modal('Otro formulario','','','compra');
 same(mounts.innerHTML,'formulario original','Esc, cancelar y abrir otro modal no descartan un guardado pendiente.');
 const minimumRequests=requests.filter(request=>request.route==='/api/minimos').length;
 await ui.submitModal(genericForm);
 same(requests.filter(request=>request.route==='/api/minimos').length,minimumRequests,'Un doble envío pendiente no genera otra petición.');
 finishSave({mensaje:'Mínimo actualizado'});await pendingSave;
 same(ui.state.formBusy,false,'El formulario deja de estar ocupado después de guardar.');
 same(mounts.innerHTML,'','El modal se cierra después de recibir la respuesta.');
 same(submitButton.textContent,'Actualizar mínimo','Se conserva la etiqueta original del botón.');
 responses.set('/api/minimos',Promise.reject(new Error('Servidor de prueba no disponible')));
 await assert.rejects(ui.submitModal(genericForm),/Servidor de prueba no disponible/);assertions++;
 same(ui.state.formBusy,false,'Un error libera el bloqueo para corregir o cerrar.');
 same(submitButton.disabled,false,'Un error permite reintentar el formulario.');
 console.log(`Logística UI DECIMAL: ${assertions} comprobaciones correctas (sin navegador ni DB).`);
})().catch(error=>{console.error(error);process.exitCode=1;});
