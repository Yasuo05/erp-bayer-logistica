# Plan de entrega y verificación de Logística

Fecha: 17 de noviembre de 2026. Alcance: completar el circuito logístico de Pedro y Alisson en la base `ERP-main.zip`, conservando su arquitectura y los datos de los módulos propietarios. El contexto y las responsabilidades están en [ALCANCE_Y_RESPONSABLES.md](ALCANCE_Y_RESPONSABLES.md).

**Estado: implementación completada y verificada localmente.** La evidencia nueva de esta entrega está en [VERIFICACION_2026-11-17.md](VERIFICACION_2026-11-17.md). Las comprobaciones se realizaron sobre la instancia QA aislada de esta base; no representan una puesta en producción ni una certificación fiscal.

## Entregables y dependencias

| Bloque | Resultado revisable | Criterio de aceptación |
| --- | --- | --- |
| Base y migración | Extensión aditiva de las tablas `g7_*`, sin importar el framework ni las tablas duplicadas de la demo. | Se aplica sobre una copia del esquema recibido y se puede volver a ejecutar conforme a su diseño sin borrar operaciones anteriores. Se conservan IDs y datos existentes. |
| Persistencia | Intentos, incidencias/resoluciones, historial, balance, versión y resultado idempotente. | Cambios atómicos, conflicto sobre versión antigua, cantidades válidas y ninguna operación física duplicada. |
| API | Consulta del expediente y comandos del circuito. | Sesión, permiso, sede, CSRF y validaciones de estado/cantidad en servidor; errores comprensibles sin modificar datos al rechazar. |
| Inventario existente | Reserva al confirmar venta, salida única, liberación previa y retorno confirmado cuando corresponda. | Se reutilizan `g7_saldos`, `g7_kardex` y sus funciones; no se crea un segundo inventario. |
| Documento | Guía compatible asociada o guía operativa emitida, validada y asociada en una sola operación. | Se deriva cliente, sede, producto, unidad y cantidad del envío. No usar una guía incompatible. La guía operativa no constituye emisión electrónica SUNAT. |
| Interfaz | Envíos con filtro/búsqueda y expediente con URL propia. | Se puede recorrer preparación → guía → salida → entrega/resolución → cierre, y consultar el historial. Compras, Inventarios y Logística se organizan en páginas y menús separados. |
| Pruebas nuevas | Evidencia de resultados en la base QA de esta entrega. | Flujos normales, parciales y fallidos; seguridad, repetición, conflictos de versión y regresión comercial/inventario. No se ensayó carga simultánea. |

La confirmación comercial sigue siendo el origen ordinario del despacho. La ampliación no necesita un formulario de alta manual que permita inventar ventas o reservas. Una venta de varias líneas conserva los despachos por línea que genera la base; no se anuncia una cabecera de envío agrupada si no existe.

## Contrato de la ampliación implementada

El backend implementa el expediente sobre el `g7_despachos` real y su `venta_id`, con las tablas adicionales `g7_envios` (metadatos), `g7_envio_eventos`, `g7_envio_intentos`, `g7_envio_incidencias`, `g7_envio_resoluciones` y `g7_envio_operaciones`. Son registros del proceso; `g7_saldos` y `g7_kardex` continúan siendo el inventario común. La extensión aditiva está en [001_logistica_operativa.sql](../../sql/migrations/001_logistica_operativa.sql); el flujo está en [workflow.php](../../modulos/logistica/workflow.php).

| Ruta lógica | Uso |
| --- | --- |
| `GET /api/envios` | Listar expedientes autorizados de la sede; la interfaz permite buscar y filtrar por estado. |
| `GET /api/envios/{id}` | Consultar cabecera, balance, guía, intentos, incidencias/resoluciones e historial del despacho. |
| `POST /api/envios/{id}/{accion}` | Ejecutar el comando permitido enviando `version` e `idempotency_key`, además de sus datos y protección CSRF. |

