# Verificación local de la entrega

Fecha: **17 de noviembre de 2026**, America/Lima. Fuente recibida: `C:\Users\USER\Downloads\ERP-main.zip`. Implementación: `ERP-main`, separada del proyecto Bayer anterior y del desarrollo de demostración previo.

## Resultado ejecutado

**254 comprobaciones automatizadas correctas, cero fallos pendientes en estas suites.**

| Suite | Resultado observado | Evidencia |
| --- | --- | --- |
| Negocio e integración G7–G9 | **214 comprobaciones, 16 escenarios, 0 fallos.** Se verificó el rollback final de operaciones y permisos de prueba. | `resultado-integracion.json` |
| HTTP | **33 comprobaciones correctas.** Sesión, CSRF, controles de sede, endpoints antiguos, listados y archivos Excel válidos. | `resultado-http.json` |
| Sedes inactivas | **7 comprobaciones correctas.** Una cuenta asignada únicamente a una sede desactivada no obtiene acceso a otras sedes; cambios revertidos. | `resultado-sedes.json` |

Los JSON completos se incluyen en esta carpeta. Las comprobaciones de sintaxis PHP y JavaScript de los archivos cambiados también finalizaron correctamente. No se reutilizaron cifras de la auditoría incluida en el ZIP ni de las pruebas históricas de la demo.

## Alcance comprobado

- Venta confirmada → un despacho y su reserva; unidades comerciales compatibles con el inventario, sin redondear una cuarta cifra significativa.
- Configuración y preparación; guía existente compatible o emisión/validación/asociación operativa sobre las tablas oficiales. Rechazo de guía incompatible, número duplicado y salida sin guía.
- Salida única con descuento de existencia, consumo de reserva y Kardex. Los intentos de entrega no vuelven a descontar inventario.
- Aceptación total, aceptación de una parte sin rechazo, entrega parcial con rechazo, visita fallida y reintento. El rechazo continúa pendiente hasta su aceptación o resolución física.
- Retorno físico con entrada en Kardex al costo de salida; pérdida/disposición sin otro movimiento de existencia. Balance por despacho: `salida = aceptada + retornada + pérdida/disposición + pendiente`.
- Cierre normal o con incidencia; informativas abiertas no bloquean por sí solas, mientras las bloqueantes y el saldo positivo impiden cerrar. Cancelación antes de salir libera su reserva; después de salir se rechaza.
- Replay sin duplicar efectos, conflicto de contenido y de versión; autorización vigente antes del replay, incluso al retirar roles o desactivar la cuenta.
- Integración con el Portal Bayer: su sincronización conserva número, estado, versión y detalles de la guía operativa antes y después de salir, sin duplicarla.
- Protección de configuración, SQL, datos locales y scripts frente al servidor PHP integrado. Regresión de las consultas y exportaciones G7 por sede.

## Recorrido observado en el navegador

Se utilizó el despacho de ejemplo **DP-2026-0014** en la base aislada. Se completaron destino/transporte, preparación, emisión de la guía operativa **QA-LOG-20261117-14**, salida de **2.000 L**, entrega parcial, reintento y retorno físico del saldo. El expediente quedó **CERRADO_CON_INCIDENCIA** con:

`2.000 enviadas = 1.500 aceptadas + 0.500 retornadas + 0.000 pérdida/disposición + 0.000 pendientes`.

La captura [expediente-verificado.jpg](expediente-verificado.jpg) muestra el cierre y su balance. Son operaciones de QA sobre datos de ejemplo, conservadas localmente para revisión. Las fixtures de las suites automatizadas se revirtieron; InnoDB puede conservar saltos de AUTO_INCREMENT después del rollback.

## Entorno y límites

- PHP **8.2.12**, MariaDB **10.4.32**, instancia propia en `127.0.0.1:43327`, directorio `.runtime/mariadb`. El servidor web usa `127.0.0.1:8097`, sesiones aisladas y la extensión zip habilitada sólo para ese proceso; no se modificó el PHP global.
- El dump MySQL 8 se conserva. El primer inicio genera una copia compatible para MariaDB reemplazando la collation `utf8mb4_0900_ai_ci` y la expresión `REGEXP_LIKE` de un CHECK equivalente. La migración aditiva tiene seguimiento de checksum; volver a iniciar no reimporta la base ni reinicia expedientes.
- Las ventas multilínea conservan los despachos por línea de la base recibida. No se agregó una agrupación nueva de envíos ni otro inventario.
- La solicitud de devolución se registra como incidencia con evidencia y no mueve stock. El movimiento ocurre al confirmar el retorno físico mediante la acción autorizada del monolito.
- Se comprobó conflicto de versión e idempotencia en secuencias controladas. No se realizó una prueba de carga simultánea ni certificación de producción.
- La matriz recibida usa permisos generales `ver`, `crear` y `editar`. La emisión exige crear y editar. Un permiso de inventario más específico requiere acuerdo con G4; este trabajo conserva su matriz y comprueba asignaciones vigentes.
- Los documentos operativos no son guías electrónicas SUNAT. Facturación, datos reales, reglas de publicación Bayer y despliegue del equipo requieren su configuración y validación propias. Compras e Inventarios conservan sus demás funciones y responsables.

## Repetir

Desde PowerShell en `ERP-main`:

```powershell
.\scripts\iniciar-logistica-local.ps1 -SinNavegador
& 'C:\xampp\php\php.exe' tests\logistica_integracion.php --qa
& 'C:\xampp\php\php.exe' tests\logistica_sedes.php --qa
.\tests\logistica_http.ps1
.\scripts\detener-logistica-local.ps1
```

Las pruebas de negocio y sedes verifican host, puerto y directorio QA exactos antes de escribir. No deben adaptarse a una base compartida para ejecutarlas allí.
