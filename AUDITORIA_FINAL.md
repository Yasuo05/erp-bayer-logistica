# Auditoría final de funcionamiento — ERP Comercial Multisede Agrícola Pucchún

**Fecha:** 13/11/2026 · **Base:** `bayer_pucchun_erp_v2` con datos de ejemplo · **Código:** rama local `correcciones-auditoria` (sin commit)
**Referencia:** infografías 1–20; en especial 4 (mapa de módulos), 5 (flujo integral), 6 (relaciones), 14–19 (cada equipo) y 20 (contratos).
**Método:**
- Dos escenarios de punta a punta con datos nuevos, hechos desde la interfaz y la API del sistema.
- Pruebas de acceso sin sesión y con usuarios temporales de rol Gerencia y Digitador (restringido a una sede). Los usuarios se borraron al terminar.
- 18 controles cruzados de consistencia directamente en la base de datos.
- Revisión de las pantallas de los 16 módulos.
- No se modificó código. Los registros generados por las pruebas de permisos se borraron.

## Veredicto

**El sistema cumple el funcionamiento descrito en las infografías.** Recorre el flujo integral de la Inf. 5 (proveedor → compras → recepción → inventario → ventas → facturación → cobranza → caja → despacho → analítica → Bayer), con los módulos comunicados según los contratos de la Inf. 20 y con datos consistentes entre ellos: **18 de 18 controles sin descuadres**.

**Está listo para pasar a validación con usuarios** (fase 8 de la Inf. 12).

**Estado actual:** las brechas encontradas en la sección 5 ya están corregidas y verificadas:
- **Sección 7:** multisede en Logística y Facturación, usuarios, roles y permisos, y parámetros.
- **Sección 8:** fichas de clientes y proveedores, crédito, listas de precios, unidades en tránsito, botón «Anular» y acceso Bayer.
- **Sección 9:** diseño unificado y stock del portal.

Para salir a producción solo falta lo que corresponde a la organización:
- la conexión con SUNAT;
- los datos reales de la empresa;
- activar la API de Bayer con su token.

## 1. Escenarios de punta a punta (probados)

**Sede Cañete · venta al contado con factura**

| Paso | Resultado |
|---|---|
| 1 | La cotización COT001-00000006 toma el precio de lista del maestro: 3 × 315.00 y 5 × 149.00, con un descuento de 20.00 |
| 2 | Pedido PED001-00000004 y venta VEN001-00000004 confirmados. El stock se reservó y se crearon los despachos DP-0012 y DP-0013 con el cliente del maestro |
| 3 | Factura **F001-00000006** por **1,970.60** (base 1,670.00 + IGV 300.60), igual a la venta |
| 4 | El XML lleva unidades SUNAT LTR/BG, descuento 20.00, ubigeo 150501, establecimiento 0000 y el RUC del emisor del ERP. Se firmó y el envío simulado quedó **aceptado** |
| 5 | Cobranza en efectivo por el total, que ingresó a la caja de Cañete. La venta quedó con saldo 0.00 y en estado «aceptado / ENTREGADO» |
| 6 | Despachos con salida y entrega; el stock se descontó |

**Sede Mala · compra de reposición y venta a crédito con boleta**

| Paso | Resultado |
|---|---|
| 1 | La orden OC-2026-0008 se recibió: el stock de PRD-0002 en Mala pasó de 0 a 6 y se generó la cuenta por pagar CP-2026-0019 por 1,080.00 |
| 2 | Pago al proveedor por transferencia de 300.00, que salió de la cuenta bancaria de Mala |
| 3 | La venta a crédito VEN002-00000003 reservó stock y generó su cuenta por cobrar de 578.20 |
| 4 | Se emitió la boleta **B002-00000001**, de la serie propia de la sede, firmada |
| 5 | Cobranza por depósito de 200.00, ligada a B002-00000001. Entró al banco (400 − 300 + 200 = **300.00**) y la cuenta por cobrar quedó pendiente en **378.20** |

**Inventario (G7):** se probaron el traslado TR-2026-0001 de Cañete a Mala con su recepción en destino, la merma MR-2026-0001, el rechazo de un ajuste mayor al stock y el conteo cíclico CF-2026-0001 con regularización. Todo quedó registrado en el kardex.

## 2. Estado de los 16 módulos (Inf. 4)

