# ERP Agrícola Pucchún — Módulo de Facturación Electrónica (Grupo 1)

Esqueleto del módulo de facturación del ERP comercial multisede de Agrícola Pucchún (Matt Innova Solution).
Por ahora **no tiene funcionalidad**: define la estructura, el menú y las páginas de cada sección para construir sobre ellas.

## Requisitos

- **Laragon** (Apache y MySQL/MariaDB) o equivalente, con **PHP 8.1 o superior**
- **Composer**
- **Git**
- Extensión **zip** de PHP activa (Laragon: Menú → PHP → Extensions → zip, y reiniciar Apache). Sin ella no funcionan la exportación a Excel ni el CDR simulado y su descarga. La pantalla Parámetros muestra si está activa.

## Instalación en tu PC

1. Colocar el proyecto en `C:\laragon\www\erp-facturacion` (Laragon) o en la carpeta web de tu servidor.
2. En esa carpeta, instalar las dependencias (autoload de clases y Dompdf para los PDF). Repetirlo después de cada `git pull` que cambie `composer.json`:
   ```
   composer install
   ```
3. Copiar `.env.example` como `.env` y ajustar los datos de MySQL si hace falta.
4. Preparar la base: importa `sql/bayer_pucchun_erp_v2.sql` (en la raíz del ERP), que crea la base `bayer_pucchun_erp_v2` completa (tablas y datos de ejemplo), y pon `DB_DATABASE=bayer_pucchun_erp_v2` en el `.env`. No hay que ejecutar ninguna migración.
5. Encender **Apache** y **MySQL** en Laragon y abrir **http://localhost/erp-facturacion** (o `http://erp-facturacion.test` si Laragon creó el virtual host).

La página de inicio muestra al final el **estado del entorno** (PHP y conexión a MySQL). Si creaste `.env` antes de agregar `APP_IGV_RATE` a la plantilla, agrega esa línea manualmente; su valor predeterminado para el demo es `0.18`.

## Demo local del Grupo 1

La base del ERP ya trae datos de demostración para probar el módulo: clientes, productos, ventas aprobadas y series.

- `/emision/ventas-por-facturar`: lista las ventas pendientes y permite registrar manualmente una venta local.
- `/emision/emitir`: permite revisar la venta pendiente y emitir su factura o boleta.
- El formulario de venta inicia con una línea de producto; puedes agregar o quitar líneas según lo que vendas.
- Facturas y boletas quedan numeradas en series `F001` y `B001`.
- Las notas de crédito y débito permiten elegir un comprobante base, registrar motivo y monto del ajuste; usan series `FC01` y `FD01`.
- La pantalla `/configuracion/series` permite crear, activar o desactivar series y controlar su siguiente correlativo sin reutilizar números emitidos.
- Los borradores locales se pueden anular con motivo; el documento y su correlativo se conservan y se agrega el evento a trazabilidad.
- Un comprobante aceptado u observado solo se anula ante SUNAT (comunicación de baja o resumen diario). Antes de pedir la baja, `EstadoComprobanteService::verificarAnulacion()` comprueba el motivo (hasta 100 caracteres), el plazo (`plazo_anulacion_dias` en `config/sunat.php`, 7 días por ahora, a confirmar con la normativa) y que no tenga notas de crédito o débito vigentes.
- El cálculo de ejemplo trata el precio como base imponible y aplica la tasa configurable `APP_IGV_RATE` (por defecto 18%). Los comprobantes quedan en estado `borrador_local`, sin XML, firma, PDF, envío o validez tributaria.

**Confirmación segura de emisión:** la revisión firma con HMAC los datos del cliente, las líneas, el tipo de comprobante y la tasa de IGV, usando el secreto de la sesión. Al confirmar, el servicio vuelve a leer y bloquea la venta con `FOR UPDATE` dentro de la transacción; si cambió cualquier dato revisado, vuelve a pedir una revisión. En ese mismo punto valida el formato del DNI o el dígito verificador del RUC antes de guardar el comprobante.

Los registros de ventas, clientes y productos son datos locales de demostración. No representan un contrato de integración con otros grupos ni deben considerarse reglas tributarias verificadas.

## XML y firma digital

Desde el detalle de un comprobante (`/comprobantes/ver`) se sigue el flujo **Borrador local → XML generado → Firmado**:

1. **Generar XML**: arma el XML UBL 2.1 con la estructura de SUNAT (`Invoice` para factura y boleta, `CreditNote` y `DebitNote` para las notas) usando los datos del emisor (Grupo 4, tabla `demo_empresa`) y del cliente y productos (Grupo 5). El archivo se nombra `RUC-TIPO-SERIE-NUMERO.xml`.
2. **Firmar XML**: agrega la firma XMLDSig (RSA-SHA1, C14N) dentro de `ext:UBLExtensions` con el certificado de `FIRMA_CERTIFICADO` y guarda el hash (DigestValue) que luego usan el PDF y el QR.
3. **Descargar XML**: baja el XML sin firmar o firmado.

Para probar la firma en local, genera un certificado autofirmado (SUNAT no lo acepta; en producción se usa el `.pfx` de la empresa):

```
php bin/generar_certificado_demo.php
```

**Entrega al envío y CDR:** los comprobantes listos para enviar son los que están en estado `firmado`. Se obtiene su XML firmado con:

```php
$archivo = (new \App\Services\DocumentoElectronicoService())->xmlFirmadoParaEnvio($comprobanteId);
// $archivo['nombre'] → 20000000001-01-F001-1.xml (el .zip para SUNAT usa el mismo nombre)
// $archivo['xml']    → contenido del XML firmado
// $archivo['hash']   → DigestValue de la firma
```

Después del envío, el estado pasa de `firmado` a `enviado` (transiciones en `config/estados.php`).

**Datos originales de la emisión:** al emitir, el comprobante guarda una copia del emisor, del cliente y de la tasa de IGV (`datos_emision`). El XML se genera siempre con esa copia, aunque después cambien en sus módulos de origen.

**Validación:** antes de guardar, el XML se vuelve a leer para comprobar su estructura (elementos obligatorios de SUNAT) y que coincida con el comprobante: serie y número, fecha, RUC, cliente, importes y cada línea, y que los importes cuadren (subtotal + IGV = total). También se valida contra el esquema XSD oficial de OASIS UBL 2.1 si está en `storage/xsd/`; se descarga una vez con `php bin/descargar_esquemas_xsd.php` (unos 3 MB, fuera de Git). El XML aún sin firmar se valida con un marcador temporal en el bloque de la firma. Si algo no coincide, el XML no se guarda.

**Firma verificada:** después de firmar se ejecuta automáticamente el verificador (digest y firma) y se vuelve a validar el contenido; solo entonces el estado pasa a `firmado`.

**Corrección tras rechazo:** si SUNAT rechaza el comprobante, se corrige el dato en su módulo de origen y en el detalle del comprobante se usa **Corregir y regenerar XML** (`rechazado → generado_xml`), indicando qué se corrigió. El XML se regenera con el mismo número y se vuelve a firmar y enviar; el XML rechazado queda en `facturacion_xml_historial`. Los importes y las líneas no se modifican: si están mal, corresponde una nota de crédito o débito.

**XML de anulación ante SUNAT:** en el detalle de un comprobante aceptado u observado, la sección **Anulación ante SUNAT** pide el motivo (hasta 100 caracteres) y genera, valida y firma el XML: **comunicación de baja** (`VoidedDocuments`, `RUC-RA-AAAAMMDD-N.xml`) para facturas y sus notas (series F), o **resumen diario** con estado 3 «anulado» (`SummaryDocuments`, `RUC-RC-AAAAMMDD-N.xml`) para boletas y sus notas (series B). Antes aplica las reglas de `EstadoComprobanteService::verificarAnulacion()` (estado, motivo, plazo y notas vigentes). Se guarda en `facturacion_bajas` con estado `firmado` y el comprobante conserva su estado hasta que SUNAT acepte la baja. Para el envío (SUNAT responde con un ticket):

```php
$baja = (new \App\Services\DocumentoAnulacionService())->xmlParaEnvio($bajaId);
// $baja['nombre'] → 20000000001-RA-20261028-1.xml · $baja['xml'] → XML firmado · $baja['comprobantes'] → ids que anula
```

Cuando SUNAT acepte el ticket, cada comprobante pasa a `anulado` y el documento de `facturacion_bajas` cambia de estado (`enviado`, `aceptado` o `rechazado`; uno rechazado permite generar otro).

Código: `app/Services/XmlComprobanteService.php` (XML), `app/Services/XmlAnulacionService.php` y `app/Services/DocumentoAnulacionService.php` (anulación), `app/Services/ValidadorXmlService.php` (validación), `app/Services/FirmaXmlService.php` (firma y verificación), `app/Services/DocumentoElectronicoService.php` (flujo) y `config/sunat.php` (códigos de catálogos). El XML y el hash se guardan en la tabla `facturacion_xml`.