Las rutas lógicas se envían al controlador existente como `modulos/logistica/api.php?route=...`; el frontend usa JSON y el encabezado `X-CSRF-Token`. Los comandos implementados son `configurar`, `preparar`, `guia`, `emitir_guia`, `salir`, `intento`, `incidencia`, `resolver`, `cerrar` y `cancelar`. La creación manual arbitraria y la entrega directa antigua se rechazan; las rutas antiguas de salida/cancelación pasan por el mismo flujo con versión e idempotencia.

`emitir_guia` recibe únicamente número y fecha, además de versión/clave de operación. El servidor deriva los datos comerciales y las cantidades del envío preparado, crea o reutiliza el documento operativo compatible en las tablas de Guías existentes y lo valida/asocia dentro de la transacción. Exige los permisos persistidos `crear` y `editar`; su resultado se identifica como guía operativa, sin presentación como GRE aceptada por SUNAT. `guia` permite asociar una guía existente compatible y `salir` vuelve a verificar su versión y contenido antes del movimiento físico.

El retorno confirmado por un operador autorizado ejecuta un movimiento real mediante `g7_mutate_stock` con el costo de salida. Pérdida/disposición concilian el proceso sin sumar existencias. La autorización, los movimientos y el balance se verifican en el servidor; una solicitud de devolución o su referencia no se convierten por sí solas en una entrada de inventario. Esta implementación es una operación interna del monolito, no una confirmación recibida de un proveedor externo remoto.

Los estados logísticos distinguen preparación, salida e intentos de su cierre definitivo: `ENTREGADO` registra aceptación completa, mientras `CERRADO` termina el expediente sin retorno/pérdida. Cuando el balance termina con retorno o pérdida/disposición confirmada, el cierre es `CERRADO_CON_INCIDENCIA`. Ambos cierres y `CANCELADO` son terminales. Las incidencias informativas abiertas no impiden cerrar; sí lo hacen las bloqueantes abiertas o cualquier cantidad pendiente.

La interfaz está conectada en `modulos/logistica/envios.php`: Envíos y su expediente (`envio.php?id=...`), con cantidades, destino/transporte, guía, intentos, incidencias/resoluciones, historial y acciones según estado/capacidad. Desde la revisión del 21 de noviembre, Compras, Inventarios y Logística usan entradas separadas en el menú común del ERP y una página por sección. El índice antiguo redirige a Envíos y los fragmentos anteriores se resuelven a las páginas nuevas. En Traslados, el origen respeta la sede activa y el catálogo de destinos sólo ofrece referencias de almacenes activos; la recepción exige operar desde una sede autorizada de destino.

## Integración completada

1. Se comparó la nueva base con las reglas recuperadas y se mantuvieron sus despachos por línea, sin portar el framework ni el inventario de la demo anterior.
2. Se aplicó la migración en una instancia QA aislada y se conservaron los registros históricos del esquema recibido.
3. Se conectaron persistencia/API a sesión, permisos vigentes del usuario, sede, CSRF y movimientos comunes. Se corrigieron los accesos por sede y la autorización tras retirar roles o desactivar usuarios.
4. Se conectó Envíos/expediente a los endpoints reales y se verificaron funciones de negocio, solicitudes HTTP y un recorrido visual.
5. Las suites nuevas terminaron sin fallos: 214 comprobaciones de integración en 16 escenarios y 33 comprobaciones HTTP. El detalle y los límites están en el informe de verificación.
6. La entrega contiene código, migración, scripts y documentación local. No se aplicaron estos cambios a una base compartida ni se presenta esta entrega como despliegue productivo.

## Casos de aceptación del MVP

Usar registros nuevos y trazables en QA, con stock suficiente y una sede determinada. Las cantidades son ejemplos independientes y no una única secuencia obligatoria. Comparar el estado antes y después en despacho, reserva, Kardex, intentos, incidencias, operaciones e historial.