| # | Módulo | Estado | Observación |
|---|---|---|---|
| 1 | Administración y configuración | **FUNCIONAL** *(actualizado, ver §7)* | Funcionan empresa, sedes, almacenes, series por sede y códigos SUNAT. Falta una pantalla de parámetros centrales: el IGV se escribe por línea en Comercial y en el `.env` de Facturación |
| 2 | Usuarios, roles y seguridad | **FUNCIONAL** *(actualizado, ver §7)* | Funcionan login, matriz de permisos aplicada en todos los módulos, sedes por usuario y bloqueo sin sesión. Falta pantalla de usuarios, roles y permisos: la matriz está en `core/permisos.php` |
| 3 | Productos y catálogos | **FUNCIONAL** | Productos, marcas y fabricantes, categorías, unidades con código SUNAT, precio de lista y homologación Bayer. No hay listas de precios con vigencia |
| 4 | Clientes | **FUNCIONAL** | Validación de RUC/DNI y unicidad. Los contactos y condiciones de crédito existen en la base, pero no en el formulario |
| 5 | Proveedores | **FUNCIONAL** | Maestro básico (código y razón social), usado por Compras y Cuentas por pagar |
| 6 | Compras | **FUNCIONAL** | Orden → recepción → stock → cuenta por pagar |
| 7 | Inventarios y almacenes | **FUNCIONAL** | Stock por sede, kardex, traslados, ajustes, conteos y reservas |
| 8 | Ventas | **FUNCIONAL** | Cotización → pedido → venta con precio, stock visible, reserva y anulación controlada |
| 9 | Facturación electrónica | **FUNCIONAL (interno)** | Funciona sobre ventas reales: descuentos, series por sede, XML firmado, notas de crédito, anulación y re-facturación. El envío a SUNAT es simulado (pendiente externo) |
| 10 | Caja y tesorería | **FUNCIONAL** | Caja: apertura, ingresos, egresos, arqueo y cierre. Tesorería: cuentas, cobros y pagos bancarios, reversiones |
| 11 | Cuentas por cobrar | **FUNCIONAL** | Cuotas, saldos con notas de crédito y vencidos |
| 12 | Cuentas por pagar | **FUNCIONAL** | Pagos en efectivo (caja) o transferencia (banco), con control de fondos |
| 13 | Logística y guías | **FUNCIONAL** | Despachos vinculados a la venta y al cliente, guía interna. La guía de remisión electrónica SUNAT queda pendiente junto con SUNAT |
| 14 | Reportes y analítica | **FUNCIONAL** | Panel DataHub: facturación neta, stock, ventas por sede/mes/producto y operación interna por sede |
| 15 | Integraciones y Portal Bayer | **PARCIAL** | Funciona: sincronización automática y solo publicación de lo aceptado. No verificado: acceso de un usuario Bayer real y API para Bayer (deshabilitada por configuración) |
| 16 | Auditoría y trazabilidad | **FUNCIONAL** | Vista única de las 4 bitácoras, con usuario, sede y valor anterior → nuevo |

## 3. Contratos entre equipos (Inf. 20) — probados

| Contrato | Estado | Evidencia en esta auditoría |
|---|---|---|
| G4 → todos: usuario, sede, roles, permisos | **FUNCIONAL** *(las 2 brechas de sede se corrigieron, ver §7)* | Sin sesión, todo redirige al login. Gerencia: consulta, pero no crea ni emite (403). Digitador de Mala: solo ve las ventas de Mala. **Brechas:** Logística y Facturación no aplican la restricción de sede (sección 5) |
| G5 → G7 / G8 / G1: productos, clientes, proveedores, precios | **FUNCIONAL** | Precio de lista precargado, cliente del maestro en ventas, despachos y comprobantes, proveedor en compras |
| G7 → G8: stock disponible | **FUNCIONAL** | Stock visible en la cotización y reserva al confirmar la venta |
| G8 → G7: venta confirmada, salida requerida | **FUNCIONAL** | Un despacho por línea; la anulación libera la reserva |
| G8 → G1: venta facturable | **FUNCIONAL** | La bandeja muestra ventas reales y los totales son idénticos |
| G1 → G8: comprobante y estado tributario | **FUNCIONAL** | Estado en Ventas, notas de crédito en saldos, re-facturación tras anular, cobros ligados al comprobante |
| Todos → G9: datos para analítica | **FUNCIONAL** | Base neta 16,815.00 = 16,815.00; stock 1,048 = 1,048; 13 despachos = 13 guías |

