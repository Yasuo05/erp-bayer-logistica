# Logística de Pedro y Alisson sobre ERP-main

La base recibida se extrajo de `C:\Users\USER\Downloads\ERP-main.zip`. El ZIP original y el desarrollo anterior se conservan. Esta carpeta contiene la integración nueva: usa las ventas, despachos, reservas, saldos y guías de la base recibida.

La revisión del 21 de noviembre integra Logística con los componentes de Catálogo y Comercial: encabezado único, colores y tipografía compartidos, barra de búsqueda, filtros de estado, tablas y formularios del ERP. Compras, Inventarios y Logística tienen menús separados y páginas propias; cada expediente de envío también tiene su URL. Los estilos propios quedan limitados al módulo. Las capturas y el registro de verificación están en `docs/logistica/`.

## Abrir en esta computadora

Desde PowerShell en esta carpeta:

Compatible con **Windows PowerShell 5.1**, el terminal incluido en Windows; no requiere instalar `pwsh`.

```powershell
.\scripts\iniciar-logistica-local.ps1
```

Si la política de ejecución impide abrir el script, utiliza:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\iniciar-logistica-local.ps1
```

Abre `http://127.0.0.1:8097/modulos/logistica/envios.php`.
Cuenta de ejemplo incluida en el ZIP: **admin@pucchun.com / admin123**.

El inicio usa los binarios de XAMPP y una instancia MariaDB propia en el puerto **43327**, con datos en `.runtime/mariadb`. Verifica la ubicación de la instancia antes de importar. No utiliza las bases del XAMPP habitual. El primer inicio importa la base nueva y aplica la migración; los siguientes conservan los datos. El script adapta dos expresiones del dump MySQL 8 para MariaDB 10.4 en una copia local, conservando el SQL fuente.

Para detenerlo y conservar los datos:

```powershell
.\scripts\detener-logistica-local.ps1
```

## Recorrido

El menú principal del ERP organiza las páginas así:

| Menú | Páginas |
| --- | --- |
| Compras | Órdenes de compra, Recepciones, Cuentas por pagar. |
| Inventarios | Stock por sedes, Movimientos y Kardex, Inventario físico y ajustes. |
| Logística | Envíos y entregas, Traslados entre sedes, Panel de control, Auditoría de operaciones. |

Los enlaces abren documentos independientes: se pueden recargar, abrir en otra pestaña o guardar. Un expediente usa `envio.php?id=...`; Stock enlaza a `kardex.php?producto=...`. Las direcciones antiguas con fragmentos se redirigen a la página correspondiente.

1. Confirma una venta en **Comercial → Ventas**. La reserva y los envíos nacen en la misma transacción.
2. En **Logística → Envíos**, abre el expediente y completa destino y transporte. Marca la mercadería preparada.
3. Asocia una guía válida compatible o emite una guía operativa desde el expediente. Los productos, cliente, sede y cantidades se obtienen de la venta. La guía operativa no acredita emisión electrónica ante SUNAT.
4. Confirma la salida física: descuenta existencias y libera la reserva una sola vez.
5. Registra entregas completas, aceptaciones de una parte, rechazos o visitas fallidas. Cada intento conserva receptor, cantidades y evidencia.
6. Registra incidencias y, si corresponde, confirma el retorno físico con su entrada en Kardex o documenta pérdida/disposición. El rechazo no ingresa stock por sí solo.
7. Cierra cuando no queden cantidades pendientes ni incidencias bloqueantes abiertas. Una cancelación anterior a la salida libera la reserva.

El expediente conserva historial y versiones; las claves idempotentes evitan repetir efectos por reintentos de red. Los permisos se consultan en la matriz persistida del ERP y las operaciones se limitan a la sede activa.

## Repetir las pruebas

Con el entorno local iniciado, ejecuta desde esta carpeta:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\probar-logistica-local.ps1
```

Funciona en Windows PowerShell 5.1 y no necesita `pwsh`. Ejecuta negocio, sedes, HTTP y páginas; si Node.js está disponible, añade las pruebas de interfaz. No inicia servidores ni importa la base. Los cambios de negocio se revierten y se comprueba la restauración; pueden quedar saltos de numeración interna y registros de acceso de QA.

La ejecución completa del 22 de noviembre aprobó **449 comprobaciones**: 265 de negocio en 18 escenarios, 7 de sedes, 33 HTTP, 115 de páginas y 29 de interfaz. El informe, las situaciones concretas y los límites están en `docs/logistica/PRUEBAS_Y_SITUACIONES_2026-11-22.md`. El resultado de cada ejecución se guarda en `.runtime/resultado-completo.json`.

## Entrega y responsabilidades

- `docs/logistica/ALCANCE_Y_RESPONSABLES.md`: reparto recuperado y fuentes.
- `docs/logistica/PLAN_DE_ENTREGA.md`: criterios de aceptación y escenarios QA.
- `sql/migrations/001_logistica_operativa.sql`: ampliación conservadora del esquema.
- `tests/logistica_integracion.php`: pruebas de negocio sobre la instancia local aislada; las operaciones de prueba se revierten.
- `tests/logistica_paginas.ps1`: navegación, páginas independientes, autenticación y acceso a expedientes por sede en Windows PowerShell 5.1.

Compras e Inventarios conservan las pantallas de la base recibida y sus responsables. Facturación conserva su configuración de envío simulado: para usar sus pantallas instala las dependencias con `composer install` dentro de `modulos/facturacion`. SUNAT real y datos reales requieren configuración del equipo.