| ID | Escenario | Resultado esperado |
| --- | --- | --- |
| M01 | Confirmar una venta nueva con una línea de 100. | Un despacho de esa línea, cliente/origen correctos y reserva de 100 una sola vez. |
| M02 | Confirmar la misma venta o repetir el comando original. | No aparece otro despacho, reserva ni registro físico duplicado. |
| M03 | Completar preparación y asociar guía compatible. | Destino/transporte e historial persistidos; guía vinculada al despacho correcto. |
| M04 | Intentar salir sin guía o con una incompatible. | Rechazo; reserva, stock y estado físico permanecen como estaban. |
| M05 | Confirmar salida de las 100. | Descuento de 100 y liberación/consumo de su reserva exactamente una vez; un movimiento de salida identificable. |
| M06 | Aceptar las 100. | Un intento aceptado, pendiente 0 y estado `ENTREGADO`; el comando de cierre produce `CERRADO` si no hay bloqueos. No se vuelve a descontar stock al entregar. |
| M07 | Aceptar 60 de 100 sin rechazo. | 60 aceptadas, 40 pendientes; despacho abierto. No se inventa rechazo para las 40 no intentadas. |
| M08 | Aceptar 80 y rechazar 20 de 100. | Intento parcial, incidencia cuantitativa cuando corresponda, 20 pendientes. Rechazo no ingresa mercancía al almacén. |
| M09 | Reintentar y aceptar 12 de las 20 pendientes. | 92 aceptadas y 8 pendientes; no duplicar incidencia ni cantidad física. |
| M10 | Documentar solicitud de devolución de las 20. | Se representa mediante una incidencia informativa con motivo/evidencia; no existe un comando dedicado de solicitud. Mantiene 80 aceptadas y 20 pendientes, sin ingresar stock, hasta confirmar el retorno físico. |
| M11 | Confirmar retorno de 12 y pérdida/disposición autorizada de 8. | `100 = 80 + 12 + 8 + 0`. El retorno usa el movimiento de entrada correcto; pérdida/disposición no repite la salida original. Cierre `CERRADO_CON_INCIDENCIA` sólo si no quedan cantidades pendientes ni bloqueos. |
| M12 | Repetir la aplicación del mismo hecho físico confirmado. | Conflicto o mismo resultado original, sin otra entrada ni otra resolución de mercancía. |
| M13 | Intento fallido por receptor ausente, sin receptor ni líneas. | Intento conservado, cero aceptación/rechazo y saldo intacto. Puede registrarse otra visita conforme a las reglas del caso. |
| M14 | Reclamo informativo de daño con las 100 aceptadas. | Pendiente 0; no contar las 20 reclamadas como otras 20 rechazadas. Si bloquea, resolver el caso antes del cierre. |
| M15 | Cerrar con pendiente positivo o incidencia bloqueante. | Rechazo sin cambios. Resolver una incidencia por sí solo no oculta un saldo físico pendiente. |
| M16 | Cancelar un despacho en preparación. | Cancela una vez, libera sólo su reserva y conserva historia. No crea salida ni devolución. |
| M17 | Cancelar después de salir o después de cerrar. | Rechazo ordinario; se requiere el proceso de resolución/compensación aplicable. |
| M18 | Cantidad negativa, cero indebido, exceso de saldo, más de tres decimales o notación inválida. | Rechazo sin redondeo silencioso ni modificación parcial. Las unidades incompatibles no se suman. |

Esta matriz M01–M18 conserva los criterios del MVP para seguimiento. No es un listado de 18 pruebas independientes aprobadas ni acredita por analogía funciones de la demo anterior. Las suites entregadas agrupan sus comprobaciones en escenarios propios; el informe de verificación identifica qué se ejecutó y sus límites. M10 tiene la representación explícita descrita arriba, sin un comando dedicado de solicitud de devolución.

## Seguridad, repetición y concurrencia