## 4. Controles cruzados de consistencia (18/18 sin descuadres)

| Control | Resultado |
|---|---|
| Saldo de inventario = suma del kardex | 0 descuadres (verificado también después de traslados, mermas y conteos) |
| Stock reservado = despachos en preparación | 0 |
| Venta = suma de líneas; cotización = pedido = venta | 0 / 0 |
| Venta confirmada con despacho por cada línea; venta anulada sin despachos activos | 0 / 0 |
| Comprobante vigente = total de la venta | 0 |
| Venta a crédito con su cuenta por cobrar por el mismo importe | 0 |
| Cobranza = suma de sus aplicaciones | 0 |
| Cobro en efectivo con ingreso en caja; cobro bancario con ingreso en tesorería | 0 / 0 |
| Cobranza ligada a un comprobante anulado habiendo uno vigente | 0 |
| Despachos sin cliente del maestro | 0 |
| Facturación ↔ DataHub (base neta) · stock ERP ↔ DataHub · despachos ↔ guías | 16,815.00 = 16,815.00 · 1,048 = 1,048 · 13 = 13 |

Notas:
- 5,960.00 de pagos a proveedores y 2 cobros bancarios son **anteriores a Tesorería**, por eso no tienen movimiento bancario. Corresponden a los datos de ejemplo iniciales.
- Los 3 comprobantes sin venta del ERP son los de demostración que traía la base original.

## 5. Brechas pendientes (priorizadas)

| Prioridad | Brecha | Grupo | Evidencia | Propuesta |
|---|---|---|---|---|
| **P2** | Logística no aplica la restricción de sede | G7 + G4 | El Digitador asignado solo a Mala creó una orden de compra para el almacén de Cañete (se borró) y ve el stock de ambas sedes | Usar `sede_exigir()` y `sede_filtro()` (de `core/sede.php`) en la API de Logística |
| **P2** | Facturación no filtra por sede | G1 + G4 | El Digitador de Mala ve facturas de Cañete en la emisión de notas; la bandeja tampoco filtra | Filtrar bandeja, listas y notas por `erp_context.sede` |
| **P2** | Sin pantalla de usuarios, roles y permisos | G4 | Los usuarios se crean por base de datos; la matriz de permisos está fija en el código | CRUD de usuarios y roles, y que `puede()` lea `permisos` y `rol_permiso` |
| **P2** | Sin parámetros centrales (IGV) | G4 | La tasa se escribe por línea en Comercial y en el `.env` de Facturación | Tabla de parámetros leída por G8 y G1 |
| **P3** | Unidades en tránsito no visibles | G7 | Durante un traslado el total bajó de 98 a 93 hasta la recepción | Mostrar «en tránsito» en stock y en DataHub |
| **P3** | Botón «Anular» visible en ventas facturadas | G8 | El servidor lo bloquea con el mensaje correcto, pero el botón aparece | Ocultarlo cuando haya comprobante vigente |
| **P3** | Formularios de clientes y proveedores básicos | G5 | Contactos, condiciones de crédito y RUC del proveedor no están en pantalla | Ampliar los formularios |
| **P3** | Listas de precios con vigencia | G5 | Solo hay un precio de lista | Tabla de listas y vigencias |
| No verificado | Acceso de un usuario Bayer al portal y API Bayer | G9 | No hay usuario Bayer y la API está deshabilitada por configuración | Crear un usuario Bayer de prueba y validar que solo vea lo publicado |
| Externo | SUNAT: envío real, CDR, guía de remisión electrónica | Organización | El envío actual es simulado | Certificado, credenciales SOL/OSE, `ENVIO_GATEWAY_URL` y XSD |
| Externo | Datos reales | Organización | Empresa, sedes, cuentas, ubigeos y series con datos de ejemplo marcados «REEMPLAZAR» | Cargarlos y ejecutar `sql/borrar_datos_ejemplo.sql` |

## 6. Comparación con las auditorías anteriores

