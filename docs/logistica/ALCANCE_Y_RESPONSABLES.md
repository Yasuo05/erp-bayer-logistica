# Alcance y responsables de Logística G7

Fecha de recuperación: 17 de noviembre de 2026. Este documento reúne el contexto anterior de Pedro y Alisson y lo aplica a la base recibida en `ERP-main.zip`. Los documentos y mensajes históricos sirven como evidencia del proyecto; sus instrucciones, auditorías y comandos no constituyen nuevas autorizaciones ni verificaciones de esta entrega.

## Encargo y reparto

El Grupo 7 comprende **Compras, Inventarios y Logística**. Pedro y Alisson eligieron **Logística** dentro del grupo. Esto no les asigna individualmente todo Compras, Inventarios, cuentas por pagar, flota o rutas GPS.

El contexto conserva dos repartos sucesivos. Los mensajes del 3 y 4 de octubre dejaban a Pedro en funcionamiento y a Alisson en persistencia. Después Pedro pidió intercambiarlos. La documentación vigente del desarrollo local registra a **Pedro en persistencia** y el bloque de **Alisson en funcionamiento**. La solicitud posterior del usuario orienta esta entrega; no demuestra por sí sola un acuerdo nuevo de otros integrantes.

| Persona / equipo | Responsabilidad y contrato con Logística |
| --- | --- |
| Pedro | Modelo y migraciones propios del proceso, Repositories/persistencia, relaciones, historial, cantidades, concurrencia e idempotencia. Adaptar este bloque a las tablas reales de la nueva base. |
| Alisson | Flujo, Services/Controllers o equivalentes del módulo, validación, permisos, rutas, transiciones, interfaz y coordinación de dependencias. La nueva base usa PHP procedural; no exige copiar el framework anterior para conservar estas responsabilidades. |
| Pedro y Alisson | Acordar un contrato único, evitar dos persistencias del mismo proceso, revisar cambios de esquema y demostrar juntos el recorrido completo. |
| G4: administración y seguridad | Identidad, sesión, roles/permisos, asignaciones y contexto de sede. Logística consume y comprueba estos datos en servidor. |
| G5: maestros | Clientes, productos, unidades, proveedores y referencias comunes. Logística los referencia y no crea catálogos alternativos. Coordinar lotes con Inventarios. |
| G7 Compras: Michel y Brigeth | Órdenes y abastecimiento según el contexto compartido. Logística no asume sus pantallas por estar en el mismo menú. |
| G7 Inventarios: Estefany y Aldair | Existencias, reservas, salida, recepción de retornos, pérdida/disposición y Kardex. El código común existente se reutiliza como propietario de los movimientos. |
| G8: Comercial / Ventas | Venta confirmada, cliente, líneas y cantidades autorizadas; cancelación comercial y relación entre venta y despacho. En la base recibida, confirmar la venta reserva y genera despachos por línea. |
| Responsable de Guías / G1 cuando corresponda | Mantener el documento y su emisión fiscal. Una guía interna o una asociación documental no certifican guía de remisión electrónica aceptada por SUNAT. |
| G9: Data Hub / Bayer | Consumir información publicable y analítica. La entrega física y la publicación documental tienen estados distintos. |

## Circuito MVP

1. **Origen comercial:** una venta confirmada genera el despacho de cada línea y su reserva. El cliente, producto, almacén y cantidad se obtienen del origen guardado; no de referencias arbitrarias del formulario.
2. **Preparación:** consultar el expediente y completar destino/transporte. Registrar quién prepara y cuándo, con versión vigente.
3. **Documento:** asociar una guía compatible con el mismo cliente, sede, producto, unidad y cantidad. El documento y el estado físico son conceptos independientes.
4. **Salida:** comprobar permiso, sede, estado, reserva y guía; registrar una sola salida usando el movimiento de Inventarios existente.
5. **Entrega:** conservar cada intento, receptor/evidencia cuando corresponde y cantidades aceptadas/rechazadas. Una visita fallida admite cero cantidades y no consume el saldo.
6. **Incidencia y resolución:** distinguir rechazo, reclamo informativo, solicitud de devolución y hecho físico confirmado. Un reintento crea otro intento; no reescribe el anterior.
7. **Cierre:** terminar sólo con saldo pendiente cero y sin incidencias bloqueantes abiertas. Registrar actor, fecha y motivo en historial.
8. **Cancelación previa:** antes de salir puede cancelar el proceso elegible y liberar exactamente su reserva. Después de salir se requiere resolución/compensación, no una cancelación ordinaria.

