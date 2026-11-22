-- =====================================================================
-- BORRAR DATOS DE EJEMPLO · ERP Agrícola Pucchún
-- Elimina los maestros de ejemplo que trae sql/bayer_pucchun_erp_v2.sql y TODAS las operaciones hechas
-- con ellos (cotizaciones, pedidos, ventas, cobranzas, caja, compras, stock, despachos, comprobantes
-- y su réplica en el Data Hub). Se identifican por sus códigos (SED-*, ALM-*-01, PRD-00xx, CLI-00xx,
-- PRV-00xx, VEN-00xx) y por la sede/almacén/producto en que se registraron.
--
-- ATENCIÓN: si ya se registraron operaciones REALES usando estas sedes, almacenes o productos de
-- ejemplo, también se borrarían. Ejecutarlo solo mientras la base tenga datos de prueba, y hacer
-- un respaldo antes. No se tocan: los datos base del sistema, el usuario administrador ni la
-- numeración de facturación (SUNAT no permite reutilizar correlativos).
-- =====================================================================
SET NAMES utf8mb4;
START TRANSACTION;

-- ---------- Conjuntos a borrar ----------
CREATE TEMPORARY TABLE ej_suc  AS SELECT id FROM sucursales WHERE codigo IN ('SED-CAN','SED-MAL');
CREATE TEMPORARY TABLE ej_alm  AS SELECT id FROM almacenes WHERE codigo IN ('ALM-CAN-01','ALM-MAL-01');
CREATE TEMPORARY TABLE ej_prod AS SELECT id FROM productos WHERE codigo IN ('PRD-0001','PRD-0002','PRD-0003','PRD-0004','PRD-0005','PRD-0006','PRD-0007','PRD-0008','PRD-0009','PRD-0010','PRD-0011','PRD-0012');
CREATE TEMPORARY TABLE ej_cli  AS SELECT id, tipo_doc, nro_doc FROM clientes WHERE codigo IN ('CLI-0001','CLI-0002','CLI-0003','CLI-0004','CLI-0005','CLI-0006','CLI-0007','CLI-0008');
CREATE TEMPORARY TABLE ej_cot  AS SELECT id FROM cotizaciones WHERE sucursal_id IN (SELECT id FROM ej_suc);
CREATE TEMPORARY TABLE ej_ped  AS SELECT id FROM pedidos WHERE sucursal_id IN (SELECT id FROM ej_suc);
CREATE TEMPORARY TABLE ej_ven  AS SELECT id FROM ventas WHERE sucursal_id IN (SELECT id FROM ej_suc);
CREATE TEMPORARY TABLE ej_cob  AS SELECT id FROM cobranzas WHERE sucursal_id IN (SELECT id FROM ej_suc);
CREATE TEMPORARY TABLE ej_caja AS SELECT id FROM cajas WHERE sucursal_id IN (SELECT id FROM ej_suc);
CREATE TEMPORARY TABLE ej_ses  AS SELECT id FROM caja_sesiones WHERE caja_id IN (SELECT id FROM ej_caja);
CREATE TEMPORARY TABLE ej_cxc  AS SELECT id FROM cuentas_por_cobrar WHERE venta_id IN (SELECT id FROM ej_ven);
-- Comprobantes de las ventas de ejemplo + sus notas de crédito/débito (la nota hereda la venta de su comprobante de referencia)
CREATE TEMPORARY TABLE ej_comp AS SELECT f.id FROM facturacion_comprobantes f
  LEFT JOIN facturacion_comprobantes o ON o.id = f.comprobante_referenciado_id
  WHERE COALESCE(f.venta_erp_id, o.venta_erp_id) IN (SELECT id FROM ej_ven);