| Indicador | 1.ª auditoría | Auditoría de comunicación | Final |
|---|---|---|---|
| Flujo integral de la Inf. 5 | Cortado en Ventas | Ida completa, sin vuelta | **Completo, ida y vuelta** |
| Contratos de la Inf. 20 funcionando | 2 de 7 | 4 de 7 | **7 de 7**, sin brechas tras §7 |
| Módulos FUNCIONALES | 3 de 16 | 10 de 16 | **15 de 16** tras §7 (Integraciones/Portal Bayer sigue PARCIAL) |
| Acceso sin sesión a módulos | Facturación abierta | Corregido | Verificado cerrado |
| Consistencia entre módulos | No medible | DataHub con diferencias | **18/18 controles** |

## 7. Corrección de las brechas P2 (14/11/2026) y su verificación

| Brecha | Corrección | Verificación |
|---|---|---|
| Logística no aplicaba la sede | En la API, cada operación de escritura exige que el almacén afectado sea de la sede activa: orden, recepción, traslado (origen), ajuste, conteo, despacho y pago de cuenta por pagar. Stock, catálogo, órdenes, despachos, cuentas por pagar y kardex se filtran por los almacenes de la sede | Probado con un Digitador asignado a Mala. No puede comprar ni despachar en Cañete («No tienes acceso a la sede seleccionada»). En Mala sí pasa el control de sede. Solo ve su almacén, sus órdenes, sus despachos y sus cuentas por pagar |
| Facturación no filtraba por sede | Se filtran por la sede de la venta: bandeja, listas de facturas y boletas, consultas, bandeja de envíos y referencias para notas. Además, `index.php` bloquea con 403 el acceso por id directo a ventas, comprobantes, PDF y envíos de otra sede | Probado: el Digitador de Mala solo lista documentos de Mala. Abrir por URL un comprobante o PDF de Cañete devuelve 403, y emitir una venta de Cañete también. La cabecera muestra «Sede Mala» |
| Sin pantalla de usuarios, roles y permisos | **Administración › Usuarios**: alta, edición, rol, estado, contraseña (mínimo 8), sedes y último ingreso. **Administración › Roles y permisos**: matriz editable en `rol_permiso`, que `puede()` lee en todos los módulos (la constante del código queda solo como respaldo). El administrador no puede quitarse su rol ni desactivarse; el rol Administrador conserva todos los permisos. Todo se registra en la auditoría | Probado: alta de usuario. Se rechazan contraseña corta, correo duplicado y que el administrador se quite el rol o se desactive. Al quitar «crear» al Digitador, perdió el botón de nueva cotización y Logística le respondió 403; luego se restauró la matriz |
| Sin parámetros centrales (IGV) | Tabla `parametros` y pantalla **Administración › Parámetros** (IGV, moneda base, vigencia de cotización), con validación y auditoría. Comercial toma el IGV y la vigencia por defecto; Facturación emite con ese IGV (el `.env` queda como respaldo) | Probado: IGV 150 rechazado. Con IGV 10, la cotización usó 10 por defecto y Facturación mostró «Tasa de IGV 10%». Luego se restauró a 18 |

**Migración:** `sql/migraciones/003_permisos_y_parametros.sql`, idempotente. Carga la matriz de permisos que antes estaba en el código y los parámetros iniciales. Está incluida en `sql/bayer_pucchun_erp_v2.sql` (importación probada: 93 tablas).

**Regresión:** 32 pantallas y 7 endpoints de Logística sin errores. Los controles siguen sin descuadres:
- saldo = kardex y reservado = despachos;
- comprobante = venta;
- base neta 16,815.00 = 16,815.00;
- stock 1,048 = 1,048.

Los usuarios de prueba se crearon y se borraron; en la base queda solo el administrador.

**Pendiente tras esta corrección:**
- Las brechas P3 de la §5 (unidades en tránsito, botón «Anular» en ventas facturadas, formularios ampliados de clientes y proveedores, listas de precios).
- La verificación del acceso de un usuario Bayer.
- SUNAT y los datos reales, a cargo de la organización.

## 8. Corrección de las brechas P3 y verificación del acceso Bayer (14/11/2026)