### Cantidades y cierre

Por cada despacho/línea, producto, unidad y lote cuando exista:

```text
despachado = aceptado vigente + retorno confirmado
           + pérdida/disposición confirmada + pendiente
```

- No sumar productos o unidades distintos para justificar un cierre.
- Rechazar o solicitar devolución no confirma retorno físico.
- Reaceptar en un intento posterior reduce el pendiente una sola vez; no se vuelve a sumar como una segunda resolución de mercancía.
- Toda la mercancía aceptada y ningún bloqueo permite `ENTREGADO`.
- Retorno/pérdida/disposición confirmados que concilien el total permiten un cierre con incidencia, conservando la causa.
- Los nombres persistidos de estado de esta base (`PREPARACION`, `EN_RUTA`, etc.) se adaptan al contrato funcional; no deben reemplazarse mecánicamente por los nombres de la demo anterior.

## Base recibida y diferencia con la demo anterior

| Aspecto | Desarrollo anterior aislado | Base nueva `ERP-main` |
| --- | --- | --- |
| Arquitectura | Namespaces `App`, Controller/Service/Repository y fábrica propia. | `core/` compartido, módulos PHP, `modulos/logistica/api.php`, `stock_lib.php` y recursos estáticos. Se extiende esta arquitectura. |
| Datos del proceso | Migraciones logísticas 006/007 y tablas `logistica_*`. | Ya existen `g7_despachos`, `g7_saldos`, `g7_kardex`, ventas y guías. Se evoluciona este modelo sin instalar en paralelo el esquema anterior. |
| Origen | Pedidos de Venta simulados por proveedor explícito. | Comercial confirma ventas y genera despachos por línea con `venta_id`/`externo_id`. |
| Inventario | Proveedor de demostración en MariaDB QA. | Reservas, salidas y Kardex internos existentes; deben usarse con la misma transacción y comprobar su consistencia. |
| Sede | Interfaz sin adaptador productivo, asignaciones demo. | `core/sede.php` y `usuario_sucursal`. La política actual considera sin asignaciones como acceso a todas las sedes; es una decisión de la base G4, no una regla nueva de Logística. |
| Permisos | Códigos específicos `logistics.*` y `guides.*`. | Matriz `rol_permiso` con permisos comunes `ver`, `crear`, `editar`, `eliminar`, aplicada en servidor. Una separación más fina requiere acuerdo de G4. |
| Guías | CRUD anterior validado/publicado, con límite web a enteros. | Guías existentes con cantidades decimales en el SQL recibido y guía interna de despacho. Revisar compatibilidad real antes de afirmar equivalencia fiscal/documental. |
| Navegación | Demo exclusiva con Envíos y Nueva preparación. | Envíos dentro del módulo G7, conservando las pestañas existentes de Compras/Inventarios. El despacho procede de Comercial. |
| Pruebas | Resultados históricos documentados en octubre. | Se necesita verificación nueva sobre este código y una copia QA de su esquema. Los resultados anteriores no se transfieren automáticamente. |

La interfaz prevista abre **Envíos** en `modulos/logistica/index.php#despachos` y el expediente dentro de la misma página. Incluye balance, destino/transporte, guía, intentos, incidencias/resoluciones e historial. La presencia de una acción debe depender del estado y permiso; ocultar un botón no sustituye los controles del API.