CREATE TEMPORARY TABLE ej_oc   AS SELECT id FROM g7_ordenes_compra WHERE almacen_id IN (SELECT id FROM ej_alm);
CREATE TEMPORARY TABLE ej_rec  AS SELECT id FROM g7_recepciones WHERE almacen_id IN (SELECT id FROM ej_alm);
CREATE TEMPORARY TABLE ej_doc  AS SELECT id FROM documentos_cabecera WHERE sucursal_id IN (SELECT id FROM ej_suc);
CREATE TEMPORARY TABLE ej_guia AS SELECT id FROM guias_cabecera WHERE sucursal_id IN (SELECT id FROM ej_suc);
CREATE TEMPORARY TABLE ej_stk  AS SELECT id FROM stock_cabecera WHERE almacen_id IN (SELECT id FROM ej_alm);
CREATE TEMPORARY TABLE ej_tcta AS SELECT id FROM tesoreria_cuentas WHERE sucursal_id IN (SELECT id FROM ej_suc);
CREATE TEMPORARY TABLE ej_serie AS SELECT id FROM facturacion_series WHERE sucursal_id IN (SELECT id FROM ej_suc);

-- ---------- G9 · Data Hub (réplica) ----------
DELETE FROM guias_detalle      WHERE guia_id IN (SELECT id FROM ej_guia);
DELETE FROM guias_cabecera     WHERE id IN (SELECT id FROM ej_guia);
DELETE FROM documentos_detalle WHERE documento_id IN (SELECT id FROM ej_doc);
DELETE FROM documentos_cabecera WHERE id IN (SELECT id FROM ej_doc);
DELETE FROM stock_detalle      WHERE stock_id IN (SELECT id FROM ej_stk);
DELETE FROM stock_cabecera     WHERE id IN (SELECT id FROM ej_stk);

-- ---------- G8 · Tesorería (movimientos de las cuentas de las sedes de ejemplo) ----------
DELETE FROM tesoreria_auditoria   WHERE cuenta_id IN (SELECT id FROM ej_tcta);
DELETE FROM tesoreria_movimientos WHERE cuenta_id IN (SELECT id FROM ej_tcta) AND reversa_de_id IS NOT NULL;
DELETE FROM tesoreria_movimientos WHERE cuenta_id IN (SELECT id FROM ej_tcta);
DELETE FROM tesoreria_cuentas     WHERE id IN (SELECT id FROM ej_tcta);

-- Las cobranzas de ejemplo dejan de apuntar a sus comprobantes antes de borrarlos
UPDATE cobranzas SET comprobante_id = NULL WHERE id IN (SELECT id FROM ej_cob);

-- ---------- G1 · Facturación (comprobantes de ventas de ejemplo; la numeración no se reutiliza) ----------
DELETE FROM facturacion_envio_eventos WHERE envio_id IN (SELECT e.id FROM facturacion_envios e WHERE e.comprobante_id IN (SELECT id FROM ej_comp));
DELETE FROM facturacion_envios        WHERE comprobante_id IN (SELECT id FROM ej_comp);
DELETE FROM facturacion_baja_items    WHERE comprobante_id IN (SELECT id FROM ej_comp);
DELETE FROM facturacion_xml_historial WHERE comprobante_id IN (SELECT id FROM ej_comp);
DELETE FROM facturacion_xml           WHERE comprobante_id IN (SELECT id FROM ej_comp);
DELETE FROM facturacion_eventos       WHERE comprobante_id IN (SELECT id FROM ej_comp);
DELETE FROM facturacion_detalles      WHERE comprobante_id IN (SELECT id FROM ej_comp);
DELETE FROM facturacion_comprobantes  WHERE id IN (SELECT id FROM ej_comp) AND comprobante_referenciado_id IS NOT NULL;
DELETE FROM facturacion_comprobantes  WHERE id IN (SELECT id FROM ej_comp);
-- Receptores copiados de los clientes de ejemplo que ya no tienen comprobantes
DELETE r FROM demo_clientes r JOIN ej_cli c ON c.tipo_doc COLLATE utf8mb4_unicode_ci = r.tipo_documento AND c.nro_doc COLLATE utf8mb4_unicode_ci = r.numero_documento
 WHERE NOT EXISTS (SELECT 1 FROM facturacion_comprobantes f WHERE f.cliente_id = r.id) AND NOT EXISTS (SELECT 1 FROM demo_ventas v WHERE v.cliente_id = r.id);