## Envío, respuesta, CDR y trazabilidad

La bandeja `/envio/estado` permite registrar intentos, respuesta, ticket y CDR; `/envio/rechazos` lista observaciones, rechazos y errores; el detalle muestra todos los eventos y reintentos.

El gateway activo (`GatewayDemo`) simula aceptación, observación, rechazo, recepción asíncrona con ticket y fallo transitorio. `GatewayHttp` permite conectar un middleware externo que implemente `POST /documents` y `GET /tickets/{ticket}` con el contrato JSON de `app/Services/GatewayHttp.php`; configura `ENVIO_GATEWAY_URL`, token opcional y timeout. **No es una integración SUNAT lista para producción**: el endpoint y contrato del proveedor/SEE deben acordarse y probarse con sus credenciales. Cada incidencia tiene su acción: una **falla técnica** (error temporal) se reintenta reenviando el mismo XML firmado; un **rechazo** es de contenido y se corrige con «Corregir y regenerar XML»; una **observación** no se reenvía porque el comprobante es válido, se registra su revisión; un **intento interrumpido** (quedó en «enviando» sin respuesta) se recupera con la misma clave de idempotencia, sin crear otro intento. Los intentos se limitan a cinco; la base conserva clave de idempotencia y huella SHA-256 del CDR. La respuesta demo no tiene validez tributaria.

En modo demo, “Simular consulta” resuelve el ticket como aceptado y genera un CDR ZIP de demostración; con un gateway configurado, “Consultar respuesta” consulta el estado real devuelto por el servicio. En el detalle de un rechazo se corrige y regenera el XML; para probar la recuperación de un intento interrumpido ejecuta `php bin/simular_intento_interrumpido.php`.

## PDF, consulta, descarga y trazabilidad

`/consultas/descargas` busca cualquier comprobante por tipo, serie y número (`F001-1`, `B001-00000001`, `B001` o solo el número), cliente (nombre o documento, tal como estaba al emitir), estado y rango de fechas. Los resultados se paginan (5, 10, 20 o 50 por página) y los enlaces conservan los filtros. **Facturas** y **Boletas** reutilizan la misma búsqueda y tabla, fijando el tipo, e incluyen las reglas de emisión. Desde cada resultado:

- **Ver**: abre el detalle del comprobante. El ícono de reloj abre su trazabilidad.
- **PDF**: representación impresa generada con Dompdf; se abre en el navegador y el ícono de descarga la baja. Se nombra igual que el XML (`RUC-TIPO-SERIE-NUMERO.pdf`) y usa los datos del emisor, del cliente y la tasa de IGV guardados al emitir. Lleva la marca de agua "SIN VALIDEZ TRIBUTARIA" mientras no esté aceptado por SUNAT **y también si la aceptación vino del simulador** ("ANULADO" si se anuló). El código QR queda reservado para la integración con SUNAT.
- **XML**: descarga el XML si ya se generó.
- **CDR**: descarga el CDR del último envío que lo tenga (`/envio/cdr`), tras verificar su huella y que corresponda al comprobante; el nombre indica el intento y si es simulado.

Cuando un archivo no está disponible, el motivo se muestra debajo de los botones (por ejemplo, "XML: sin firmar · CDR: aún no enviado").

**Exportar a Excel** descarga un `.xlsx` con los comprobantes de la búsqueda actual (mismos filtros; en Facturas y Boletas, solo ese tipo): número, tipo, fecha, cliente, documento, importes, estado, respuesta, XML y CDR. Se genera sin librerías externas (`app/Services/HojaExcel.php`), con encabezado fijo, filtros y los importes como números.

`/consultas/trazabilidad` une en una línea de tiempo los eventos del comprobante (emisión, XML, firma, estados) y los del envío (solicitudes, respuestas, CDR, reintentos, recuperaciones), ordenados por fecha. Se filtra por serie y número, etapa, fechas y solo observaciones o errores; con `?id=` muestra el historial completo de un comprobante. La vista general y la bandeja de Estado de envíos se paginan igual que los demás listados. Cuando el ERP entregue `$_SESSION['erp_context']` con `usuario` y `sede` (cada uno con `id` y `nombre`), los eventos nuevos guardarán y mostrarán esos datos; los registros históricos o sin ese contexto quedan sin atribución.

Código: `app/Controllers/ConsultaController.php`, `app/Services/ConsultaComprobantesService.php`, `app/Repositories/ConsultaRepository.php` (solo lectura), `app/Core/Paginacion.php`, `app/Services/PdfComprobanteService.php`, `app/Views/consultas/` y `app/Views/pdf/comprobante.php`.

