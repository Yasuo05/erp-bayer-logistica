# ERP Bayer · Pucchún

ERP en PHP con los módulos de Maestros, Comercial, Logística, Facturación electrónica y Portal Bayer (Data Hub), sobre una sola base MySQL.

## Puesta en marcha (local)

1. **Requisitos:** PHP 8.1 o superior (con pdo_mysql, mbstring, openssl, zip), MySQL 8 y Composer. Laragon o XAMPP ya traen PHP y MySQL.
2. **Base de datos:** importa `sql/bayer_pucchun_erp_v2.sql` únicamente en una base nueva (phpMyAdmin › Importar, o `mysql -u root -p < sql/bayer_pucchun_erp_v2.sql`). Luego ejecuta `php scripts/migrar-logistica.php` para agregar el flujo de entregas. El dump original incluye datos de ejemplo y operaciones de prueba.
   - Para quitar los datos de ejemplo y las operaciones de prueba: `sql/borrar_datos_ejemplo.sql`.
   - Para volver a tenerlos: vuelve a importar `sql/bayer_pucchun_erp_v2.sql`.
   - **Cuidado:** si vuelves a importar el archivo sobre una base que ya usas, la reemplaza por completo.
3. **Configuración:** copia `.env.example` como `.env` en tres lugares: la raíz, `modulos/facturacion/` y `modulos/datahub/`. En cada uno pon tu usuario y contraseña de MySQL. No subas los `.env` a GitHub (ya están en `.gitignore`).
4. **Dependencias:** ejecuta `composer install` dentro de `modulos/facturacion` y dentro de `modulos/datahub`.
5. **Certificado de prueba para firmar comprobantes:** dentro de `modulos/facturacion` ejecuta `php bin/generar_certificado_demo.php`. Crea un certificado autofirmado que solo sirve para pruebas; SUNAT no lo acepta y no se sube a GitHub porque contiene una llave privada.
6. **Servidor:** desde la raíz del proyecto ejecuta `php -S localhost:8080 router.php` y abre http://localhost:8080.

**Usuario de prueba:** `admin@pucchun.com` / `admin123` (rol Administrador). Cámbialo en Administración › Usuarios antes de usar el sistema con datos reales.

## Cómo probarlo (recorrido de punta a punta)

1. **Catálogo › Listas de precios:** hay precios vigentes de ejemplo.
2. **Comercial › Cotizaciones:**
   - Crea una cotización y elige sede y producto: el precio vigente se completa solo.
   - Acéptala y conviértela en **pedido**; confirma el pedido.
3. **Comercial › Ventas:**
   - Convierte el pedido en venta. Si eliges crédito y el monto supera el límite del cliente, el sistema lo rechaza.
   - Confirma la venta: Logística reserva el stock y prepara el despacho.
4. **Facturación › Ventas por facturar:**
   - Emite la factura o boleta, firma el XML y simula el envío.
   - El envío a SUNAT es simulado: la conexión real queda a cargo de la organización.
   - Descarga el PDF.
5. **Comercial › Cobranzas / Caja / Cuentas por cobrar:** registra el cobro y revisa que baje el saldo.
6. **Logística:** revisa el stock por sede, el kardex y los despachos, y haz un traslado entre sedes (se ve «en tránsito» hasta recibirlo).
7. **Portal Bayer:** el panel muestra ventas, stock y la operación interna. Para ver lo que ve Bayer, crea un usuario con rol Bayer y entra por `/modulos/datahub/public/login`: solo ve lo publicado.
8. **Administración:** usuarios, roles y permisos, sedes por usuario, parámetros (IGV) y la auditoría de todo lo anterior.
9. **Exportar:** en cualquier listado, el botón Exportar descarga un Excel con todos los registros filtrados.

Con el selector de sede de la barra superior, cada pantalla muestra solo la operación de esa sede.

## Estructura

| Carpeta | Contenido |
|---|---|
| `core/` | Sesión, permisos, sedes, parámetros, auditoría, menú, exportación a Excel y el marco común de las pantallas |
| `modulos/` | Catálogo, Clientes, Ubicación, Organización, Homologaciones, Comercial, Logística, Facturación, Portal Bayer (`datahub`) y Administración |
| `assets/` | Estilos, scripts e imágenes del ERP |
| `sql/` | Base completa (`bayer_pucchun_erp_v2.sql`) y el script para borrar los datos de ejemplo |
| `scripts/` | Utilidades de desarrollo, por ejemplo para regenerar `assets/css/erp_marco.css` |

## Pendiente para la organización

- Conectar el envío real a SUNAT y usar el certificado digital real de la empresa (`FIRMA_CERTIFICADO` y `FIRMA_CLAVE` en `modulos/facturacion/.env`).
- Cargar los datos reales.
- Activar la API para Bayer con un token propio (`API_ENABLED` y `API_TOKEN` en `modulos/datahub/.env`).

El detalle de la auditoría y de todas las correcciones está en `AUDITORIA_FINAL.md`.
