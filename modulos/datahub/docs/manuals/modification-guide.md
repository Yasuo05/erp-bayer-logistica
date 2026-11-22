# Guía para el equipo
- Rutas: `routes/web.php`.
- Controladores: `app/Controllers`.
- Reglas de negocio: `app/Services`.
- Persistencia: `app/Repositories`.
- Validación de diccionario Bayer: `app/Validators/BayerDataValidator.php`.
- Vistas: `app/Views`.
- Consultas planas para Bayer: `app/Services/BayerDataService.php`.
- Exportadores: `core/excel.php` del ERP (Excel), `SimplePdfExporter` y `ExportController`.
- Base de datos: la del ERP (`sql/bayer_pucchun_erp_v2.sql` en la raíz).
- Estilos: `public/assets/css/app.css`.
- Gráficos: `public/assets/js/app.js` con Chart.js CDN.

## Para agregar campos
1. Agregar el campo en la base del ERP y volver a generar `sql/bayer_pucchun_erp_v2.sql`.
2. Actualizar repositorio.
3. Actualizar formulario y validador.
4. Si Bayer lo requiere, actualizar `BayerDataService`.
5. Validar exportaciones.