-- ---------- G8 · Caja, cobranzas, cuentas por cobrar y ventas ----------
DELETE FROM caja_movimientos WHERE sesion_id IN (SELECT id FROM ej_ses) AND reversa_de_id IS NOT NULL;
DELETE FROM caja_movimientos WHERE sesion_id IN (SELECT id FROM ej_ses);
DELETE FROM caja_sesiones    WHERE id IN (SELECT id FROM ej_ses);
DELETE FROM cajas            WHERE id IN (SELECT id FROM ej_caja);
DELETE FROM cobranza_aplicaciones WHERE cobranza_id IN (SELECT id FROM ej_cob);
DELETE FROM cobranzas        WHERE id IN (SELECT id FROM ej_cob);
DELETE FROM cuenta_cuotas    WHERE cuenta_id IN (SELECT id FROM ej_cxc);
DELETE FROM cuentas_por_cobrar WHERE id IN (SELECT id FROM ej_cxc);

-- ---------- G7 · Logística (despachos de las ventas, compras, stock y kardex) ----------
DELETE FROM g7_despachos      WHERE almacen_id IN (SELECT id FROM ej_alm);
DELETE FROM g7_cuentas_pagar  WHERE recepcion_id IN (SELECT id FROM ej_rec);
DELETE FROM g7_recepciones    WHERE id IN (SELECT id FROM ej_rec);
DELETE FROM g7_orden_detalles WHERE orden_id IN (SELECT id FROM ej_oc);
DELETE FROM g7_ordenes_compra WHERE id IN (SELECT id FROM ej_oc);
DELETE FROM g7_conteo_detalles WHERE conteo_id IN (SELECT c.id FROM g7_conteos c WHERE c.almacen_id IN (SELECT id FROM ej_alm));
DELETE FROM g7_conteos        WHERE almacen_id IN (SELECT id FROM ej_alm);
DELETE FROM g7_ajustes        WHERE almacen_id IN (SELECT id FROM ej_alm);
DELETE FROM g7_transferencias WHERE origen_id IN (SELECT id FROM ej_alm);
DELETE FROM g7_transferencias WHERE destino_id IN (SELECT id FROM ej_alm);
DELETE FROM g7_kardex         WHERE almacen_id IN (SELECT id FROM ej_alm);
DELETE FROM g7_saldos         WHERE almacen_id IN (SELECT id FROM ej_alm);

-- ---------- G8 · Documentos comerciales y su auditoría ----------
DELETE FROM comercial_auditoria WHERE entidad = 'venta'      AND entidad_id IN (SELECT id FROM ej_ven);
DELETE FROM comercial_auditoria WHERE entidad = 'pedido'     AND entidad_id IN (SELECT id FROM ej_ped);
DELETE FROM comercial_auditoria WHERE entidad = 'cotizacion' AND entidad_id IN (SELECT id FROM ej_cot);
DELETE FROM comercial_auditoria WHERE entidad = 'cobranza'   AND entidad_id IN (SELECT id FROM ej_cob);
DELETE FROM venta_detalles      WHERE venta_id IN (SELECT id FROM ej_ven);
DELETE FROM ventas              WHERE id IN (SELECT id FROM ej_ven);
DELETE FROM pedido_detalles     WHERE pedido_id IN (SELECT id FROM ej_ped);
DELETE FROM pedidos             WHERE id IN (SELECT id FROM ej_ped);
DELETE FROM cotizacion_detalles WHERE cotizacion_id IN (SELECT id FROM ej_cot);
DELETE FROM cotizaciones        WHERE id IN (SELECT id FROM ej_cot);
DELETE FROM comercial_correlativos WHERE sucursal_id IN (SELECT id FROM ej_suc);
-- Series de facturación asignadas a las sedes de ejemplo (p. ej. F002 de Sede Mala)
DELETE FROM facturacion_series_cambios WHERE serie_id IN (SELECT id FROM ej_serie);
DELETE FROM facturacion_series         WHERE id IN (SELECT id FROM ej_serie);