| Brecha | Corrección | Verificación |
|---|---|---|
| Fichas de clientes básicas (G5, Inf. 15) | **Clientes**: tipo de cliente, sector o cultivo, contacto, teléfono, correo, vendedor asignado, límite de crédito, días de crédito y estado Activo/Inactivo. Se valida el RUC (11 dígitos, prefijo y dígito verificador de SUNAT), el DNI (8 dígitos), el correo, el límite (≥ 0), los días (0–365) y el documento duplicado | Se rechazaron un RUC con dígito verificador errado (el mensaje llega al usuario), un correo inválido, un límite negativo y un RUC repetido. Se guardó un cliente válido; cambiar su estado a Inactivo funcionó con el filtro, y luego se eliminó |
| Fichas de proveedores básicas | **Proveedores**: RUC (obligatorio, validado y único), contacto, teléfono, correo y días de pago. Logística calcula el vencimiento de la cuenta por pagar con los días de pago del proveedor (30 si no tiene) | Se rechazaron un RUC inválido y uno duplicado; el proveedor válido se guardó y luego se eliminó |
| Venta a crédito sin control de crédito (G5 → G8) | Al convertir un pedido en venta a crédito, el cliente debe tener línea de crédito, y su deuda vigente más la nueva venta no puede superar el límite. Si no se indica vencimiento, se usa el plazo de días de crédito del cliente | Con el límite bajado a 1,000, la venta de 1,445.50 se rechazó («supera la línea de crédito…»). Con el límite real (15,000) se aceptó, con vencimiento a 30 días. La venta de prueba se borró y el correlativo volvió a su valor |
| Sin listas de precios con vigencia (G5 → G8) | Tabla `lista_precios` y pantalla **Catálogo › Listas de precios**: producto, sede (o todas), precio, vigencia desde/hasta y observación. No se permiten vigencias que se crucen para el mismo producto y sede. La cotización completa el precio con el de la sede, si no con el general y, si no hay ninguno, con el precio de lista del producto | Se rechazaron una vigencia cruzada y fechas invertidas. La cotización completó 88.00 (precio de la sede), 90.25 (precio general), 232.75 (precio general) y 58.00 (precio de lista del producto) |
| Unidades en tránsito no visibles (G7) | El stock de Logística suma los traslados pendientes de recepción hacia los almacenes visibles (`en_transito`) y lo muestra en la tabla («+ 2.000 en tránsito») | Probado con un traslado de 2 unidades de Cañete a Mala; luego se deshizo y saldo = kardex sigue sin descuadres |
| «Anular» visible en ventas facturadas (G8) | Las ventas con comprobante vigente ya no muestran el botón. En su lugar indican que se reviertan con la anulación del comprobante o una nota de crédito en Facturación (el servidor ya lo bloqueaba) | En las 7 ventas facturadas no aparece el botón y sí la indicación |

### Acceso del usuario Bayer (portal y API, G9)

Se probó con un usuario temporal de rol Bayer, entrando por el login del portal. Se encontraron y corrigieron dos fallas:

1. **El rol no se reconocía.** El login del portal guardaba el rol tal como está en la base («Bayer»), pero las reglas esperan «BAYER». Por eso el usuario Bayer veía la «Operación interna» y no podía exportar. Ahora el rol se normaliza al iniciar sesión (`rol_datahub()`), y un rol sin acceso no puede entrar.
2. **El dashboard mostraba registros no publicados.** Ahora, para el rol Bayer:
   - KPI, series, ranking, últimos documentos, inventario, ventas por sede y catálogo cuentan solo lo PUBLICADO;
   - no se muestran los pendientes internos;
   - los enlaces a las pantallas internas se cambian por descargas de lo publicado;
   - se oculta «Volver al ERP».

Resultado verificado:
- **Usuario Bayer:**
  - entra al dashboard y ve 5 documentos y 8 guías (solo los publicados, de 10 y 13);
  - Documentos, Guías y Stock internos le devuelven 403;
  - la exportación le entrega solo lo publicado.
- **Administrador:** sigue viendo todo (10 documentos, 4 pendientes y la operación interna).
- **API** (`/api/v1/bayer/sales`, `shipments`, `inventory`):
  - apagada responde 503, y sin token o con token errado responde 401;
  - con el token entrega solo lo publicado (10 líneas de venta de 19, 8 guías y 37 líneas de stock).
  - Se activó solo para la prueba y quedó apagada (`API_ENABLED=false`); para usarla, la organización debe activarla y entregar el token a Bayer.

El usuario temporal y sus registros de auditoría se borraron.

**Migración:** `sql/migraciones/004_maestros_credito_precios.sql`, idempotente. Está incluida en `sql/bayer_pucchun_erp_v2.sql` (importación probada en una base temporal: 94 tablas). Los datos de ejemplo cargan los límites de crédito, los RUC de proveedores y 2 precios vigentes, y `borrar_datos_ejemplo.sql` los quita (ciclo probado: vuelve exactamente al estado inicial).

