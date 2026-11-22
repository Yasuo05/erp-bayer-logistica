# Pruebas y situaciones de Logística

Ejecución final: 22 de noviembre de 2026, America/Lima. **449 comprobaciones correctas, cero fallos**, sobre la instancia local aislada (HTTP 8097, MariaDB 43327 y datadir exacto `ERP-main/.runtime/mariadb`).

| Suite | Comprobaciones | Evidencia |
| --- | ---: | --- |
| Negocio, 18 escenarios | 265 | `qa-integracion-2026-11-22.json` |
| Sedes inactivas y autorización | 7 | `qa-sedes-2026-11-22.json` |
| API HTTP, CSRF, exportación y rutas protegidas | 33 | `qa-http-2026-11-22.json` |
| Páginas separadas, sesión y errores HTTP | 115 | `qa-paginas-2026-11-22.json` |
| Interfaz con respuestas DECIMAL y guardado demorado | 29 | `tests/logistica_ui_decimales.cjs`; salida del comando completo |

El resumen ejecutable está en `qa-completo-2026-11-22.json`. Las cifras cuentan aserciones, no 449 recorridos completos independientes.

## Situaciones ejecutadas

| Situación | Resultado comprobado |
| --- | --- |
| Despachar y aceptar 100 | Sale stock una vez, se libera su reserva y el expediente cierra con pendiente cero. Aceptar la entrega no produce otra salida. |
| Aceptar 60, visita fallida y reintento de 40 | Quedan 40 pendientes tras la aceptación parcial; la visita fallida no cambia cantidades y el siguiente intento completa 100. |
| Aceptar 80 y rechazar 20 | El rechazo conserva 20 pendientes y no ingresa stock por sí solo. |
| Retornar 12 y documentar pérdida de 8 | Sólo las 12 que regresan físicamente entran al almacén. Balance final: 100 = 80 aceptadas + 12 retornadas + 8 de pérdida; cierre con incidencia. |
| Reintentar 12 de las 20 rechazadas | Se conservan 92 aceptadas y 8 pendientes, sin confundir el reintento con una devolución. |
| Cancelar antes o después de salir | Antes de salir libera la reserva sin movimiento físico. Después de salir se rechaza la cancelación. |
| Cerrar con pendientes o bloqueo | Rechazo sin cambios. Resolver un bloqueo y completar el saldo permite cerrar. |
| Salir sin guía, usar otro cliente o una guía modificada | Rechazo. Revisar y reasociar la versión compatible permite la salida. |
| Repetir una petición | La misma clave y contenido recuperan el resultado original sin duplicar stock, guía, historial o resolución. Contenido diferente, versión vieja o permiso revocado se rechazan. |
| Cantidades fraccionarias | 1.125 se concilia con 0.001 + 1.124. Negativos, exceso de saldo, cuarta cifra decimal no nula y unidades incompatibles se rechazan. |
| Venta con dos líneas: primera de 10 y segunda de 6, con sólo 5 disponibles para la segunda | Falla la confirmación comercial completa. El rollback conserva la venta en borrador y restaura la reserva, los saldos y los registros de la primera línea. |
| Dos despachos de 60 y 40 comparten una guía de 100 | Se reserva y despacha exactamente 100 en total; los dos balances se mantienen independientes y no se duplica la guía. |
| Usuario sin sede activa autorizada o con única sede inactiva | Listas sin datos y acceso directo/comandos rechazados; no se concede acceso global por tener una lista vacía. |
| Petición sin CSRF, archivos internos o exportación Excel | POST sin token rechazado; archivos internos devuelven 404; las tres exportaciones contienen una hoja XLSX válida. |

## Fallos encontrados y corregidos

- Los totales de traslados y pagos concatenaban strings DECIMAL. Ahora se suman como números para su presentación; las cantidades enviadas al servidor siguen siendo strings exactos.
- El cero `0.000` se contaba como una diferencia de inventario. Se corrigió la comparación y la valoración de faltantes.
- El cero de entrada en Kardex era un string verdadero y podía pintar las salidas en verde. Se distinguen entradas positivas, salidas positivas y movimientos sin efecto físico.
- Las líneas de compra repetían IDs. Cada etiqueta apunta ahora a un campo único, incluidas las líneas añadidas.
- Un formulario genérico podía cerrarse mientras guardaba. Se bloquean el cierre, la sustitución del modal y el doble envío durante la petición; un error vuelve a habilitarlo y conserva su etiqueta.
- Las pruebas HTTP dependían de una opción de PowerShell 7. Ahora también se ejecutan con Windows PowerShell 5.1, incluidas las respuestas de error y los archivos XLSX.

## Recorrido visual

Se comprobó búsqueda sin resultados, restauración de la lista y filtro de cuatro envíos por despachar. En DP-2026-0007, preparar sin transporte devuelve un error dentro del formulario. En DP-2026-0003, 15.001 para un pendiente de 15, -1 y 1.0001 son inválidos; cerrar con pendiente se rechaza y no aparece un nuevo intento al recargar. No se guardaron entregas válidas sobre estos expedientes existentes.

También se verificaron seis IDs distintos en dos líneas de compra y que la etiqueta de cantidad de la segunda línea enfoca su propio campo. La página real de cuentas por pagar presenta S/ 83,360.00 pendientes y S/ 11,010.00 registrados. El caso de dos traslados simultáneos con decimales se ensayó con respuestas API simuladas en las funciones reales: 5.125 + 10.000 = 15.125. No se añadieron traslados sólo para la captura.

Captura: `qa-totales-corregidos-2026-11-22.jpg`. La revisión final del navegador no mostró errores JavaScript.

## Límites confirmados

Una incidencia admite una resolución. Si se resuelven 12 de 20, el caso queda resuelto y el saldo conserva 8 pendientes. Una segunda resolución en ese mismo caso devuelve 409; para gestionar las 8 restantes se registra otra incidencia. Esta restricción quedó probada y no se cambió el esquema.

La clave idempotente protege la repetición de una petición; la referencia documental es texto y no constituye por sí sola una identidad única del hecho físico entre incidencias diferentes. No se presenta como deduplicación documental global.

Por lectura de la pantalla de inventario existente, el formulario de conteo ofrece artículos con existencia positiva; no se amplió en esta revisión para mercancía encontrada con saldo de sistema cero. Es un límite pendiente de ese formulario, fuera del circuito de entrega ensayado.

No se realizó carga concurrente, prueba móvil exhaustiva, emisión SUNAT real ni una transacción financiera. Negocio y permisos se probaron dentro de transacciones revertidas, con comparación final de filas y saldos. Los contadores AUTO_INCREMENT pueden tener saltos y las sesiones de prueba pueden dejar registros de acceso.

## Ejecutar de nuevo

Con el entorno iniciado, desde la raíz de `ERP-main`:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\probar-logistica-local.ps1
```

El comando no inicia ni importa la base. Detiene la ejecución ante un fallo y guarda `.runtime/resultado-completo.json`. Node.js es necesario para incluir las 29 comprobaciones de interfaz; si falta, el resumen indica que esa suite fue omitida.