-- ---------- G5 · Maestros ----------
DELETE FROM homologacion_productos_bayer WHERE producto_id IN (SELECT id FROM ej_prod);
DELETE FROM lista_precios WHERE producto_id IN (SELECT id FROM ej_prod);
DELETE FROM homologacion_clientes        WHERE cliente_id IN (SELECT id FROM ej_cli);
DELETE FROM productos   WHERE id IN (SELECT id FROM ej_prod);
DELETE FROM clientes    WHERE id IN (SELECT id FROM ej_cli);
DELETE FROM proveedores WHERE codigo IN ('PRV-0001','PRV-0002','PRV-0003');
DELETE FROM marcas WHERE nombre IN ('Bayer','AgroSur','NutriCampo','SemillaPac') AND NOT EXISTS (SELECT 1 FROM productos p WHERE p.marca_id = marcas.id);
DELETE FROM categorias_producto WHERE nombre IN ('Insecticidas','Fungicidas','Herbicidas','Fertilizantes','Semillas','Bioestimulantes')
   AND NOT EXISTS (SELECT 1 FROM productos p WHERE p.categoria_id = categorias_producto.id);
DELETE FROM unidades_medida WHERE codigo IN ('UND','KG','LT','GL','SAC')
   AND NOT EXISTS (SELECT 1 FROM productos p WHERE p.unidad_base_id = unidades_medida.id)
   AND NOT EXISTS (SELECT 1 FROM homologacion_productos_bayer h WHERE h.unidad_bayer_id = unidades_medida.id);

-- ---------- G4 · Organización y ubicación ----------
DELETE FROM homologacion_vendedores  WHERE vendedor_id IN (SELECT v.id FROM vendedores v WHERE v.codigo IN ('VEN-0001','VEN-0002','VEN-0003'));
DELETE FROM vendedores WHERE codigo IN ('VEN-0001','VEN-0002','VEN-0003');
DELETE FROM homologacion_almacenes  WHERE almacen_id IN (SELECT id FROM ej_alm);
DELETE FROM almacenes  WHERE id IN (SELECT id FROM ej_alm);
DELETE FROM homologacion_sucursales WHERE sucursal_id IN (SELECT id FROM ej_suc);
DELETE FROM usuario_sucursal        WHERE sucursal_id IN (SELECT id FROM ej_suc);
DELETE FROM sucursales WHERE id IN (SELECT id FROM ej_suc);
DELETE FROM empresas   WHERE ruc = '20601001005' AND NOT EXISTS (SELECT 1 FROM sucursales s WHERE s.empresa_id = empresas.id);
DELETE FROM distritos  WHERE ubigeo IN ('150507','150509','150510','150514','150101','110201')
   AND NOT EXISTS (SELECT 1 FROM sucursales s WHERE s.distrito_id = distritos.id) AND NOT EXISTS (SELECT 1 FROM clientes c WHERE c.distrito_id = distritos.id);
DELETE FROM provincias WHERE nombre = 'Chincha' AND NOT EXISTS (SELECT 1 FROM distritos d WHERE d.provincia_id = provincias.id);
DELETE FROM departamentos WHERE nombre = 'Ica' AND NOT EXISTS (SELECT 1 FROM provincias p WHERE p.departamento_id = departamentos.id);

DROP TEMPORARY TABLE ej_suc, ej_alm, ej_prod, ej_cli, ej_cot, ej_ped, ej_ven, ej_cob, ej_caja, ej_ses, ej_cxc, ej_comp, ej_oc, ej_rec, ej_doc, ej_guia, ej_stk, ej_tcta, ej_serie;
COMMIT;