**Regresión:** 48 pantallas y 13 endpoints de Logística sin errores. Los controles siguen sin descuadres:
- saldo = kardex y reservado = despachos en preparación;
- comprobante = venta;
- base neta 16,815.00 = 16,815.00;
- stock 1,048 = 1,048.

**Pendiente:** solo lo que corresponde a la organización: la conexión a SUNAT, los datos reales en lugar de los de ejemplo, y activar la API de Bayer con su token.

## 9. Diseño unificado y stock del portal (14/11/2026)

**Un solo marco para todo el ERP.** Facturación y el Portal Bayer tenían su propia cabecera y su propio menú, así que al entrar se perdía el menú del ERP. Ahora:
- **Marco común:** `core/erp_marco.php` arma la misma barra superior para todo el sistema: menú, selector de sede, usuario, salida y migas de pan. Lo usan las páginas del ERP (`core/layout_header.php`), Facturación (`modulos/facturacion/app/Views/layouts/app.php`) y el portal (`modulos/datahub/app/Views/layouts/header.php`).
- **Menú del ERP:** incluye todas las opciones de Facturación, agrupadas por sección (Emisión, Comprobantes, Envío electrónico, Consultas, Configuración). Ya no existen los botones «Volver al ERP».
- **Estilos encapsulados:** en esos módulos, la barra usa `assets/css/erp_marco.css`, generado desde `style.css` con `scripts/generar_erp_marco_css.py` y encerrado en `.erp-marco`, para que no choque con su CSS propio ni con Bootstrap. Además se aplican la tipografía y el verde del ERP.
- **Usuario Bayer:** como no entra al ERP, conserva su portal propio, solo con lo publicado, pero con los colores y el logo del ERP.
- **Barra adaptable:** antes, con todos los grupos del menú, la barra desbordaba entre 1280 y 1920 px y dejaba fuera de pantalla al usuario y el botón de salir. Ahora se adapta: bajo 1600 px queda solo el logo, y bajo 1440 px el menú pasa al botón ☰. Se verificó de 390 a 2560 px en el ERP, en Facturación y en el portal.

**Stock del portal.** DataHub guarda una foto del stock por almacén cada día, y el panel y la exportación las sumaban todas: mostraba 2,100 en lugar de 1,048. Ahora:
- el panel cuenta solo la foto más reciente de cada almacén;
- para Bayer, la más reciente publicada;
- la exportación y la API de inventario entregan el stock actual, o el histórico si se pide un rango de fechas.

**Verificación:**
- 51 opciones del menú cargan sin errores, dentro del marco y con su opción marcada.
- El detalle de comprobante y las páginas 404 también se muestran dentro del marco; el PDF sigue saliendo como PDF.
- El usuario Bayer ve su portal propio, sin el menú interno, con el stock en 1,048 (usuario temporal, ya borrado).

## 10. Exportación a Excel (15/11/2026)

**Problema:** el botón «Exportar» de las pantallas de maestros copiaba la tabla visible a un CSV separado por comas. En Windows en español (Perú), Excel espera punto y coma, así que cada fila caía en una sola columna. Además, solo traía la página visible (15 filas) y los encabezados llevaban las flechas de ordenar. Las descargas de Logística y del Portal Bayer eran Excel sin formato: los números del portal se guardaban como texto y los encabezados estaban en inglés técnico.

**Corrección:** todas las exportaciones generan un **.xlsx real** con el mismo formato, gracias a un generador común en `core/excel.php`:
- título, fecha de generación y número de registros;
- encabezado azul del ERP, con filtro y fijo al desplazarse;
- anchos de columna según el contenido;
- montos y cantidades como números (se pueden sumar), fechas como fechas, y códigos como RUC o DNI como texto.

Por módulo:
- **Maestros:** el botón trae **todos** los registros que cumplen la búsqueda y el filtro de estado, no solo la página visible.
- **Logística:** stock (con la columna «En tránsito»), kardex y órdenes de compra.
- **Portal Bayer:** encabezados en español en el Excel. La API conserva los nombres en inglés que espera Bayer.
- **Facturación:** ya tenía buen formato; solo se unificó el color del encabezado.

**Verificación:**
- 10 descargas de los cuatro módulos, con XML válido y leídas correctamente por una librería de Excel.
- 52 pantallas sin errores.
