-- Extensión operativa G7. Ejecutar sobre una copia respaldada del esquema ERP.
-- No crea maestros, ventas ni inventario paralelos; utiliza g7_despachos y g7_saldos.
CREATE TABLE IF NOT EXISTS g7_envios (
 despacho_id BIGINT NOT NULL PRIMARY KEY,
 estado_logistica VARCHAR(24) NOT NULL DEFAULT 'BORRADOR',
 version INT UNSIGNED NOT NULL DEFAULT 1,
 guia_id BIGINT NULL,
 guia_version INT UNSIGNED NULL,
 cantidad_salida DECIMAL(14,3) NOT NULL DEFAULT 0,
 cantidad_aceptada DECIMAL(14,3) NOT NULL DEFAULT 0,
 cantidad_retornada DECIMAL(14,3) NOT NULL DEFAULT 0,
 cantidad_perdida DECIMAL(14,3) NOT NULL DEFAULT 0,
 costo_salida DECIMAL(16,5) NOT NULL DEFAULT 0,
 preparado_en DATETIME NULL,
 cerrado_en DATETIME NULL,
 legado TINYINT NOT NULL DEFAULT 0,
 actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_g7_envio_despacho FOREIGN KEY(despacho_id) REFERENCES g7_despachos(id),
 CONSTRAINT fk_g7_envio_guia FOREIGN KEY(guia_id) REFERENCES guias_cabecera(id),
 CONSTRAINT ck_g7_envio_balance CHECK(cantidad_salida >= 0 AND cantidad_aceptada >= 0 AND cantidad_retornada >= 0 AND cantidad_perdida >= 0 AND cantidad_aceptada + cantidad_retornada + cantidad_perdida <= cantidad_salida),
 CONSTRAINT ck_g7_envio_version CHECK(version >= 1),
 KEY ix_g7_envio_estado(estado_logistica), KEY ix_g7_envio_guia(guia_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS g7_envio_eventos (
 id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
 despacho_id BIGINT NOT NULL, version INT UNSIGNED NOT NULL,
 accion VARCHAR(35) NOT NULL, detalle TEXT NOT NULL,
 usuario_id BIGINT NOT NULL, fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_g7_evento_envio FOREIGN KEY(despacho_id) REFERENCES g7_envios(despacho_id),
 CONSTRAINT fk_g7_evento_usuario FOREIGN KEY(usuario_id) REFERENCES usuarios(id),
 UNIQUE KEY uq_g7_evento_version(despacho_id,version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS g7_envio_intentos (
 id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
 despacho_id BIGINT NOT NULL, numero INT UNSIGNED NOT NULL,
 resultado VARCHAR(20) NOT NULL, cantidad_aceptada DECIMAL(14,3) NOT NULL DEFAULT 0,
 cantidad_rechazada DECIMAL(14,3) NOT NULL DEFAULT 0,
 receptor VARCHAR(150) NOT NULL DEFAULT '', motivo VARCHAR(500) NOT NULL DEFAULT '', evidencia VARCHAR(1000) NOT NULL,
 usuario_id BIGINT NOT NULL, fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_g7_intento_envio FOREIGN KEY(despacho_id) REFERENCES g7_envios(despacho_id),
 CONSTRAINT fk_g7_intento_usuario FOREIGN KEY(usuario_id) REFERENCES usuarios(id),
 CONSTRAINT ck_g7_intento_cantidad CHECK(cantidad_aceptada >= 0 AND cantidad_rechazada >= 0),
 CONSTRAINT ck_g7_intento_resultado CHECK(resultado IN ('ACEPTADA','PARCIAL','RECHAZADA','FALLIDA')),
 UNIQUE KEY uq_g7_intento_numero(despacho_id,numero)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS g7_envio_incidencias (
 id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
 despacho_id BIGINT NOT NULL, tipo VARCHAR(20) NOT NULL,
 estado VARCHAR(12) NOT NULL DEFAULT 'ABIERTA', motivo VARCHAR(500) NOT NULL,
 cantidad DECIMAL(14,3) NOT NULL DEFAULT 0,
 evidencia VARCHAR(1000) NOT NULL, usuario_id BIGINT NOT NULL,
 fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, resuelta_en DATETIME NULL,
 CONSTRAINT fk_g7_incidencia_envio FOREIGN KEY(despacho_id) REFERENCES g7_envios(despacho_id),
 CONSTRAINT fk_g7_incidencia_usuario FOREIGN KEY(usuario_id) REFERENCES usuarios(id),
 CONSTRAINT ck_g7_incidencia_tipo CHECK(tipo IN ('INFORMATIVA','BLOQUEANTE')),
 CONSTRAINT ck_g7_incidencia_estado CHECK(estado IN ('ABIERTA','RESUELTA')),
 CONSTRAINT ck_g7_incidencia_cantidad CHECK(cantidad >= 0),
 KEY ix_g7_incidencia_abierta(despacho_id,estado,tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS g7_envio_resoluciones (
 id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
 despacho_id BIGINT NOT NULL, incidencia_id BIGINT NOT NULL,
 tipo VARCHAR(20) NOT NULL, cantidad DECIMAL(14,3) NOT NULL DEFAULT 0,
 documento VARCHAR(150) NOT NULL DEFAULT '', evidencia VARCHAR(1000) NOT NULL,
 usuario_id BIGINT NOT NULL, fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_g7_resolucion_envio FOREIGN KEY(despacho_id) REFERENCES g7_envios(despacho_id),
 CONSTRAINT fk_g7_resolucion_incidencia FOREIGN KEY(incidencia_id) REFERENCES g7_envio_incidencias(id),
 CONSTRAINT fk_g7_resolucion_usuario FOREIGN KEY(usuario_id) REFERENCES usuarios(id),
 CONSTRAINT ck_g7_resolucion_cantidad CHECK(cantidad >= 0),
 CONSTRAINT ck_g7_resolucion_tipo CHECK(tipo IN ('CIERRE','RETORNO','PERDIDA','DISPOSICION')),
 UNIQUE KEY uq_g7_resolucion_incidencia(incidencia_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS g7_envio_operaciones (
 id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
 despacho_id BIGINT NOT NULL, clave VARCHAR(100) NOT NULL,
 accion VARCHAR(30) NOT NULL, solicitud_hash CHAR(64) NOT NULL,
 respuesta TEXT NOT NULL, usuario_id BIGINT NOT NULL,
 fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_g7_operacion_envio FOREIGN KEY(despacho_id) REFERENCES g7_envios(despacho_id),
 CONSTRAINT fk_g7_operacion_usuario FOREIGN KEY(usuario_id) REFERENCES usuarios(id),
 UNIQUE KEY uq_g7_operacion_clave(despacho_id,clave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Los hechos anteriores conservan su estado; no se inventan intentos ni evidencias históricas.
INSERT IGNORE INTO g7_envios(despacho_id,estado_logistica,cantidad_salida,cantidad_aceptada,costo_salida,cerrado_en,legado)
SELECT d.id,
 CASE d.estado WHEN 'PREPARACION' THEN 'BORRADOR' WHEN 'CANCELADO' THEN 'CANCELADO' WHEN 'ENTREGADO' THEN 'CERRADO' ELSE 'EN_RUTA' END,
 CASE WHEN d.estado IN ('EN_RUTA','ENTREGADO') THEN d.cantidad ELSE 0 END,
 CASE WHEN d.estado='ENTREGADO' THEN d.cantidad ELSE 0 END,
 COALESCE((SELECT k.costo_unitario FROM g7_kardex k WHERE k.documento=d.codigo AND k.tipo='VENTA_DESPACHO' ORDER BY k.id DESC LIMIT 1),0),
 CASE WHEN d.estado IN ('ENTREGADO','CANCELADO') THEN COALESCE(d.fecha_entrega,d.fecha) ELSE NULL END,
 CASE WHEN d.estado IN ('EN_RUTA','ENTREGADO') THEN 1 ELSE 0 END
FROM g7_despachos d;

-- Cada traslado conserva el costo de la salida, incluso si el promedio cambia en origen.
SET @g7_transfer_cost_exists = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='g7_transferencias' AND column_name='costo_unitario');
SET @g7_transfer_cost_ddl = IF(@g7_transfer_cost_exists=0,'ALTER TABLE g7_transferencias ADD COLUMN costo_unitario DECIMAL(16,5) NULL DEFAULT NULL','SELECT 1');
PREPARE g7_transfer_cost_statement FROM @g7_transfer_cost_ddl;
EXECUTE g7_transfer_cost_statement;
DEALLOCATE PREPARE g7_transfer_cost_statement;
UPDATE g7_transferencias t SET t.costo_unitario=(SELECT k.costo_unitario FROM g7_kardex k WHERE k.documento=t.codigo AND k.almacen_id=t.origen_id AND k.tipo='TRASLADO_SALIDA' ORDER BY k.id DESC LIMIT 1) WHERE t.costo_unitario IS NULL;