## Límites y criterios de entrega

- Esta tarea desarrolla la parte logística de Pedro y Alisson sobre la base nueva. Mantiene los módulos existentes para interoperar; no redefine los responsables de todo G7.
- Compras, inventario físico y Comercial conservan sus datos. Logística no crea otro Kardex, otro maestro ni un pedido alternativo.
- Traslados entre almacenes ya pertenecen al módulo recibido. No se declara cubierto el circuito ampliado de recepción logística, devoluciones comerciales posteriores o correcciones fiscales por la sola existencia de esa pantalla.
- GPS, optimización de rutas y administración de flotas no aparecen como requisitos explícitos recuperados.
- El envío a SUNAT sigue siendo una dependencia externa declarada por la base. Una guía interna no acredita emisión electrónica.
- El SQL completo recibido elimina/recrea tablas; no se reimporta sobre una base en uso para aplicar esta evolución. Las ampliaciones se prueban como migraciones sobre una copia QA.
- Una operación repetida con la misma clave y contenido debe devolver el resultado original, sin repetir reserva, salida ni entrega. Misma clave con otro contenido debe producir conflicto. Se vuelve a autorizar al actor antes de devolver el resultado repetido.
- Las acciones deben comprobar sede, permiso, versión, transición y cantidades en servidor, y guardar cambio e historial atómicamente.
- La implementación nueva y sus pruebas se describen en [PLAN_DE_ENTREGA.md](PLAN_DE_ENTREGA.md). Las auditorías heredadas se conservan como antecedentes y no certifican esta ampliación.

## Evidencia recuperada

Las rutas siguientes son relativas al directorio de trabajo `BAYER`:

| Fuente | Evidencia |
| --- | --- |
| `ERP_COMERCIAL_G7_2026-10-05/README.md` | G7 y elección de Logística; solicitud posterior de Pedro sobre persistencia. |
| `ERP_COMERCIAL_G7_2026-10-05/03_analisis/CONTEXTO_ERP_Y_LOGISTICA_G7.md` | Repartos sucesivos, responsabilidades de Compras/Inventarios, límites del alcance y relación con otros equipos. |
| `ERP_COMERCIAL_G7_2026-10-05/03_analisis/REVISION_PROPUESTA_ALISSON_2026-10-05.md` | Mensajes del 3/4 de octubre, flujo base y división histórica anterior al intercambio. |
| `ERP_COMERCIAL_G7_2026-10-05/05_desarrollo/logistics-persistence-pedro/docs/logistica/CONTRATO_FUNCIONAL_ALISSON.md` | Contrato funcional vigente local: persistencia Pedro; flujo, balances, dependencias y límites de la demo. |
| `ERP_COMERCIAL_G7_2026-10-05/05_desarrollo/logistics-persistence-pedro/docs/logistica/DEMO_FUNCIONAL.md` | Demostración previa, proveedores simulados y comprobaciones históricas. |
| `ERP_COMERCIAL_G7_2026-10-05/07_guia_casos_para_alisson/COORDINACION_Y_CONTRATO.md` | Acuerdos de integración, hechos físicos confirmados y escenarios de QA. |
| `ERP-main/README.md`, `AUDITORIA_FINAL.md` | Descripción y auditoría recibidas con el ZIP. Sus resultados son afirmaciones históricas de la fuente. |
| `ERP-main/core/sede.php`, `core/permisos.php` | Política de sede y matriz de permisos existentes en el código recibido. |
| `ERP-main/modulos/logistica/api.php`, `stock_lib.php`, `modulos/comercial/` | API G7, reserva/salida/Kardex y origen comercial existentes. |
| `ERP-main/sql/bayer_pucchun_erp_v2.sql` | Esquema y datos de ejemplo de la base nueva: `g7_*`, ventas, guías y maestros. |