| ID | Comprobación | Resultado esperado |
| --- | --- | --- |
| S01 | Abrir expediente/API sin sesión. | Login/401 según el contrato del endpoint; no se devuelven datos operativos. |
| S02 | Usuario de consulta intenta cualquier comando por solicitud directa. | 403 o denegación equivalente; los datos no cambian. |
| S03 | Usuario limitado a una sede lista o abre un despacho de otra sede por ID. | No se filtran datos ni se acepta la operación; también se comprueba el acceso a la guía asociada. |
| S04 | Alterar `almacen_id`, `cliente_id`, `venta_id` o sede del payload. | No cambia la identidad guardada ni permite operar fuera del origen/sede autorizados. |
| S05 | POST sin token CSRF o con token incorrecto. | Rechazo antes de ejecutar la acción. |
| S06 | Misma clave, actor, comando y contenido después de que el despacho haya avanzado. | Resultado original sin recalcular ni repetir el efecto. |
| S07 | Misma clave con contenido distinto. | Conflicto sin operación adicional. |
| S08 | Repetir una clave cuando el actor ya perdió permiso. | Acceso denegado; el replay no evita la autorización. |
| S09 | Dos comandos sobre la misma versión. | Sólo uno consume saldo o hace transición; el otro recibe conflicto de versión/estado. |
| S10 | Fallo deliberado entre movimiento físico y persistencia del comando. | Rollback completo en el monolito; no queda stock alterado sin expediente/historial o viceversa. |
| S11 | Receptor, destino, transporte y observaciones con caracteres HTML. | Se presentan como texto, sin ejecutar contenido. |

La comprobación de concurrencia de esta entrega usa comandos secuenciales con la misma versión, versiones antiguas y repetición de claves. Demuestra conflictos de versión y replay sin duplicar efectos; no demuestra comportamiento bajo solicitudes simultáneas ni rendimiento con carga concurrente.

## Regresión mínima de la base recibida

- Confirmar y anular una venta elegible: las reservas se crean/liberan una vez; no se anula una venta con despacho que ya salió.
- Comparar stock y Kardex antes/después de una salida, retorno y cancelación. El reservado coincide con las preparaciones activas pertinentes.
- Consultar despachos históricos `PREPARACION`, `EN_RUTA`, `ENTREGADO` y `CANCELADO`: no pierden cliente, fechas o referencias al ampliar el esquema.
- Abrir Compras, Stock/Kardex, Traslados, Comercial y Data Hub. La navegación nueva de Envíos no rompe sus rutas.
- Comprobar que el selector de sede del marco común se respeta en listados y comandos.
- Recargar la página y reiniciar el servidor local: los registros y sus historiales permanecen.
- Verificar sintaxis PHP y JavaScript de los archivos modificados antes de las pruebas HTTP.

## Estado y evidencia de pruebas

La implementación local está completada. Las pruebas nuevas registran los siguientes resultados; el informe [VERIFICACION_2026-11-17.md](VERIFICACION_2026-11-17.md) conserva la evidencia y los límites de la ejecución.

| Verificación nueva | Resultado y alcance |
| --- | --- |
| [Suite de integración](../../tests/logistica_integracion.php) | 214 comprobaciones aprobadas, 0 fallos, en 16 escenarios; funciones reales, inventario y persistencia sobre QA aislada, con rollback final de sus fixtures. |
| [Suite HTTP](../../tests/logistica_http.ps1) | 33 comprobaciones aprobadas: solicitudes al servidor local, autenticación/CSRF, accesos por sede, regresión de consultas/exportaciones y protección de rutas internas. |
| Recorrido visual | Expediente `DP-2026-0014`: `2.000 = 1.500 aceptadas + 0.500 retornadas + 0.000 pendientes`. La evidencia se conserva en [expediente-verificado.jpg](expediente-verificado.jpg). Es una muestra visual concreta, no una revisión exhaustiva de todas las pantallas. |
| Versión e idempotencia | Repeticiones y conflictos probados en secuencia. No se ejecutó una prueba de carga simultánea. |

La matriz de aceptación contiene 29 criterios M/S y se conserva como referencia; los resultados anteriores no significan que cada uno haya sido certificado como un caso independiente. En particular, M10 se cubre como incidencia informativa sin efecto físico, y la concurrencia tiene el alcance secuencial indicado. La auditoría `AUDITORIA_FINAL.md` venía en el ZIP y la demostración de octubre pertenecía a otro esquema con proveedores simulados: ambas se mantienen como antecedentes, separadas de estas comprobaciones nuevas.

La puesta en marcha y la migración usan la copia QA preparada para esta entrega. El SQL completo y el script de borrado de ejemplos no deben ejecutarse sobre bases existentes para repetir estas pruebas.