## Parámetros y seguridad

`/configuracion/parametros` muestra, solo para consulta, la tasa de IGV, la moneda, el modo de envío (simulado o proveedor), la vigencia del certificado de firma, la conexión a la base y si su estructura está completa. Los valores se cambian en el `.env`; contraseñas y tokens nunca se muestran.

Todos los formularios POST llevan un código CSRF de la sesión (`app/Core/Csrf.php`, agregado automáticamente al pintar cada vista); `public/index.php` rechaza con 403 cualquier POST sin ese código. La cookie de sesión es `HttpOnly` y `SameSite=Lax`.

Las acciones que no se pueden deshacer (enviar, reintentar, recuperar, corregir el XML, emitir una nota, desactivar una serie) piden confirmación, y los botones se desactivan al enviar para evitar el doble clic. Los errores inesperados se guardan en `storage/logs/errores-AAAA-MM.log` con un código de referencia que el usuario ve en una página de error amable (`app/Core/Registro.php`); Parámetros muestra cuántos hubo en el mes.

Al registrar una venta, el RUC se valida con su dígito verificador (módulo 11 de SUNAT) en la pantalla y en el servidor (`app/Services/DocumentoIdentidad.php`).

## Pruebas automáticas

```bash
composer test
```

O por grupo: `php tests/ejecutar.php unitarias` (reglas y cálculos, sin base de datos), `integracion` (circuito completo: emisión, XML, firma, envío, rechazos, reintentos, intentos interrumpidos, notas y series) o `web` (pantallas, archivos privados y CSRF por HTTP; requiere Apache).

Las de integración crean clientes, ventas y comprobantes marcados como «PRUEBA AUTOMATICA» y los borran al terminar. Los numeran en series reservadas para pruebas (`FZ99`, `BZ99`, `FZ98`, `BZ98`, `FZ97` y `BZ97`), que se crean y se borran en cada ejecución: las series reales no pierden números. Esos nombres no se pueden usar para series reales. Por seguridad, únicamente se ejecutan con `APP_ENV=local` y el envío simulado. Ejecútalas antes de subir cambios.

## Estructura (por capas, infografía 11)

```
erp-facturacion/
├─ public/               Lo único visible desde el navegador
│  ├─ index.php          Punto de entrada: todas las URLs pasan por aquí
│  └─ assets/            CSS e imágenes
├─ app/
│  ├─ Controllers/       Capa de aplicación: recibe la petición y decide qué mostrar
│  ├─ Services/          Capa de negocio: reglas, validaciones y cálculos
│  ├─ Repositories/      Capa de datos: consultas a MySQL
│  ├─ Views/             Capa de presentación: pantallas (layout, inicio, secciones)
│  ├─ Core/              Base del sistema: rutas, vistas, conexión, configuración
│  └─ helpers.php        Funciones de apoyo: e(), url(), asset(), config(), icono()
├─ config/
│  ├─ app.php            Nombre del módulo y sesión de demostración
│  ├─ menu.php           Menú: secciones, opciones y qué hará cada una
│  └─ rutas.php          Qué controlador atiende cada URL
├─ tests/                Pruebas automáticas (unitarias, integración y web)
├─ .env.example          Plantilla de configuración local (el .env no se sube a Git)
└─ composer.json
```

## Cómo dar funcionalidad a una sección

Ejemplo: **Facturas** (`/comprobantes/facturas`).

1. Crear `app/Controllers/FacturasController.php`.
2. En `config/rutas.php`, cambiar `PendienteController` por `FacturasController` en esa ruta.
3. Crear su vista en `app/Views/`.
4. Poner las reglas en un servicio (`app/Services`) y las consultas SQL en un repositorio (`app/Repositories`).
5. Trabajar con las tablas que ya existen en la base unificada del ERP: no se crean tablas ni columnas. Si hace falta cambiar la estructura o los datos base, se hace en la base del ERP y se vuelve a generar `sql/bayer_pucchun_erp_v2.sql`.

## Alcance del Grupo 1

- **Sí:** comprobantes (factura, boleta, notas de crédito y débito), series y correlativos, estados, procesamiento electrónico (XML, firma, envío, respuesta, CDR), PDF, consulta y descarga, control de errores y trazabilidad.
- **No** (otros grupos): usuarios, roles, sedes y login (**Grupo 4**); clientes y productos (**Grupo 5**); ventas, cobranza y caja (**Grupo 8**); reportes y dashboards (**Grupo 9**). Este módulo **consume** esos datos, no los administra.
