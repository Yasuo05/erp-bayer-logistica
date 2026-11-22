-- =====================================================================
-- BASE DE DATOS COMPLETA · ERP Bayer-Pucchún (bayer_pucchun_erp_v2)
-- Generada el 15/11/2026 desde la base en funcionamiento.
--
-- Trae TODO lo necesario: no hay que ejecutar ninguna migración.
--   - Estructura completa de todos los módulos: Maestros, Comercial, Logística,
--     Facturación, Portal Bayer y Administración.
--   - Datos base: tipos de documento, ubigeo, roles, permisos y parámetros.
--   - Datos de ejemplo (ficticios) y operaciones de prueba: cotizaciones, ventas,
--     comprobantes, cobranzas, kardex y publicaciones al portal.
--
-- Uso: mysql -u root -p < sql/bayer_pucchun_erp_v2.sql
--      (o phpMyAdmin › Importar). Crea la base bayer_pucchun_erp_v2.
--      Si la base ya existe, sus tablas se reemplazan por completo.
-- Usuario de prueba: admin@pucchun.com / admin123 (cámbialo antes de usar datos reales).
-- Para quitar los datos de ejemplo: sql/borrar_datos_ejemplo.sql
-- =====================================================================

-- MySQL dump 10.13  Distrib 8.0.33, for Win64 (x86_64)
-- MySQL dump 10.13  Distrib 8.0.33, for Win64 (x86_64)
--
-- Host: 127.0.0.1    Database: bayer_pucchun_erp_v2
-- ------------------------------------------------------
-- Server version	8.0.33

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Current Database: `bayer_pucchun_erp_v2`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `bayer_pucchun_erp_v2` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;

USE `bayer_pucchun_erp_v2`;

--
-- Table structure for table `almacenes`
--

DROP TABLE IF EXISTS `almacenes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `almacenes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sucursal_id` int NOT NULL,
  `codigo` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `nombre` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `tipo` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `estado` tinyint DEFAULT '1',
  `ciudad` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sucursal_id` (`sucursal_id`),
  CONSTRAINT `almacenes_ibfk_1` FOREIGN KEY (`sucursal_id`) REFERENCES `sucursales` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `almacenes`
--

LOCK TABLES `almacenes` WRITE;
/*!40000 ALTER TABLE `almacenes` DISABLE KEYS */;
INSERT INTO `almacenes` VALUES (1,1,'ALM-CAN-01','Almacén Central Cañete','principal',1,'Cañete'),(2,2,'ALM-MAL-01','Almacén Mala','principal',1,'Mala');
/*!40000 ALTER TABLE `almacenes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `api_tokens`
--

DROP TABLE IF EXISTS `api_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `api_tokens` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `usuario_id` bigint NOT NULL,
  `token_hash` char(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` datetime NOT NULL,
  `last_used_at` datetime DEFAULT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `ip_created` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent_created` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token_hash` (`token_hash`),
  KEY `ix_api_tokens_user_active` (`usuario_id`,`revoked_at`,`expires_at`),
  KEY `ix_api_tokens_expires` (`expires_at`),
  CONSTRAINT `fk_api_tokens_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `api_tokens`
--

LOCK TABLES `api_tokens` WRITE;
/*!40000 ALTER TABLE `api_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `api_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `auditoria_acciones`
--

DROP TABLE IF EXISTS `auditoria_acciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `auditoria_acciones` (
  `user_agent` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id` bigint NOT NULL AUTO_INCREMENT,
  `usuario_id` bigint NOT NULL,
  `modulo` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `accion` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `entidad_id` bigint DEFAULT NULL,
  `fecha_hora` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `metadata_json` json DEFAULT NULL,
  `resultado` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `correlation_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `ix_auditoria_entidad` (`modulo`,`entidad_id`,`fecha_hora`),
  KEY `ix_auditoria_correlation` (`correlation_id`),
  CONSTRAINT `auditoria_acciones_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `auditoria_acciones`
--

LOCK TABLES `auditoria_acciones` WRITE;
/*!40000 ALTER TABLE `auditoria_acciones` DISABLE KEYS */;
INSERT INTO `auditoria_acciones` VALUES ('Navegador web',1,1,'plataforma','inicio_sesion',1,'2026-11-13 20:58:34','::1','{\"sede_id\": null, \"usuario\": \"Administrador\"}','ok',NULL),('Navegador web',2,1,'maestros','crear_usuario_sucursal',1,'2026-11-13 20:59:05','::1','{\"tabla\": \"usuario_sucursal\", \"sede_id\": null, \"usuario\": \"Administrador\", \"valores\": {\"usuario_id\": \"4\", \"sucursal_id\": \"2\"}}','ok',NULL),('Navegador web',4,1,'tesoreria','crear_cuenta',1,'2026-11-13 20:59:29','::1','{\"codigo\": \"CTA-CAN-01\", \"sede_id\": null, \"usuario\": \"Administrador\", \"sucursal_id\": 1, \"saldo_inicial\": 20000}','ok',NULL),('Navegador web',5,1,'tesoreria','crear_cuenta',2,'2026-11-13 20:59:29','::1','{\"codigo\": \"CTA-MAL-01\", \"sede_id\": null, \"usuario\": \"Administrador\", \"sucursal_id\": 2, \"saldo_inicial\": 5000}','ok',NULL),('Navegador web',6,1,'maestros','editar_productos',14,'2026-11-13 20:59:59','::1','{\"tabla\": \"productos\", \"cambios\": {\"precio_venta\": [\"65.00\", \"67.00\"]}, \"sede_id\": null, \"usuario\": \"Administrador\"}','ok',NULL),('Navegador web',7,1,'maestros','editar_productos',14,'2026-11-13 20:59:59','::1','{\"tabla\": \"productos\", \"cambios\": {\"precio_venta\": [\"67.00\", \"65.00\"]}, \"sede_id\": null, \"usuario\": \"Administrador\"}','ok',NULL),('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36',9,1,'plataforma','inicio_sesion',1,'2026-11-14 15:36:35','::1','{\"sede_id\": null, \"usuario\": \"Administrador\"}','ok',NULL),('Navegador web',10,1,'plataforma','inicio_sesion',1,'2026-11-14 15:40:54','::1','{\"sede_id\": null, \"usuario\": \"Administrador\"}','ok',NULL),('Navegador web',11,1,'plataforma','inicio_sesion',1,'2026-11-14 15:48:17','::1','{\"sede_id\": null, \"usuario\": \"Administrador\"}','ok',NULL),('Navegador web',14,1,'plataforma','inicio_sesion',1,'2026-11-14 16:00:26','::1','{\"sede_id\": null, \"usuario\": \"Administrador\"}','ok',NULL),('Navegador web',15,1,'seguridad','crear_usuario',8,'2026-11-14 16:00:26','::1','{\"rol\": \"Digitador\", \"email\": \"prueba.sede@pucchun.test\", \"estado\": 1, \"sede_id\": null, \"usuario\": \"Administrador\", \"cambio_clave\": true}','ok',NULL),('Navegador web',16,1,'maestros','crear_usuario_sucursal',3,'2026-11-14 16:00:26','::1','{\"tabla\": \"usuario_sucursal\", \"sede_id\": null, \"usuario\": \"Administrador\", \"valores\": {\"usuario_id\": \"8\", \"sucursal_id\": \"2\"}}','ok',NULL),('Navegador web',18,1,'seguridad','editar_permisos',NULL,'2026-11-14 16:01:01','::1','{\"sede_id\": null, \"usuario\": \"Administrador\", \"quitados\": [\"2-2\"], \"agregados\": []}','ok',NULL),('Navegador web',19,1,'seguridad','editar_permisos',NULL,'2026-11-14 16:01:10','::1','{\"sede_id\": null, \"usuario\": \"Administrador\", \"quitados\": [], \"agregados\": [\"2-2\"]}','ok',NULL),('Navegador web',20,1,'configuracion','editar_parametro',NULL,'2026-11-14 16:01:10','::1','{\"antes\": \"18\", \"clave\": \"igv_tasa\", \"despues\": \"10\", \"sede_id\": null, \"usuario\": \"Administrador\"}','ok',NULL),('Navegador web',21,1,'configuracion','editar_parametro',NULL,'2026-11-14 16:01:10','::1','{\"antes\": \"10\", \"clave\": \"igv_tasa\", \"despues\": \"18\", \"sede_id\": null, \"usuario\": \"Administrador\"}','ok',NULL),(NULL,22,1,'exportaciones','json',NULL,'2026-11-14 20:15:43',NULL,NULL,NULL,NULL),(NULL,23,1,'exportaciones','json',NULL,'2026-11-14 21:27:10',NULL,NULL,NULL,NULL),(NULL,24,1,'exportaciones','xlsx',NULL,'2026-11-14 21:27:10',NULL,NULL,NULL,NULL),('Navegador web',25,1,'plataforma','inicio_sesion',1,'2026-11-14 23:47:37','::1','{\"sede_id\": null, \"usuario\": \"Administrador\"}','ok',NULL),(NULL,26,1,'exportaciones','xlsx',NULL,'2026-11-14 23:47:37',NULL,NULL,NULL,NULL),('Navegador web',27,1,'plataforma','inicio_sesion',1,'2026-11-14 23:53:07','::1','{\"sede_id\": null, \"usuario\": \"Administrador\"}','ok',NULL),(NULL,28,1,'exportaciones','xlsx',NULL,'2026-11-14 23:54:30',NULL,NULL,NULL,NULL),(NULL,29,1,'exportaciones','xlsx',NULL,'2026-11-14 23:54:30',NULL,NULL,NULL,NULL),(NULL,30,1,'exportaciones','xlsx',NULL,'2026-11-14 23:54:30',NULL,NULL,NULL,NULL),('Navegador web',31,1,'plataforma','inicio_sesion',1,'2026-11-15 15:20:35','::1','{\"sede_id\": null, \"usuario\": \"Administrador\"}','ok',NULL),(NULL,32,1,'exportaciones','xlsx',NULL,'2026-11-15 15:20:36',NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `auditoria_acciones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bitacora_acceso`
--

DROP TABLE IF EXISTS `bitacora_acceso`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bitacora_acceso` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `usuario_id` bigint DEFAULT NULL,
  `ip` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `accion` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_hora` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_bitacora_usuario` (`usuario_id`),
  CONSTRAINT `fk_bitacora_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bitacora_acceso`
--

LOCK TABLES `bitacora_acceso` WRITE;
/*!40000 ALTER TABLE `bitacora_acceso` DISABLE KEYS */;
/*!40000 ALTER TABLE `bitacora_acceso` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `caja_movimientos`
--

DROP TABLE IF EXISTS `caja_movimientos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `caja_movimientos` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `sesion_id` bigint NOT NULL,
  `tipo` enum('ingreso','egreso') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `fecha` datetime NOT NULL,
  `importe` decimal(18,2) NOT NULL,
  `concepto` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `cobranza_id` bigint DEFAULT NULL,
  `g7_cuenta_pagar_id` bigint DEFAULT NULL,
  `reversa_de_id` bigint DEFAULT NULL,
  `creado_por` bigint NOT NULL,
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_movimiento_cobranza` (`cobranza_id`),
  UNIQUE KEY `uq_movimiento_reversa` (`reversa_de_id`),
  KEY `idx_movimiento_sesion_fecha` (`sesion_id`,`fecha`),
  KEY `fk_movimiento_autor` (`creado_por`),
  KEY `idx_movimiento_cuenta_pagar` (`g7_cuenta_pagar_id`),
  CONSTRAINT `fk_movimiento_autor` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_movimiento_cobranza` FOREIGN KEY (`cobranza_id`) REFERENCES `cobranzas` (`id`),
  CONSTRAINT `fk_movimiento_cuenta_pagar` FOREIGN KEY (`g7_cuenta_pagar_id`) REFERENCES `g7_cuentas_pagar` (`id`),
  CONSTRAINT `fk_movimiento_reversa` FOREIGN KEY (`reversa_de_id`) REFERENCES `caja_movimientos` (`id`),
  CONSTRAINT `fk_movimiento_sesion` FOREIGN KEY (`sesion_id`) REFERENCES `caja_sesiones` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `caja_movimientos`
--

LOCK TABLES `caja_movimientos` WRITE;
/*!40000 ALTER TABLE `caja_movimientos` DISABLE KEYS */;
INSERT INTO `caja_movimientos` VALUES (1,1,'ingreso','2026-11-13 19:11:55',221.84,'Cobranza COB001-00000003',3,NULL,NULL,1,'2026-11-13 19:11:55','2026-11-13 19:11:55'),(2,2,'egreso','2026-11-13 19:11:55',150.00,'Pago CP-2026-0018 a FERTILIZANTES Y SEMILLAS DEL PACÍFICO S.A.',NULL,18,NULL,1,'2026-11-13 19:11:55','2026-11-13 19:11:55'),(3,1,'ingreso','2026-11-14 15:48:33',1970.60,'Cobranza COB001-00000004',5,NULL,NULL,1,'2026-11-14 15:48:33','2026-11-14 15:48:33');
/*!40000 ALTER TABLE `caja_movimientos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `caja_sesiones`
--

DROP TABLE IF EXISTS `caja_sesiones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `caja_sesiones` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `caja_id` bigint NOT NULL,
  `responsable_id` bigint NOT NULL,
  `apertura_en` datetime NOT NULL,
  `fondo_inicial` decimal(18,2) NOT NULL,
  `estado` enum('abierta','cerrada') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'abierta',
  `cierre_en` datetime DEFAULT NULL,
  `cerrado_por` bigint DEFAULT NULL,
  `saldo_esperado` decimal(18,2) DEFAULT NULL,
  `efectivo_contado` decimal(18,2) DEFAULT NULL,
  `diferencia` decimal(18,2) GENERATED ALWAYS AS ((`efectivo_contado` - `saldo_esperado`)) STORED,
  `observaciones` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `caja_abierta_id` bigint GENERATED ALWAYS AS ((case when (`estado` = _utf8mb4'abierta') then `caja_id` else NULL end)) STORED,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_caja_sesion_abierta` (`caja_abierta_id`),
  KEY `idx_sesion_caja_fecha` (`caja_id`,`apertura_en`),
  KEY `fk_sesion_responsable` (`responsable_id`),
  KEY `fk_sesion_cerrador` (`cerrado_por`),
  CONSTRAINT `fk_sesion_caja` FOREIGN KEY (`caja_id`) REFERENCES `cajas` (`id`),
  CONSTRAINT `fk_sesion_cerrador` FOREIGN KEY (`cerrado_por`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_sesion_responsable` FOREIGN KEY (`responsable_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `caja_sesiones`
--

LOCK TABLES `caja_sesiones` WRITE;
/*!40000 ALTER TABLE `caja_sesiones` DISABLE KEYS */;
INSERT INTO `caja_sesiones` (`id`, `caja_id`, `responsable_id`, `apertura_en`, `fondo_inicial`, `estado`, `cierre_en`, `cerrado_por`, `saldo_esperado`, `efectivo_contado`, `observaciones`) VALUES (1,1,1,'2026-11-13 19:11:09',300.00,'abierta',NULL,NULL,NULL,NULL,NULL),(2,2,1,'2026-11-13 19:11:09',200.00,'abierta',NULL,NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `caja_sesiones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cajas`
--

DROP TABLE IF EXISTS `cajas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cajas` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `sucursal_id` int NOT NULL,
  `codigo` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `nombre` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `moneda` char(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'PEN',
  `estado` enum('activa','inactiva') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'activa',
  `creado_por` bigint NOT NULL,
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_caja_codigo` (`sucursal_id`,`codigo`),
  KEY `fk_caja_autor` (`creado_por`),
  CONSTRAINT `fk_caja_autor` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_caja_sucursal` FOREIGN KEY (`sucursal_id`) REFERENCES `sucursales` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cajas`
--

LOCK TABLES `cajas` WRITE;
/*!40000 ALTER TABLE `cajas` DISABLE KEYS */;
INSERT INTO `cajas` VALUES (1,1,'CAJ-CAN-01','Caja principal Cañete','PEN','activa',1,'2026-11-13 19:10:55','2026-11-13 19:10:55'),(2,2,'CAJ-MAL-01','Caja principal Mala','PEN','activa',1,'2026-11-13 19:10:55','2026-11-13 19:10:55');
/*!40000 ALTER TABLE `cajas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `categorias_producto`
--

DROP TABLE IF EXISTS `categorias_producto`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `categorias_producto` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `descripcion` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_categoria_nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categorias_producto`
--

LOCK TABLES `categorias_producto` WRITE;
/*!40000 ALTER TABLE `categorias_producto` DISABLE KEYS */;
INSERT INTO `categorias_producto` VALUES (1,'Analgésicos','Alivio del dolor y fiebre'),(2,'Vitaminas','Suplementos vitamínicos'),(3,'Insecticidas','Control de plagas de insectos'),(4,'Fungicidas','Control de enfermedades fúngicas'),(5,'Herbicidas','Control de malezas'),(6,'Fertilizantes','Nutrición de cultivos'),(7,'Semillas','Semillas certificadas'),(8,'Bioestimulantes','Estimulación del desarrollo de cultivos');
/*!40000 ALTER TABLE `categorias_producto` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `clientes`
--

DROP TABLE IF EXISTS `clientes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `clientes` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `codigo` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `tipo_doc` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `nro_doc` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `razon_social` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `departamento_id` int DEFAULT NULL,
  `provincia_id` int DEFAULT NULL,
  `distrito_id` int DEFAULT NULL,
  `tipo_cliente` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `sector` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `contacto` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `correo` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `telefono` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `vendedor_id` bigint DEFAULT NULL,
  `limite_credito` decimal(14,2) DEFAULT NULL,
  `dias_credito` int DEFAULT NULL,
  `estado` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'ACTIVO',
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `departamento_id` (`departamento_id`),
  KEY `provincia_id` (`provincia_id`),
  KEY `distrito_id` (`distrito_id`),
  KEY `idx_clientes_codigo` (`codigo`),
  KEY `fk_clientes_vendedor` (`vendedor_id`),
  CONSTRAINT `clientes_ibfk_1` FOREIGN KEY (`departamento_id`) REFERENCES `departamentos` (`id`),
  CONSTRAINT `clientes_ibfk_2` FOREIGN KEY (`provincia_id`) REFERENCES `provincias` (`id`),
  CONSTRAINT `clientes_ibfk_3` FOREIGN KEY (`distrito_id`) REFERENCES `distritos` (`id`),
  CONSTRAINT `fk_clientes_vendedor` FOREIGN KEY (`vendedor_id`) REFERENCES `vendedores` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clientes`
--

LOCK TABLES `clientes` WRITE;
/*!40000 ALTER TABLE `clientes` DISABLE KEYS */;
INSERT INTO `clientes` VALUES (1,'CLI-0001','RUC','20602111220','AGROEXPORTADORA VALLE SUR S.A.C.',1,1,3,'Empresa','Agroexportación','Área de compras',NULL,NULL,1,50000.00,30,'ACTIVO',NULL),(2,'CLI-0002','RUC','20603222335','FUNDO LAS PALMERAS S.A.C.',1,1,6,'Fundo','Frutales','Administración',NULL,NULL,1,20000.00,30,'ACTIVO',NULL),(3,'CLI-0003','RUC','20604333441','COOPERATIVA AGRARIA SAN JOSÉ DE CAÑETE',1,1,1,'Cooperativa','Algodón y maíz','Gerencia',NULL,NULL,3,30000.00,45,'ACTIVO',NULL),(4,'CLI-0004','RUC','20605444556','INVERSIONES AGRÍCOLAS MALA E.I.R.L.',1,1,4,'Empresa','Vid y palta','Logística',NULL,NULL,2,15000.00,30,'ACTIVO',NULL),(5,'CLI-0005','RUC','20606555661','AGRÍCOLA CHINCHA DEL SUR S.A.C.',2,3,8,'Empresa','Agroexportación','Compras',NULL,NULL,2,25000.00,45,'ACTIVO',NULL),(6,'CLI-0006','RUC','10445566778','RAMÍREZ QUISPE JUAN CARLOS',1,1,5,'Agricultor','Hortalizas',NULL,NULL,NULL,1,5000.00,15,'ACTIVO',NULL),(7,'CLI-0007','DNI','45123678','HUAMÁN TORRES MARÍA ELENA',1,1,3,'Agricultor','Maíz',NULL,NULL,NULL,1,NULL,NULL,'ACTIVO',NULL),(8,'CLI-0008','DNI','41987654','ROJAS MEDINA PEDRO PABLO',1,1,4,'Agricultor','Frutales',NULL,NULL,NULL,2,3000.00,15,'ACTIVO',NULL);
/*!40000 ALTER TABLE `clientes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cobranza_aplicaciones`
--

DROP TABLE IF EXISTS `cobranza_aplicaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cobranza_aplicaciones` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `cobranza_id` bigint NOT NULL,
  `cuota_id` bigint DEFAULT NULL,
  `venta_contado_id` bigint DEFAULT NULL,
  `importe` decimal(18,2) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_aplicacion_cuota` (`cobranza_id`,`cuota_id`),
  UNIQUE KEY `uq_aplicacion_contado` (`cobranza_id`,`venta_contado_id`),
  KEY `fk_aplicacion_cuota` (`cuota_id`),
  KEY `fk_aplicacion_contado` (`venta_contado_id`),
  CONSTRAINT `fk_aplicacion_cobranza` FOREIGN KEY (`cobranza_id`) REFERENCES `cobranzas` (`id`),
  CONSTRAINT `fk_aplicacion_contado` FOREIGN KEY (`venta_contado_id`) REFERENCES `ventas` (`id`),
  CONSTRAINT `fk_aplicacion_cuota` FOREIGN KEY (`cuota_id`) REFERENCES `cuenta_cuotas` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cobranza_aplicaciones`
--

LOCK TABLES `cobranza_aplicaciones` WRITE;
/*!40000 ALTER TABLE `cobranza_aplicaciones` DISABLE KEYS */;
INSERT INTO `cobranza_aplicaciones` VALUES (1,1,NULL,1,5593.20),(2,2,1,NULL,1000.00),(3,3,NULL,3,221.84),(4,4,2,NULL,500.00),(5,5,NULL,6,1970.60),(6,6,3,NULL,200.00);
/*!40000 ALTER TABLE `cobranza_aplicaciones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cobranzas`
--

DROP TABLE IF EXISTS `cobranzas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cobranzas` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `sucursal_id` int NOT NULL,
  `cliente_id` bigint NOT NULL,
  `comprobante_id` bigint unsigned DEFAULT NULL,
  `numero` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `fecha` datetime NOT NULL,
  `moneda` char(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'PEN',
  `importe` decimal(18,2) NOT NULL,
  `medio_pago` enum('efectivo','transferencia','deposito','tarjeta') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `clave_operacion` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `referencia` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `estado` enum('borrador','confirmada','anulada') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'borrador',
  `anulado_por` bigint DEFAULT NULL,
  `anulado_en` datetime DEFAULT NULL,
  `motivo_anulacion` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `creado_por` bigint NOT NULL,
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cobranza_numero` (`sucursal_id`,`numero`),
  UNIQUE KEY `uq_cobranza_operacion` (`clave_operacion`),
  KEY `idx_cobranza_cliente_fecha` (`cliente_id`,`fecha`),
  KEY `idx_cobranza_estado_fecha` (`estado`,`fecha`),
  KEY `fk_cobranza_autor` (`creado_por`),
  KEY `fk_cobranza_anulador` (`anulado_por`),
  KEY `fk_cobranza_comprobante` (`comprobante_id`),
  CONSTRAINT `fk_cobranza_anulador` FOREIGN KEY (`anulado_por`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_cobranza_autor` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_cobranza_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`),
  CONSTRAINT `fk_cobranza_comprobante` FOREIGN KEY (`comprobante_id`) REFERENCES `facturacion_comprobantes` (`id`),
  CONSTRAINT `fk_cobranza_sucursal` FOREIGN KEY (`sucursal_id`) REFERENCES `sucursales` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cobranzas`
--

LOCK TABLES `cobranzas` WRITE;
/*!40000 ALTER TABLE `cobranzas` DISABLE KEYS */;
INSERT INTO `cobranzas` VALUES (1,1,1,4,'COB001-00000001','2026-11-13 19:11:55','PEN',5593.20,'transferencia','437df36f-088f-48c4-aa9a-16673e380c46','OP 004512','confirmada',NULL,NULL,NULL,1,'2026-11-13 19:11:55','2026-11-13 20:50:48'),(2,1,2,5,'COB001-00000002','2026-11-13 19:11:55','PEN',1000.00,'deposito','ecf10556-ae47-a519-f5e1-e2ca4d1857e5','DEP 778120','confirmada',NULL,NULL,NULL,1,'2026-11-13 19:11:55','2026-11-13 20:50:48'),(3,1,7,6,'COB001-00000003','2026-11-13 19:11:55','PEN',221.84,'efectivo','6673a756-016e-3fae-c543-d6e335f47d9c',NULL,'confirmada',NULL,NULL,NULL,1,'2026-11-13 19:11:55','2026-11-13 20:50:48'),(4,2,5,11,'COB002-00000001','2026-11-13 20:59:29','PEN',500.00,'transferencia','a5f3bdd3-e53a-236e-2ed2-0b1b4eef52fd','OP 552190','anulada',1,'2026-11-13 20:59:37','Transferencia rechazada por el banco',1,'2026-11-13 20:59:29','2026-11-13 20:59:37'),(5,1,6,12,'COB001-00000004','2026-11-14 15:48:33','PEN',1970.60,'efectivo','191bd06e-f298-99d2-e318-65b2aefae509',NULL,'confirmada',NULL,NULL,NULL,1,'2026-11-14 15:48:33','2026-11-14 15:48:33'),(6,2,8,13,'COB002-00000002','2026-11-14 15:48:49','PEN',200.00,'deposito','ad3ecc12-c493-ea93-bede-a654682b6feb','DEP 991204','confirmada',NULL,NULL,NULL,1,'2026-11-14 15:48:49','2026-11-14 15:48:49');
/*!40000 ALTER TABLE `cobranzas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `comercial_auditoria`
--

DROP TABLE IF EXISTS `comercial_auditoria`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `comercial_auditoria` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `usuario_id` bigint NOT NULL,
  `fecha` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `entidad` enum('cotizacion','pedido','venta','cuenta_por_cobrar','cobranza','caja','caja_sesion','caja_movimiento') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `entidad_id` bigint NOT NULL,
  `accion` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `estado_anterior` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `estado_nuevo` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `motivo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_auditoria_entidad` (`entidad`,`entidad_id`,`fecha`),
  KEY `idx_auditoria_usuario_fecha` (`usuario_id`,`fecha`),
  CONSTRAINT `fk_auditoria_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=77 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `comercial_auditoria`
--

LOCK TABLES `comercial_auditoria` WRITE;
/*!40000 ALTER TABLE `comercial_auditoria` DISABLE KEYS */;
INSERT INTO `comercial_auditoria` VALUES (1,1,'2026-11-13 19:11:09','cotizacion',1,'creada',NULL,'borrador',NULL),(2,1,'2026-11-13 19:11:09','cotizacion',1,'estado_actualizado','borrador','emitida',NULL),(3,1,'2026-11-13 19:11:10','cotizacion',1,'estado_actualizado','emitida','aceptada',NULL),(4,1,'2026-11-13 19:11:10','pedido',1,'creado',NULL,'borrador',NULL),(5,1,'2026-11-13 19:11:10','pedido',1,'confirmado','borrador','confirmado',NULL),(6,1,'2026-11-13 19:11:10','venta',1,'creado',NULL,'borrador',NULL),(7,1,'2026-11-13 19:11:10','venta',1,'confirmado','borrador','confirmada',NULL),(8,1,'2026-11-13 19:11:10','cotizacion',2,'creada',NULL,'borrador',NULL),(9,1,'2026-11-13 19:11:10','cotizacion',2,'estado_actualizado','borrador','emitida',NULL),(10,1,'2026-11-13 19:11:10','cotizacion',2,'estado_actualizado','emitida','aceptada',NULL),(11,1,'2026-11-13 19:11:10','pedido',2,'creado',NULL,'borrador',NULL),(12,1,'2026-11-13 19:11:10','pedido',2,'confirmado','borrador','confirmado',NULL),(13,1,'2026-11-13 19:11:10','venta',2,'creado',NULL,'borrador',NULL),(14,1,'2026-11-13 19:11:10','venta',2,'confirmado','borrador','confirmada',NULL),(15,1,'2026-11-13 19:11:10','cotizacion',3,'creada',NULL,'borrador',NULL),(16,1,'2026-11-13 19:11:10','cotizacion',3,'estado_actualizado','borrador','emitida',NULL),(17,1,'2026-11-13 19:11:10','cotizacion',3,'estado_actualizado','emitida','aceptada',NULL),(18,1,'2026-11-13 19:11:10','pedido',3,'creado',NULL,'borrador',NULL),(19,1,'2026-11-13 19:11:10','pedido',3,'confirmado','borrador','confirmado',NULL),(20,1,'2026-11-13 19:11:10','venta',3,'creado',NULL,'borrador',NULL),(21,1,'2026-11-13 19:11:10','venta',3,'confirmado','borrador','confirmada',NULL),(22,1,'2026-11-13 19:11:10','cotizacion',4,'creada',NULL,'borrador',NULL),(23,1,'2026-11-13 19:11:11','cotizacion',5,'creada',NULL,'borrador',NULL),(24,1,'2026-11-13 19:11:11','cotizacion',6,'creada',NULL,'borrador',NULL),(25,1,'2026-11-13 19:11:11','cotizacion',4,'estado_actualizado','borrador','emitida',NULL),(26,1,'2026-11-13 19:11:11','cotizacion',7,'creada',NULL,'borrador',NULL),(27,1,'2026-11-13 19:11:30','cotizacion',4,'estado_actualizado','emitida','aceptada',NULL),(28,1,'2026-11-13 19:11:31','pedido',4,'creado',NULL,'borrador',NULL),(29,1,'2026-11-13 19:11:31','pedido',4,'confirmado','borrador','confirmado',NULL),(30,1,'2026-11-13 19:11:31','venta',4,'creado',NULL,'borrador',NULL),(31,1,'2026-11-13 19:11:31','venta',4,'confirmado','borrador','confirmada',NULL),(32,1,'2026-11-13 19:11:31','cotizacion',5,'estado_actualizado','borrador','emitida',NULL),(33,1,'2026-11-13 19:11:31','cotizacion',5,'estado_actualizado','emitida','aceptada',NULL),(34,1,'2026-11-13 19:11:31','pedido',5,'creado',NULL,'borrador',NULL),(35,1,'2026-11-13 19:11:31','pedido',5,'confirmado','borrador','confirmado',NULL),(36,1,'2026-11-13 19:11:31','venta',5,'creado',NULL,'borrador',NULL),(37,1,'2026-11-13 19:11:31','venta',5,'confirmado','borrador','confirmada',NULL),(38,1,'2026-11-13 19:11:31','cotizacion',6,'estado_actualizado','borrador','emitida',NULL),(39,1,'2026-11-13 19:11:31','cotizacion',7,'estado_actualizado','borrador','emitida',NULL),(40,1,'2026-11-13 19:11:31','cotizacion',7,'estado_actualizado','emitida','aceptada',NULL),(41,1,'2026-11-13 19:11:31','pedido',6,'creado',NULL,'borrador',NULL),(42,1,'2026-11-13 19:11:55','cobranza',1,'creada',NULL,'borrador',NULL),(43,1,'2026-11-13 19:11:55','cobranza',1,'confirmada','borrador','confirmada',NULL),(44,1,'2026-11-13 19:11:55','cobranza',2,'creada',NULL,'borrador',NULL),(45,1,'2026-11-13 19:11:55','cobranza',2,'confirmada','borrador','confirmada',NULL),(46,1,'2026-11-13 19:11:55','cobranza',3,'creada',NULL,'borrador',NULL),(47,1,'2026-11-13 19:11:55','cobranza',3,'confirmada','borrador','confirmada',NULL),(48,1,'2026-11-13 19:27:42','cotizacion',8,'creada',NULL,'borrador',NULL),(49,1,'2026-11-13 19:27:42','cotizacion',8,'estado_actualizado','borrador','emitida',NULL),(50,1,'2026-11-13 19:27:42','cotizacion',8,'estado_actualizado','emitida','aceptada',NULL),(51,1,'2026-11-13 19:27:42','pedido',7,'creado',NULL,'borrador',NULL),(52,1,'2026-11-13 19:27:42','pedido',7,'confirmado','borrador','confirmado',NULL),(53,1,'2026-11-13 20:58:34','cotizacion',9,'creada',NULL,'borrador',NULL),(54,1,'2026-11-13 20:58:45','cotizacion',9,'estado_actualizado','borrador','anulada','Registrada por error en prueba de sede activa'),(55,1,'2026-11-13 20:59:29','cobranza',4,'creada',NULL,'borrador',NULL),(56,1,'2026-11-13 20:59:29','cobranza',4,'confirmada','borrador','confirmada',NULL),(57,1,'2026-11-13 20:59:37','cobranza',4,'anulada','confirmada','anulada','Transferencia rechazada por el banco'),(58,1,'2026-11-14 15:48:17','cotizacion',10,'creada',NULL,'borrador',NULL),(59,1,'2026-11-14 15:48:17','cotizacion',10,'estado_actualizado','borrador','emitida',NULL),(60,1,'2026-11-14 15:48:17','cotizacion',10,'estado_actualizado','emitida','aceptada',NULL),(61,1,'2026-11-14 15:48:17','pedido',8,'creado',NULL,'borrador',NULL),(62,1,'2026-11-14 15:48:17','pedido',8,'confirmado','borrador','confirmado',NULL),(63,1,'2026-11-14 15:48:17','venta',6,'creado',NULL,'borrador',NULL),(64,1,'2026-11-14 15:48:17','venta',6,'confirmado','borrador','confirmada',NULL),(65,1,'2026-11-14 15:48:33','cobranza',5,'creada',NULL,'borrador',NULL),(66,1,'2026-11-14 15:48:33','cobranza',5,'confirmada','borrador','confirmada',NULL),(67,1,'2026-11-14 15:48:48','cotizacion',11,'creada',NULL,'borrador',NULL),(68,1,'2026-11-14 15:48:48','cotizacion',11,'estado_actualizado','borrador','emitida',NULL),(69,1,'2026-11-14 15:48:48','cotizacion',11,'estado_actualizado','emitida','aceptada',NULL),(70,1,'2026-11-14 15:48:48','pedido',9,'creado',NULL,'borrador',NULL),(71,1,'2026-11-14 15:48:48','pedido',9,'confirmado','borrador','confirmado',NULL),(72,1,'2026-11-14 15:48:49','venta',7,'creado',NULL,'borrador',NULL),(73,1,'2026-11-14 15:48:49','venta',7,'confirmado','borrador','confirmada',NULL),(74,1,'2026-11-14 15:48:49','cobranza',6,'creada',NULL,'borrador',NULL),(75,1,'2026-11-14 15:48:49','cobranza',6,'confirmada','borrador','confirmada',NULL);
/*!40000 ALTER TABLE `comercial_auditoria` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `comercial_correlativos`
--

DROP TABLE IF EXISTS `comercial_correlativos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `comercial_correlativos` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `sucursal_id` int NOT NULL,
  `documento` enum('cotizacion','pedido','venta','cobranza') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `serie` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `ultimo_numero` bigint NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_correlativo_documento` (`sucursal_id`,`documento`,`serie`),
  CONSTRAINT `fk_correlativo_sucursal` FOREIGN KEY (`sucursal_id`) REFERENCES `sucursales` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `comercial_correlativos`
--

LOCK TABLES `comercial_correlativos` WRITE;
/*!40000 ALTER TABLE `comercial_correlativos` DISABLE KEYS */;
INSERT INTO `comercial_correlativos` VALUES (1,1,'cotizacion','COT001',6),(2,1,'pedido','PED001',4),(3,1,'venta','VEN001',4),(4,2,'cotizacion','COT002',5),(5,2,'pedido','PED002',5),(6,2,'venta','VEN002',3),(7,1,'cobranza','COB001',4),(8,2,'cobranza','COB002',2);
/*!40000 ALTER TABLE `comercial_correlativos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cotizacion_detalles`
--

DROP TABLE IF EXISTS `cotizacion_detalles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cotizacion_detalles` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `cotizacion_id` bigint NOT NULL,
  `linea` int NOT NULL,
  `producto_id` bigint NOT NULL,
  `unidad_id` int NOT NULL,
  `codigo_producto` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `descripcion` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `cantidad` decimal(18,4) NOT NULL,
  `precio_unitario` decimal(18,4) NOT NULL,
  `descuento` decimal(18,2) NOT NULL DEFAULT '0.00',
  `subtotal` decimal(18,2) NOT NULL,
  `tasa_impuesto` decimal(7,4) NOT NULL DEFAULT '0.0000',
  `impuesto` decimal(18,2) NOT NULL DEFAULT '0.00',
  `total` decimal(18,2) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cotizacion_linea` (`cotizacion_id`,`linea`),
  KEY `fk_cotizacion_detalle_producto` (`producto_id`),
  KEY `fk_cotizacion_detalle_unidad` (`unidad_id`),
  CONSTRAINT `fk_cotizacion_detalle_documento` FOREIGN KEY (`cotizacion_id`) REFERENCES `cotizaciones` (`id`),
  CONSTRAINT `fk_cotizacion_detalle_producto` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`),
  CONSTRAINT `fk_cotizacion_detalle_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `unidades_medida` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cotizacion_detalles`
--

LOCK TABLES `cotizacion_detalles` WRITE;
/*!40000 ALTER TABLE `cotizacion_detalles` DISABLE KEYS */;
INSERT INTO `cotizacion_detalles` VALUES (1,1,1,3,5,'PRD-0001','Insecticida Imidacloprid 350 SC',20.0000,95.0000,0.00,1900.00,18.0000,342.00,2242.00),(2,1,2,6,4,'PRD-0004','Fungicida Trifloxistrobina + Tebuconazol 75 WG',10.0000,289.0000,50.00,2890.00,18.0000,511.20,3351.20),(3,2,1,10,7,'PRD-0008','Fertilizante Urea 46% N',15.0000,128.0000,0.00,1920.00,18.0000,345.60,2265.60),(4,2,2,12,4,'PRD-0010','Fertilizante foliar NPK 20-20-20',30.0000,27.0000,0.00,810.00,18.0000,145.80,955.80),(5,3,1,14,5,'PRD-0012','Bioestimulante a base de extracto de algas',2.0000,65.0000,0.00,130.00,18.0000,23.40,153.40),(6,3,2,5,5,'PRD-0003','Insecticida Deltametrina 25 EC',1.0000,58.0000,0.00,58.00,18.0000,10.44,68.44),(7,4,1,3,5,'PRD-0001','Insecticida Imidacloprid 350 SC',10.0000,95.0000,0.00,950.00,18.0000,171.00,1121.00),(8,4,2,7,5,'PRD-0005','Fungicida Fluopyram + Tebuconazol 400 SC',5.0000,315.0000,0.00,1575.00,18.0000,283.50,1858.50),(9,5,1,6,4,'PRD-0004','Fungicida Trifloxistrobina + Tebuconazol 75 WG',8.0000,289.0000,100.00,2312.00,18.0000,398.16,2610.16),(10,5,2,10,7,'PRD-0008','Fertilizante Urea 46% N',20.0000,128.0000,0.00,2560.00,18.0000,460.80,3020.80),(11,6,1,8,6,'PRD-0006','Herbicida Glifosato 480 SL',10.0000,78.0000,0.00,780.00,18.0000,140.40,920.40),(12,6,2,13,7,'PRD-0011','Semilla de maíz amarillo duro híbrido',5.0000,360.0000,0.00,1800.00,18.0000,324.00,2124.00),(13,7,1,10,7,'PRD-0008','Fertilizante Urea 46% N',4.0000,128.0000,0.00,512.00,18.0000,92.16,604.16),(14,7,2,14,5,'PRD-0012','Bioestimulante a base de extracto de algas',3.0000,65.0000,0.00,195.00,18.0000,35.10,230.10),(15,8,1,4,5,'PRD-0002','Insecticida Spirotetramat 150 OD',5.0000,245.0000,0.00,1225.00,18.0000,220.50,1445.50),(16,9,1,3,5,'PRD-0001','Insecticida Imidacloprid 350 SC',1.0000,95.0000,0.00,95.00,18.0000,17.10,112.10),(17,10,1,7,5,'PRD-0005','Fungicida Fluopyram + Tebuconazol 400 SC',3.0000,315.0000,0.00,945.00,18.0000,170.10,1115.10),(18,10,2,11,7,'PRD-0009','Fertilizante Nitrato de Potasio',5.0000,149.0000,20.00,745.00,18.0000,130.50,855.50),(19,11,1,4,5,'PRD-0002','Insecticida Spirotetramat 150 OD',2.0000,245.0000,0.00,490.00,18.0000,88.20,578.20);
/*!40000 ALTER TABLE `cotizacion_detalles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cotizaciones`
--

DROP TABLE IF EXISTS `cotizaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cotizaciones` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `sucursal_id` int NOT NULL,
  `cliente_id` bigint NOT NULL,
  `vendedor_id` bigint DEFAULT NULL,
  `numero` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `fecha` date NOT NULL,
  `moneda` char(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'PEN',
  `estado` enum('borrador','emitida','aceptada','rechazada','vencida','anulada') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'borrador',
  `subtotal` decimal(18,2) NOT NULL DEFAULT '0.00',
  `descuento` decimal(18,2) NOT NULL DEFAULT '0.00',
  `impuesto` decimal(18,2) NOT NULL DEFAULT '0.00',
  `total` decimal(18,2) NOT NULL DEFAULT '0.00',
  `observaciones` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `anulado_por` bigint DEFAULT NULL,
  `anulado_en` datetime DEFAULT NULL,
  `motivo_anulacion` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `creado_por` bigint NOT NULL,
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `vigente_hasta` date NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cotizacion_numero` (`sucursal_id`,`numero`),
  KEY `idx_cotizacion_cliente_fecha` (`cliente_id`,`fecha`),
  KEY `idx_cotizacion_estado_fecha` (`estado`,`fecha`),
  KEY `fk_cotizacion_vendedor` (`vendedor_id`),
  KEY `fk_cotizacion_autor` (`creado_por`),
  KEY `fk_cotizacion_anulador` (`anulado_por`),
  CONSTRAINT `fk_cotizacion_anulador` FOREIGN KEY (`anulado_por`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_cotizacion_autor` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_cotizacion_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`),
  CONSTRAINT `fk_cotizacion_sucursal` FOREIGN KEY (`sucursal_id`) REFERENCES `sucursales` (`id`),
  CONSTRAINT `fk_cotizacion_vendedor` FOREIGN KEY (`vendedor_id`) REFERENCES `vendedores` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cotizaciones`
--

LOCK TABLES `cotizaciones` WRITE;
/*!40000 ALTER TABLE `cotizaciones` DISABLE KEYS */;
INSERT INTO `cotizaciones` VALUES (1,1,1,1,'COT001-00000001','2026-11-13','PEN','aceptada',4790.00,50.00,853.20,5593.20,'Campaña de palta',NULL,NULL,NULL,1,'2026-11-13 19:11:09','2026-11-13 19:11:10','2026-11-28'),(2,1,2,1,'COT001-00000002','2026-11-13','PEN','aceptada',2730.00,0.00,491.40,3221.40,'Fertilización de frutales',NULL,NULL,NULL,1,'2026-11-13 19:11:10','2026-11-13 19:11:10','2026-11-28'),(3,1,7,1,'COT001-00000003','2026-11-13','PEN','aceptada',188.00,0.00,33.84,221.84,'Venta mostrador',NULL,NULL,NULL,1,'2026-11-13 19:11:10','2026-11-13 19:11:10','2026-11-28'),(4,2,4,2,'COT002-00000001','2026-11-13','PEN','aceptada',2525.00,0.00,454.50,2979.50,'Control sanitario vid',NULL,NULL,NULL,1,'2026-11-13 19:11:10','2026-11-13 19:11:30','2026-11-28'),(5,2,5,2,'COT002-00000002','2026-11-13','PEN','aceptada',4872.00,100.00,858.96,5630.96,'Programa fitosanitario',NULL,NULL,NULL,1,'2026-11-13 19:11:11','2026-11-13 19:11:31','2026-11-28'),(6,1,3,3,'COT001-00000004','2026-11-13','PEN','emitida',2580.00,0.00,464.40,3044.40,'Propuesta campaña algodón',NULL,NULL,NULL,1,'2026-11-13 19:11:11','2026-11-13 19:11:31','2026-11-28'),(7,2,8,2,'COT002-00000003','2026-11-13','PEN','aceptada',707.00,0.00,127.26,834.26,'Pedido de fertilizantes',NULL,NULL,NULL,1,'2026-11-13 19:11:11','2026-11-13 19:11:31','2026-11-28'),(8,2,4,2,'COT002-00000004','2026-11-14','PEN','aceptada',1225.00,0.00,220.50,1445.50,'Programa de control de ácaros',NULL,NULL,NULL,1,'2026-11-13 19:27:42','2026-11-13 19:27:42','2026-11-29'),(9,1,1,1,'COT001-00000005','2026-11-14','PEN','anulada',95.00,0.00,17.10,112.10,NULL,1,'2026-11-13 20:58:45','Registrada por error en prueba de sede activa',1,'2026-11-13 20:58:34','2026-11-13 20:58:45','2026-11-29'),(10,1,6,1,'COT001-00000006','2026-11-14','PEN','aceptada',1690.00,20.00,300.60,1970.60,'Auditoría final',NULL,NULL,NULL,1,'2026-11-14 15:48:17','2026-11-14 15:48:17','2026-11-29'),(11,2,8,2,'COT002-00000005','2026-11-14','PEN','aceptada',490.00,0.00,88.20,578.20,'Auditoría final',NULL,NULL,NULL,1,'2026-11-14 15:48:48','2026-11-14 15:48:48','2026-11-29');
/*!40000 ALTER TABLE `cotizaciones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cuenta_cuotas`
--

DROP TABLE IF EXISTS `cuenta_cuotas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cuenta_cuotas` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `cuenta_id` bigint NOT NULL,
  `numero_cuota` int NOT NULL,
  `vencimiento` date NOT NULL,
  `importe` decimal(18,2) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cuenta_cuota` (`cuenta_id`,`numero_cuota`),
  KEY `idx_cuota_vencimiento` (`vencimiento`),
  CONSTRAINT `fk_cuota_cuenta` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas_por_cobrar` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cuenta_cuotas`
--

LOCK TABLES `cuenta_cuotas` WRITE;
/*!40000 ALTER TABLE `cuenta_cuotas` DISABLE KEYS */;
INSERT INTO `cuenta_cuotas` VALUES (1,1,1,'2026-12-13',3221.40),(2,2,1,'2026-12-28',5630.96),(3,3,1,'2026-12-14',578.20);
/*!40000 ALTER TABLE `cuenta_cuotas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cuentas_por_cobrar`
--

DROP TABLE IF EXISTS `cuentas_por_cobrar`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cuentas_por_cobrar` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `venta_id` bigint NOT NULL,
  `importe_original` decimal(18,2) NOT NULL,
  `estado` enum('activa','anulada') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'activa',
  `creado_por` bigint NOT NULL,
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cuenta_venta` (`venta_id`),
  KEY `fk_cuenta_autor` (`creado_por`),
  CONSTRAINT `fk_cuenta_autor` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_cuenta_venta` FOREIGN KEY (`venta_id`) REFERENCES `ventas` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cuentas_por_cobrar`
--

LOCK TABLES `cuentas_por_cobrar` WRITE;
/*!40000 ALTER TABLE `cuentas_por_cobrar` DISABLE KEYS */;
INSERT INTO `cuentas_por_cobrar` VALUES (1,2,3221.40,'activa',1,'2026-11-13 19:11:10','2026-11-13 19:11:10'),(2,5,5630.96,'activa',1,'2026-11-13 19:11:31','2026-11-13 19:11:31'),(3,7,578.20,'activa',1,'2026-11-14 15:48:49','2026-11-14 15:48:49');
/*!40000 ALTER TABLE `cuentas_por_cobrar` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `demo_clientes`
--

DROP TABLE IF EXISTS `demo_clientes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `demo_clientes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tipo_documento` varchar(4) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `numero_documento` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `numero_documento` (`numero_documento`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `demo_clientes`
--

LOCK TABLES `demo_clientes` WRITE;
/*!40000 ALTER TABLE `demo_clientes` DISABLE KEYS */;
INSERT INTO `demo_clientes` VALUES (1,'RUC','20123456789','Comercial Demo del Norte S.A.C.','compras@demo.local'),(2,'DNI','45678912','María Ejemplo Pérez','maria@demo.local'),(3,'DNI','72664039','Cliente de prueba 3',NULL),(4,'RUC','20602111220','AGROEXPORTADORA VALLE SUR S.A.C.',NULL),(5,'RUC','20603222335','FUNDO LAS PALMERAS S.A.C.',NULL),(6,'DNI','45123678','HUAMÁN TORRES MARÍA ELENA',NULL),(7,'RUC','20605444556','INVERSIONES AGRÍCOLAS MALA E.I.R.L.',NULL),(8,'RUC','20606555661','AGRÍCOLA CHINCHA DEL SUR S.A.C.',NULL),(9,'RUC','10445566778','RAMÍREZ QUISPE JUAN CARLOS',NULL),(10,'DNI','41987654','ROJAS MEDINA PEDRO PABLO',NULL);
/*!40000 ALTER TABLE `demo_clientes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `demo_empresa`
--

DROP TABLE IF EXISTS `demo_empresa`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `demo_empresa` (
  `id` tinyint unsigned NOT NULL,
  `ruc` char(11) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `razon_social` varchar(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre_comercial` varchar(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ubigeo` char(6) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `departamento` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `provincia` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `distrito` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `direccion` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `codigo_establecimiento` char(4) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0000',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `demo_empresa`
--

LOCK TABLES `demo_empresa` WRITE;
/*!40000 ALTER TABLE `demo_empresa` DISABLE KEYS */;
INSERT INTO `demo_empresa` VALUES (1,'20000000001','AGRICOLA PUCCHUN S.A.C.','Agrícola Pucchún','150101','LIMA','LIMA','LIMA','Av. Demostración 123','0000');
/*!40000 ALTER TABLE `demo_empresa` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `demo_productos`
--

DROP TABLE IF EXISTS `demo_productos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `demo_productos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `precio` decimal(12,2) NOT NULL,
  `unidad_medida` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'NIU',
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo` (`codigo`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `demo_productos`
--

LOCK TABLES `demo_productos` WRITE;
/*!40000 ALTER TABLE `demo_productos` DISABLE KEYS */;
INSERT INTO `demo_productos` VALUES (1,'PROD-001','Mango Kent (caja de 10 kg)',45.00,'NIU',1),(2,'PROD-002','Limón sutil (malla de 10 kg)',32.50,'NIU',1),(3,'SERV-001','Servicio de embalaje',12.00,'ZZ',1),(4,'PROD-003','Palta Hass (kg)',9.00,'NIU',1),(5,'PROD-004','Plátano de isla (kg)',4.00,'NIU',1),(6,'PROD-005','Papa amarilla (kg)',5.50,'NIU',1),(7,'PROD-006','Cebolla roja (kg)',3.50,'NIU',1),(8,'PROD-007','Tomate (kg)',4.80,'NIU',1),(9,'PROD-008','Camote amarillo (kg)',3.00,'NIU',1),(10,'PROD-009','Papaya (kg)',3.80,'NIU',1),(11,'PROD-010','Maracuyá (kg)',5.00,'NIU',1),(12,'PROD-011','Naranja Valencia (kg)',3.50,'NIU',1),(13,'PROD-012','Uva Red Globe (kg)',8.00,'NIU',1);
/*!40000 ALTER TABLE `demo_productos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `demo_venta_detalles`
--

DROP TABLE IF EXISTS `demo_venta_detalles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `demo_venta_detalles` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `venta_id` int unsigned NOT NULL,
  `producto_id` int unsigned NOT NULL,
  `cantidad` decimal(12,3) NOT NULL,
  `precio_unitario` decimal(12,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_demo_vd_venta` (`venta_id`),
  KEY `fk_demo_vd_producto` (`producto_id`),
  CONSTRAINT `fk_demo_vd_producto` FOREIGN KEY (`producto_id`) REFERENCES `demo_productos` (`id`),
  CONSTRAINT `fk_demo_vd_venta` FOREIGN KEY (`venta_id`) REFERENCES `demo_ventas` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `demo_venta_detalles`
--

LOCK TABLES `demo_venta_detalles` WRITE;
/*!40000 ALTER TABLE `demo_venta_detalles` DISABLE KEYS */;
INSERT INTO `demo_venta_detalles` VALUES (1,1,1,4.000,45.00),(2,2,3,3.000,12.00),(3,3,8,1.000,4.80);
/*!40000 ALTER TABLE `demo_venta_detalles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `demo_ventas`
--

DROP TABLE IF EXISTS `demo_ventas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `demo_ventas` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `cliente_id` int unsigned NOT NULL,
  `fecha` datetime NOT NULL,
  `estado` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'aprobada',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_venta_cliente_fecha` (`cliente_id`,`fecha`),
  CONSTRAINT `fk_demo_venta_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `demo_clientes` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `demo_ventas`
--

LOCK TABLES `demo_ventas` WRITE;
/*!40000 ALTER TABLE `demo_ventas` DISABLE KEYS */;
INSERT INTO `demo_ventas` VALUES (1,1,'2026-09-30 12:09:55','aprobada'),(2,2,'2026-09-30 12:09:55','aprobada'),(3,3,'2026-10-09 13:53:29','aprobada');
/*!40000 ALTER TABLE `demo_ventas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `departamentos`
--

DROP TABLE IF EXISTS `departamentos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `departamentos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `departamentos`
--

LOCK TABLES `departamentos` WRITE;
/*!40000 ALTER TABLE `departamentos` DISABLE KEYS */;
INSERT INTO `departamentos` VALUES (1,'Lima'),(2,'Ica');
/*!40000 ALTER TABLE `departamentos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `detalle_publicacion`
--

DROP TABLE IF EXISTS `detalle_publicacion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `detalle_publicacion` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `publicacion_id` bigint NOT NULL,
  `registro_id` bigint NOT NULL,
  `dataset` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `version` int unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_publicacion_version` (`dataset`,`registro_id`,`version`),
  KEY `publicacion_id` (`publicacion_id`),
  CONSTRAINT `detalle_publicacion_ibfk_1` FOREIGN KEY (`publicacion_id`) REFERENCES `publicaciones` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ck_publicacion_version` CHECK ((`version` >= 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `detalle_publicacion`
--

LOCK TABLES `detalle_publicacion` WRITE;
/*!40000 ALTER TABLE `detalle_publicacion` DISABLE KEYS */;
/*!40000 ALTER TABLE `detalle_publicacion` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `distritos`
--

DROP TABLE IF EXISTS `distritos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `distritos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `provincia_id` int NOT NULL,
  `nombre` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `ubigeo` char(6) COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_distrito_ubigeo` (`ubigeo`),
  KEY `provincia_id` (`provincia_id`),
  CONSTRAINT `distritos_ibfk_1` FOREIGN KEY (`provincia_id`) REFERENCES `provincias` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `distritos`
--

LOCK TABLES `distritos` WRITE;
/*!40000 ALTER TABLE `distritos` DISABLE KEYS */;
INSERT INTO `distritos` VALUES (1,1,'San Vicente de Cañete','150501'),(2,2,'Miraflores','150122'),(3,1,'Imperial','150507'),(4,1,'Mala','150509'),(5,1,'Nuevo Imperial','150510'),(6,1,'San Luis','150514'),(7,2,'Lima','150101'),(8,3,'Chincha Alta','110201');
/*!40000 ALTER TABLE `distritos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `documentos_cabecera`
--

DROP TABLE IF EXISTS `documentos_cabecera`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `documentos_cabecera` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `tipo_documento_id` int NOT NULL,
  `numero` varchar(25) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha` date NOT NULL,
  `cliente_id` bigint NOT NULL,
  `vendedor_id` bigint NOT NULL,
  `sucursal_id` int NOT NULL,
  `estado_registro` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'BORRADOR',
  `created_by` bigint DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `published_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `updated_by` bigint DEFAULT NULL,
  `version` int unsigned NOT NULL DEFAULT '1',
  `validated_at` datetime DEFAULT NULL,
  `validated_by` bigint DEFAULT NULL,
  `observed_at` datetime DEFAULT NULL,
  `observed_by` bigint DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancelled_by` bigint DEFAULT NULL,
  `published_by` bigint DEFAULT NULL,
  `observation_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `cancellation_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `origen` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `origen_ref` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `origen_hash` char(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado_comercial` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'APROBADA',
  `monto_total` decimal(14,2) NOT NULL DEFAULT '0.00',
  `moneda` char(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PEN',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_doc` (`tipo_documento_id`,`numero`),
  UNIQUE KEY `uq_documentos_cabecera_origen` (`origen`,`origen_ref`),
  KEY `cliente_id` (`cliente_id`),
  KEY `vendedor_id` (`vendedor_id`),
  KEY `created_by` (`created_by`),
  KEY `fk_documentos_updated_by` (`updated_by`),
  KEY `fk_documentos_validated_by` (`validated_by`),
  KEY `fk_documentos_observed_by` (`observed_by`),
  KEY `fk_documentos_cancelled_by` (`cancelled_by`),
  KEY `fk_documentos_published_by` (`published_by`),
  KEY `ix_documentos_estado_fecha` (`estado_registro`,`fecha`,`id`),
  KEY `ix_documentos_ubicacion_fecha` (`sucursal_id`,`fecha`,`id`),
  KEY `idx_documentos_cabecera_estado` (`estado_registro`),
  KEY `idx_doc_fecha` (`fecha`),
  CONSTRAINT `documentos_cabecera_ibfk_1` FOREIGN KEY (`tipo_documento_id`) REFERENCES `tipos_documento` (`id`),
  CONSTRAINT `documentos_cabecera_ibfk_2` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`),
  CONSTRAINT `documentos_cabecera_ibfk_3` FOREIGN KEY (`vendedor_id`) REFERENCES `vendedores` (`id`),
  CONSTRAINT `documentos_cabecera_ibfk_4` FOREIGN KEY (`sucursal_id`) REFERENCES `sucursales` (`id`),
  CONSTRAINT `documentos_cabecera_ibfk_5` FOREIGN KEY (`created_by`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_documentos_cancelled_by` FOREIGN KEY (`cancelled_by`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_documentos_observed_by` FOREIGN KEY (`observed_by`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_documentos_published_by` FOREIGN KEY (`published_by`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_documentos_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_documentos_validated_by` FOREIGN KEY (`validated_by`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `ck_documentos_estado` CHECK ((cast(`estado_registro` as char charset binary) in (_utf8mb4'BORRADOR',_utf8mb4'VALIDADO',_utf8mb4'PUBLICADO',_utf8mb4'OBSERVADO',_utf8mb4'ANULADO'))),
  CONSTRAINT `ck_documentos_fecha` CHECK (((`fecha` >= _utf8mb4'1000-01-01') and (dayofmonth(`fecha`) >= 1))),
  CONSTRAINT `ck_documentos_version` CHECK ((`version` >= 1))
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `documentos_cabecera`
--

LOCK TABLES `documentos_cabecera` WRITE;
/*!40000 ALTER TABLE `documentos_cabecera` DISABLE KEYS */;
INSERT INTO `documentos_cabecera` VALUES (1,3,'F001-00000002','2026-11-13',1,1,1,'PUBLICADO',NULL,'2026-11-13 19:12:07',NULL,'2026-11-13 20:58:03',NULL,4,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'ERP_G1','4','2c7096dd5c03b6e7854eb5de88c65058f137290f','APROBADA',5593.20,'PEN'),(2,3,'F001-00000003','2026-11-13',2,1,1,'PUBLICADO',NULL,'2026-11-13 19:12:07',NULL,'2026-11-13 20:58:03',NULL,4,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'ERP_G1','5','9ee0315836e8d06bd5f3ec644d846ce070c103ca','APROBADA',3221.40,'PEN'),(3,4,'B001-00000003','2026-11-13',7,1,1,'BORRADOR',NULL,'2026-11-13 19:12:07',NULL,'2026-11-13 20:58:03',NULL,3,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'ERP_G1','6','7f2ff63426d032db097808632ccd411cf6335458','APROBADA',221.84,'PEN'),(4,3,'F001-00000004','2026-11-13',4,2,2,'ANULADO',NULL,'2026-11-13 19:12:07',NULL,'2026-11-13 20:58:03',NULL,4,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'ERP_G1','7','c40b00586516e6071b1f7f783b192c966d91da3d','ANULADA',2979.50,'PEN'),(5,3,'F001-00000005','2026-11-13',4,2,2,'BORRADOR',NULL,'2026-11-13 19:28:47',NULL,'2026-11-13 20:58:03',NULL,2,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'ERP_G1','8','08ed54c400ebd56ecc5a44ed447750d847de8ac3','APROBADA',2979.50,'PEN'),(7,5,'FC01-00000001','2026-11-13',1,1,1,'PUBLICADO',NULL,'2026-11-13 20:58:03','2026-11-13 20:58:03','2026-11-13 20:58:03',NULL,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'ERP_G1','9','c7d146a4178316012a06f5636d3f13b25d02a418','APROBADA',-118.00,'PEN'),(8,5,'FC01-00000002','2026-11-13',2,1,1,'PUBLICADO',NULL,'2026-11-13 20:58:03','2026-11-13 20:58:03','2026-11-13 20:58:03',NULL,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'ERP_G1','10','fa2743ce8c3bf7d32e02ba9751e4553be8b3e530','APROBADA',-236.00,'PEN'),(9,3,'F002-00000001','2026-11-13',5,2,2,'BORRADOR',NULL,'2026-11-14 01:09:54',NULL,'2026-11-14 01:09:54',NULL,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'ERP_G1','11','b417d83cb475b9336179b6ba3e9e37455f12a77c','APROBADA',5630.96,'PEN'),(10,3,'F001-00000006','2026-11-14',6,1,1,'PUBLICADO',NULL,'2026-11-14 15:49:33','2026-11-14 15:49:33','2026-11-14 15:49:33',NULL,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'ERP_G1','12','6ad96301b55b6d0bee839387d8fc47e7af9151c7','APROBADA',1970.60,'PEN'),(11,4,'B002-00000001','2026-11-14',8,2,2,'BORRADOR',NULL,'2026-11-14 15:49:33',NULL,'2026-11-14 15:49:33',NULL,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'ERP_G1','13','153606ff2fba9b21bc63710bfeb28a78fb70d597','APROBADA',578.20,'PEN');
/*!40000 ALTER TABLE `documentos_cabecera` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `documentos_detalle`
--

DROP TABLE IF EXISTS `documentos_detalle`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `documentos_detalle` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `documento_id` bigint NOT NULL,
  `producto_id` bigint NOT NULL,
  `unidad_id` int NOT NULL,
  `cantidad` decimal(14,3) NOT NULL,
  `valor_unitario` decimal(16,6) NOT NULL,
  `importe` decimal(14,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `documento_id` (`documento_id`),
  KEY `producto_id` (`producto_id`),
  KEY `unidad_id` (`unidad_id`),
  CONSTRAINT `documentos_detalle_ibfk_1` FOREIGN KEY (`documento_id`) REFERENCES `documentos_cabecera` (`id`) ON DELETE CASCADE,
  CONSTRAINT `documentos_detalle_ibfk_2` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`),
  CONSTRAINT `documentos_detalle_ibfk_3` FOREIGN KEY (`unidad_id`) REFERENCES `unidades_medida` (`id`),
  CONSTRAINT `ck_documentos_cantidad` CHECK (((`cantidad` > 0) or ((`cantidad` = 0) and (`importe` is not null)))),
  CONSTRAINT `ck_documentos_valor` CHECK ((`valor_unitario` >= 0))
) ENGINE=InnoDB AUTO_INCREMENT=54 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `documentos_detalle`
--

LOCK TABLES `documentos_detalle` WRITE;
/*!40000 ALTER TABLE `documentos_detalle` DISABLE KEYS */;
INSERT INTO `documentos_detalle` VALUES (35,1,3,5,20.000,95.000000,1900.00),(36,1,6,4,10.000,284.000000,2840.00),(37,2,10,7,15.000,128.000000,1920.00),(38,2,12,4,30.000,27.000000,810.00),(39,3,14,5,2.000,65.000000,130.00),(40,3,5,5,1.000,58.000000,58.00),(41,4,3,5,10.000,95.000000,950.00),(42,4,7,5,5.000,315.000000,1575.00),(43,5,3,5,10.000,95.000000,950.00),(44,5,7,5,5.000,315.000000,1575.00),(45,7,3,5,0.000,0.000000,-40.08),(46,7,6,4,0.000,0.000000,-59.92),(47,8,10,7,0.000,0.000000,-140.66),(48,8,12,4,0.000,0.000000,-59.34),(49,9,6,4,8.000,276.500000,2212.00),(50,9,10,7,20.000,128.000000,2560.00),(51,10,7,5,3.000,315.000000,945.00),(52,10,11,7,5.000,145.000000,725.00),(53,11,4,5,2.000,245.000000,490.00);
/*!40000 ALTER TABLE `documentos_detalle` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `empresas`
--

DROP TABLE IF EXISTS `empresas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `empresas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ruc` char(11) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `razon_social` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `nombre_comercial` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `estado` tinyint DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `empresas`
--

LOCK TABLES `empresas` WRITE;
/*!40000 ALTER TABLE `empresas` DISABLE KEYS */;
INSERT INTO `empresas` VALUES (1,'20601001005','AGRÍCOLA PUCCHÚN S.A.C.','Agrícola Pucchún',1);
/*!40000 ALTER TABLE `empresas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `exportaciones`
--

DROP TABLE IF EXISTS `exportaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `exportaciones` (
  `nombre_archivo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id` bigint NOT NULL AUTO_INCREMENT,
  `tipo_dataset` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `formato` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `filtro_json` json DEFAULT NULL,
  `usuario_id` bigint NOT NULL,
  `generated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `record_count` int unsigned NOT NULL DEFAULT '0',
  `resultado` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDIENTE',
  `duration_ms` bigint unsigned DEFAULT NULL,
  `correlation_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `ix_exportaciones_dataset_fecha` (`tipo_dataset`,`generated_at`),
  CONSTRAINT `exportaciones_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `exportaciones`
--

LOCK TABLES `exportaciones` WRITE;
/*!40000 ALTER TABLE `exportaciones` DISABLE KEYS */;
/*!40000 ALTER TABLE `exportaciones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `facturacion_baja_items`
--

DROP TABLE IF EXISTS `facturacion_baja_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `facturacion_baja_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `baja_id` bigint unsigned NOT NULL,
  `comprobante_id` bigint unsigned NOT NULL,
  `motivo` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_baja_item_baja` (`baja_id`),
  KEY `ix_baja_item_comprobante` (`comprobante_id`),
  CONSTRAINT `fk_baja_item_baja` FOREIGN KEY (`baja_id`) REFERENCES `facturacion_bajas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_baja_item_comprobante` FOREIGN KEY (`comprobante_id`) REFERENCES `facturacion_comprobantes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `facturacion_baja_items`
--

LOCK TABLES `facturacion_baja_items` WRITE;
/*!40000 ALTER TABLE `facturacion_baja_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `facturacion_baja_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `facturacion_bajas`
--

DROP TABLE IF EXISTS `facturacion_bajas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `facturacion_bajas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tipo` enum('comunicacion_baja','resumen_diario') NOT NULL,
  `identificador` varchar(30) NOT NULL,
  `fecha_referencia` date NOT NULL,
  `fecha_generacion` date NOT NULL,
  `correlativo` int unsigned NOT NULL,
  `nombre_archivo` varchar(80) NOT NULL,
  `xml` mediumtext NOT NULL,
  `hash` varchar(64) NOT NULL,
  `estado` varchar(30) NOT NULL DEFAULT 'firmado',
  `creado_en` datetime NOT NULL,
  `envio_estado` varchar(30) NOT NULL DEFAULT 'firmado',
  `origen_envio` varchar(20) DEFAULT NULL,
  `ticket` varchar(100) DEFAULT NULL,
  `codigo_respuesta` varchar(30) DEFAULT NULL,
  `mensaje_respuesta` text,
  `cdr_contenido` mediumblob,
  `cdr_sha256` char(64) DEFAULT NULL,
  `solicitado_en` datetime DEFAULT NULL,
  `respondido_en` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_baja_identificador` (`identificador`),
  KEY `ix_baja_tipo_fecha` (`tipo`,`fecha_generacion`),
  KEY `ix_baja_ticket` (`ticket`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `facturacion_bajas`
--

LOCK TABLES `facturacion_bajas` WRITE;
/*!40000 ALTER TABLE `facturacion_bajas` DISABLE KEYS */;
/*!40000 ALTER TABLE `facturacion_bajas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `facturacion_comprobantes`
--

DROP TABLE IF EXISTS `facturacion_comprobantes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `facturacion_comprobantes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `venta_id` int unsigned DEFAULT NULL,
  `venta_erp_id` bigint DEFAULT NULL,
  `venta_erp_vigente` bigint GENERATED ALWAYS AS ((case when (`estado` in (_utf8mb4'anulado',_utf8mb4'anulado_local')) then NULL else `venta_erp_id` end)) STORED,
  `comprobante_referenciado_id` bigint unsigned DEFAULT NULL,
  `tipo` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `serie` varchar(4) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `numero` int unsigned NOT NULL,
  `cliente_id` int unsigned NOT NULL,
  `fecha_emision` datetime NOT NULL,
  `motivo` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `codigo_motivo` char(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subtotal` decimal(12,2) NOT NULL,
  `impuesto` decimal(12,2) NOT NULL,
  `total` decimal(12,2) NOT NULL,
  `datos_emision` json DEFAULT NULL,
  `estado` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'borrador_local',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_comprobante_numero` (`tipo`,`serie`,`numero`),
  UNIQUE KEY `uq_venta_facturada` (`venta_id`),
  UNIQUE KEY `uq_venta_erp_vigente` (`venta_erp_vigente`),
  KEY `fk_comp_cliente` (`cliente_id`),
  KEY `fk_comp_referencia` (`comprobante_referenciado_id`),
  KEY `idx_comp_venta_erp` (`venta_erp_id`),
  CONSTRAINT `fk_comp_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `demo_clientes` (`id`),
  CONSTRAINT `fk_comp_referencia` FOREIGN KEY (`comprobante_referenciado_id`) REFERENCES `facturacion_comprobantes` (`id`),
  CONSTRAINT `fk_comp_venta` FOREIGN KEY (`venta_id`) REFERENCES `demo_ventas` (`id`),
  CONSTRAINT `fk_comp_venta_erp` FOREIGN KEY (`venta_erp_id`) REFERENCES `ventas` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `facturacion_comprobantes`
--

LOCK TABLES `facturacion_comprobantes` WRITE;
/*!40000 ALTER TABLE `facturacion_comprobantes` DISABLE KEYS */;
INSERT INTO `facturacion_comprobantes` (`id`, `venta_id`, `venta_erp_id`, `comprobante_referenciado_id`, `tipo`, `serie`, `numero`, `cliente_id`, `fecha_emision`, `motivo`, `codigo_motivo`, `subtotal`, `impuesto`, `total`, `datos_emision`, `estado`) VALUES (1,1,NULL,NULL,'factura','F001',1,1,'2026-09-30 13:55:27',NULL,NULL,180.00,32.40,212.40,'{\"emisor\": {\"ruc\": \"20000000001\", \"ubigeo\": \"150101\", \"distrito\": \"LIMA\", \"direccion\": \"Av. Demostración 123\", \"provincia\": \"LIMA\", \"departamento\": \"LIMA\", \"razon_social\": \"AGRICOLA PUCCHUN S.A.C.\", \"nombre_comercial\": \"Agrícola Pucchún\", \"codigo_establecimiento\": \"0000\"}, \"cliente\": {\"nombre\": \"Comercial Demo del Norte S.A.C.\", \"tipo_documento\": \"RUC\", \"numero_documento\": \"20123456789\"}, \"tasa_igv\": 0.18}','firmado'),(2,2,NULL,NULL,'boleta','B001',1,2,'2026-10-09 13:48:44',NULL,NULL,36.00,6.48,42.48,'{\"emisor\": {\"ruc\": \"20000000001\", \"ubigeo\": \"150101\", \"distrito\": \"LIMA\", \"direccion\": \"Av. Demostración 123\", \"provincia\": \"LIMA\", \"departamento\": \"LIMA\", \"razon_social\": \"AGRICOLA PUCCHUN S.A.C.\", \"nombre_comercial\": \"Agrícola Pucchún\", \"codigo_establecimiento\": \"0000\"}, \"cliente\": {\"nombre\": \"María Ejemplo Pérez\", \"tipo_documento\": \"DNI\", \"numero_documento\": \"45678912\"}, \"tasa_igv\": 0.18}','firmado'),(3,3,NULL,NULL,'boleta','B001',2,3,'2026-10-09 13:54:07',NULL,NULL,4.80,0.86,5.66,'{\"emisor\": {\"ruc\": \"20000000001\", \"ubigeo\": \"150101\", \"distrito\": \"LIMA\", \"direccion\": \"Av. Demostración 123\", \"provincia\": \"LIMA\", \"departamento\": \"LIMA\", \"razon_social\": \"AGRICOLA PUCCHUN S.A.C.\", \"nombre_comercial\": \"Agrícola Pucchún\", \"codigo_establecimiento\": \"0000\"}, \"cliente\": {\"nombre\": \"2345245345\", \"tipo_documento\": \"DNI\", \"numero_documento\": \"72664039\"}, \"tasa_igv\": 0.18}','generado_xml'),(4,NULL,1,NULL,'factura','F001',2,4,'2026-11-13 19:11:43',NULL,NULL,4740.00,853.20,5593.20,'{\"emisor\": {\"ruc\": \"20601001005\", \"ubigeo\": \"150501\", \"distrito\": \"SAN VICENTE DE CAÑETE\", \"direccion\": \"Av. Mariscal Benavides 845\", \"provincia\": \"CAÑETE\", \"departamento\": \"LIMA\", \"razon_social\": \"AGRÍCOLA PUCCHÚN S.A.C.\", \"nombre_comercial\": \"Agrícola Pucchún\", \"codigo_establecimiento\": \"0000\"}, \"cliente\": {\"nombre\": \"AGROEXPORTADORA VALLE SUR S.A.C.\", \"tipo_documento\": \"RUC\", \"numero_documento\": \"20602111220\"}, \"tasa_igv\": 0.18}','aceptado'),(5,NULL,2,NULL,'factura','F001',3,5,'2026-11-13 19:11:44',NULL,NULL,2730.00,491.40,3221.40,'{\"emisor\": {\"ruc\": \"20601001005\", \"ubigeo\": \"150501\", \"distrito\": \"SAN VICENTE DE CAÑETE\", \"direccion\": \"Av. Mariscal Benavides 845\", \"provincia\": \"CAÑETE\", \"departamento\": \"LIMA\", \"razon_social\": \"AGRÍCOLA PUCCHÚN S.A.C.\", \"nombre_comercial\": \"Agrícola Pucchún\", \"codigo_establecimiento\": \"0000\"}, \"cliente\": {\"nombre\": \"FUNDO LAS PALMERAS S.A.C.\", \"tipo_documento\": \"RUC\", \"numero_documento\": \"20603222335\"}, \"tasa_igv\": 0.18}','aceptado'),(6,NULL,3,NULL,'boleta','B001',3,6,'2026-11-13 19:11:44',NULL,NULL,188.00,33.84,221.84,'{\"emisor\": {\"ruc\": \"20601001005\", \"ubigeo\": \"150501\", \"distrito\": \"SAN VICENTE DE CAÑETE\", \"direccion\": \"Av. Mariscal Benavides 845\", \"provincia\": \"CAÑETE\", \"departamento\": \"LIMA\", \"razon_social\": \"AGRÍCOLA PUCCHÚN S.A.C.\", \"nombre_comercial\": \"Agrícola Pucchún\", \"codigo_establecimiento\": \"0000\"}, \"cliente\": {\"nombre\": \"HUAMÁN TORRES MARÍA ELENA\", \"tipo_documento\": \"DNI\", \"numero_documento\": \"45123678\"}, \"tasa_igv\": 0.18}','firmado'),(7,NULL,4,NULL,'factura','F001',4,7,'2026-11-13 19:11:44',NULL,NULL,2525.00,454.50,2979.50,'{\"emisor\": {\"ruc\": \"20601001005\", \"ubigeo\": \"150509\", \"distrito\": \"MALA\", \"direccion\": \"Jr. Grau 312\", \"provincia\": \"CAÑETE\", \"departamento\": \"LIMA\", \"razon_social\": \"AGRÍCOLA PUCCHÚN S.A.C.\", \"nombre_comercial\": \"Agrícola Pucchún\", \"codigo_establecimiento\": \"0001\"}, \"cliente\": {\"nombre\": \"INVERSIONES AGRÍCOLAS MALA E.I.R.L.\", \"tipo_documento\": \"RUC\", \"numero_documento\": \"20605444556\"}, \"tasa_igv\": 0.18}','anulado_local'),(8,NULL,4,NULL,'factura','F001',5,7,'2026-11-13 19:26:35',NULL,NULL,2525.00,454.50,2979.50,'{\"emisor\": {\"ruc\": \"20601001005\", \"ubigeo\": \"150509\", \"distrito\": \"MALA\", \"direccion\": \"Jr. Grau 312\", \"provincia\": \"CAÑETE\", \"departamento\": \"LIMA\", \"razon_social\": \"AGRÍCOLA PUCCHÚN S.A.C.\", \"nombre_comercial\": \"Agrícola Pucchún\", \"codigo_establecimiento\": \"0001\"}, \"cliente\": {\"nombre\": \"INVERSIONES AGRÍCOLAS MALA E.I.R.L.\", \"tipo_documento\": \"RUC\", \"numero_documento\": \"20605444556\"}, \"tasa_igv\": 0.18}','borrador_local'),(9,NULL,NULL,4,'nota_credito','FC01',1,4,'2026-11-13 19:26:54','Descuento comercial posterior','09',100.00,18.00,118.00,'{\"emisor\": {\"ruc\": \"20601001005\", \"ubigeo\": \"150501\", \"distrito\": \"SAN VICENTE DE CAÑETE\", \"direccion\": \"Av. Mariscal Benavides 845\", \"provincia\": \"CAÑETE\", \"departamento\": \"LIMA\", \"razon_social\": \"AGRÍCOLA PUCCHÚN S.A.C.\", \"nombre_comercial\": \"Agrícola Pucchún\", \"codigo_establecimiento\": \"0000\"}, \"cliente\": {\"nombre\": \"AGROEXPORTADORA VALLE SUR S.A.C.\", \"tipo_documento\": \"RUC\", \"numero_documento\": \"20602111220\"}, \"tasa_igv\": 0.18}','aceptado'),(10,NULL,NULL,5,'nota_credito','FC01',2,5,'2026-11-13 19:26:54','Bonificación por volumen','09',200.00,36.00,236.00,'{\"emisor\": {\"ruc\": \"20601001005\", \"ubigeo\": \"150501\", \"distrito\": \"SAN VICENTE DE CAÑETE\", \"direccion\": \"Av. Mariscal Benavides 845\", \"provincia\": \"CAÑETE\", \"departamento\": \"LIMA\", \"razon_social\": \"AGRÍCOLA PUCCHÚN S.A.C.\", \"nombre_comercial\": \"Agrícola Pucchún\", \"codigo_establecimiento\": \"0000\"}, \"cliente\": {\"nombre\": \"FUNDO LAS PALMERAS S.A.C.\", \"tipo_documento\": \"RUC\", \"numero_documento\": \"20603222335\"}, \"tasa_igv\": 0.18}','aceptado'),(11,NULL,5,NULL,'factura','F002',1,8,'2026-11-13 20:59:16',NULL,NULL,4772.00,858.96,5630.96,'{\"emisor\": {\"ruc\": \"20601001005\", \"ubigeo\": \"150509\", \"distrito\": \"MALA\", \"direccion\": \"Jr. Grau 312\", \"provincia\": \"CAÑETE\", \"departamento\": \"LIMA\", \"razon_social\": \"AGRÍCOLA PUCCHÚN S.A.C.\", \"nombre_comercial\": \"Agrícola Pucchún\", \"codigo_establecimiento\": \"0001\"}, \"cliente\": {\"nombre\": \"AGRÍCOLA CHINCHA DEL SUR S.A.C.\", \"tipo_documento\": \"RUC\", \"numero_documento\": \"20606555661\"}, \"tasa_igv\": 0.18}','borrador_local'),(12,NULL,6,NULL,'factura','F001',6,9,'2026-11-14 15:48:33',NULL,NULL,1670.00,300.60,1970.60,'{\"emisor\": {\"ruc\": \"20601001005\", \"ubigeo\": \"150501\", \"distrito\": \"SAN VICENTE DE CAÑETE\", \"direccion\": \"Av. Mariscal Benavides 845\", \"provincia\": \"CAÑETE\", \"departamento\": \"LIMA\", \"razon_social\": \"AGRÍCOLA PUCCHÚN S.A.C.\", \"nombre_comercial\": \"Agrícola Pucchún\", \"codigo_establecimiento\": \"0000\"}, \"cliente\": {\"nombre\": \"RAMÍREZ QUISPE JUAN CARLOS\", \"tipo_documento\": \"RUC\", \"numero_documento\": \"10445566778\"}, \"tasa_igv\": 0.18}','aceptado'),(13,NULL,7,NULL,'boleta','B002',1,10,'2026-11-14 15:48:49',NULL,NULL,490.00,88.20,578.20,'{\"emisor\": {\"ruc\": \"20601001005\", \"ubigeo\": \"150509\", \"distrito\": \"MALA\", \"direccion\": \"Jr. Grau 312\", \"provincia\": \"CAÑETE\", \"departamento\": \"LIMA\", \"razon_social\": \"AGRÍCOLA PUCCHÚN S.A.C.\", \"nombre_comercial\": \"Agrícola Pucchún\", \"codigo_establecimiento\": \"0001\"}, \"cliente\": {\"nombre\": \"ROJAS MEDINA PEDRO PABLO\", \"tipo_documento\": \"DNI\", \"numero_documento\": \"41987654\"}, \"tasa_igv\": 0.18}','firmado');
/*!40000 ALTER TABLE `facturacion_comprobantes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `facturacion_detalles`
--

DROP TABLE IF EXISTS `facturacion_detalles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `facturacion_detalles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `comprobante_id` bigint unsigned NOT NULL,
  `codigo_producto` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `descripcion` varchar(180) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `cantidad` decimal(12,3) NOT NULL,
  `unidad_medida` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'NIU',
  `precio_unitario` decimal(14,4) NOT NULL,
  `descuento` decimal(12,2) NOT NULL DEFAULT '0.00',
  `subtotal` decimal(12,2) NOT NULL,
  `impuesto` decimal(12,2) NOT NULL,
  `total` decimal(12,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_det_comprobante` (`comprobante_id`),
  CONSTRAINT `fk_det_comprobante` FOREIGN KEY (`comprobante_id`) REFERENCES `facturacion_comprobantes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `facturacion_detalles`
--

LOCK TABLES `facturacion_detalles` WRITE;
/*!40000 ALTER TABLE `facturacion_detalles` DISABLE KEYS */;
INSERT INTO `facturacion_detalles` VALUES (1,1,'PROD-001','Mango Kent (caja de 10 kg)',4.000,'NIU',45.0000,0.00,180.00,32.40,212.40),(2,2,'SERV-001','Servicio de embalaje',3.000,'ZZ',12.0000,0.00,36.00,6.48,42.48),(3,3,'PROD-007','Tomate (kg)',1.000,'NIU',4.8000,0.00,4.80,0.86,5.66),(4,4,'PRD-0001','Insecticida Imidacloprid 350 SC',20.000,'LTR',95.0000,0.00,1900.00,342.00,2242.00),(5,4,'PRD-0004','Fungicida Trifloxistrobina + Tebuconazol 75 WG',10.000,'KGM',289.0000,50.00,2840.00,511.20,3351.20),(6,5,'PRD-0008','Fertilizante Urea 46% N',15.000,'BG',128.0000,0.00,1920.00,345.60,2265.60),(7,5,'PRD-0010','Fertilizante foliar NPK 20-20-20',30.000,'KGM',27.0000,0.00,810.00,145.80,955.80),(8,6,'PRD-0012','Bioestimulante a base de extracto de algas',2.000,'LTR',65.0000,0.00,130.00,23.40,153.40),(9,6,'PRD-0003','Insecticida Deltametrina 25 EC',1.000,'LTR',58.0000,0.00,58.00,10.44,68.44),(10,7,'PRD-0001','Insecticida Imidacloprid 350 SC',10.000,'LTR',95.0000,0.00,950.00,171.00,1121.00),(11,7,'PRD-0005','Fungicida Fluopyram + Tebuconazol 400 SC',5.000,'LTR',315.0000,0.00,1575.00,283.50,1858.50),(12,8,'PRD-0001','Insecticida Imidacloprid 350 SC',10.000,'LTR',95.0000,0.00,950.00,171.00,1121.00),(13,8,'PRD-0005','Fungicida Fluopyram + Tebuconazol 400 SC',5.000,'LTR',315.0000,0.00,1575.00,283.50,1858.50),(14,9,NULL,'Ajuste: Descuento comercial posterior',1.000,'ZZ',100.0000,0.00,100.00,18.00,118.00),(15,10,NULL,'Ajuste: Bonificación por volumen',1.000,'ZZ',200.0000,0.00,200.00,36.00,236.00),(16,11,'PRD-0004','Fungicida Trifloxistrobina + Tebuconazol 75 WG',8.000,'KGM',289.0000,100.00,2212.00,398.16,2610.16),(17,11,'PRD-0008','Fertilizante Urea 46% N',20.000,'BG',128.0000,0.00,2560.00,460.80,3020.80),(18,12,'PRD-0005','Fungicida Fluopyram + Tebuconazol 400 SC',3.000,'LTR',315.0000,0.00,945.00,170.10,1115.10),(19,12,'PRD-0009','Fertilizante Nitrato de Potasio',5.000,'BG',149.0000,20.00,725.00,130.50,855.50),(20,13,'PRD-0002','Insecticida Spirotetramat 150 OD',2.000,'LTR',245.0000,0.00,490.00,88.20,578.20);
/*!40000 ALTER TABLE `facturacion_detalles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `facturacion_envio_eventos`
--

DROP TABLE IF EXISTS `facturacion_envio_eventos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `facturacion_envio_eventos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `envio_id` bigint unsigned NOT NULL,
  `etapa` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nivel` enum('info','observacion','error') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'info',
  `codigo` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `detalle` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `metadatos` json DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_envio_evento_fecha` (`envio_id`,`creado_en`),
  CONSTRAINT `fk_envio_evento_envio` FOREIGN KEY (`envio_id`) REFERENCES `facturacion_envios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `facturacion_envio_eventos`
--

LOCK TABLES `facturacion_envio_eventos` WRITE;
/*!40000 ALTER TABLE `facturacion_envio_eventos` DISABLE KEYS */;
INSERT INTO `facturacion_envio_eventos` VALUES (1,1,'solicitud','info',NULL,'Envío registrado para 20000000001-03-B001-2.xml. Clave de control: d1802e1d55a56529b71db4d1ecc0a190a29cab92ac15061999752e553478e493. Respuesta: simulada, sin validez tributaria.',NULL,'2026-10-09 13:54:45'),(2,1,'respuesta','error','GATEWAY_EXCEPTION','Fallo de transporte: La extensión PHP zip es necesaria para generar el CDR simulado.',NULL,'2026-10-09 13:54:45'),(3,2,'reintento','info',NULL,'Reintento 2 solicitado. Motivo: Se activo la extension zip',NULL,'2026-10-09 14:04:37'),(4,2,'solicitud','info',NULL,'Envío registrado para 20000000001-03-B001-2.xml. Clave de control: 818b5a094ec038e9c6a10aaab25cd3845fcc679b7a3f1c0644703349a2309847. Respuesta: simulada, sin validez tributaria.',NULL,'2026-10-09 14:04:37'),(5,2,'respuesta','error','1033','Comprobante rechazado: corrige el motivo indicado antes de emitir nuevamente.',NULL,'2026-10-09 14:04:37'),(6,2,'cdr','info',NULL,'CDR guardado: R-3-2.zip (SHA-256 6e0e3bfa991af477f06c0d598d2a7b2c7ff71aaf059a4e9a4913ee9808713b5c).',NULL,'2026-10-09 14:04:37'),(7,3,'solicitud','info',NULL,'Envío registrado para 20601001005-01-F001-2.xml. Clave de control: fc4ab24e98fc44220cd1444a288083000a8ad975a99794b3474183fb36da8d7c. Respuesta: simulada, sin validez tributaria.','{\"usuario_id\": \"1\", \"usuario_nombre\": \"Administrador\"}','2026-11-13 19:26:43'),(8,3,'respuesta','info',NULL,'Comprobante aceptado.','{\"usuario_id\": \"1\", \"usuario_nombre\": \"Administrador\"}','2026-11-13 19:26:43'),(9,3,'cdr','info',NULL,'CDR guardado: R-4-3.zip (SHA-256 f5d0604cffa4dcd7bf365e63aa00df4e69e1967e7f3627c936a799cf100dc8f3).','{\"usuario_id\": \"1\", \"usuario_nombre\": \"Administrador\"}','2026-11-13 19:26:43'),(10,4,'solicitud','info',NULL,'Envío registrado para 20601001005-01-F001-3.xml. Clave de control: c0512684384c5ce97aa49b34dcdbe0c3b7c40757ff8c71fe769680bd9bd5629f. Respuesta: simulada, sin validez tributaria.','{\"usuario_id\": \"1\", \"usuario_nombre\": \"Administrador\"}','2026-11-13 19:26:43'),(11,4,'respuesta','info',NULL,'Comprobante aceptado.','{\"usuario_id\": \"1\", \"usuario_nombre\": \"Administrador\"}','2026-11-13 19:26:43'),(12,4,'cdr','info',NULL,'CDR guardado: R-5-4.zip (SHA-256 7685536874680878170ee453cb0a6fa54546fa45c23f0d08332e53d142502ebc).','{\"usuario_id\": \"1\", \"usuario_nombre\": \"Administrador\"}','2026-11-13 19:26:43'),(13,5,'solicitud','info',NULL,'Envío registrado para 20601001005-07-FC01-1.xml. Clave de control: fd006f03a1e3c28ee2967e7a33e51f0a11544319070b8871ff34e74c4cad352e. Respuesta: simulada, sin validez tributaria.','{\"usuario_id\": \"1\", \"usuario_nombre\": \"Administrador\"}','2026-11-13 19:26:54'),(14,5,'respuesta','info',NULL,'Comprobante aceptado.','{\"usuario_id\": \"1\", \"usuario_nombre\": \"Administrador\"}','2026-11-13 19:26:54'),(15,5,'cdr','info',NULL,'CDR guardado: R-9-5.zip (SHA-256 44b069b92672ddcd365c5b007afcbf61e761df180dcbee378759513a37a42070).','{\"usuario_id\": \"1\", \"usuario_nombre\": \"Administrador\"}','2026-11-13 19:26:54'),(16,6,'solicitud','info',NULL,'Envío registrado para 20601001005-07-FC01-2.xml. Clave de control: 943bc6576db13569fafaa12c504a25293dce8f3bb6eef60e1b3f6379930ae75d. Respuesta: simulada, sin validez tributaria.','{\"usuario_id\": \"1\", \"usuario_nombre\": \"Administrador\"}','2026-11-13 19:26:54'),(17,6,'respuesta','info',NULL,'Comprobante aceptado.','{\"usuario_id\": \"1\", \"usuario_nombre\": \"Administrador\"}','2026-11-13 19:26:54'),(18,6,'cdr','info',NULL,'CDR guardado: R-10-6.zip (SHA-256 4f025d6c9214bfb66d142b0e3d25a088ac652731d7e1af329b2006cebee59769).','{\"usuario_id\": \"1\", \"usuario_nombre\": \"Administrador\"}','2026-11-13 19:26:54'),(19,7,'solicitud','info',NULL,'Envío registrado para 20601001005-01-F001-6.xml. Clave de control: 110c40372bb6c5054afa632dddb33cd2a52c3fd3a8cfb59ce5f348b36acca948. Respuesta: simulada, sin validez tributaria.','{\"sede_id\": \"1\", \"usuario_id\": \"1\", \"sede_nombre\": \"Sede Cañete\", \"usuario_nombre\": \"Administrador\"}','2026-11-14 15:48:33'),(20,7,'respuesta','info',NULL,'Comprobante aceptado.','{\"sede_id\": \"1\", \"usuario_id\": \"1\", \"sede_nombre\": \"Sede Cañete\", \"usuario_nombre\": \"Administrador\"}','2026-11-14 15:48:33'),(21,7,'cdr','info',NULL,'CDR guardado: R-12-7.zip (SHA-256 48d1f7024cce78c94cad048a4213a053cded3d635c000288a52a1ee97b2d5b98).','{\"sede_id\": \"1\", \"usuario_id\": \"1\", \"sede_nombre\": \"Sede Cañete\", \"usuario_nombre\": \"Administrador\"}','2026-11-14 15:48:33');
/*!40000 ALTER TABLE `facturacion_envio_eventos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `facturacion_envios`
--

DROP TABLE IF EXISTS `facturacion_envios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `facturacion_envios` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `comprobante_id` bigint unsigned NOT NULL,
  `intento` smallint unsigned NOT NULL,
  `origen` enum('simulado','proveedor') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'simulado',
  `idempotencia` char(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `estado` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ticket` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `codigo_respuesta` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mensaje_respuesta` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `tipo_respuesta` enum('aceptado','observado','rechazado','pendiente','error_transitorio') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cdr_nombre` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cdr_contenido` mediumblob,
  `cdr_sha256` char(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `solicitado_en` datetime NOT NULL,
  `respondido_en` datetime DEFAULT NULL,
  `proximo_reintento_en` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_envio_intento` (`comprobante_id`,`intento`),
  UNIQUE KEY `uq_envio_idempotencia` (`idempotencia`),
  KEY `ix_envio_estado_fecha` (`estado`,`proximo_reintento_en`),
  KEY `ix_envio_ticket` (`ticket`),
  CONSTRAINT `fk_envio_comprobante` FOREIGN KEY (`comprobante_id`) REFERENCES `facturacion_comprobantes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `facturacion_envios`
--

LOCK TABLES `facturacion_envios` WRITE;
/*!40000 ALTER TABLE `facturacion_envios` DISABLE KEYS */;
INSERT INTO `facturacion_envios` VALUES (1,3,1,'simulado','d1802e1d55a56529b71db4d1ecc0a190a29cab92ac15061999752e553478e493','error_transitorio',NULL,'GATEWAY_EXCEPTION','Fallo de transporte: La extensión PHP zip es necesaria para generar el CDR simulado.','error_transitorio',NULL,NULL,NULL,'2026-10-09 13:54:45','2026-10-09 13:54:45','2026-10-09 13:55:45'),(2,3,2,'simulado','818b5a094ec038e9c6a10aaab25cd3845fcc679b7a3f1c0644703349a2309847','rechazado',NULL,'1033','Comprobante rechazado: corrige el motivo indicado antes de emitir nuevamente.','rechazado','R-3-2.zip',_binary 'PK\0\0\0�pI]j�+c\0\0b\0\0\0\0\0R-20000000001-03-B001-2.xml�RAn\�0�\�UN�\�@�Ke�  �\�C[	�p\�%X�\�\�v\"\�\�\�RU�/�wvgf׎\�;Y@�\�\n�f�x��i.T>>?�\�\�`�\�EY�Q\�\�hK�,��TvTFM��DQ��\��\��I�Ĳ-JJv���p�g�\�Q\�G���R�E�̩C\�2���E(��\�ҧ���U�=!yI�U�ӧ\�\�xt�\�7\�0iJ�Ψrٖ\�)\��6F\�X�\�N\�\Z�\�\�\�ФZ\��\�	�\�ڻ\�\�\�A�\�=\�PFRͪ\����\�·\��Zi�\�8�N�\�۪-3�lF��:�;+dU�\�X����\�gDV9j�?��>\�;�\�o�\�\�>O~[\�0��\�f��\�_�\�Y\�\�k�\��z>w2�PK?\0\0\0�pI]j�+c\0\0b\0\0\0\0\0\0\0\0\0\0\0\0\0��\0\0\0\0R-20000000001-03-B001-2.xmlPK\0\0\0\0\0\0I\0\0\0�\0\0\0\0','6e0e3bfa991af477f06c0d598d2a7b2c7ff71aaf059a4e9a4913ee9808713b5c','2026-10-09 14:04:37','2026-10-09 14:04:37',NULL),(3,4,1,'simulado','fc4ab24e98fc44220cd1444a288083000a8ad975a99794b3474183fb36da8d7c','aceptado',NULL,'0','Comprobante aceptado.','aceptado','R-4-3.zip',_binary 'PK\0\0\0U�m]z���C\0\0&\0\0\0\0\0R-20601001005-01-F001-2.xml�R\�J\�0}\�W�>)ص(���:\�A����w5\�$%7ï7k\�\�n\�҇\��s\�9\�6\�|�J��\�\�Y0\�-L.u1\�\�V\�}0g�dQU�\�y\�\Z�2\Z��N�����\Z��\�\n�bBndZg%E\��\�\�t@\'�#2\�4\�Tp\�G��Q\�\�EQX(��\�m@;�\�(��у���	k\\/H�R�L\�a����D�\�	}1ؾ՚�k��\�͸%7��\�M���\�\�ǎ\�4\�e�h\�AK��[\�^\��%VV��Y�\\�Tu\�O7�&[^\�>��2���_�\�ߛO!�\�g�m��8\�\�n\Z\�œx�ކ\�$\\�c8�\�\�\�\�\�\�s�˔D���\0PK?\0\0\0U�m]z���C\0\0&\0\0\0\0\0\0\0\0\0\0\0\0\0��\0\0\0\0R-20601001005-01-F001-2.xmlPK\0\0\0\0\0\0I\0\0\0|\0\0\0\0','f5d0604cffa4dcd7bf365e63aa00df4e69e1967e7f3627c936a799cf100dc8f3','2026-11-13 19:26:43','2026-11-13 19:26:43',NULL),(4,5,1,'simulado','c0512684384c5ce97aa49b34dcdbe0c3b7c40757ff8c71fe769680bd9bd5629f','aceptado',NULL,'0','Comprobante aceptado.','aceptado','R-5-4.zip',_binary 'PK\0\0\0U�m]��\�eC\0\0&\0\0\0\0\0R-20601001005-01-F001-3.xml�R]K\�0}߯}R�[;Q$�su\��\nC@�\�\�@��\�t�Y�\�\"Z��\�s\�9\�\�&��UEV`Q\Z�\�0\nha\n�\�4�x����\r�i]WRp\�Y�\�h\�;5�Ac55%R\� \�\Z�\\\�ȴ\�+�\��k,h�N8�\�i\�\�\�3��\�Ӳ�Pr�\�ۀv��Q�?�GO\'&�uI� }\�\�,[�\�\�\�[2\�\�q�6�\�\�\\; \\@\�xa�[r�\�\�\\\�̈Fy�nql?M_�\�F43�h+~R�l\�\0��\�fn\�\�\"W(US�\�\rA�ɊW��/\�\�ǭ\�\�[\�\�\�C\�Qʳٖ`�\�\�w7�\�8ڼwa�so���n\�`\��\�9\�eJF=�\r�PK?\0\0\0U�m]��\�eC\0\0&\0\0\0\0\0\0\0\0\0\0\0\0\0��\0\0\0\0R-20601001005-01-F001-3.xmlPK\0\0\0\0\0\0I\0\0\0|\0\0\0\0','7685536874680878170ee453cb0a6fa54546fa45c23f0d08332e53d142502ebc','2026-11-13 19:26:43','2026-11-13 19:26:43',NULL),(5,9,1,'simulado','fd006f03a1e3c28ee2967e7a33e51f0a11544319070b8871ff34e74c4cad352e','aceptado',NULL,'0','Comprobante aceptado.','aceptado','R-9-5.zip',_binary 'PK\0\0\0[�m]��C\0\0&\0\0\0\0\0R-20601001005-07-FC01-1.xml�R\�j\�0�\�+�O-ԉ\�\�(�v=��\�~�,o\\�%�B���\�\�RZヵ3;3�V2]�����4z\�\�( ��)�.\'�\��<|�l�\�꺒�;\�Z\0\�F#ߩq4VS\�Q\"\�\\R�A\�\�L���(>Aq�Ƃ\�\�\�`@z�V�\n.�h�\Z�������;\�Go\�\�/F���=]���\�%\�\�9ci�����d�+\�W\�mZ�ɹv@��\�\�\��\�\�ѹ���\�\�\�\�~��,\�:(5�h+~R�l\�\0��\�fn\�\�\"W(US���R��d_\�Y�7�[ɯ�\�\�͇����g�-��\�\�\�n\�Eq�yo\�\�>��Q\�C�O�{��A\�\�2%��\�\�PK?\0\0\0[�m]��C\0\0&\0\0\0\0\0\0\0\0\0\0\0\0\0��\0\0\0\0R-20601001005-07-FC01-1.xmlPK\0\0\0\0\0\0I\0\0\0|\0\0\0\0','44b069b92672ddcd365c5b007afcbf61e761df180dcbee378759513a37a42070','2026-11-13 19:26:54','2026-11-13 19:26:54',NULL),(6,10,1,'simulado','943bc6576db13569fafaa12c504a25293dce8f3bb6eef60e1b3f6379930ae75d','aceptado',NULL,'0','Comprobante aceptado.','aceptado','R-10-6.zip',_binary 'PK\0\0\0[�m]�\�gD\0\0&\0\0\0\0\0R-20601001005-07-FC01-2.xml�R]K\�0}߯}R�[6\��\�e\�ց*�izWMRr\�1�\�f\�\�\�V-}h\�9\��so�\�6�\"kp���F\�!�ie\�i\�\���\�$󺮔>����5$t\Z�F�3\�\nTȌЀk�j�\'�&�\�wЂm�`=:\�$\Z����gR\�?Z�Vkk\�e\�\�1؀\���Q�?��@�G&�uI� {\�x�-\�\�\�\�%\�\�\�z\�\�Vgsa<!�\���\��\�\�t!Yfe��G�8�5M����:(�p�?���d�ҩz;7\�b�T��\�\�Ae\�ZT��\�\�/��;\�\�͇����\'����\�)\�nBo\�n\�\�\�Ƌ��\�\�0��\�\�\�~\�=�Lɨ\�\��\'PK?\0\0\0[�m]�\�gD\0\0&\0\0\0\0\0\0\0\0\0\0\0\0\0��\0\0\0\0R-20601001005-07-FC01-2.xmlPK\0\0\0\0\0\0I\0\0\0}\0\0\0\0','4f025d6c9214bfb66d142b0e3d25a088ac652731d7e1af329b2006cebee59769','2026-11-13 19:26:54','2026-11-13 19:26:54',NULL),(7,12,1,'simulado','110c40372bb6c5054afa632dddb33cd2a52c3fd3a8cfb59ce5f348b36acca948','aceptado',NULL,'0','Comprobante aceptado.','aceptado','R-12-7.zip',_binary 'PK\0\0\0~n]\�\��\�B\0\0&\0\0\0\0\0R-20601001005-01-F001-6.xml�R]K\�0}߯}R�[;pH\�2\�\�����4���&)�\��z\�n\�gE�\�!�\�\�sνM2۪�l��4z\Z\�\�( ��ɥ.��\��2|fl�̫���;\�ZVF#ߩq\Z\�VS\�Q\"\�\\R�@\�\��L묤(>Aq�Ŝ\�\�\�`@z�V�\n.�h�0J=/\nw\�?�\rh��e�3z\�tqf\�Z�\�\�\�-\�U�>��%�}ሾ\Z�i�&\�\�\�*\�s3ܑ[|O炦F\�\�{t�c�iZ��|0ꠅɁE;\�\�uK\n(����Y�ܠTu\�Ow�&^\����2���\�\�O��!G�)/f[�\��/�\�\�8�DqԼ\�a�K\'C�Ϸ{��A\�\�2%��\�\�PK?\0\0\0~n]\�\��\�B\0\0&\0\0\0\0\0\0\0\0\0\0\0\0\0��\0\0\0\0R-20601001005-01-F001-6.xmlPK\0\0\0\0\0\0I\0\0\0{\0\0\0\0','48d1f7024cce78c94cad048a4213a053cded3d635c000288a52a1ee97b2d5b98','2026-11-14 15:48:33','2026-11-14 15:48:33',NULL);
/*!40000 ALTER TABLE `facturacion_envios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `facturacion_eventos`
--

DROP TABLE IF EXISTS `facturacion_eventos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `facturacion_eventos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `comprobante_id` bigint unsigned NOT NULL,
  `estado` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `detalle` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `metadatos` json DEFAULT NULL,
  `creado_en` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_evento_comprobante` (`comprobante_id`),
  CONSTRAINT `fk_evento_comprobante` FOREIGN KEY (`comprobante_id`) REFERENCES `facturacion_comprobantes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=52 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `facturacion_eventos`
--

LOCK TABLES `facturacion_eventos` WRITE;
/*!40000 ALTER TABLE `facturacion_eventos` DISABLE KEYS */;
INSERT INTO `facturacion_eventos` VALUES (15,4,'borrador_local','Comprobante calculado; pendiente de envío electrónico.',NULL,'2026-11-13 19:11:43'),(16,4,'generado_xml','XML UBL 2.1 generado y validado (estructura y datos): 20601001005-01-F001-2.xml.',NULL,'2026-11-13 19:11:43'),(17,4,'firmado','XML firmado con el certificado «certificado-demo.pfx» y verificado (firma y contenido correctos). Hash: wwvs3SIclApfLszw1EEQOTFhUgI=',NULL,'2026-11-13 19:11:44'),(18,5,'borrador_local','Comprobante calculado; pendiente de envío electrónico.',NULL,'2026-11-13 19:11:44'),(19,5,'generado_xml','XML UBL 2.1 generado y validado (estructura y datos): 20601001005-01-F001-3.xml.',NULL,'2026-11-13 19:11:44'),(20,5,'firmado','XML firmado con el certificado «certificado-demo.pfx» y verificado (firma y contenido correctos). Hash: NhuRLqoU3uFm1jDTHQTzwQx6Bm8=',NULL,'2026-11-13 19:11:44'),(21,6,'borrador_local','Comprobante calculado; pendiente de envío electrónico.',NULL,'2026-11-13 19:11:44'),(22,6,'generado_xml','XML UBL 2.1 generado y validado (estructura y datos): 20601001005-03-B001-3.xml.',NULL,'2026-11-13 19:11:44'),(23,6,'firmado','XML firmado con el certificado «certificado-demo.pfx» y verificado (firma y contenido correctos). Hash: MZc3JPOAjiju6j4/9wPxvErkTO8=',NULL,'2026-11-13 19:11:44'),(24,7,'borrador_local','Comprobante calculado; pendiente de envío electrónico.',NULL,'2026-11-13 19:11:44'),(25,7,'generado_xml','XML UBL 2.1 generado y validado (estructura y datos): 20601001005-01-F001-4.xml.',NULL,'2026-11-13 19:11:44'),(26,7,'firmado','XML firmado con el certificado «certificado-demo.pfx» y verificado (firma y contenido correctos). Hash: M0jEJah53QRhLWC/IG1np7l806E=',NULL,'2026-11-13 19:11:44'),(27,7,'anulado_local','Anulación registrada. Motivo: Error en datos del cliente','{\"usuario_id\": \"1\", \"usuario_nombre\": \"Administrador\"}','2026-11-13 19:26:26'),(28,8,'borrador_local','Comprobante calculado; pendiente de envío electrónico.','{\"usuario_id\": \"1\", \"usuario_nombre\": \"Administrador\"}','2026-11-13 19:26:35'),(29,4,'enviado','Intento 1 transmitido al gateway.','{\"usuario_id\": \"1\", \"usuario_nombre\": \"Administrador\"}','2026-11-13 19:26:43'),(30,4,'aceptado','Intento 1: Comprobante aceptado.','{\"usuario_id\": \"1\", \"usuario_nombre\": \"Administrador\"}','2026-11-13 19:26:43'),(31,5,'enviado','Intento 1 transmitido al gateway.','{\"usuario_id\": \"1\", \"usuario_nombre\": \"Administrador\"}','2026-11-13 19:26:43'),(32,5,'aceptado','Intento 1: Comprobante aceptado.','{\"usuario_id\": \"1\", \"usuario_nombre\": \"Administrador\"}','2026-11-13 19:26:43'),(33,9,'borrador_local','Comprobante calculado; pendiente de envío electrónico.','{\"usuario_id\": \"1\", \"usuario_nombre\": \"Administrador\"}','2026-11-13 19:26:54'),(34,9,'generado_xml','XML UBL 2.1 generado y validado (estructura y datos): 20601001005-07-FC01-1.xml.','{\"usuario_id\": \"1\", \"usuario_nombre\": \"Administrador\"}','2026-11-13 19:26:54'),(35,9,'firmado','XML firmado con el certificado «certificado-demo.pfx» y verificado (firma y contenido correctos). Hash: hBcjmAO9jfrFYX49mT0hf0jt5GI=','{\"usuario_id\": \"1\", \"usuario_nombre\": \"Administrador\"}','2026-11-13 19:26:54'),(36,9,'enviado','Intento 1 transmitido al gateway.','{\"usuario_id\": \"1\", \"usuario_nombre\": \"Administrador\"}','2026-11-13 19:26:54'),(37,9,'aceptado','Intento 1: Comprobante aceptado.','{\"usuario_id\": \"1\", \"usuario_nombre\": \"Administrador\"}','2026-11-13 19:26:54'),(38,10,'borrador_local','Comprobante calculado; pendiente de envío electrónico.','{\"usuario_id\": \"1\", \"usuario_nombre\": \"Administrador\"}','2026-11-13 19:26:54'),(39,10,'generado_xml','XML UBL 2.1 generado y validado (estructura y datos): 20601001005-07-FC01-2.xml.','{\"usuario_id\": \"1\", \"usuario_nombre\": \"Administrador\"}','2026-11-13 19:26:54'),(40,10,'firmado','XML firmado con el certificado «certificado-demo.pfx» y verificado (firma y contenido correctos). Hash: FNFtyrfxUn3B8Ja+cFXLXi/wK94=','{\"usuario_id\": \"1\", \"usuario_nombre\": \"Administrador\"}','2026-11-13 19:26:54'),(41,10,'enviado','Intento 1 transmitido al gateway.','{\"usuario_id\": \"1\", \"usuario_nombre\": \"Administrador\"}','2026-11-13 19:26:54'),(42,10,'aceptado','Intento 1: Comprobante aceptado.','{\"usuario_id\": \"1\", \"usuario_nombre\": \"Administrador\"}','2026-11-13 19:26:54'),(43,11,'borrador_local','Comprobante calculado; pendiente de envío electrónico.','{\"usuario_id\": \"1\", \"usuario_nombre\": \"Administrador\"}','2026-11-13 20:59:16'),(44,12,'borrador_local','Comprobante calculado; pendiente de envío electrónico.','{\"sede_id\": \"1\", \"usuario_id\": \"1\", \"sede_nombre\": \"Sede Cañete\", \"usuario_nombre\": \"Administrador\"}','2026-11-14 15:48:33'),(45,12,'generado_xml','XML UBL 2.1 generado y validado (estructura y datos): 20601001005-01-F001-6.xml.','{\"sede_id\": \"1\", \"usuario_id\": \"1\", \"sede_nombre\": \"Sede Cañete\", \"usuario_nombre\": \"Administrador\"}','2026-11-14 15:48:33'),(46,12,'firmado','XML firmado con el certificado «certificado-demo.pfx» y verificado (firma y contenido correctos). Hash: 1t06SupsuSslBsEdXMdIdXvTIZo=','{\"sede_id\": \"1\", \"usuario_id\": \"1\", \"sede_nombre\": \"Sede Cañete\", \"usuario_nombre\": \"Administrador\"}','2026-11-14 15:48:33'),(47,12,'enviado','Intento 1 transmitido al gateway.','{\"sede_id\": \"1\", \"usuario_id\": \"1\", \"sede_nombre\": \"Sede Cañete\", \"usuario_nombre\": \"Administrador\"}','2026-11-14 15:48:33'),(48,12,'aceptado','Intento 1: Comprobante aceptado.','{\"sede_id\": \"1\", \"usuario_id\": \"1\", \"sede_nombre\": \"Sede Cañete\", \"usuario_nombre\": \"Administrador\"}','2026-11-14 15:48:33'),(49,13,'borrador_local','Comprobante calculado; pendiente de envío electrónico.','{\"sede_id\": \"2\", \"usuario_id\": \"1\", \"sede_nombre\": \"Sede Mala\", \"usuario_nombre\": \"Administrador\"}','2026-11-14 15:48:49'),(50,13,'generado_xml','XML UBL 2.1 generado y validado (estructura y datos): 20601001005-03-B002-1.xml.','{\"sede_id\": \"2\", \"usuario_id\": \"1\", \"sede_nombre\": \"Sede Mala\", \"usuario_nombre\": \"Administrador\"}','2026-11-14 15:48:49'),(51,13,'firmado','XML firmado con el certificado «certificado-demo.pfx» y verificado (firma y contenido correctos). Hash: RPGO/VVSIscJYcEWdoXrK6Tq25M=','{\"sede_id\": \"2\", \"usuario_id\": \"1\", \"sede_nombre\": \"Sede Mala\", \"usuario_nombre\": \"Administrador\"}','2026-11-14 15:48:49');
/*!40000 ALTER TABLE `facturacion_eventos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `facturacion_series`
--

DROP TABLE IF EXISTS `facturacion_series`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `facturacion_series` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tipo` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `serie` varchar(4) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sucursal_id` int DEFAULT NULL,
  `descripcion` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `siguiente` int unsigned NOT NULL DEFAULT '1',
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tipo_serie` (`tipo`,`serie`),
  KEY `fk_serie_sucursal` (`sucursal_id`),
  CONSTRAINT `fk_serie_sucursal` FOREIGN KEY (`sucursal_id`) REFERENCES `sucursales` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `facturacion_series`
--

LOCK TABLES `facturacion_series` WRITE;
/*!40000 ALTER TABLE `facturacion_series` DISABLE KEYS */;
INSERT INTO `facturacion_series` VALUES (1,'factura','F001',NULL,NULL,7,1,'2026-09-30 12:09:57','2026-11-14 15:48:33'),(2,'boleta','B001',NULL,NULL,4,1,'2026-09-30 12:09:57','2026-11-13 19:11:44'),(3,'nota_credito','FC01',NULL,NULL,3,1,'2026-09-30 12:09:57','2026-11-13 19:26:54'),(4,'nota_debito','FD01',NULL,NULL,1,1,'2026-09-30 12:09:57','2026-09-30 12:09:57'),(5,'nota_credito','BC01',NULL,'Notas de crédito de boletas',1,1,'2026-10-14 19:25:57','2026-10-14 19:25:57'),(6,'nota_debito','BD01',NULL,'Notas de débito de boletas',1,1,'2026-10-14 19:25:57','2026-10-14 19:25:57'),(7,'factura','F002',2,'Facturas Sede Mala',2,1,'2026-11-13 20:59:16','2026-11-13 20:59:16'),(8,'boleta','B002',2,'Boletas Sede Mala',2,1,'2026-11-14 15:43:19','2026-11-14 15:48:49');
/*!40000 ALTER TABLE `facturacion_series` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `facturacion_series_cambios`
--

DROP TABLE IF EXISTS `facturacion_series_cambios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `facturacion_series_cambios` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `serie_id` int unsigned NOT NULL,
  `valor_anterior` int unsigned DEFAULT NULL,
  `valor_nuevo` int unsigned NOT NULL,
  `motivo` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `usuario` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `creado_en` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_serie_cambio_fecha` (`serie_id`,`creado_en`),
  CONSTRAINT `fk_serie_cambio_serie` FOREIGN KEY (`serie_id`) REFERENCES `facturacion_series` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `facturacion_series_cambios`
--

LOCK TABLES `facturacion_series_cambios` WRITE;
/*!40000 ALTER TABLE `facturacion_series_cambios` DISABLE KEYS */;
INSERT INTO `facturacion_series_cambios` VALUES (1,5,NULL,1,'Creación de la serie (migración 009).','Sistema','2026-10-14 19:25:57'),(2,6,NULL,1,'Creación de la serie (migración 009).','Sistema','2026-10-14 19:25:57'),(4,7,NULL,1,'Creación de la serie.','Administrador','2026-11-13 20:59:16');
/*!40000 ALTER TABLE `facturacion_series_cambios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `facturacion_xml`
--

DROP TABLE IF EXISTS `facturacion_xml`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `facturacion_xml` (
  `comprobante_id` bigint unsigned NOT NULL,
  `nombre_archivo` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `xml` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `hash` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `generado_en` datetime NOT NULL,
  `firmado_en` datetime DEFAULT NULL,
  PRIMARY KEY (`comprobante_id`),
  CONSTRAINT `fk_xml_comprobante` FOREIGN KEY (`comprobante_id`) REFERENCES `facturacion_comprobantes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `facturacion_xml`
--

LOCK TABLES `facturacion_xml` WRITE;
/*!40000 ALTER TABLE `facturacion_xml` DISABLE KEYS */;
INSERT INTO `facturacion_xml` VALUES (1,'20000000001-01-F001-1.xml','<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<Invoice xmlns=\"urn:oasis:names:specification:ubl:schema:xsd:Invoice-2\" xmlns:cac=\"urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2\" xmlns:cbc=\"urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2\" xmlns:ext=\"urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2\" xmlns:ds=\"http://www.w3.org/2000/09/xmldsig#\">\n  <ext:UBLExtensions>\n    <ext:UBLExtension>\n      <ext:ExtensionContent><ds:Signature Id=\"SignatureSP\"><ds:SignedInfo><ds:CanonicalizationMethod Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/><ds:SignatureMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#rsa-sha1\"/><ds:Reference URI=\"\"><ds:Transforms><ds:Transform Algorithm=\"http://www.w3.org/2000/09/xmldsig#enveloped-signature\"/></ds:Transforms><ds:DigestMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#sha1\"/><ds:DigestValue>EYtJXQ6qLqcMjOrkp8jVSU4DTwo=</ds:DigestValue></ds:Reference></ds:SignedInfo><ds:SignatureValue>o3Q+d+0c49jxXboS6pMqXLnwzj5+taKAuzVAir8UaMgu9m+1ppPI96+gH9ocdZ2QgcmgJ95rkhNekWeG4Dh3pFmc8QrN6jZpDUXarJ5rVAY7zxMkejSVgudBsjJIOLKlKLcboYNTB5b+zMzTBzxnZ8OVBfpgCawoASmH+rSPNSRRvRhWSABS2hROzgsO8ScFRWto24tLe8USJ0n/R1N++1xJgcEK+pNJv1O+fW5ro/pHS8xU2POPjyiG+gS/mL63io5ID4WwMNJO9xmSRb169FTTRO3tTehN5thfRnpUr//engxJ+xQ1MfoYXG+3Svi3ht6XLgGBJKi+yCiKo+CYiA==</ds:SignatureValue><ds:KeyInfo><ds:X509Data><ds:X509Certificate>MIIDRTCCAi2gAwIBAgIBADANBgkqhkiG9w0BAQsFADBmMQswCQYDVQQGEwJQRTEnMCUGA1UECgweQUdSSUNPTEEgUFVDQ0hVTiBTLkEuQy4gKERFTU8pMS4wLAYDVQQDDCVDZXJ0aWZpY2FkbyBkZSBwcnVlYmEgRVJQIEZhY3R1cmFjaW9uMB4XDTI2MDkzMDE3Mjg0OVoXDTI3MDkzMDE3Mjg0OVowZjELMAkGA1UEBhMCUEUxJzAlBgNVBAoMHkFHUklDT0xBIFBVQ0NIVU4gUy5BLkMuIChERU1PKTEuMCwGA1UEAwwlQ2VydGlmaWNhZG8gZGUgcHJ1ZWJhIEVSUCBGYWN0dXJhY2lvbjCCASIwDQYJKoZIhvcNAQEBBQADggEPADCCAQoCggEBAMuSbhRTsaO4XShapf0TZxBwC+NTThWSBmBVCRsJMeC6oJB/Qy4KHpLn6kVpT231WjvN9YKfv+JIrTBF2b4NOGENgn86agCeUULJv7Burlg1ARhZalj1KDi3tPY5TzxzXqzS2mXeux/3/hXoj4998mPZcUFn3ii8cPkFNnC5CC2fAcjVCID0Ad4kP7zWd+K6TeXuXk9lWd2kNNDO5fcVWxH1qzPy6LdnGfjTqY345F5UgAPZ45OJh/qQQhGtd/OgwqSEG8qwFnOs/J/XsnXYH2qD0oAMgeiO566cazjRCNIAsivcF12b5dJtUR6W+aTwJtv4Fp/am59zSHU7Oh3CgJ0CAwEAATANBgkqhkiG9w0BAQsFAAOCAQEATTvnG4Ux8dOoRmMTAIAMpyTvoySUsh+zGFKtJ1VwOi2tt/s0kl9X5F3JjaOiUl0iA/Yl7i4T81af5bHydZ2tHZ6EWPXdqUx20eJhnRCloo6jaKndDao4xuJhYbIAlYy59yEytBzMv5t05rnTlAQBS26fISQO7nnhOcC5xilo+ChAZubBGkWkSmt4fkPkfZf++di+t4k6fUdajggK43a7v3fre68lGKCEKD+8SL3O+qtRyWFw79qh8DRd127R8V5Xazq6CrdD5VuxZH2qG0IRZAHWg0B82rXz778ekL3XAqnJ4sGCQkqtIpS5xPT2OI29u0I7POzBN21HZHUQG7B2HA==</ds:X509Certificate></ds:X509Data></ds:KeyInfo></ds:Signature></ext:ExtensionContent>\n    </ext:UBLExtension>\n  </ext:UBLExtensions>\n  <cbc:UBLVersionID>2.1</cbc:UBLVersionID>\n  <cbc:CustomizationID>2.0</cbc:CustomizationID>\n  <cbc:ID>F001-1</cbc:ID>\n  <cbc:IssueDate>2026-09-30</cbc:IssueDate>\n  <cbc:IssueTime>13:55:27</cbc:IssueTime>\n  <cbc:InvoiceTypeCode listID=\"0101\" listAgencyName=\"PE:SUNAT\" listName=\"Tipo de Documento\" listURI=\"urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo01\">01</cbc:InvoiceTypeCode>\n  <cbc:Note languageLocaleID=\"1000\">SON DOSCIENTOS DOCE CON 40/100 SOLES</cbc:Note>\n  <cbc:DocumentCurrencyCode>PEN</cbc:DocumentCurrencyCode>\n  <cac:Signature>\n    <cbc:ID>SignatureSP</cbc:ID>\n    <cac:SignatoryParty>\n      <cac:PartyIdentification>\n        <cbc:ID>20000000001</cbc:ID>\n      </cac:PartyIdentification>\n      <cac:PartyName>\n        <cbc:Name>AGRICOLA PUCCHUN S.A.C.</cbc:Name>\n      </cac:PartyName>\n    </cac:SignatoryParty>\n    <cac:DigitalSignatureAttachment>\n      <cac:ExternalReference>\n        <cbc:URI>#SignatureSP</cbc:URI>\n      </cac:ExternalReference>\n    </cac:DigitalSignatureAttachment>\n  </cac:Signature>\n  <cac:AccountingSupplierParty>\n    <cac:Party>\n      <cac:PartyIdentification>\n        <cbc:ID schemeID=\"6\" schemeName=\"Documento de Identidad\" schemeAgencyName=\"PE:SUNAT\" schemeURI=\"urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo06\">20000000001</cbc:ID>\n      </cac:PartyIdentification>\n      <cac:PartyName>\n        <cbc:Name>Agrícola Pucchún</cbc:Name>\n      </cac:PartyName>\n      <cac:PartyLegalEntity>\n        <cbc:RegistrationName>AGRICOLA PUCCHUN S.A.C.</cbc:RegistrationName>\n        <cac:RegistrationAddress>\n          <cbc:ID schemeAgencyName=\"PE:INEI\" schemeName=\"Ubigeos\">150101</cbc:ID>\n          <cbc:AddressTypeCode>0000</cbc:AddressTypeCode>\n          <cbc:CityName>LIMA</cbc:CityName>\n          <cbc:CountrySubentity>LIMA</cbc:CountrySubentity>\n          <cbc:District>LIMA</cbc:District>\n          <cac:AddressLine>\n            <cbc:Line>Av. Demostración 123</cbc:Line>\n          </cac:AddressLine>\n          <cac:Country>\n            <cbc:IdentificationCode>PE</cbc:IdentificationCode>\n          </cac:Country>\n        </cac:RegistrationAddress>\n      </cac:PartyLegalEntity>\n    </cac:Party>\n  </cac:AccountingSupplierParty>\n  <cac:AccountingCustomerParty>\n    <cac:Party>\n      <cac:PartyIdentification>\n        <cbc:ID schemeID=\"6\" schemeName=\"Documento de Identidad\" schemeAgencyName=\"PE:SUNAT\" schemeURI=\"urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo06\">20123456789</cbc:ID>\n      </cac:PartyIdentification>\n      <cac:PartyLegalEntity>\n        <cbc:RegistrationName>Comercial Demo del Norte S.A.C.</cbc:RegistrationName>\n      </cac:PartyLegalEntity>\n    </cac:Party>\n  </cac:AccountingCustomerParty>\n  <cac:PaymentTerms>\n    <cbc:ID>FormaPago</cbc:ID>\n    <cbc:PaymentMeansID>Contado</cbc:PaymentMeansID>\n  </cac:PaymentTerms>\n  <cac:TaxTotal>\n    <cbc:TaxAmount currencyID=\"PEN\">32.40</cbc:TaxAmount>\n    <cac:TaxSubtotal>\n      <cbc:TaxableAmount currencyID=\"PEN\">180.00</cbc:TaxableAmount>\n      <cbc:TaxAmount currencyID=\"PEN\">32.40</cbc:TaxAmount>\n      <cac:TaxCategory>\n        <cac:TaxScheme>\n          <cbc:ID>1000</cbc:ID>\n          <cbc:Name>IGV</cbc:Name>\n          <cbc:TaxTypeCode>VAT</cbc:TaxTypeCode>\n        </cac:TaxScheme>\n      </cac:TaxCategory>\n    </cac:TaxSubtotal>\n  </cac:TaxTotal>\n  <cac:LegalMonetaryTotal>\n    <cbc:LineExtensionAmount currencyID=\"PEN\">180.00</cbc:LineExtensionAmount>\n    <cbc:TaxInclusiveAmount currencyID=\"PEN\">212.40</cbc:TaxInclusiveAmount>\n    <cbc:PayableAmount currencyID=\"PEN\">212.40</cbc:PayableAmount>\n  </cac:LegalMonetaryTotal>\n  <cac:InvoiceLine>\n    <cbc:ID>1</cbc:ID>\n    <cbc:InvoicedQuantity unitCode=\"NIU\" unitCodeListID=\"UN/ECE rec 20\" unitCodeListAgencyName=\"United Nations Economic Commission for Europe\">4</cbc:InvoicedQuantity>\n    <cbc:LineExtensionAmount currencyID=\"PEN\">180.00</cbc:LineExtensionAmount>\n    <cac:PricingReference>\n      <cac:AlternativeConditionPrice>\n        <cbc:PriceAmount currencyID=\"PEN\">53.10</cbc:PriceAmount>\n        <cbc:PriceTypeCode>01</cbc:PriceTypeCode>\n      </cac:AlternativeConditionPrice>\n    </cac:PricingReference>\n    <cac:TaxTotal>\n      <cbc:TaxAmount currencyID=\"PEN\">32.40</cbc:TaxAmount>\n      <cac:TaxSubtotal>\n        <cbc:TaxableAmount currencyID=\"PEN\">180.00</cbc:TaxableAmount>\n        <cbc:TaxAmount currencyID=\"PEN\">32.40</cbc:TaxAmount>\n        <cac:TaxCategory>\n          <cbc:Percent>18</cbc:Percent>\n          <cbc:TaxExemptionReasonCode>10</cbc:TaxExemptionReasonCode>\n          <cac:TaxScheme>\n            <cbc:ID>1000</cbc:ID>\n            <cbc:Name>IGV</cbc:Name>\n            <cbc:TaxTypeCode>VAT</cbc:TaxTypeCode>\n          </cac:TaxScheme>\n        </cac:TaxCategory>\n      </cac:TaxSubtotal>\n    </cac:TaxTotal>\n    <cac:Item>\n      <cbc:Description>Mango Kent (caja de 10 kg)</cbc:Description>\n      <cac:SellersItemIdentification>\n        <cbc:ID>PROD-001</cbc:ID>\n      </cac:SellersItemIdentification>\n    </cac:Item>\n    <cac:Price>\n      <cbc:PriceAmount currencyID=\"PEN\">45.00</cbc:PriceAmount>\n    </cac:Price>\n  </cac:InvoiceLine>\n</Invoice>\n','EYtJXQ6qLqcMjOrkp8jVSU4DTwo=','2026-09-30 13:55:32','2026-09-30 13:55:36'),(2,'20000000001-03-B001-1.xml','<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<Invoice xmlns=\"urn:oasis:names:specification:ubl:schema:xsd:Invoice-2\" xmlns:cac=\"urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2\" xmlns:cbc=\"urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2\" xmlns:ext=\"urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2\" xmlns:ds=\"http://www.w3.org/2000/09/xmldsig#\">\n  <ext:UBLExtensions>\n    <ext:UBLExtension>\n      <ext:ExtensionContent><ds:Signature Id=\"SignatureSP\"><ds:SignedInfo><ds:CanonicalizationMethod Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/><ds:SignatureMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#rsa-sha1\"/><ds:Reference URI=\"\"><ds:Transforms><ds:Transform Algorithm=\"http://www.w3.org/2000/09/xmldsig#enveloped-signature\"/></ds:Transforms><ds:DigestMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#sha1\"/><ds:DigestValue>FHVdzhmQ6+CAjp2m3UPT2rltEpg=</ds:DigestValue></ds:Reference></ds:SignedInfo><ds:SignatureValue>Lt8sXIRu+hx9Js/gOnFaBCk6LvIuT9ANz9KI1XwynMc5HvPc5a99JW22UommdJmBYbBwb7XvCR9hhd4eXs7YfEOEWnr6DdS/wg0h98lFvsZSWZvqb6KUkTnR47mHsjc+Q+xQ5QN9uzpAyNVLQXsvNv/4NunZiUzoAzD9g0bGmA3sBcalWsEUJQPg1svOchSAuIgmzGe+s4HAFApqfYGe1g39fzwnPwJEZtsIvavz1mHVo1QlEc49PX6RyZpcAySJ/eRIKJtqRFvdRObmYXBOIwejqRsV0j+9eyb8HGO0NQ6CH6YUYCbIPvT9izNeWlmeEF7P6E+Q/e2qkFUMvxtyMQ==</ds:SignatureValue><ds:KeyInfo><ds:X509Data><ds:X509Certificate>MIIDRTCCAi2gAwIBAgIBADANBgkqhkiG9w0BAQsFADBmMQswCQYDVQQGEwJQRTEnMCUGA1UECgweQUdSSUNPTEEgUFVDQ0hVTiBTLkEuQy4gKERFTU8pMS4wLAYDVQQDDCVDZXJ0aWZpY2FkbyBkZSBwcnVlYmEgRVJQIEZhY3R1cmFjaW9uMB4XDTI2MDkzMDE3Mjg0OVoXDTI3MDkzMDE3Mjg0OVowZjELMAkGA1UEBhMCUEUxJzAlBgNVBAoMHkFHUklDT0xBIFBVQ0NIVU4gUy5BLkMuIChERU1PKTEuMCwGA1UEAwwlQ2VydGlmaWNhZG8gZGUgcHJ1ZWJhIEVSUCBGYWN0dXJhY2lvbjCCASIwDQYJKoZIhvcNAQEBBQADggEPADCCAQoCggEBAMuSbhRTsaO4XShapf0TZxBwC+NTThWSBmBVCRsJMeC6oJB/Qy4KHpLn6kVpT231WjvN9YKfv+JIrTBF2b4NOGENgn86agCeUULJv7Burlg1ARhZalj1KDi3tPY5TzxzXqzS2mXeux/3/hXoj4998mPZcUFn3ii8cPkFNnC5CC2fAcjVCID0Ad4kP7zWd+K6TeXuXk9lWd2kNNDO5fcVWxH1qzPy6LdnGfjTqY345F5UgAPZ45OJh/qQQhGtd/OgwqSEG8qwFnOs/J/XsnXYH2qD0oAMgeiO566cazjRCNIAsivcF12b5dJtUR6W+aTwJtv4Fp/am59zSHU7Oh3CgJ0CAwEAATANBgkqhkiG9w0BAQsFAAOCAQEATTvnG4Ux8dOoRmMTAIAMpyTvoySUsh+zGFKtJ1VwOi2tt/s0kl9X5F3JjaOiUl0iA/Yl7i4T81af5bHydZ2tHZ6EWPXdqUx20eJhnRCloo6jaKndDao4xuJhYbIAlYy59yEytBzMv5t05rnTlAQBS26fISQO7nnhOcC5xilo+ChAZubBGkWkSmt4fkPkfZf++di+t4k6fUdajggK43a7v3fre68lGKCEKD+8SL3O+qtRyWFw79qh8DRd127R8V5Xazq6CrdD5VuxZH2qG0IRZAHWg0B82rXz778ekL3XAqnJ4sGCQkqtIpS5xPT2OI29u0I7POzBN21HZHUQG7B2HA==</ds:X509Certificate></ds:X509Data></ds:KeyInfo></ds:Signature></ext:ExtensionContent>\n    </ext:UBLExtension>\n  </ext:UBLExtensions>\n  <cbc:UBLVersionID>2.1</cbc:UBLVersionID>\n  <cbc:CustomizationID>2.0</cbc:CustomizationID>\n  <cbc:ID>B001-1</cbc:ID>\n  <cbc:IssueDate>2026-10-09</cbc:IssueDate>\n  <cbc:IssueTime>13:48:44</cbc:IssueTime>\n  <cbc:InvoiceTypeCode listID=\"0101\" listAgencyName=\"PE:SUNAT\" listName=\"Tipo de Documento\" listURI=\"urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo01\">03</cbc:InvoiceTypeCode>\n  <cbc:Note languageLocaleID=\"1000\">SON CUARENTA Y DOS CON 48/100 SOLES</cbc:Note>\n  <cbc:DocumentCurrencyCode>PEN</cbc:DocumentCurrencyCode>\n  <cac:Signature>\n    <cbc:ID>SignatureSP</cbc:ID>\n    <cac:SignatoryParty>\n      <cac:PartyIdentification>\n        <cbc:ID>20000000001</cbc:ID>\n      </cac:PartyIdentification>\n      <cac:PartyName>\n        <cbc:Name>AGRICOLA PUCCHUN S.A.C.</cbc:Name>\n      </cac:PartyName>\n    </cac:SignatoryParty>\n    <cac:DigitalSignatureAttachment>\n      <cac:ExternalReference>\n        <cbc:URI>#SignatureSP</cbc:URI>\n      </cac:ExternalReference>\n    </cac:DigitalSignatureAttachment>\n  </cac:Signature>\n  <cac:AccountingSupplierParty>\n    <cac:Party>\n      <cac:PartyIdentification>\n        <cbc:ID schemeID=\"6\" schemeName=\"Documento de Identidad\" schemeAgencyName=\"PE:SUNAT\" schemeURI=\"urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo06\">20000000001</cbc:ID>\n      </cac:PartyIdentification>\n      <cac:PartyName>\n        <cbc:Name>Agrícola Pucchún</cbc:Name>\n      </cac:PartyName>\n      <cac:PartyLegalEntity>\n        <cbc:RegistrationName>AGRICOLA PUCCHUN S.A.C.</cbc:RegistrationName>\n        <cac:RegistrationAddress>\n          <cbc:ID schemeAgencyName=\"PE:INEI\" schemeName=\"Ubigeos\">150101</cbc:ID>\n          <cbc:AddressTypeCode>0000</cbc:AddressTypeCode>\n          <cbc:CityName>LIMA</cbc:CityName>\n          <cbc:CountrySubentity>LIMA</cbc:CountrySubentity>\n          <cbc:District>LIMA</cbc:District>\n          <cac:AddressLine>\n            <cbc:Line>Av. Demostración 123</cbc:Line>\n          </cac:AddressLine>\n          <cac:Country>\n            <cbc:IdentificationCode>PE</cbc:IdentificationCode>\n          </cac:Country>\n        </cac:RegistrationAddress>\n      </cac:PartyLegalEntity>\n    </cac:Party>\n  </cac:AccountingSupplierParty>\n  <cac:AccountingCustomerParty>\n    <cac:Party>\n      <cac:PartyIdentification>\n        <cbc:ID schemeID=\"1\" schemeName=\"Documento de Identidad\" schemeAgencyName=\"PE:SUNAT\" schemeURI=\"urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo06\">45678912</cbc:ID>\n      </cac:PartyIdentification>\n      <cac:PartyLegalEntity>\n        <cbc:RegistrationName>María Ejemplo Pérez</cbc:RegistrationName>\n      </cac:PartyLegalEntity>\n    </cac:Party>\n  </cac:AccountingCustomerParty>\n  <cac:TaxTotal>\n    <cbc:TaxAmount currencyID=\"PEN\">6.48</cbc:TaxAmount>\n    <cac:TaxSubtotal>\n      <cbc:TaxableAmount currencyID=\"PEN\">36.00</cbc:TaxableAmount>\n      <cbc:TaxAmount currencyID=\"PEN\">6.48</cbc:TaxAmount>\n      <cac:TaxCategory>\n        <cac:TaxScheme>\n          <cbc:ID>1000</cbc:ID>\n          <cbc:Name>IGV</cbc:Name>\n          <cbc:TaxTypeCode>VAT</cbc:TaxTypeCode>\n        </cac:TaxScheme>\n      </cac:TaxCategory>\n    </cac:TaxSubtotal>\n  </cac:TaxTotal>\n  <cac:LegalMonetaryTotal>\n    <cbc:LineExtensionAmount currencyID=\"PEN\">36.00</cbc:LineExtensionAmount>\n    <cbc:TaxInclusiveAmount currencyID=\"PEN\">42.48</cbc:TaxInclusiveAmount>\n    <cbc:PayableAmount currencyID=\"PEN\">42.48</cbc:PayableAmount>\n  </cac:LegalMonetaryTotal>\n  <cac:InvoiceLine>\n    <cbc:ID>1</cbc:ID>\n    <cbc:InvoicedQuantity unitCode=\"ZZ\" unitCodeListID=\"UN/ECE rec 20\" unitCodeListAgencyName=\"United Nations Economic Commission for Europe\">3</cbc:InvoicedQuantity>\n    <cbc:LineExtensionAmount currencyID=\"PEN\">36.00</cbc:LineExtensionAmount>\n    <cac:PricingReference>\n      <cac:AlternativeConditionPrice>\n        <cbc:PriceAmount currencyID=\"PEN\">14.16</cbc:PriceAmount>\n        <cbc:PriceTypeCode>01</cbc:PriceTypeCode>\n      </cac:AlternativeConditionPrice>\n    </cac:PricingReference>\n    <cac:TaxTotal>\n      <cbc:TaxAmount currencyID=\"PEN\">6.48</cbc:TaxAmount>\n      <cac:TaxSubtotal>\n        <cbc:TaxableAmount currencyID=\"PEN\">36.00</cbc:TaxableAmount>\n        <cbc:TaxAmount currencyID=\"PEN\">6.48</cbc:TaxAmount>\n        <cac:TaxCategory>\n          <cbc:Percent>18</cbc:Percent>\n          <cbc:TaxExemptionReasonCode>10</cbc:TaxExemptionReasonCode>\n          <cac:TaxScheme>\n            <cbc:ID>1000</cbc:ID>\n            <cbc:Name>IGV</cbc:Name>\n            <cbc:TaxTypeCode>VAT</cbc:TaxTypeCode>\n          </cac:TaxScheme>\n        </cac:TaxCategory>\n      </cac:TaxSubtotal>\n    </cac:TaxTotal>\n    <cac:Item>\n      <cbc:Description>Servicio de embalaje</cbc:Description>\n      <cac:SellersItemIdentification>\n        <cbc:ID>SERV-001</cbc:ID>\n      </cac:SellersItemIdentification>\n    </cac:Item>\n    <cac:Price>\n      <cbc:PriceAmount currencyID=\"PEN\">12.00</cbc:PriceAmount>\n    </cac:Price>\n  </cac:InvoiceLine>\n</Invoice>\n','FHVdzhmQ6+CAjp2m3UPT2rltEpg=','2026-10-09 13:48:50','2026-10-09 14:02:34'),(3,'20000000001-03-B001-2.xml','<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<Invoice xmlns=\"urn:oasis:names:specification:ubl:schema:xsd:Invoice-2\" xmlns:cac=\"urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2\" xmlns:cbc=\"urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2\" xmlns:ext=\"urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2\" xmlns:ds=\"http://www.w3.org/2000/09/xmldsig#\">\n  <ext:UBLExtensions>\n    <ext:UBLExtension>\n      <ext:ExtensionContent/>\n    </ext:UBLExtension>\n  </ext:UBLExtensions>\n  <cbc:UBLVersionID>2.1</cbc:UBLVersionID>\n  <cbc:CustomizationID>2.0</cbc:CustomizationID>\n  <cbc:ID>B001-2</cbc:ID>\n  <cbc:IssueDate>2026-10-09</cbc:IssueDate>\n  <cbc:IssueTime>13:54:07</cbc:IssueTime>\n  <cbc:InvoiceTypeCode listID=\"0101\" listAgencyName=\"PE:SUNAT\" listName=\"Tipo de Documento\" listURI=\"urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo01\">03</cbc:InvoiceTypeCode>\n  <cbc:Note languageLocaleID=\"1000\">SON CINCO CON 66/100 SOLES</cbc:Note>\n  <cbc:DocumentCurrencyCode>PEN</cbc:DocumentCurrencyCode>\n  <cac:Signature>\n    <cbc:ID>SignatureSP</cbc:ID>\n    <cac:SignatoryParty>\n      <cac:PartyIdentification>\n        <cbc:ID>20000000001</cbc:ID>\n      </cac:PartyIdentification>\n      <cac:PartyName>\n        <cbc:Name>AGRICOLA PUCCHUN S.A.C.</cbc:Name>\n      </cac:PartyName>\n    </cac:SignatoryParty>\n    <cac:DigitalSignatureAttachment>\n      <cac:ExternalReference>\n        <cbc:URI>#SignatureSP</cbc:URI>\n      </cac:ExternalReference>\n    </cac:DigitalSignatureAttachment>\n  </cac:Signature>\n  <cac:AccountingSupplierParty>\n    <cac:Party>\n      <cac:PartyIdentification>\n        <cbc:ID schemeID=\"6\" schemeName=\"Documento de Identidad\" schemeAgencyName=\"PE:SUNAT\" schemeURI=\"urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo06\">20000000001</cbc:ID>\n      </cac:PartyIdentification>\n      <cac:PartyName>\n        <cbc:Name>Agrícola Pucchún</cbc:Name>\n      </cac:PartyName>\n      <cac:PartyLegalEntity>\n        <cbc:RegistrationName>AGRICOLA PUCCHUN S.A.C.</cbc:RegistrationName>\n        <cac:RegistrationAddress>\n          <cbc:ID schemeAgencyName=\"PE:INEI\" schemeName=\"Ubigeos\">150101</cbc:ID>\n          <cbc:AddressTypeCode>0000</cbc:AddressTypeCode>\n          <cbc:CityName>LIMA</cbc:CityName>\n          <cbc:CountrySubentity>LIMA</cbc:CountrySubentity>\n          <cbc:District>LIMA</cbc:District>\n          <cac:AddressLine>\n            <cbc:Line>Av. Demostración 123</cbc:Line>\n          </cac:AddressLine>\n          <cac:Country>\n            <cbc:IdentificationCode>PE</cbc:IdentificationCode>\n          </cac:Country>\n        </cac:RegistrationAddress>\n      </cac:PartyLegalEntity>\n    </cac:Party>\n  </cac:AccountingSupplierParty>\n  <cac:AccountingCustomerParty>\n    <cac:Party>\n      <cac:PartyIdentification>\n        <cbc:ID schemeID=\"1\" schemeName=\"Documento de Identidad\" schemeAgencyName=\"PE:SUNAT\" schemeURI=\"urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo06\">72664039</cbc:ID>\n      </cac:PartyIdentification>\n      <cac:PartyLegalEntity>\n        <cbc:RegistrationName>2345245345</cbc:RegistrationName>\n      </cac:PartyLegalEntity>\n    </cac:Party>\n  </cac:AccountingCustomerParty>\n  <cac:TaxTotal>\n    <cbc:TaxAmount currencyID=\"PEN\">0.86</cbc:TaxAmount>\n    <cac:TaxSubtotal>\n      <cbc:TaxableAmount currencyID=\"PEN\">4.80</cbc:TaxableAmount>\n      <cbc:TaxAmount currencyID=\"PEN\">0.86</cbc:TaxAmount>\n      <cac:TaxCategory>\n        <cac:TaxScheme>\n          <cbc:ID>1000</cbc:ID>\n          <cbc:Name>IGV</cbc:Name>\n          <cbc:TaxTypeCode>VAT</cbc:TaxTypeCode>\n        </cac:TaxScheme>\n      </cac:TaxCategory>\n    </cac:TaxSubtotal>\n  </cac:TaxTotal>\n  <cac:LegalMonetaryTotal>\n    <cbc:LineExtensionAmount currencyID=\"PEN\">4.80</cbc:LineExtensionAmount>\n    <cbc:TaxInclusiveAmount currencyID=\"PEN\">5.66</cbc:TaxInclusiveAmount>\n    <cbc:PayableAmount currencyID=\"PEN\">5.66</cbc:PayableAmount>\n  </cac:LegalMonetaryTotal>\n  <cac:InvoiceLine>\n    <cbc:ID>1</cbc:ID>\n    <cbc:InvoicedQuantity unitCode=\"NIU\" unitCodeListID=\"UN/ECE rec 20\" unitCodeListAgencyName=\"United Nations Economic Commission for Europe\">1</cbc:InvoicedQuantity>\n    <cbc:LineExtensionAmount currencyID=\"PEN\">4.80</cbc:LineExtensionAmount>\n    <cac:PricingReference>\n      <cac:AlternativeConditionPrice>\n        <cbc:PriceAmount currencyID=\"PEN\">5.66</cbc:PriceAmount>\n        <cbc:PriceTypeCode>01</cbc:PriceTypeCode>\n      </cac:AlternativeConditionPrice>\n    </cac:PricingReference>\n    <cac:TaxTotal>\n      <cbc:TaxAmount currencyID=\"PEN\">0.86</cbc:TaxAmount>\n      <cac:TaxSubtotal>\n        <cbc:TaxableAmount currencyID=\"PEN\">4.80</cbc:TaxableAmount>\n        <cbc:TaxAmount currencyID=\"PEN\">0.86</cbc:TaxAmount>\n        <cac:TaxCategory>\n          <cbc:Percent>18</cbc:Percent>\n          <cbc:TaxExemptionReasonCode>10</cbc:TaxExemptionReasonCode>\n          <cac:TaxScheme>\n            <cbc:ID>1000</cbc:ID>\n            <cbc:Name>IGV</cbc:Name>\n            <cbc:TaxTypeCode>VAT</cbc:TaxTypeCode>\n          </cac:TaxScheme>\n        </cac:TaxCategory>\n      </cac:TaxSubtotal>\n    </cac:TaxTotal>\n    <cac:Item>\n      <cbc:Description>Tomate (kg)</cbc:Description>\n      <cac:SellersItemIdentification>\n        <cbc:ID>PROD-007</cbc:ID>\n      </cac:SellersItemIdentification>\n    </cac:Item>\n    <cac:Price>\n      <cbc:PriceAmount currencyID=\"PEN\">4.80</cbc:PriceAmount>\n    </cac:Price>\n  </cac:InvoiceLine>\n</Invoice>\n',NULL,'2026-10-09 14:06:11',NULL),(4,'20601001005-01-F001-2.xml','<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<Invoice xmlns=\"urn:oasis:names:specification:ubl:schema:xsd:Invoice-2\" xmlns:cac=\"urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2\" xmlns:cbc=\"urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2\" xmlns:ext=\"urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2\" xmlns:ds=\"http://www.w3.org/2000/09/xmldsig#\">\n  <ext:UBLExtensions>\n    <ext:UBLExtension>\n      <ext:ExtensionContent><ds:Signature Id=\"SignatureSP\"><ds:SignedInfo><ds:CanonicalizationMethod Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/><ds:SignatureMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#rsa-sha1\"/><ds:Reference URI=\"\"><ds:Transforms><ds:Transform Algorithm=\"http://www.w3.org/2000/09/xmldsig#enveloped-signature\"/></ds:Transforms><ds:DigestMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#sha1\"/><ds:DigestValue>wwvs3SIclApfLszw1EEQOTFhUgI=</ds:DigestValue></ds:Reference></ds:SignedInfo><ds:SignatureValue>CxvHPxR8sKYR5jqhlIKq5HI1Ir+L7OYAhcgovflR/T9Zn6p28dqdkejg0WAgokU6y2+uhextjMRgqBrlZY12Ok1tlCSkU2KS1lIPAU5TUi1iD++qxAUQZeN1MolpfvP7a1Smdg3/Amd0L6PJGL/957aJfTa2DAAtf/XC9t/BViG8Uo6pilNs9DxI2iCFi1ObIbiLxPmHML/CtdSyP07ONvGebNO8v84fyg0kby2zefZHoGqkv/O2wEHxwBryhVDZp6hHzVx20tWUT6xpvufcV1+cFlN7elkvAUWLZWit7yBAcRugphU+b7aFhaCVrmcgIuugoT7LdHFuOgOz545nDQ==</ds:SignatureValue><ds:KeyInfo><ds:X509Data><ds:X509Certificate>MIIDRTCCAi2gAwIBAgIBADANBgkqhkiG9w0BAQsFADBmMQswCQYDVQQGEwJQRTEnMCUGA1UECgweQUdSSUNPTEEgUFVDQ0hVTiBTLkEuQy4gKERFTU8pMS4wLAYDVQQDDCVDZXJ0aWZpY2FkbyBkZSBwcnVlYmEgRVJQIEZhY3R1cmFjaW9uMB4XDTI2MTExMzE3NDA0OFoXDTI3MTExMzE3NDA0OFowZjELMAkGA1UEBhMCUEUxJzAlBgNVBAoMHkFHUklDT0xBIFBVQ0NIVU4gUy5BLkMuIChERU1PKTEuMCwGA1UEAwwlQ2VydGlmaWNhZG8gZGUgcHJ1ZWJhIEVSUCBGYWN0dXJhY2lvbjCCASIwDQYJKoZIhvcNAQEBBQADggEPADCCAQoCggEBALPMQehYkmbd/3vwapACgW9cwRqtGvn6aqEoI+F4gmuQPorDJcpEPO1rPz+2Ac4c/PtUD7pyJosnrTumru7aEQYMU3gsDZCSyuvXZ64gv6QRYHwDef43tbvcMVUB1wmMtAigRo0CJCZHfAyBa3dW7CGgMx4hQVrWqaUOZjjctcEFbzuuumCC34ISvdmH5u8NcHs8ImsPbAjJ2VQKAXoNnWpmY5jONSkU9DStq60HTV4gaxWqUjhEEsAqDBIEQXfqVfMeBpaUa+/3Jn8B8ILYtgCpEbSUdHELIIQD6HKcFCfDJ1Q78zpZk2Wn/fICeQ71RjKb0ONQ+OUIksN9wjpNwisCAwEAATANBgkqhkiG9w0BAQsFAAOCAQEAfqIOiL+KadGbR4O1z4DEQQEpsgn+vdVIz+GF/IXeZUMjZNcDqR9IlhYblesWZKN0GWIFJREuUHTWRXtJhCvjCTbtK/lnezuDo3RT0e0ri15gCKW6StUVIJ6QrnpPCzjHkK+YZ6nCRC5AGHUoQlbP6rWg5nGZT3An6xHyAlvpTdJ2T3kEdVZ+LYvzCIVWu3LMcYK2Xp4eh5VgWGcrdaiybITp5rrIYGoE9nFIvHuLsgwxcHGRJjRPW5y5ctacLa1zJqs2DFNlK7hXV4yxcw1sQaq1OyXu8whkbUM8+cxkKAWMhTdfv6HJdXhbdH2Tmx4DjqSP6iJPoCd2AE6qoI8EEQ==</ds:X509Certificate></ds:X509Data></ds:KeyInfo></ds:Signature></ext:ExtensionContent>\n    </ext:UBLExtension>\n  </ext:UBLExtensions>\n  <cbc:UBLVersionID>2.1</cbc:UBLVersionID>\n  <cbc:CustomizationID>2.0</cbc:CustomizationID>\n  <cbc:ID>F001-2</cbc:ID>\n  <cbc:IssueDate>2026-11-13</cbc:IssueDate>\n  <cbc:IssueTime>19:11:43</cbc:IssueTime>\n  <cbc:InvoiceTypeCode listID=\"0101\" listAgencyName=\"PE:SUNAT\" listName=\"Tipo de Documento\" listURI=\"urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo01\">01</cbc:InvoiceTypeCode>\n  <cbc:Note languageLocaleID=\"1000\">SON CINCO MIL QUINIENTOS NOVENTA Y TRES CON 20/100 SOLES</cbc:Note>\n  <cbc:DocumentCurrencyCode>PEN</cbc:DocumentCurrencyCode>\n  <cac:Signature>\n    <cbc:ID>SignatureSP</cbc:ID>\n    <cac:SignatoryParty>\n      <cac:PartyIdentification>\n        <cbc:ID>20601001005</cbc:ID>\n      </cac:PartyIdentification>\n      <cac:PartyName>\n        <cbc:Name>AGRÍCOLA PUCCHÚN S.A.C.</cbc:Name>\n      </cac:PartyName>\n    </cac:SignatoryParty>\n    <cac:DigitalSignatureAttachment>\n      <cac:ExternalReference>\n        <cbc:URI>#SignatureSP</cbc:URI>\n      </cac:ExternalReference>\n    </cac:DigitalSignatureAttachment>\n  </cac:Signature>\n  <cac:AccountingSupplierParty>\n    <cac:Party>\n      <cac:PartyIdentification>\n        <cbc:ID schemeID=\"6\" schemeName=\"Documento de Identidad\" schemeAgencyName=\"PE:SUNAT\" schemeURI=\"urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo06\">20601001005</cbc:ID>\n      </cac:PartyIdentification>\n      <cac:PartyName>\n        <cbc:Name>Agrícola Pucchún</cbc:Name>\n      </cac:PartyName>\n      <cac:PartyLegalEntity>\n        <cbc:RegistrationName>AGRÍCOLA PUCCHÚN S.A.C.</cbc:RegistrationName>\n        <cac:RegistrationAddress>\n          <cbc:ID schemeAgencyName=\"PE:INEI\" schemeName=\"Ubigeos\">150501</cbc:ID>\n          <cbc:AddressTypeCode>0000</cbc:AddressTypeCode>\n          <cbc:CityName>CAÑETE</cbc:CityName>\n          <cbc:CountrySubentity>LIMA</cbc:CountrySubentity>\n          <cbc:District>SAN VICENTE DE CAÑETE</cbc:District>\n          <cac:AddressLine>\n            <cbc:Line>Av. Mariscal Benavides 845</cbc:Line>\n          </cac:AddressLine>\n          <cac:Country>\n            <cbc:IdentificationCode>PE</cbc:IdentificationCode>\n          </cac:Country>\n        </cac:RegistrationAddress>\n      </cac:PartyLegalEntity>\n    </cac:Party>\n  </cac:AccountingSupplierParty>\n  <cac:AccountingCustomerParty>\n    <cac:Party>\n      <cac:PartyIdentification>\n        <cbc:ID schemeID=\"6\" schemeName=\"Documento de Identidad\" schemeAgencyName=\"PE:SUNAT\" schemeURI=\"urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo06\">20602111220</cbc:ID>\n      </cac:PartyIdentification>\n      <cac:PartyLegalEntity>\n        <cbc:RegistrationName>AGROEXPORTADORA VALLE SUR S.A.C.</cbc:RegistrationName>\n      </cac:PartyLegalEntity>\n    </cac:Party>\n  </cac:AccountingCustomerParty>\n  <cac:PaymentTerms>\n    <cbc:ID>FormaPago</cbc:ID>\n    <cbc:PaymentMeansID>Contado</cbc:PaymentMeansID>\n  </cac:PaymentTerms>\n  <cac:TaxTotal>\n    <cbc:TaxAmount currencyID=\"PEN\">853.20</cbc:TaxAmount>\n    <cac:TaxSubtotal>\n      <cbc:TaxableAmount currencyID=\"PEN\">4740.00</cbc:TaxableAmount>\n      <cbc:TaxAmount currencyID=\"PEN\">853.20</cbc:TaxAmount>\n      <cac:TaxCategory>\n        <cac:TaxScheme>\n          <cbc:ID>1000</cbc:ID>\n          <cbc:Name>IGV</cbc:Name>\n          <cbc:TaxTypeCode>VAT</cbc:TaxTypeCode>\n        </cac:TaxScheme>\n      </cac:TaxCategory>\n    </cac:TaxSubtotal>\n  </cac:TaxTotal>\n  <cac:LegalMonetaryTotal>\n    <cbc:LineExtensionAmount currencyID=\"PEN\">4740.00</cbc:LineExtensionAmount>\n    <cbc:TaxInclusiveAmount currencyID=\"PEN\">5593.20</cbc:TaxInclusiveAmount>\n    <cbc:PayableAmount currencyID=\"PEN\">5593.20</cbc:PayableAmount>\n  </cac:LegalMonetaryTotal>\n  <cac:InvoiceLine>\n    <cbc:ID>1</cbc:ID>\n    <cbc:InvoicedQuantity unitCode=\"LTR\" unitCodeListID=\"UN/ECE rec 20\" unitCodeListAgencyName=\"United Nations Economic Commission for Europe\">20</cbc:InvoicedQuantity>\n    <cbc:LineExtensionAmount currencyID=\"PEN\">1900.00</cbc:LineExtensionAmount>\n    <cac:PricingReference>\n      <cac:AlternativeConditionPrice>\n        <cbc:PriceAmount currencyID=\"PEN\">112.10</cbc:PriceAmount>\n        <cbc:PriceTypeCode>01</cbc:PriceTypeCode>\n      </cac:AlternativeConditionPrice>\n    </cac:PricingReference>\n    <cac:TaxTotal>\n      <cbc:TaxAmount currencyID=\"PEN\">342.00</cbc:TaxAmount>\n      <cac:TaxSubtotal>\n        <cbc:TaxableAmount currencyID=\"PEN\">1900.00</cbc:TaxableAmount>\n        <cbc:TaxAmount currencyID=\"PEN\">342.00</cbc:TaxAmount>\n        <cac:TaxCategory>\n          <cbc:Percent>18</cbc:Percent>\n          <cbc:TaxExemptionReasonCode>10</cbc:TaxExemptionReasonCode>\n          <cac:TaxScheme>\n            <cbc:ID>1000</cbc:ID>\n            <cbc:Name>IGV</cbc:Name>\n            <cbc:TaxTypeCode>VAT</cbc:TaxTypeCode>\n          </cac:TaxScheme>\n        </cac:TaxCategory>\n      </cac:TaxSubtotal>\n    </cac:TaxTotal>\n    <cac:Item>\n      <cbc:Description>Insecticida Imidacloprid 350 SC</cbc:Description>\n      <cac:SellersItemIdentification>\n        <cbc:ID>PRD-0001</cbc:ID>\n      </cac:SellersItemIdentification>\n    </cac:Item>\n    <cac:Price>\n      <cbc:PriceAmount currencyID=\"PEN\">95.00</cbc:PriceAmount>\n    </cac:Price>\n  </cac:InvoiceLine>\n  <cac:InvoiceLine>\n    <cbc:ID>2</cbc:ID>\n    <cbc:InvoicedQuantity unitCode=\"KGM\" unitCodeListID=\"UN/ECE rec 20\" unitCodeListAgencyName=\"United Nations Economic Commission for Europe\">10</cbc:InvoicedQuantity>\n    <cbc:LineExtensionAmount currencyID=\"PEN\">2840.00</cbc:LineExtensionAmount>\n    <cac:PricingReference>\n      <cac:AlternativeConditionPrice>\n        <cbc:PriceAmount currencyID=\"PEN\">341.02</cbc:PriceAmount>\n        <cbc:PriceTypeCode>01</cbc:PriceTypeCode>\n      </cac:AlternativeConditionPrice>\n    </cac:PricingReference>\n    <cac:AllowanceCharge>\n      <cbc:ChargeIndicator>false</cbc:ChargeIndicator>\n      <cbc:AllowanceChargeReasonCode>00</cbc:AllowanceChargeReasonCode>\n      <cbc:MultiplierFactorNumeric>0.01730</cbc:MultiplierFactorNumeric>\n      <cbc:Amount currencyID=\"PEN\">50.00</cbc:Amount>\n      <cbc:BaseAmount currencyID=\"PEN\">2890.00</cbc:BaseAmount>\n    </cac:AllowanceCharge>\n    <cac:TaxTotal>\n      <cbc:TaxAmount currencyID=\"PEN\">511.20</cbc:TaxAmount>\n      <cac:TaxSubtotal>\n        <cbc:TaxableAmount currencyID=\"PEN\">2840.00</cbc:TaxableAmount>\n        <cbc:TaxAmount currencyID=\"PEN\">511.20</cbc:TaxAmount>\n        <cac:TaxCategory>\n          <cbc:Percent>18</cbc:Percent>\n          <cbc:TaxExemptionReasonCode>10</cbc:TaxExemptionReasonCode>\n          <cac:TaxScheme>\n            <cbc:ID>1000</cbc:ID>\n            <cbc:Name>IGV</cbc:Name>\n            <cbc:TaxTypeCode>VAT</cbc:TaxTypeCode>\n          </cac:TaxScheme>\n        </cac:TaxCategory>\n      </cac:TaxSubtotal>\n    </cac:TaxTotal>\n    <cac:Item>\n      <cbc:Description>Fungicida Trifloxistrobina + Tebuconazol 75 WG</cbc:Description>\n      <cac:SellersItemIdentification>\n        <cbc:ID>PRD-0004</cbc:ID>\n      </cac:SellersItemIdentification>\n    </cac:Item>\n    <cac:Price>\n      <cbc:PriceAmount currencyID=\"PEN\">289.00</cbc:PriceAmount>\n    </cac:Price>\n  </cac:InvoiceLine>\n</Invoice>\n','wwvs3SIclApfLszw1EEQOTFhUgI=','2026-11-13 19:11:43','2026-11-13 19:11:44'),(5,'20601001005-01-F001-3.xml','<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<Invoice xmlns=\"urn:oasis:names:specification:ubl:schema:xsd:Invoice-2\" xmlns:cac=\"urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2\" xmlns:cbc=\"urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2\" xmlns:ext=\"urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2\" xmlns:ds=\"http://www.w3.org/2000/09/xmldsig#\">\n  <ext:UBLExtensions>\n    <ext:UBLExtension>\n      <ext:ExtensionContent><ds:Signature Id=\"SignatureSP\"><ds:SignedInfo><ds:CanonicalizationMethod Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/><ds:SignatureMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#rsa-sha1\"/><ds:Reference URI=\"\"><ds:Transforms><ds:Transform Algorithm=\"http://www.w3.org/2000/09/xmldsig#enveloped-signature\"/></ds:Transforms><ds:DigestMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#sha1\"/><ds:DigestValue>NhuRLqoU3uFm1jDTHQTzwQx6Bm8=</ds:DigestValue></ds:Reference></ds:SignedInfo><ds:SignatureValue>gfSMIdwLVtLkLYfPFqFsueTEql+n3Qc18VfogJtdqJ8oKoSACLxG6P+3/DbVTrroz4Ccqj5bbE3+WmtXvcSTMtMj7DnpjOD1YRxJkHSVf5W7kpB9H8XJvXfJdleSKGF06cnhJ9wjcFlILZb28p3fjVhRAa/KfM5p2/MYL/NfGYDiUjvFntfQCjjTl6Bx4OEYj/+BkrBSoYuzzqdOyzMJHinZbFTpujl8W5L4BUBtfNhiT/2ShRvcLDKNlmQ+zTWsgAozrN554dxYz2OrN1aq+jh6GgKN8nbMNebd1bALxOXfnH2O12f6HlZ+gGUVMQHXKuPBQ23m3q/LFmyn03EWHQ==</ds:SignatureValue><ds:KeyInfo><ds:X509Data><ds:X509Certificate>MIIDRTCCAi2gAwIBAgIBADANBgkqhkiG9w0BAQsFADBmMQswCQYDVQQGEwJQRTEnMCUGA1UECgweQUdSSUNPTEEgUFVDQ0hVTiBTLkEuQy4gKERFTU8pMS4wLAYDVQQDDCVDZXJ0aWZpY2FkbyBkZSBwcnVlYmEgRVJQIEZhY3R1cmFjaW9uMB4XDTI2MTExMzE3NDA0OFoXDTI3MTExMzE3NDA0OFowZjELMAkGA1UEBhMCUEUxJzAlBgNVBAoMHkFHUklDT0xBIFBVQ0NIVU4gUy5BLkMuIChERU1PKTEuMCwGA1UEAwwlQ2VydGlmaWNhZG8gZGUgcHJ1ZWJhIEVSUCBGYWN0dXJhY2lvbjCCASIwDQYJKoZIhvcNAQEBBQADggEPADCCAQoCggEBALPMQehYkmbd/3vwapACgW9cwRqtGvn6aqEoI+F4gmuQPorDJcpEPO1rPz+2Ac4c/PtUD7pyJosnrTumru7aEQYMU3gsDZCSyuvXZ64gv6QRYHwDef43tbvcMVUB1wmMtAigRo0CJCZHfAyBa3dW7CGgMx4hQVrWqaUOZjjctcEFbzuuumCC34ISvdmH5u8NcHs8ImsPbAjJ2VQKAXoNnWpmY5jONSkU9DStq60HTV4gaxWqUjhEEsAqDBIEQXfqVfMeBpaUa+/3Jn8B8ILYtgCpEbSUdHELIIQD6HKcFCfDJ1Q78zpZk2Wn/fICeQ71RjKb0ONQ+OUIksN9wjpNwisCAwEAATANBgkqhkiG9w0BAQsFAAOCAQEAfqIOiL+KadGbR4O1z4DEQQEpsgn+vdVIz+GF/IXeZUMjZNcDqR9IlhYblesWZKN0GWIFJREuUHTWRXtJhCvjCTbtK/lnezuDo3RT0e0ri15gCKW6StUVIJ6QrnpPCzjHkK+YZ6nCRC5AGHUoQlbP6rWg5nGZT3An6xHyAlvpTdJ2T3kEdVZ+LYvzCIVWu3LMcYK2Xp4eh5VgWGcrdaiybITp5rrIYGoE9nFIvHuLsgwxcHGRJjRPW5y5ctacLa1zJqs2DFNlK7hXV4yxcw1sQaq1OyXu8whkbUM8+cxkKAWMhTdfv6HJdXhbdH2Tmx4DjqSP6iJPoCd2AE6qoI8EEQ==</ds:X509Certificate></ds:X509Data></ds:KeyInfo></ds:Signature></ext:ExtensionContent>\n    </ext:UBLExtension>\n  </ext:UBLExtensions>\n  <cbc:UBLVersionID>2.1</cbc:UBLVersionID>\n  <cbc:CustomizationID>2.0</cbc:CustomizationID>\n  <cbc:ID>F001-3</cbc:ID>\n  <cbc:IssueDate>2026-11-13</cbc:IssueDate>\n  <cbc:IssueTime>19:11:44</cbc:IssueTime>\n  <cbc:InvoiceTypeCode listID=\"0101\" listAgencyName=\"PE:SUNAT\" listName=\"Tipo de Documento\" listURI=\"urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo01\">01</cbc:InvoiceTypeCode>\n  <cbc:Note languageLocaleID=\"1000\">SON TRES MIL DOSCIENTOS VEINTIUNO CON 40/100 SOLES</cbc:Note>\n  <cbc:DocumentCurrencyCode>PEN</cbc:DocumentCurrencyCode>\n  <cac:Signature>\n    <cbc:ID>SignatureSP</cbc:ID>\n    <cac:SignatoryParty>\n      <cac:PartyIdentification>\n        <cbc:ID>20601001005</cbc:ID>\n      </cac:PartyIdentification>\n      <cac:PartyName>\n        <cbc:Name>AGRÍCOLA PUCCHÚN S.A.C.</cbc:Name>\n      </cac:PartyName>\n    </cac:SignatoryParty>\n    <cac:DigitalSignatureAttachment>\n      <cac:ExternalReference>\n        <cbc:URI>#SignatureSP</cbc:URI>\n      </cac:ExternalReference>\n    </cac:DigitalSignatureAttachment>\n  </cac:Signature>\n  <cac:AccountingSupplierParty>\n    <cac:Party>\n      <cac:PartyIdentification>\n        <cbc:ID schemeID=\"6\" schemeName=\"Documento de Identidad\" schemeAgencyName=\"PE:SUNAT\" schemeURI=\"urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo06\">20601001005</cbc:ID>\n      </cac:PartyIdentification>\n      <cac:PartyName>\n        <cbc:Name>Agrícola Pucchún</cbc:Name>\n      </cac:PartyName>\n      <cac:PartyLegalEntity>\n        <cbc:RegistrationName>AGRÍCOLA PUCCHÚN S.A.C.</cbc:RegistrationName>\n        <cac:RegistrationAddress>\n          <cbc:ID schemeAgencyName=\"PE:INEI\" schemeName=\"Ubigeos\">150501</cbc:ID>\n          <cbc:AddressTypeCode>0000</cbc:AddressTypeCode>\n          <cbc:CityName>CAÑETE</cbc:CityName>\n          <cbc:CountrySubentity>LIMA</cbc:CountrySubentity>\n          <cbc:District>SAN VICENTE DE CAÑETE</cbc:District>\n          <cac:AddressLine>\n            <cbc:Line>Av. Mariscal Benavides 845</cbc:Line>\n          </cac:AddressLine>\n          <cac:Country>\n            <cbc:IdentificationCode>PE</cbc:IdentificationCode>\n          </cac:Country>\n        </cac:RegistrationAddress>\n      </cac:PartyLegalEntity>\n    </cac:Party>\n  </cac:AccountingSupplierParty>\n  <cac:AccountingCustomerParty>\n    <cac:Party>\n      <cac:PartyIdentification>\n        <cbc:ID schemeID=\"6\" schemeName=\"Documento de Identidad\" schemeAgencyName=\"PE:SUNAT\" schemeURI=\"urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo06\">20603222335</cbc:ID>\n      </cac:PartyIdentification>\n      <cac:PartyLegalEntity>\n        <cbc:RegistrationName>FUNDO LAS PALMERAS S.A.C.</cbc:RegistrationName>\n      </cac:PartyLegalEntity>\n    </cac:Party>\n  </cac:AccountingCustomerParty>\n  <cac:PaymentTerms>\n    <cbc:ID>FormaPago</cbc:ID>\n    <cbc:PaymentMeansID>Contado</cbc:PaymentMeansID>\n  </cac:PaymentTerms>\n  <cac:TaxTotal>\n    <cbc:TaxAmount currencyID=\"PEN\">491.40</cbc:TaxAmount>\n    <cac:TaxSubtotal>\n      <cbc:TaxableAmount currencyID=\"PEN\">2730.00</cbc:TaxableAmount>\n      <cbc:TaxAmount currencyID=\"PEN\">491.40</cbc:TaxAmount>\n      <cac:TaxCategory>\n        <cac:TaxScheme>\n          <cbc:ID>1000</cbc:ID>\n          <cbc:Name>IGV</cbc:Name>\n          <cbc:TaxTypeCode>VAT</cbc:TaxTypeCode>\n        </cac:TaxScheme>\n      </cac:TaxCategory>\n    </cac:TaxSubtotal>\n  </cac:TaxTotal>\n  <cac:LegalMonetaryTotal>\n    <cbc:LineExtensionAmount currencyID=\"PEN\">2730.00</cbc:LineExtensionAmount>\n    <cbc:TaxInclusiveAmount currencyID=\"PEN\">3221.40</cbc:TaxInclusiveAmount>\n    <cbc:PayableAmount currencyID=\"PEN\">3221.40</cbc:PayableAmount>\n  </cac:LegalMonetaryTotal>\n  <cac:InvoiceLine>\n    <cbc:ID>1</cbc:ID>\n    <cbc:InvoicedQuantity unitCode=\"BG\" unitCodeListID=\"UN/ECE rec 20\" unitCodeListAgencyName=\"United Nations Economic Commission for Europe\">15</cbc:InvoicedQuantity>\n    <cbc:LineExtensionAmount currencyID=\"PEN\">1920.00</cbc:LineExtensionAmount>\n    <cac:PricingReference>\n      <cac:AlternativeConditionPrice>\n        <cbc:PriceAmount currencyID=\"PEN\">151.04</cbc:PriceAmount>\n        <cbc:PriceTypeCode>01</cbc:PriceTypeCode>\n      </cac:AlternativeConditionPrice>\n    </cac:PricingReference>\n    <cac:TaxTotal>\n      <cbc:TaxAmount currencyID=\"PEN\">345.60</cbc:TaxAmount>\n      <cac:TaxSubtotal>\n        <cbc:TaxableAmount currencyID=\"PEN\">1920.00</cbc:TaxableAmount>\n        <cbc:TaxAmount currencyID=\"PEN\">345.60</cbc:TaxAmount>\n        <cac:TaxCategory>\n          <cbc:Percent>18</cbc:Percent>\n          <cbc:TaxExemptionReasonCode>10</cbc:TaxExemptionReasonCode>\n          <cac:TaxScheme>\n            <cbc:ID>1000</cbc:ID>\n            <cbc:Name>IGV</cbc:Name>\n            <cbc:TaxTypeCode>VAT</cbc:TaxTypeCode>\n          </cac:TaxScheme>\n        </cac:TaxCategory>\n      </cac:TaxSubtotal>\n    </cac:TaxTotal>\n    <cac:Item>\n      <cbc:Description>Fertilizante Urea 46% N</cbc:Description>\n      <cac:SellersItemIdentification>\n        <cbc:ID>PRD-0008</cbc:ID>\n      </cac:SellersItemIdentification>\n    </cac:Item>\n    <cac:Price>\n      <cbc:PriceAmount currencyID=\"PEN\">128.00</cbc:PriceAmount>\n    </cac:Price>\n  </cac:InvoiceLine>\n  <cac:InvoiceLine>\n    <cbc:ID>2</cbc:ID>\n    <cbc:InvoicedQuantity unitCode=\"KGM\" unitCodeListID=\"UN/ECE rec 20\" unitCodeListAgencyName=\"United Nations Economic Commission for Europe\">30</cbc:InvoicedQuantity>\n    <cbc:LineExtensionAmount currencyID=\"PEN\">810.00</cbc:LineExtensionAmount>\n    <cac:PricingReference>\n      <cac:AlternativeConditionPrice>\n        <cbc:PriceAmount currencyID=\"PEN\">31.86</cbc:PriceAmount>\n        <cbc:PriceTypeCode>01</cbc:PriceTypeCode>\n      </cac:AlternativeConditionPrice>\n    </cac:PricingReference>\n    <cac:TaxTotal>\n      <cbc:TaxAmount currencyID=\"PEN\">145.80</cbc:TaxAmount>\n      <cac:TaxSubtotal>\n        <cbc:TaxableAmount currencyID=\"PEN\">810.00</cbc:TaxableAmount>\n        <cbc:TaxAmount currencyID=\"PEN\">145.80</cbc:TaxAmount>\n        <cac:TaxCategory>\n          <cbc:Percent>18</cbc:Percent>\n          <cbc:TaxExemptionReasonCode>10</cbc:TaxExemptionReasonCode>\n          <cac:TaxScheme>\n            <cbc:ID>1000</cbc:ID>\n            <cbc:Name>IGV</cbc:Name>\n            <cbc:TaxTypeCode>VAT</cbc:TaxTypeCode>\n          </cac:TaxScheme>\n        </cac:TaxCategory>\n      </cac:TaxSubtotal>\n    </cac:TaxTotal>\n    <cac:Item>\n      <cbc:Description>Fertilizante foliar NPK 20-20-20</cbc:Description>\n      <cac:SellersItemIdentification>\n        <cbc:ID>PRD-0010</cbc:ID>\n      </cac:SellersItemIdentification>\n    </cac:Item>\n    <cac:Price>\n      <cbc:PriceAmount currencyID=\"PEN\">27.00</cbc:PriceAmount>\n    </cac:Price>\n  </cac:InvoiceLine>\n</Invoice>\n','NhuRLqoU3uFm1jDTHQTzwQx6Bm8=','2026-11-13 19:11:44','2026-11-13 19:11:44'),(6,'20601001005-03-B001-3.xml','<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<Invoice xmlns=\"urn:oasis:names:specification:ubl:schema:xsd:Invoice-2\" xmlns:cac=\"urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2\" xmlns:cbc=\"urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2\" xmlns:ext=\"urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2\" xmlns:ds=\"http://www.w3.org/2000/09/xmldsig#\">\n  <ext:UBLExtensions>\n    <ext:UBLExtension>\n      <ext:ExtensionContent><ds:Signature Id=\"SignatureSP\"><ds:SignedInfo><ds:CanonicalizationMethod Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/><ds:SignatureMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#rsa-sha1\"/><ds:Reference URI=\"\"><ds:Transforms><ds:Transform Algorithm=\"http://www.w3.org/2000/09/xmldsig#enveloped-signature\"/></ds:Transforms><ds:DigestMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#sha1\"/><ds:DigestValue>MZc3JPOAjiju6j4/9wPxvErkTO8=</ds:DigestValue></ds:Reference></ds:SignedInfo><ds:SignatureValue>dAXT4sl4R7HmvwQfBhknwEfeKCjfpooXgOwo3B6vLZJG6+K3FeQXBHeASPzeQB4SbqAZ1ez+tSNMx93AvKgnPC/i4HkKNS7AnupTlnQWDjQuiz6Cs580DNgSx52nhzjq9m1OLL+pl6WvJQKtXaUFh8MxPDVOIeuKQoOVyxB5uyIotlH64yWEga2cn3kZ9cS3MacWs32Qfn/yHv1d0avmYiqub7cQmXDAaKiMBmBxDwNW9QJt4/0jhUeQigr5B+9wdoZTE89vY3dxGxJ3W89lmXydWvR+JOZ83Gaf5Y5o804Ex7fjVHv0Lp712vXmcowrErPn/wkfIUJQJlNrbb4Cvw==</ds:SignatureValue><ds:KeyInfo><ds:X509Data><ds:X509Certificate>MIIDRTCCAi2gAwIBAgIBADANBgkqhkiG9w0BAQsFADBmMQswCQYDVQQGEwJQRTEnMCUGA1UECgweQUdSSUNPTEEgUFVDQ0hVTiBTLkEuQy4gKERFTU8pMS4wLAYDVQQDDCVDZXJ0aWZpY2FkbyBkZSBwcnVlYmEgRVJQIEZhY3R1cmFjaW9uMB4XDTI2MTExMzE3NDA0OFoXDTI3MTExMzE3NDA0OFowZjELMAkGA1UEBhMCUEUxJzAlBgNVBAoMHkFHUklDT0xBIFBVQ0NIVU4gUy5BLkMuIChERU1PKTEuMCwGA1UEAwwlQ2VydGlmaWNhZG8gZGUgcHJ1ZWJhIEVSUCBGYWN0dXJhY2lvbjCCASIwDQYJKoZIhvcNAQEBBQADggEPADCCAQoCggEBALPMQehYkmbd/3vwapACgW9cwRqtGvn6aqEoI+F4gmuQPorDJcpEPO1rPz+2Ac4c/PtUD7pyJosnrTumru7aEQYMU3gsDZCSyuvXZ64gv6QRYHwDef43tbvcMVUB1wmMtAigRo0CJCZHfAyBa3dW7CGgMx4hQVrWqaUOZjjctcEFbzuuumCC34ISvdmH5u8NcHs8ImsPbAjJ2VQKAXoNnWpmY5jONSkU9DStq60HTV4gaxWqUjhEEsAqDBIEQXfqVfMeBpaUa+/3Jn8B8ILYtgCpEbSUdHELIIQD6HKcFCfDJ1Q78zpZk2Wn/fICeQ71RjKb0ONQ+OUIksN9wjpNwisCAwEAATANBgkqhkiG9w0BAQsFAAOCAQEAfqIOiL+KadGbR4O1z4DEQQEpsgn+vdVIz+GF/IXeZUMjZNcDqR9IlhYblesWZKN0GWIFJREuUHTWRXtJhCvjCTbtK/lnezuDo3RT0e0ri15gCKW6StUVIJ6QrnpPCzjHkK+YZ6nCRC5AGHUoQlbP6rWg5nGZT3An6xHyAlvpTdJ2T3kEdVZ+LYvzCIVWu3LMcYK2Xp4eh5VgWGcrdaiybITp5rrIYGoE9nFIvHuLsgwxcHGRJjRPW5y5ctacLa1zJqs2DFNlK7hXV4yxcw1sQaq1OyXu8whkbUM8+cxkKAWMhTdfv6HJdXhbdH2Tmx4DjqSP6iJPoCd2AE6qoI8EEQ==</ds:X509Certificate></ds:X509Data></ds:KeyInfo></ds:Signature></ext:ExtensionContent>\n    </ext:UBLExtension>\n  </ext:UBLExtensions>\n  <cbc:UBLVersionID>2.1</cbc:UBLVersionID>\n  <cbc:CustomizationID>2.0</cbc:CustomizationID>\n  <cbc:ID>B001-3</cbc:ID>\n  <cbc:IssueDate>2026-11-13</cbc:IssueDate>\n  <cbc:IssueTime>19:11:44</cbc:IssueTime>\n  <cbc:InvoiceTypeCode listID=\"0101\" listAgencyName=\"PE:SUNAT\" listName=\"Tipo de Documento\" listURI=\"urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo01\">03</cbc:InvoiceTypeCode>\n  <cbc:Note languageLocaleID=\"1000\">SON DOSCIENTOS VEINTIUNO CON 84/100 SOLES</cbc:Note>\n  <cbc:DocumentCurrencyCode>PEN</cbc:DocumentCurrencyCode>\n  <cac:Signature>\n    <cbc:ID>SignatureSP</cbc:ID>\n    <cac:SignatoryParty>\n      <cac:PartyIdentification>\n        <cbc:ID>20601001005</cbc:ID>\n      </cac:PartyIdentification>\n      <cac:PartyName>\n        <cbc:Name>AGRÍCOLA PUCCHÚN S.A.C.</cbc:Name>\n      </cac:PartyName>\n    </cac:SignatoryParty>\n    <cac:DigitalSignatureAttachment>\n      <cac:ExternalReference>\n        <cbc:URI>#SignatureSP</cbc:URI>\n      </cac:ExternalReference>\n    </cac:DigitalSignatureAttachment>\n  </cac:Signature>\n  <cac:AccountingSupplierParty>\n    <cac:Party>\n      <cac:PartyIdentification>\n        <cbc:ID schemeID=\"6\" schemeName=\"Documento de Identidad\" schemeAgencyName=\"PE:SUNAT\" schemeURI=\"urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo06\">20601001005</cbc:ID>\n      </cac:PartyIdentification>\n      <cac:PartyName>\n        <cbc:Name>Agrícola Pucchún</cbc:Name>\n      </cac:PartyName>\n      <cac:PartyLegalEntity>\n        <cbc:RegistrationName>AGRÍCOLA PUCCHÚN S.A.C.</cbc:RegistrationName>\n        <cac:RegistrationAddress>\n          <cbc:ID schemeAgencyName=\"PE:INEI\" schemeName=\"Ubigeos\">150501</cbc:ID>\n          <cbc:AddressTypeCode>0000</cbc:AddressTypeCode>\n          <cbc:CityName>CAÑETE</cbc:CityName>\n          <cbc:CountrySubentity>LIMA</cbc:CountrySubentity>\n          <cbc:District>SAN VICENTE DE CAÑETE</cbc:District>\n          <cac:AddressLine>\n            <cbc:Line>Av. Mariscal Benavides 845</cbc:Line>\n          </cac:AddressLine>\n          <cac:Country>\n            <cbc:IdentificationCode>PE</cbc:IdentificationCode>\n          </cac:Country>\n        </cac:RegistrationAddress>\n      </cac:PartyLegalEntity>\n    </cac:Party>\n  </cac:AccountingSupplierParty>\n  <cac:AccountingCustomerParty>\n    <cac:Party>\n      <cac:PartyIdentification>\n        <cbc:ID schemeID=\"1\" schemeName=\"Documento de Identidad\" schemeAgencyName=\"PE:SUNAT\" schemeURI=\"urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo06\">45123678</cbc:ID>\n      </cac:PartyIdentification>\n      <cac:PartyLegalEntity>\n        <cbc:RegistrationName>HUAMÁN TORRES MARÍA ELENA</cbc:RegistrationName>\n      </cac:PartyLegalEntity>\n    </cac:Party>\n  </cac:AccountingCustomerParty>\n  <cac:TaxTotal>\n    <cbc:TaxAmount currencyID=\"PEN\">33.84</cbc:TaxAmount>\n    <cac:TaxSubtotal>\n      <cbc:TaxableAmount currencyID=\"PEN\">188.00</cbc:TaxableAmount>\n      <cbc:TaxAmount currencyID=\"PEN\">33.84</cbc:TaxAmount>\n      <cac:TaxCategory>\n        <cac:TaxScheme>\n          <cbc:ID>1000</cbc:ID>\n          <cbc:Name>IGV</cbc:Name>\n          <cbc:TaxTypeCode>VAT</cbc:TaxTypeCode>\n        </cac:TaxScheme>\n      </cac:TaxCategory>\n    </cac:TaxSubtotal>\n  </cac:TaxTotal>\n  <cac:LegalMonetaryTotal>\n    <cbc:LineExtensionAmount currencyID=\"PEN\">188.00</cbc:LineExtensionAmount>\n    <cbc:TaxInclusiveAmount currencyID=\"PEN\">221.84</cbc:TaxInclusiveAmount>\n    <cbc:PayableAmount currencyID=\"PEN\">221.84</cbc:PayableAmount>\n  </cac:LegalMonetaryTotal>\n  <cac:InvoiceLine>\n    <cbc:ID>1</cbc:ID>\n    <cbc:InvoicedQuantity unitCode=\"LTR\" unitCodeListID=\"UN/ECE rec 20\" unitCodeListAgencyName=\"United Nations Economic Commission for Europe\">2</cbc:InvoicedQuantity>\n    <cbc:LineExtensionAmount currencyID=\"PEN\">130.00</cbc:LineExtensionAmount>\n    <cac:PricingReference>\n      <cac:AlternativeConditionPrice>\n        <cbc:PriceAmount currencyID=\"PEN\">76.70</cbc:PriceAmount>\n        <cbc:PriceTypeCode>01</cbc:PriceTypeCode>\n      </cac:AlternativeConditionPrice>\n    </cac:PricingReference>\n    <cac:TaxTotal>\n      <cbc:TaxAmount currencyID=\"PEN\">23.40</cbc:TaxAmount>\n      <cac:TaxSubtotal>\n        <cbc:TaxableAmount currencyID=\"PEN\">130.00</cbc:TaxableAmount>\n        <cbc:TaxAmount currencyID=\"PEN\">23.40</cbc:TaxAmount>\n        <cac:TaxCategory>\n          <cbc:Percent>18</cbc:Percent>\n          <cbc:TaxExemptionReasonCode>10</cbc:TaxExemptionReasonCode>\n          <cac:TaxScheme>\n            <cbc:ID>1000</cbc:ID>\n            <cbc:Name>IGV</cbc:Name>\n            <cbc:TaxTypeCode>VAT</cbc:TaxTypeCode>\n          </cac:TaxScheme>\n        </cac:TaxCategory>\n      </cac:TaxSubtotal>\n    </cac:TaxTotal>\n    <cac:Item>\n      <cbc:Description>Bioestimulante a base de extracto de algas</cbc:Description>\n      <cac:SellersItemIdentification>\n        <cbc:ID>PRD-0012</cbc:ID>\n      </cac:SellersItemIdentification>\n    </cac:Item>\n    <cac:Price>\n      <cbc:PriceAmount currencyID=\"PEN\">65.00</cbc:PriceAmount>\n    </cac:Price>\n  </cac:InvoiceLine>\n  <cac:InvoiceLine>\n    <cbc:ID>2</cbc:ID>\n    <cbc:InvoicedQuantity unitCode=\"LTR\" unitCodeListID=\"UN/ECE rec 20\" unitCodeListAgencyName=\"United Nations Economic Commission for Europe\">1</cbc:InvoicedQuantity>\n    <cbc:LineExtensionAmount currencyID=\"PEN\">58.00</cbc:LineExtensionAmount>\n    <cac:PricingReference>\n      <cac:AlternativeConditionPrice>\n        <cbc:PriceAmount currencyID=\"PEN\">68.44</cbc:PriceAmount>\n        <cbc:PriceTypeCode>01</cbc:PriceTypeCode>\n      </cac:AlternativeConditionPrice>\n    </cac:PricingReference>\n    <cac:TaxTotal>\n      <cbc:TaxAmount currencyID=\"PEN\">10.44</cbc:TaxAmount>\n      <cac:TaxSubtotal>\n        <cbc:TaxableAmount currencyID=\"PEN\">58.00</cbc:TaxableAmount>\n        <cbc:TaxAmount currencyID=\"PEN\">10.44</cbc:TaxAmount>\n        <cac:TaxCategory>\n          <cbc:Percent>18</cbc:Percent>\n          <cbc:TaxExemptionReasonCode>10</cbc:TaxExemptionReasonCode>\n          <cac:TaxScheme>\n            <cbc:ID>1000</cbc:ID>\n            <cbc:Name>IGV</cbc:Name>\n            <cbc:TaxTypeCode>VAT</cbc:TaxTypeCode>\n          </cac:TaxScheme>\n        </cac:TaxCategory>\n      </cac:TaxSubtotal>\n    </cac:TaxTotal>\n    <cac:Item>\n      <cbc:Description>Insecticida Deltametrina 25 EC</cbc:Description>\n      <cac:SellersItemIdentification>\n        <cbc:ID>PRD-0003</cbc:ID>\n      </cac:SellersItemIdentification>\n    </cac:Item>\n    <cac:Price>\n      <cbc:PriceAmount currencyID=\"PEN\">58.00</cbc:PriceAmount>\n    </cac:Price>\n  </cac:InvoiceLine>\n</Invoice>\n','MZc3JPOAjiju6j4/9wPxvErkTO8=','2026-11-13 19:11:44','2026-11-13 19:11:44'),(7,'20601001005-01-F001-4.xml','<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<Invoice xmlns=\"urn:oasis:names:specification:ubl:schema:xsd:Invoice-2\" xmlns:cac=\"urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2\" xmlns:cbc=\"urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2\" xmlns:ext=\"urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2\" xmlns:ds=\"http://www.w3.org/2000/09/xmldsig#\">\n  <ext:UBLExtensions>\n    <ext:UBLExtension>\n      <ext:ExtensionContent><ds:Signature Id=\"SignatureSP\"><ds:SignedInfo><ds:CanonicalizationMethod Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/><ds:SignatureMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#rsa-sha1\"/><ds:Reference URI=\"\"><ds:Transforms><ds:Transform Algorithm=\"http://www.w3.org/2000/09/xmldsig#enveloped-signature\"/></ds:Transforms><ds:DigestMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#sha1\"/><ds:DigestValue>M0jEJah53QRhLWC/IG1np7l806E=</ds:DigestValue></ds:Reference></ds:SignedInfo><ds:SignatureValue>oGPWvUqxhUTKRmyxjx4k5GRwO2ILZMiANDJm32S9s81Wd3qB+JHAlJo0SvEN9zUUdLXm5y2nUnB3zwRSF3fnl7uhZVIs2kLMN6l4FchUWoWoAYP5qgnz8QubxfRhGxaXTEDnuDFWJR7y/uEpqvlSKmD50v4HgE32Kiw8DJvFafEs50zvYCzPLwdpbKNDs5LoG1t6bWCxIx5losHMk2gqR8j9zuHFVIbVraCghSsdqDUMZIGzHb8IehqIxPkrLA1xrvzypH08TT2b/xmQ9Eep7Gsc3tfyVGCkkhD6gf3HtqgClx6BtKlkuUOpZ02Q1N2j10tQFPyIV/eNOcASAQzDOQ==</ds:SignatureValue><ds:KeyInfo><ds:X509Data><ds:X509Certificate>MIIDRTCCAi2gAwIBAgIBADANBgkqhkiG9w0BAQsFADBmMQswCQYDVQQGEwJQRTEnMCUGA1UECgweQUdSSUNPTEEgUFVDQ0hVTiBTLkEuQy4gKERFTU8pMS4wLAYDVQQDDCVDZXJ0aWZpY2FkbyBkZSBwcnVlYmEgRVJQIEZhY3R1cmFjaW9uMB4XDTI2MTExMzE3NDA0OFoXDTI3MTExMzE3NDA0OFowZjELMAkGA1UEBhMCUEUxJzAlBgNVBAoMHkFHUklDT0xBIFBVQ0NIVU4gUy5BLkMuIChERU1PKTEuMCwGA1UEAwwlQ2VydGlmaWNhZG8gZGUgcHJ1ZWJhIEVSUCBGYWN0dXJhY2lvbjCCASIwDQYJKoZIhvcNAQEBBQADggEPADCCAQoCggEBALPMQehYkmbd/3vwapACgW9cwRqtGvn6aqEoI+F4gmuQPorDJcpEPO1rPz+2Ac4c/PtUD7pyJosnrTumru7aEQYMU3gsDZCSyuvXZ64gv6QRYHwDef43tbvcMVUB1wmMtAigRo0CJCZHfAyBa3dW7CGgMx4hQVrWqaUOZjjctcEFbzuuumCC34ISvdmH5u8NcHs8ImsPbAjJ2VQKAXoNnWpmY5jONSkU9DStq60HTV4gaxWqUjhEEsAqDBIEQXfqVfMeBpaUa+/3Jn8B8ILYtgCpEbSUdHELIIQD6HKcFCfDJ1Q78zpZk2Wn/fICeQ71RjKb0ONQ+OUIksN9wjpNwisCAwEAATANBgkqhkiG9w0BAQsFAAOCAQEAfqIOiL+KadGbR4O1z4DEQQEpsgn+vdVIz+GF/IXeZUMjZNcDqR9IlhYblesWZKN0GWIFJREuUHTWRXtJhCvjCTbtK/lnezuDo3RT0e0ri15gCKW6StUVIJ6QrnpPCzjHkK+YZ6nCRC5AGHUoQlbP6rWg5nGZT3An6xHyAlvpTdJ2T3kEdVZ+LYvzCIVWu3LMcYK2Xp4eh5VgWGcrdaiybITp5rrIYGoE9nFIvHuLsgwxcHGRJjRPW5y5ctacLa1zJqs2DFNlK7hXV4yxcw1sQaq1OyXu8whkbUM8+cxkKAWMhTdfv6HJdXhbdH2Tmx4DjqSP6iJPoCd2AE6qoI8EEQ==</ds:X509Certificate></ds:X509Data></ds:KeyInfo></ds:Signature></ext:ExtensionContent>\n    </ext:UBLExtension>\n  </ext:UBLExtensions>\n  <cbc:UBLVersionID>2.1</cbc:UBLVersionID>\n  <cbc:CustomizationID>2.0</cbc:CustomizationID>\n  <cbc:ID>F001-4</cbc:ID>\n  <cbc:IssueDate>2026-11-13</cbc:IssueDate>\n  <cbc:IssueTime>19:11:44</cbc:IssueTime>\n  <cbc:InvoiceTypeCode listID=\"0101\" listAgencyName=\"PE:SUNAT\" listName=\"Tipo de Documento\" listURI=\"urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo01\">01</cbc:InvoiceTypeCode>\n  <cbc:Note languageLocaleID=\"1000\">SON DOS MIL NOVECIENTOS SETENTA Y NUEVE CON 50/100 SOLES</cbc:Note>\n  <cbc:DocumentCurrencyCode>PEN</cbc:DocumentCurrencyCode>\n  <cac:Signature>\n    <cbc:ID>SignatureSP</cbc:ID>\n    <cac:SignatoryParty>\n      <cac:PartyIdentification>\n        <cbc:ID>20601001005</cbc:ID>\n      </cac:PartyIdentification>\n      <cac:PartyName>\n        <cbc:Name>AGRÍCOLA PUCCHÚN S.A.C.</cbc:Name>\n      </cac:PartyName>\n    </cac:SignatoryParty>\n    <cac:DigitalSignatureAttachment>\n      <cac:ExternalReference>\n        <cbc:URI>#SignatureSP</cbc:URI>\n      </cac:ExternalReference>\n    </cac:DigitalSignatureAttachment>\n  </cac:Signature>\n  <cac:AccountingSupplierParty>\n    <cac:Party>\n      <cac:PartyIdentification>\n        <cbc:ID schemeID=\"6\" schemeName=\"Documento de Identidad\" schemeAgencyName=\"PE:SUNAT\" schemeURI=\"urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo06\">20601001005</cbc:ID>\n      </cac:PartyIdentification>\n      <cac:PartyName>\n        <cbc:Name>Agrícola Pucchún</cbc:Name>\n      </cac:PartyName>\n      <cac:PartyLegalEntity>\n        <cbc:RegistrationName>AGRÍCOLA PUCCHÚN S.A.C.</cbc:RegistrationName>\n        <cac:RegistrationAddress>\n          <cbc:ID schemeAgencyName=\"PE:INEI\" schemeName=\"Ubigeos\">150509</cbc:ID>\n          <cbc:AddressTypeCode>0001</cbc:AddressTypeCode>\n          <cbc:CityName>CAÑETE</cbc:CityName>\n          <cbc:CountrySubentity>LIMA</cbc:CountrySubentity>\n          <cbc:District>MALA</cbc:District>\n          <cac:AddressLine>\n            <cbc:Line>Jr. Grau 312</cbc:Line>\n          </cac:AddressLine>\n          <cac:Country>\n            <cbc:IdentificationCode>PE</cbc:IdentificationCode>\n          </cac:Country>\n        </cac:RegistrationAddress>\n      </cac:PartyLegalEntity>\n    </cac:Party>\n  </cac:AccountingSupplierParty>\n  <cac:AccountingCustomerParty>\n    <cac:Party>\n      <cac:PartyIdentification>\n        <cbc:ID schemeID=\"6\" schemeName=\"Documento de Identidad\" schemeAgencyName=\"PE:SUNAT\" schemeURI=\"urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo06\">20605444556</cbc:ID>\n      </cac:PartyIdentification>\n      <cac:PartyLegalEntity>\n        <cbc:RegistrationName>INVERSIONES AGRÍCOLAS MALA E.I.R.L.</cbc:RegistrationName>\n      </cac:PartyLegalEntity>\n    </cac:Party>\n  </cac:AccountingCustomerParty>\n  <cac:PaymentTerms>\n    <cbc:ID>FormaPago</cbc:ID>\n    <cbc:PaymentMeansID>Contado</cbc:PaymentMeansID>\n  </cac:PaymentTerms>\n  <cac:TaxTotal>\n    <cbc:TaxAmount currencyID=\"PEN\">454.50</cbc:TaxAmount>\n    <cac:TaxSubtotal>\n      <cbc:TaxableAmount currencyID=\"PEN\">2525.00</cbc:TaxableAmount>\n      <cbc:TaxAmount currencyID=\"PEN\">454.50</cbc:TaxAmount>\n      <cac:TaxCategory>\n        <cac:TaxScheme>\n          <cbc:ID>1000</cbc:ID>\n          <cbc:Name>IGV</cbc:Name>\n          <cbc:TaxTypeCode>VAT</cbc:TaxTypeCode>\n        </cac:TaxScheme>\n      </cac:TaxCategory>\n    </cac:TaxSubtotal>\n  </cac:TaxTotal>\n  <cac:LegalMonetaryTotal>\n    <cbc:LineExtensionAmount currencyID=\"PEN\">2525.00</cbc:LineExtensionAmount>\n    <cbc:TaxInclusiveAmount currencyID=\"PEN\">2979.50</cbc:TaxInclusiveAmount>\n    <cbc:PayableAmount currencyID=\"PEN\">2979.50</cbc:PayableAmount>\n  </cac:LegalMonetaryTotal>\n  <cac:InvoiceLine>\n    <cbc:ID>1</cbc:ID>\n    <cbc:InvoicedQuantity unitCode=\"LTR\" unitCodeListID=\"UN/ECE rec 20\" unitCodeListAgencyName=\"United Nations Economic Commission for Europe\">10</cbc:InvoicedQuantity>\n    <cbc:LineExtensionAmount currencyID=\"PEN\">950.00</cbc:LineExtensionAmount>\n    <cac:PricingReference>\n      <cac:AlternativeConditionPrice>\n        <cbc:PriceAmount currencyID=\"PEN\">112.10</cbc:PriceAmount>\n        <cbc:PriceTypeCode>01</cbc:PriceTypeCode>\n      </cac:AlternativeConditionPrice>\n    </cac:PricingReference>\n    <cac:TaxTotal>\n      <cbc:TaxAmount currencyID=\"PEN\">171.00</cbc:TaxAmount>\n      <cac:TaxSubtotal>\n        <cbc:TaxableAmount currencyID=\"PEN\">950.00</cbc:TaxableAmount>\n        <cbc:TaxAmount currencyID=\"PEN\">171.00</cbc:TaxAmount>\n        <cac:TaxCategory>\n          <cbc:Percent>18</cbc:Percent>\n          <cbc:TaxExemptionReasonCode>10</cbc:TaxExemptionReasonCode>\n          <cac:TaxScheme>\n            <cbc:ID>1000</cbc:ID>\n            <cbc:Name>IGV</cbc:Name>\n            <cbc:TaxTypeCode>VAT</cbc:TaxTypeCode>\n          </cac:TaxScheme>\n        </cac:TaxCategory>\n      </cac:TaxSubtotal>\n    </cac:TaxTotal>\n    <cac:Item>\n      <cbc:Description>Insecticida Imidacloprid 350 SC</cbc:Description>\n      <cac:SellersItemIdentification>\n        <cbc:ID>PRD-0001</cbc:ID>\n      </cac:SellersItemIdentification>\n    </cac:Item>\n    <cac:Price>\n      <cbc:PriceAmount currencyID=\"PEN\">95.00</cbc:PriceAmount>\n    </cac:Price>\n  </cac:InvoiceLine>\n  <cac:InvoiceLine>\n    <cbc:ID>2</cbc:ID>\n    <cbc:InvoicedQuantity unitCode=\"LTR\" unitCodeListID=\"UN/ECE rec 20\" unitCodeListAgencyName=\"United Nations Economic Commission for Europe\">5</cbc:InvoicedQuantity>\n    <cbc:LineExtensionAmount currencyID=\"PEN\">1575.00</cbc:LineExtensionAmount>\n    <cac:PricingReference>\n      <cac:AlternativeConditionPrice>\n        <cbc:PriceAmount currencyID=\"PEN\">371.70</cbc:PriceAmount>\n        <cbc:PriceTypeCode>01</cbc:PriceTypeCode>\n      </cac:AlternativeConditionPrice>\n    </cac:PricingReference>\n    <cac:TaxTotal>\n      <cbc:TaxAmount currencyID=\"PEN\">283.50</cbc:TaxAmount>\n      <cac:TaxSubtotal>\n        <cbc:TaxableAmount currencyID=\"PEN\">1575.00</cbc:TaxableAmount>\n        <cbc:TaxAmount currencyID=\"PEN\">283.50</cbc:TaxAmount>\n        <cac:TaxCategory>\n          <cbc:Percent>18</cbc:Percent>\n          <cbc:TaxExemptionReasonCode>10</cbc:TaxExemptionReasonCode>\n          <cac:TaxScheme>\n            <cbc:ID>1000</cbc:ID>\n            <cbc:Name>IGV</cbc:Name>\n            <cbc:TaxTypeCode>VAT</cbc:TaxTypeCode>\n          </cac:TaxScheme>\n        </cac:TaxCategory>\n      </cac:TaxSubtotal>\n    </cac:TaxTotal>\n    <cac:Item>\n      <cbc:Description>Fungicida Fluopyram + Tebuconazol 400 SC</cbc:Description>\n      <cac:SellersItemIdentification>\n        <cbc:ID>PRD-0005</cbc:ID>\n      </cac:SellersItemIdentification>\n    </cac:Item>\n    <cac:Price>\n      <cbc:PriceAmount currencyID=\"PEN\">315.00</cbc:PriceAmount>\n    </cac:Price>\n  </cac:InvoiceLine>\n</Invoice>\n','M0jEJah53QRhLWC/IG1np7l806E=','2026-11-13 19:11:44','2026-11-13 19:11:44'),(9,'20601001005-07-FC01-1.xml','<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<CreditNote xmlns=\"urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2\" xmlns:cac=\"urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2\" xmlns:cbc=\"urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2\" xmlns:ext=\"urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2\" xmlns:ds=\"http://www.w3.org/2000/09/xmldsig#\">\n  <ext:UBLExtensions>\n    <ext:UBLExtension>\n      <ext:ExtensionContent><ds:Signature Id=\"SignatureSP\"><ds:SignedInfo><ds:CanonicalizationMethod Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/><ds:SignatureMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#rsa-sha1\"/><ds:Reference URI=\"\"><ds:Transforms><ds:Transform Algorithm=\"http://www.w3.org/2000/09/xmldsig#enveloped-signature\"/></ds:Transforms><ds:DigestMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#sha1\"/><ds:DigestValue>hBcjmAO9jfrFYX49mT0hf0jt5GI=</ds:DigestValue></ds:Reference></ds:SignedInfo><ds:SignatureValue>sli+sEfmELeYn5Y5SzkhnXsyOAr7hK1Asf60gViKg6YR4PFcqE/f+C57QkhOYX+OgLAjzWT0q9o4+sg9J1CjjWfRiX1OsVbPsmtOOw4w6/WcdMrqj3wpN5kS79mpRNs2EZBuc1pGrJ4gORxJ1SqT1wdU/sYorLYvLnwTc5bJcMnZ51kzl2T5O0gt9I/fG5gNmT6TQv0/jmxoL100gVR3PZKTSeuYHFv/AN8oF3KNAgk/k1BDyQTSwFQq10ILYrWm78cHmlD3a4HCtLShDnOsJWw+wVPmJgHAn+7R8gVLH096zHs7OHHjCKDZt2aDMomZOVu26FJRR4DlRbBFGbzirA==</ds:SignatureValue><ds:KeyInfo><ds:X509Data><ds:X509Certificate>MIIDRTCCAi2gAwIBAgIBADANBgkqhkiG9w0BAQsFADBmMQswCQYDVQQGEwJQRTEnMCUGA1UECgweQUdSSUNPTEEgUFVDQ0hVTiBTLkEuQy4gKERFTU8pMS4wLAYDVQQDDCVDZXJ0aWZpY2FkbyBkZSBwcnVlYmEgRVJQIEZhY3R1cmFjaW9uMB4XDTI2MTExMzE3NDA0OFoXDTI3MTExMzE3NDA0OFowZjELMAkGA1UEBhMCUEUxJzAlBgNVBAoMHkFHUklDT0xBIFBVQ0NIVU4gUy5BLkMuIChERU1PKTEuMCwGA1UEAwwlQ2VydGlmaWNhZG8gZGUgcHJ1ZWJhIEVSUCBGYWN0dXJhY2lvbjCCASIwDQYJKoZIhvcNAQEBBQADggEPADCCAQoCggEBALPMQehYkmbd/3vwapACgW9cwRqtGvn6aqEoI+F4gmuQPorDJcpEPO1rPz+2Ac4c/PtUD7pyJosnrTumru7aEQYMU3gsDZCSyuvXZ64gv6QRYHwDef43tbvcMVUB1wmMtAigRo0CJCZHfAyBa3dW7CGgMx4hQVrWqaUOZjjctcEFbzuuumCC34ISvdmH5u8NcHs8ImsPbAjJ2VQKAXoNnWpmY5jONSkU9DStq60HTV4gaxWqUjhEEsAqDBIEQXfqVfMeBpaUa+/3Jn8B8ILYtgCpEbSUdHELIIQD6HKcFCfDJ1Q78zpZk2Wn/fICeQ71RjKb0ONQ+OUIksN9wjpNwisCAwEAATANBgkqhkiG9w0BAQsFAAOCAQEAfqIOiL+KadGbR4O1z4DEQQEpsgn+vdVIz+GF/IXeZUMjZNcDqR9IlhYblesWZKN0GWIFJREuUHTWRXtJhCvjCTbtK/lnezuDo3RT0e0ri15gCKW6StUVIJ6QrnpPCzjHkK+YZ6nCRC5AGHUoQlbP6rWg5nGZT3An6xHyAlvpTdJ2T3kEdVZ+LYvzCIVWu3LMcYK2Xp4eh5VgWGcrdaiybITp5rrIYGoE9nFIvHuLsgwxcHGRJjRPW5y5ctacLa1zJqs2DFNlK7hXV4yxcw1sQaq1OyXu8whkbUM8+cxkKAWMhTdfv6HJdXhbdH2Tmx4DjqSP6iJPoCd2AE6qoI8EEQ==</ds:X509Certificate></ds:X509Data></ds:KeyInfo></ds:Signature></ext:ExtensionContent>\n    </ext:UBLExtension>\n  </ext:UBLExtensions>\n  <cbc:UBLVersionID>2.1</cbc:UBLVersionID>\n  <cbc:CustomizationID>2.0</cbc:CustomizationID>\n  <cbc:ID>FC01-1</cbc:ID>\n  <cbc:IssueDate>2026-11-13</cbc:IssueDate>\n  <cbc:IssueTime>19:26:54</cbc:IssueTime>\n  <cbc:Note languageLocaleID=\"1000\">SON CIENTO DIECIOCHO CON 00/100 SOLES</cbc:Note>\n  <cbc:DocumentCurrencyCode>PEN</cbc:DocumentCurrencyCode>\n  <cac:DiscrepancyResponse>\n    <cbc:ReferenceID>F001-2</cbc:ReferenceID>\n    <cbc:ResponseCode>09</cbc:ResponseCode>\n    <cbc:Description>Descuento comercial posterior</cbc:Description>\n  </cac:DiscrepancyResponse>\n  <cac:BillingReference>\n    <cac:InvoiceDocumentReference>\n      <cbc:ID>F001-2</cbc:ID>\n      <cbc:DocumentTypeCode>01</cbc:DocumentTypeCode>\n    </cac:InvoiceDocumentReference>\n  </cac:BillingReference>\n  <cac:Signature>\n    <cbc:ID>SignatureSP</cbc:ID>\n    <cac:SignatoryParty>\n      <cac:PartyIdentification>\n        <cbc:ID>20601001005</cbc:ID>\n      </cac:PartyIdentification>\n      <cac:PartyName>\n        <cbc:Name>AGRÍCOLA PUCCHÚN S.A.C.</cbc:Name>\n      </cac:PartyName>\n    </cac:SignatoryParty>\n    <cac:DigitalSignatureAttachment>\n      <cac:ExternalReference>\n        <cbc:URI>#SignatureSP</cbc:URI>\n      </cac:ExternalReference>\n    </cac:DigitalSignatureAttachment>\n  </cac:Signature>\n  <cac:AccountingSupplierParty>\n    <cac:Party>\n      <cac:PartyIdentification>\n        <cbc:ID schemeID=\"6\" schemeName=\"Documento de Identidad\" schemeAgencyName=\"PE:SUNAT\" schemeURI=\"urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo06\">20601001005</cbc:ID>\n      </cac:PartyIdentification>\n      <cac:PartyName>\n        <cbc:Name>Agrícola Pucchún</cbc:Name>\n      </cac:PartyName>\n      <cac:PartyLegalEntity>\n        <cbc:RegistrationName>AGRÍCOLA PUCCHÚN S.A.C.</cbc:RegistrationName>\n        <cac:RegistrationAddress>\n          <cbc:ID schemeAgencyName=\"PE:INEI\" schemeName=\"Ubigeos\">150501</cbc:ID>\n          <cbc:AddressTypeCode>0000</cbc:AddressTypeCode>\n          <cbc:CityName>CAÑETE</cbc:CityName>\n          <cbc:CountrySubentity>LIMA</cbc:CountrySubentity>\n          <cbc:District>SAN VICENTE DE CAÑETE</cbc:District>\n          <cac:AddressLine>\n            <cbc:Line>Av. Mariscal Benavides 845</cbc:Line>\n          </cac:AddressLine>\n          <cac:Country>\n            <cbc:IdentificationCode>PE</cbc:IdentificationCode>\n          </cac:Country>\n        </cac:RegistrationAddress>\n      </cac:PartyLegalEntity>\n    </cac:Party>\n  </cac:AccountingSupplierParty>\n  <cac:AccountingCustomerParty>\n    <cac:Party>\n      <cac:PartyIdentification>\n        <cbc:ID schemeID=\"6\" schemeName=\"Documento de Identidad\" schemeAgencyName=\"PE:SUNAT\" schemeURI=\"urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo06\">20602111220</cbc:ID>\n      </cac:PartyIdentification>\n      <cac:PartyLegalEntity>\n        <cbc:RegistrationName>AGROEXPORTADORA VALLE SUR S.A.C.</cbc:RegistrationName>\n      </cac:PartyLegalEntity>\n    </cac:Party>\n  </cac:AccountingCustomerParty>\n  <cac:TaxTotal>\n    <cbc:TaxAmount currencyID=\"PEN\">18.00</cbc:TaxAmount>\n    <cac:TaxSubtotal>\n      <cbc:TaxableAmount currencyID=\"PEN\">100.00</cbc:TaxableAmount>\n      <cbc:TaxAmount currencyID=\"PEN\">18.00</cbc:TaxAmount>\n      <cac:TaxCategory>\n        <cac:TaxScheme>\n          <cbc:ID>1000</cbc:ID>\n          <cbc:Name>IGV</cbc:Name>\n          <cbc:TaxTypeCode>VAT</cbc:TaxTypeCode>\n        </cac:TaxScheme>\n      </cac:TaxCategory>\n    </cac:TaxSubtotal>\n  </cac:TaxTotal>\n  <cac:LegalMonetaryTotal>\n    <cbc:LineExtensionAmount currencyID=\"PEN\">100.00</cbc:LineExtensionAmount>\n    <cbc:TaxInclusiveAmount currencyID=\"PEN\">118.00</cbc:TaxInclusiveAmount>\n    <cbc:PayableAmount currencyID=\"PEN\">118.00</cbc:PayableAmount>\n  </cac:LegalMonetaryTotal>\n  <cac:CreditNoteLine>\n    <cbc:ID>1</cbc:ID>\n    <cbc:CreditedQuantity unitCode=\"ZZ\" unitCodeListID=\"UN/ECE rec 20\" unitCodeListAgencyName=\"United Nations Economic Commission for Europe\">1</cbc:CreditedQuantity>\n    <cbc:LineExtensionAmount currencyID=\"PEN\">100.00</cbc:LineExtensionAmount>\n    <cac:PricingReference>\n      <cac:AlternativeConditionPrice>\n        <cbc:PriceAmount currencyID=\"PEN\">118.00</cbc:PriceAmount>\n        <cbc:PriceTypeCode>01</cbc:PriceTypeCode>\n      </cac:AlternativeConditionPrice>\n    </cac:PricingReference>\n    <cac:TaxTotal>\n      <cbc:TaxAmount currencyID=\"PEN\">18.00</cbc:TaxAmount>\n      <cac:TaxSubtotal>\n        <cbc:TaxableAmount currencyID=\"PEN\">100.00</cbc:TaxableAmount>\n        <cbc:TaxAmount currencyID=\"PEN\">18.00</cbc:TaxAmount>\n        <cac:TaxCategory>\n          <cbc:Percent>18</cbc:Percent>\n          <cbc:TaxExemptionReasonCode>10</cbc:TaxExemptionReasonCode>\n          <cac:TaxScheme>\n            <cbc:ID>1000</cbc:ID>\n            <cbc:Name>IGV</cbc:Name>\n            <cbc:TaxTypeCode>VAT</cbc:TaxTypeCode>\n          </cac:TaxScheme>\n        </cac:TaxCategory>\n      </cac:TaxSubtotal>\n    </cac:TaxTotal>\n    <cac:Item>\n      <cbc:Description>Ajuste: Descuento comercial posterior</cbc:Description>\n    </cac:Item>\n    <cac:Price>\n      <cbc:PriceAmount currencyID=\"PEN\">100.00</cbc:PriceAmount>\n    </cac:Price>\n  </cac:CreditNoteLine>\n</CreditNote>\n','hBcjmAO9jfrFYX49mT0hf0jt5GI=','2026-11-13 19:26:54','2026-11-13 19:26:54'),(10,'20601001005-07-FC01-2.xml','<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<CreditNote xmlns=\"urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2\" xmlns:cac=\"urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2\" xmlns:cbc=\"urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2\" xmlns:ext=\"urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2\" xmlns:ds=\"http://www.w3.org/2000/09/xmldsig#\">\n  <ext:UBLExtensions>\n    <ext:UBLExtension>\n      <ext:ExtensionContent><ds:Signature Id=\"SignatureSP\"><ds:SignedInfo><ds:CanonicalizationMethod Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/><ds:SignatureMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#rsa-sha1\"/><ds:Reference URI=\"\"><ds:Transforms><ds:Transform Algorithm=\"http://www.w3.org/2000/09/xmldsig#enveloped-signature\"/></ds:Transforms><ds:DigestMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#sha1\"/><ds:DigestValue>FNFtyrfxUn3B8Ja+cFXLXi/wK94=</ds:DigestValue></ds:Reference></ds:SignedInfo><ds:SignatureValue>sS3+yi/8jrd4wyAP1sQ6G6Bd+9N5JKVSJ6GATpcx3LXP/yEwwsdiKk2/CSSW/cynHJhLrzhY2BbMP/YIIorhtjqQyKR408OFR/rTRCv5upktuXLdynijOKRtFOTyQBd3Emt7T803wiR59LuezBhpx5WWJpO30OhZ7nnLcinTkB+6jWrPz8+A2A4qgKfEKZiX1cUplbpUUiyUdNFHS+VsZktehNEhPwsOE8F8lbuaQahinuGnav493ipiH5rUi4WbBaKMixlCw02+MsweFI4dLd7BmRwa4IjLfFlS3cBlH9/Yl7fISstrw/hxEa/22kN5VgcC3otuV2AS7m5FAN0WWg==</ds:SignatureValue><ds:KeyInfo><ds:X509Data><ds:X509Certificate>MIIDRTCCAi2gAwIBAgIBADANBgkqhkiG9w0BAQsFADBmMQswCQYDVQQGEwJQRTEnMCUGA1UECgweQUdSSUNPTEEgUFVDQ0hVTiBTLkEuQy4gKERFTU8pMS4wLAYDVQQDDCVDZXJ0aWZpY2FkbyBkZSBwcnVlYmEgRVJQIEZhY3R1cmFjaW9uMB4XDTI2MTExMzE3NDA0OFoXDTI3MTExMzE3NDA0OFowZjELMAkGA1UEBhMCUEUxJzAlBgNVBAoMHkFHUklDT0xBIFBVQ0NIVU4gUy5BLkMuIChERU1PKTEuMCwGA1UEAwwlQ2VydGlmaWNhZG8gZGUgcHJ1ZWJhIEVSUCBGYWN0dXJhY2lvbjCCASIwDQYJKoZIhvcNAQEBBQADggEPADCCAQoCggEBALPMQehYkmbd/3vwapACgW9cwRqtGvn6aqEoI+F4gmuQPorDJcpEPO1rPz+2Ac4c/PtUD7pyJosnrTumru7aEQYMU3gsDZCSyuvXZ64gv6QRYHwDef43tbvcMVUB1wmMtAigRo0CJCZHfAyBa3dW7CGgMx4hQVrWqaUOZjjctcEFbzuuumCC34ISvdmH5u8NcHs8ImsPbAjJ2VQKAXoNnWpmY5jONSkU9DStq60HTV4gaxWqUjhEEsAqDBIEQXfqVfMeBpaUa+/3Jn8B8ILYtgCpEbSUdHELIIQD6HKcFCfDJ1Q78zpZk2Wn/fICeQ71RjKb0ONQ+OUIksN9wjpNwisCAwEAATANBgkqhkiG9w0BAQsFAAOCAQEAfqIOiL+KadGbR4O1z4DEQQEpsgn+vdVIz+GF/IXeZUMjZNcDqR9IlhYblesWZKN0GWIFJREuUHTWRXtJhCvjCTbtK/lnezuDo3RT0e0ri15gCKW6StUVIJ6QrnpPCzjHkK+YZ6nCRC5AGHUoQlbP6rWg5nGZT3An6xHyAlvpTdJ2T3kEdVZ+LYvzCIVWu3LMcYK2Xp4eh5VgWGcrdaiybITp5rrIYGoE9nFIvHuLsgwxcHGRJjRPW5y5ctacLa1zJqs2DFNlK7hXV4yxcw1sQaq1OyXu8whkbUM8+cxkKAWMhTdfv6HJdXhbdH2Tmx4DjqSP6iJPoCd2AE6qoI8EEQ==</ds:X509Certificate></ds:X509Data></ds:KeyInfo></ds:Signature></ext:ExtensionContent>\n    </ext:UBLExtension>\n  </ext:UBLExtensions>\n  <cbc:UBLVersionID>2.1</cbc:UBLVersionID>\n  <cbc:CustomizationID>2.0</cbc:CustomizationID>\n  <cbc:ID>FC01-2</cbc:ID>\n  <cbc:IssueDate>2026-11-13</cbc:IssueDate>\n  <cbc:IssueTime>19:26:54</cbc:IssueTime>\n  <cbc:Note languageLocaleID=\"1000\">SON DOSCIENTOS TREINTA Y SEIS CON 00/100 SOLES</cbc:Note>\n  <cbc:DocumentCurrencyCode>PEN</cbc:DocumentCurrencyCode>\n  <cac:DiscrepancyResponse>\n    <cbc:ReferenceID>F001-3</cbc:ReferenceID>\n    <cbc:ResponseCode>09</cbc:ResponseCode>\n    <cbc:Description>Bonificación por volumen</cbc:Description>\n  </cac:DiscrepancyResponse>\n  <cac:BillingReference>\n    <cac:InvoiceDocumentReference>\n      <cbc:ID>F001-3</cbc:ID>\n      <cbc:DocumentTypeCode>01</cbc:DocumentTypeCode>\n    </cac:InvoiceDocumentReference>\n  </cac:BillingReference>\n  <cac:Signature>\n    <cbc:ID>SignatureSP</cbc:ID>\n    <cac:SignatoryParty>\n      <cac:PartyIdentification>\n        <cbc:ID>20601001005</cbc:ID>\n      </cac:PartyIdentification>\n      <cac:PartyName>\n        <cbc:Name>AGRÍCOLA PUCCHÚN S.A.C.</cbc:Name>\n      </cac:PartyName>\n    </cac:SignatoryParty>\n    <cac:DigitalSignatureAttachment>\n      <cac:ExternalReference>\n        <cbc:URI>#SignatureSP</cbc:URI>\n      </cac:ExternalReference>\n    </cac:DigitalSignatureAttachment>\n  </cac:Signature>\n  <cac:AccountingSupplierParty>\n    <cac:Party>\n      <cac:PartyIdentification>\n        <cbc:ID schemeID=\"6\" schemeName=\"Documento de Identidad\" schemeAgencyName=\"PE:SUNAT\" schemeURI=\"urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo06\">20601001005</cbc:ID>\n      </cac:PartyIdentification>\n      <cac:PartyName>\n        <cbc:Name>Agrícola Pucchún</cbc:Name>\n      </cac:PartyName>\n      <cac:PartyLegalEntity>\n        <cbc:RegistrationName>AGRÍCOLA PUCCHÚN S.A.C.</cbc:RegistrationName>\n        <cac:RegistrationAddress>\n          <cbc:ID schemeAgencyName=\"PE:INEI\" schemeName=\"Ubigeos\">150501</cbc:ID>\n          <cbc:AddressTypeCode>0000</cbc:AddressTypeCode>\n          <cbc:CityName>CAÑETE</cbc:CityName>\n          <cbc:CountrySubentity>LIMA</cbc:CountrySubentity>\n          <cbc:District>SAN VICENTE DE CAÑETE</cbc:District>\n          <cac:AddressLine>\n            <cbc:Line>Av. Mariscal Benavides 845</cbc:Line>\n          </cac:AddressLine>\n          <cac:Country>\n            <cbc:IdentificationCode>PE</cbc:IdentificationCode>\n          </cac:Country>\n        </cac:RegistrationAddress>\n      </cac:PartyLegalEntity>\n    </cac:Party>\n  </cac:AccountingSupplierParty>\n  <cac:AccountingCustomerParty>\n    <cac:Party>\n      <cac:PartyIdentification>\n        <cbc:ID schemeID=\"6\" schemeName=\"Documento de Identidad\" schemeAgencyName=\"PE:SUNAT\" schemeURI=\"urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo06\">20603222335</cbc:ID>\n      </cac:PartyIdentification>\n      <cac:PartyLegalEntity>\n        <cbc:RegistrationName>FUNDO LAS PALMERAS S.A.C.</cbc:RegistrationName>\n      </cac:PartyLegalEntity>\n    </cac:Party>\n  </cac:AccountingCustomerParty>\n  <cac:TaxTotal>\n    <cbc:TaxAmount currencyID=\"PEN\">36.00</cbc:TaxAmount>\n    <cac:TaxSubtotal>\n      <cbc:TaxableAmount currencyID=\"PEN\">200.00</cbc:TaxableAmount>\n      <cbc:TaxAmount currencyID=\"PEN\">36.00</cbc:TaxAmount>\n      <cac:TaxCategory>\n        <cac:TaxScheme>\n          <cbc:ID>1000</cbc:ID>\n          <cbc:Name>IGV</cbc:Name>\n          <cbc:TaxTypeCode>VAT</cbc:TaxTypeCode>\n        </cac:TaxScheme>\n      </cac:TaxCategory>\n    </cac:TaxSubtotal>\n  </cac:TaxTotal>\n  <cac:LegalMonetaryTotal>\n    <cbc:LineExtensionAmount currencyID=\"PEN\">200.00</cbc:LineExtensionAmount>\n    <cbc:TaxInclusiveAmount currencyID=\"PEN\">236.00</cbc:TaxInclusiveAmount>\n    <cbc:PayableAmount currencyID=\"PEN\">236.00</cbc:PayableAmount>\n  </cac:LegalMonetaryTotal>\n  <cac:CreditNoteLine>\n    <cbc:ID>1</cbc:ID>\n    <cbc:CreditedQuantity unitCode=\"ZZ\" unitCodeListID=\"UN/ECE rec 20\" unitCodeListAgencyName=\"United Nations Economic Commission for Europe\">1</cbc:CreditedQuantity>\n    <cbc:LineExtensionAmount currencyID=\"PEN\">200.00</cbc:LineExtensionAmount>\n    <cac:PricingReference>\n      <cac:AlternativeConditionPrice>\n        <cbc:PriceAmount currencyID=\"PEN\">236.00</cbc:PriceAmount>\n        <cbc:PriceTypeCode>01</cbc:PriceTypeCode>\n      </cac:AlternativeConditionPrice>\n    </cac:PricingReference>\n    <cac:TaxTotal>\n      <cbc:TaxAmount currencyID=\"PEN\">36.00</cbc:TaxAmount>\n      <cac:TaxSubtotal>\n        <cbc:TaxableAmount currencyID=\"PEN\">200.00</cbc:TaxableAmount>\n        <cbc:TaxAmount currencyID=\"PEN\">36.00</cbc:TaxAmount>\n        <cac:TaxCategory>\n          <cbc:Percent>18</cbc:Percent>\n          <cbc:TaxExemptionReasonCode>10</cbc:TaxExemptionReasonCode>\n          <cac:TaxScheme>\n            <cbc:ID>1000</cbc:ID>\n            <cbc:Name>IGV</cbc:Name>\n            <cbc:TaxTypeCode>VAT</cbc:TaxTypeCode>\n          </cac:TaxScheme>\n        </cac:TaxCategory>\n      </cac:TaxSubtotal>\n    </cac:TaxTotal>\n    <cac:Item>\n      <cbc:Description>Ajuste: Bonificación por volumen</cbc:Description>\n    </cac:Item>\n    <cac:Price>\n      <cbc:PriceAmount currencyID=\"PEN\">200.00</cbc:PriceAmount>\n    </cac:Price>\n  </cac:CreditNoteLine>\n</CreditNote>\n','FNFtyrfxUn3B8Ja+cFXLXi/wK94=','2026-11-13 19:26:54','2026-11-13 19:26:54'),(12,'20601001005-01-F001-6.xml','<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<Invoice xmlns=\"urn:oasis:names:specification:ubl:schema:xsd:Invoice-2\" xmlns:cac=\"urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2\" xmlns:cbc=\"urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2\" xmlns:ext=\"urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2\" xmlns:ds=\"http://www.w3.org/2000/09/xmldsig#\">\n  <ext:UBLExtensions>\n    <ext:UBLExtension>\n      <ext:ExtensionContent><ds:Signature Id=\"SignatureSP\"><ds:SignedInfo><ds:CanonicalizationMethod Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/><ds:SignatureMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#rsa-sha1\"/><ds:Reference URI=\"\"><ds:Transforms><ds:Transform Algorithm=\"http://www.w3.org/2000/09/xmldsig#enveloped-signature\"/></ds:Transforms><ds:DigestMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#sha1\"/><ds:DigestValue>1t06SupsuSslBsEdXMdIdXvTIZo=</ds:DigestValue></ds:Reference></ds:SignedInfo><ds:SignatureValue>YgHkcf+lIK+hU2zjtGkM2v3v+ZmcTCZIn+o6dIJqweqT4RYXhYDZERIOWVP0jbx/t4lIs0oEZGuvHvN7q7mjd3e3wN3UAOQreZt7xEj2/jMNkRnRS2siHdVbNmJa7xsXwU6339jSn6U9xyJgnAvNnOGzAwhF9INShV4agpH5xfc5RE7At7No/JYvQ2IOcz1JwfMtWbSrW5qi/XMFlA6WeQJu1PzUyeQ0ZBk31iBsPfrQ/urPti7K3xXqL8O/y4PUvVVVg5mBmTUtLtyAB3szCOysMo20FhBsS4Ip+Nbq3LqcGSJdTiHQvHQiUAtH9jTdNOzaPtkesFdHhIL+JKKlfA==</ds:SignatureValue><ds:KeyInfo><ds:X509Data><ds:X509Certificate>MIIDRTCCAi2gAwIBAgIBADANBgkqhkiG9w0BAQsFADBmMQswCQYDVQQGEwJQRTEnMCUGA1UECgweQUdSSUNPTEEgUFVDQ0hVTiBTLkEuQy4gKERFTU8pMS4wLAYDVQQDDCVDZXJ0aWZpY2FkbyBkZSBwcnVlYmEgRVJQIEZhY3R1cmFjaW9uMB4XDTI2MTExMzE3NDA0OFoXDTI3MTExMzE3NDA0OFowZjELMAkGA1UEBhMCUEUxJzAlBgNVBAoMHkFHUklDT0xBIFBVQ0NIVU4gUy5BLkMuIChERU1PKTEuMCwGA1UEAwwlQ2VydGlmaWNhZG8gZGUgcHJ1ZWJhIEVSUCBGYWN0dXJhY2lvbjCCASIwDQYJKoZIhvcNAQEBBQADggEPADCCAQoCggEBALPMQehYkmbd/3vwapACgW9cwRqtGvn6aqEoI+F4gmuQPorDJcpEPO1rPz+2Ac4c/PtUD7pyJosnrTumru7aEQYMU3gsDZCSyuvXZ64gv6QRYHwDef43tbvcMVUB1wmMtAigRo0CJCZHfAyBa3dW7CGgMx4hQVrWqaUOZjjctcEFbzuuumCC34ISvdmH5u8NcHs8ImsPbAjJ2VQKAXoNnWpmY5jONSkU9DStq60HTV4gaxWqUjhEEsAqDBIEQXfqVfMeBpaUa+/3Jn8B8ILYtgCpEbSUdHELIIQD6HKcFCfDJ1Q78zpZk2Wn/fICeQ71RjKb0ONQ+OUIksN9wjpNwisCAwEAATANBgkqhkiG9w0BAQsFAAOCAQEAfqIOiL+KadGbR4O1z4DEQQEpsgn+vdVIz+GF/IXeZUMjZNcDqR9IlhYblesWZKN0GWIFJREuUHTWRXtJhCvjCTbtK/lnezuDo3RT0e0ri15gCKW6StUVIJ6QrnpPCzjHkK+YZ6nCRC5AGHUoQlbP6rWg5nGZT3An6xHyAlvpTdJ2T3kEdVZ+LYvzCIVWu3LMcYK2Xp4eh5VgWGcrdaiybITp5rrIYGoE9nFIvHuLsgwxcHGRJjRPW5y5ctacLa1zJqs2DFNlK7hXV4yxcw1sQaq1OyXu8whkbUM8+cxkKAWMhTdfv6HJdXhbdH2Tmx4DjqSP6iJPoCd2AE6qoI8EEQ==</ds:X509Certificate></ds:X509Data></ds:KeyInfo></ds:Signature></ext:ExtensionContent>\n    </ext:UBLExtension>\n  </ext:UBLExtensions>\n  <cbc:UBLVersionID>2.1</cbc:UBLVersionID>\n  <cbc:CustomizationID>2.0</cbc:CustomizationID>\n  <cbc:ID>F001-6</cbc:ID>\n  <cbc:IssueDate>2026-11-14</cbc:IssueDate>\n  <cbc:IssueTime>15:48:33</cbc:IssueTime>\n  <cbc:InvoiceTypeCode listID=\"0101\" listAgencyName=\"PE:SUNAT\" listName=\"Tipo de Documento\" listURI=\"urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo01\">01</cbc:InvoiceTypeCode>\n  <cbc:Note languageLocaleID=\"1000\">SON MIL NOVECIENTOS SETENTA CON 60/100 SOLES</cbc:Note>\n  <cbc:DocumentCurrencyCode>PEN</cbc:DocumentCurrencyCode>\n  <cac:Signature>\n    <cbc:ID>SignatureSP</cbc:ID>\n    <cac:SignatoryParty>\n      <cac:PartyIdentification>\n        <cbc:ID>20601001005</cbc:ID>\n      </cac:PartyIdentification>\n      <cac:PartyName>\n        <cbc:Name>AGRÍCOLA PUCCHÚN S.A.C.</cbc:Name>\n      </cac:PartyName>\n    </cac:SignatoryParty>\n    <cac:DigitalSignatureAttachment>\n      <cac:ExternalReference>\n        <cbc:URI>#SignatureSP</cbc:URI>\n      </cac:ExternalReference>\n    </cac:DigitalSignatureAttachment>\n  </cac:Signature>\n  <cac:AccountingSupplierParty>\n    <cac:Party>\n      <cac:PartyIdentification>\n        <cbc:ID schemeID=\"6\" schemeName=\"Documento de Identidad\" schemeAgencyName=\"PE:SUNAT\" schemeURI=\"urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo06\">20601001005</cbc:ID>\n      </cac:PartyIdentification>\n      <cac:PartyName>\n        <cbc:Name>Agrícola Pucchún</cbc:Name>\n      </cac:PartyName>\n      <cac:PartyLegalEntity>\n        <cbc:RegistrationName>AGRÍCOLA PUCCHÚN S.A.C.</cbc:RegistrationName>\n        <cac:RegistrationAddress>\n          <cbc:ID schemeAgencyName=\"PE:INEI\" schemeName=\"Ubigeos\">150501</cbc:ID>\n          <cbc:AddressTypeCode>0000</cbc:AddressTypeCode>\n          <cbc:CityName>CAÑETE</cbc:CityName>\n          <cbc:CountrySubentity>LIMA</cbc:CountrySubentity>\n          <cbc:District>SAN VICENTE DE CAÑETE</cbc:District>\n          <cac:AddressLine>\n            <cbc:Line>Av. Mariscal Benavides 845</cbc:Line>\n          </cac:AddressLine>\n          <cac:Country>\n            <cbc:IdentificationCode>PE</cbc:IdentificationCode>\n          </cac:Country>\n        </cac:RegistrationAddress>\n      </cac:PartyLegalEntity>\n    </cac:Party>\n  </cac:AccountingSupplierParty>\n  <cac:AccountingCustomerParty>\n    <cac:Party>\n      <cac:PartyIdentification>\n        <cbc:ID schemeID=\"6\" schemeName=\"Documento de Identidad\" schemeAgencyName=\"PE:SUNAT\" schemeURI=\"urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo06\">10445566778</cbc:ID>\n      </cac:PartyIdentification>\n      <cac:PartyLegalEntity>\n        <cbc:RegistrationName>RAMÍREZ QUISPE JUAN CARLOS</cbc:RegistrationName>\n      </cac:PartyLegalEntity>\n    </cac:Party>\n  </cac:AccountingCustomerParty>\n  <cac:PaymentTerms>\n    <cbc:ID>FormaPago</cbc:ID>\n    <cbc:PaymentMeansID>Contado</cbc:PaymentMeansID>\n  </cac:PaymentTerms>\n  <cac:TaxTotal>\n    <cbc:TaxAmount currencyID=\"PEN\">300.60</cbc:TaxAmount>\n    <cac:TaxSubtotal>\n      <cbc:TaxableAmount currencyID=\"PEN\">1670.00</cbc:TaxableAmount>\n      <cbc:TaxAmount currencyID=\"PEN\">300.60</cbc:TaxAmount>\n      <cac:TaxCategory>\n        <cac:TaxScheme>\n          <cbc:ID>1000</cbc:ID>\n          <cbc:Name>IGV</cbc:Name>\n          <cbc:TaxTypeCode>VAT</cbc:TaxTypeCode>\n        </cac:TaxScheme>\n      </cac:TaxCategory>\n    </cac:TaxSubtotal>\n  </cac:TaxTotal>\n  <cac:LegalMonetaryTotal>\n    <cbc:LineExtensionAmount currencyID=\"PEN\">1670.00</cbc:LineExtensionAmount>\n    <cbc:TaxInclusiveAmount currencyID=\"PEN\">1970.60</cbc:TaxInclusiveAmount>\n    <cbc:PayableAmount currencyID=\"PEN\">1970.60</cbc:PayableAmount>\n  </cac:LegalMonetaryTotal>\n  <cac:InvoiceLine>\n    <cbc:ID>1</cbc:ID>\n    <cbc:InvoicedQuantity unitCode=\"LTR\" unitCodeListID=\"UN/ECE rec 20\" unitCodeListAgencyName=\"United Nations Economic Commission for Europe\">3</cbc:InvoicedQuantity>\n    <cbc:LineExtensionAmount currencyID=\"PEN\">945.00</cbc:LineExtensionAmount>\n    <cac:PricingReference>\n      <cac:AlternativeConditionPrice>\n        <cbc:PriceAmount currencyID=\"PEN\">371.70</cbc:PriceAmount>\n        <cbc:PriceTypeCode>01</cbc:PriceTypeCode>\n      </cac:AlternativeConditionPrice>\n    </cac:PricingReference>\n    <cac:TaxTotal>\n      <cbc:TaxAmount currencyID=\"PEN\">170.10</cbc:TaxAmount>\n      <cac:TaxSubtotal>\n        <cbc:TaxableAmount currencyID=\"PEN\">945.00</cbc:TaxableAmount>\n        <cbc:TaxAmount currencyID=\"PEN\">170.10</cbc:TaxAmount>\n        <cac:TaxCategory>\n          <cbc:Percent>18</cbc:Percent>\n          <cbc:TaxExemptionReasonCode>10</cbc:TaxExemptionReasonCode>\n          <cac:TaxScheme>\n            <cbc:ID>1000</cbc:ID>\n            <cbc:Name>IGV</cbc:Name>\n            <cbc:TaxTypeCode>VAT</cbc:TaxTypeCode>\n          </cac:TaxScheme>\n        </cac:TaxCategory>\n      </cac:TaxSubtotal>\n    </cac:TaxTotal>\n    <cac:Item>\n      <cbc:Description>Fungicida Fluopyram + Tebuconazol 400 SC</cbc:Description>\n      <cac:SellersItemIdentification>\n        <cbc:ID>PRD-0005</cbc:ID>\n      </cac:SellersItemIdentification>\n    </cac:Item>\n    <cac:Price>\n      <cbc:PriceAmount currencyID=\"PEN\">315.00</cbc:PriceAmount>\n    </cac:Price>\n  </cac:InvoiceLine>\n  <cac:InvoiceLine>\n    <cbc:ID>2</cbc:ID>\n    <cbc:InvoicedQuantity unitCode=\"BG\" unitCodeListID=\"UN/ECE rec 20\" unitCodeListAgencyName=\"United Nations Economic Commission for Europe\">5</cbc:InvoicedQuantity>\n    <cbc:LineExtensionAmount currencyID=\"PEN\">725.00</cbc:LineExtensionAmount>\n    <cac:PricingReference>\n      <cac:AlternativeConditionPrice>\n        <cbc:PriceAmount currencyID=\"PEN\">175.82</cbc:PriceAmount>\n        <cbc:PriceTypeCode>01</cbc:PriceTypeCode>\n      </cac:AlternativeConditionPrice>\n    </cac:PricingReference>\n    <cac:AllowanceCharge>\n      <cbc:ChargeIndicator>false</cbc:ChargeIndicator>\n      <cbc:AllowanceChargeReasonCode>00</cbc:AllowanceChargeReasonCode>\n      <cbc:MultiplierFactorNumeric>0.02685</cbc:MultiplierFactorNumeric>\n      <cbc:Amount currencyID=\"PEN\">20.00</cbc:Amount>\n      <cbc:BaseAmount currencyID=\"PEN\">745.00</cbc:BaseAmount>\n    </cac:AllowanceCharge>\n    <cac:TaxTotal>\n      <cbc:TaxAmount currencyID=\"PEN\">130.50</cbc:TaxAmount>\n      <cac:TaxSubtotal>\n        <cbc:TaxableAmount currencyID=\"PEN\">725.00</cbc:TaxableAmount>\n        <cbc:TaxAmount currencyID=\"PEN\">130.50</cbc:TaxAmount>\n        <cac:TaxCategory>\n          <cbc:Percent>18</cbc:Percent>\n          <cbc:TaxExemptionReasonCode>10</cbc:TaxExemptionReasonCode>\n          <cac:TaxScheme>\n            <cbc:ID>1000</cbc:ID>\n            <cbc:Name>IGV</cbc:Name>\n            <cbc:TaxTypeCode>VAT</cbc:TaxTypeCode>\n          </cac:TaxScheme>\n        </cac:TaxCategory>\n      </cac:TaxSubtotal>\n    </cac:TaxTotal>\n    <cac:Item>\n      <cbc:Description>Fertilizante Nitrato de Potasio</cbc:Description>\n      <cac:SellersItemIdentification>\n        <cbc:ID>PRD-0009</cbc:ID>\n      </cac:SellersItemIdentification>\n    </cac:Item>\n    <cac:Price>\n      <cbc:PriceAmount currencyID=\"PEN\">149.00</cbc:PriceAmount>\n    </cac:Price>\n  </cac:InvoiceLine>\n</Invoice>\n','1t06SupsuSslBsEdXMdIdXvTIZo=','2026-11-14 15:48:33','2026-11-14 15:48:33'),(13,'20601001005-03-B002-1.xml','<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<Invoice xmlns=\"urn:oasis:names:specification:ubl:schema:xsd:Invoice-2\" xmlns:cac=\"urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2\" xmlns:cbc=\"urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2\" xmlns:ext=\"urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2\" xmlns:ds=\"http://www.w3.org/2000/09/xmldsig#\">\n  <ext:UBLExtensions>\n    <ext:UBLExtension>\n      <ext:ExtensionContent><ds:Signature Id=\"SignatureSP\"><ds:SignedInfo><ds:CanonicalizationMethod Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/><ds:SignatureMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#rsa-sha1\"/><ds:Reference URI=\"\"><ds:Transforms><ds:Transform Algorithm=\"http://www.w3.org/2000/09/xmldsig#enveloped-signature\"/></ds:Transforms><ds:DigestMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#sha1\"/><ds:DigestValue>RPGO/VVSIscJYcEWdoXrK6Tq25M=</ds:DigestValue></ds:Reference></ds:SignedInfo><ds:SignatureValue>eFOrhcxbBb+nJvs70e/cCv5ozLaFvjWlW0TCiVecPYHY42bRq5u7OB7a+7E8K3zLYVX6enLAJAoK6uXtD7UYsok+P84g07TNJQY2vubWvv3oerrYfaHkVPkqEdb+/6AXjI1GtMjxIrtVxkJjZk1ZU/p7ipXoDeQubdRfPcY/SS6G2yqeBFa56qOC3+q5NrufDHhK/diFF3lrj23JdxDXUY5kdK7d/4W5FM8gt1mQuk3EEuvufMw7cYokzlOvlDEKBD/m7kDCyMe/FGAP+x8ZeFK8IEGLfX4S+GPLuzbH8K6m5FMsz9RAB2oiJodCVbX2gpyAmxc9ddSXfbdF3qrS5Q==</ds:SignatureValue><ds:KeyInfo><ds:X509Data><ds:X509Certificate>MIIDRTCCAi2gAwIBAgIBADANBgkqhkiG9w0BAQsFADBmMQswCQYDVQQGEwJQRTEnMCUGA1UECgweQUdSSUNPTEEgUFVDQ0hVTiBTLkEuQy4gKERFTU8pMS4wLAYDVQQDDCVDZXJ0aWZpY2FkbyBkZSBwcnVlYmEgRVJQIEZhY3R1cmFjaW9uMB4XDTI2MTExMzE3NDA0OFoXDTI3MTExMzE3NDA0OFowZjELMAkGA1UEBhMCUEUxJzAlBgNVBAoMHkFHUklDT0xBIFBVQ0NIVU4gUy5BLkMuIChERU1PKTEuMCwGA1UEAwwlQ2VydGlmaWNhZG8gZGUgcHJ1ZWJhIEVSUCBGYWN0dXJhY2lvbjCCASIwDQYJKoZIhvcNAQEBBQADggEPADCCAQoCggEBALPMQehYkmbd/3vwapACgW9cwRqtGvn6aqEoI+F4gmuQPorDJcpEPO1rPz+2Ac4c/PtUD7pyJosnrTumru7aEQYMU3gsDZCSyuvXZ64gv6QRYHwDef43tbvcMVUB1wmMtAigRo0CJCZHfAyBa3dW7CGgMx4hQVrWqaUOZjjctcEFbzuuumCC34ISvdmH5u8NcHs8ImsPbAjJ2VQKAXoNnWpmY5jONSkU9DStq60HTV4gaxWqUjhEEsAqDBIEQXfqVfMeBpaUa+/3Jn8B8ILYtgCpEbSUdHELIIQD6HKcFCfDJ1Q78zpZk2Wn/fICeQ71RjKb0ONQ+OUIksN9wjpNwisCAwEAATANBgkqhkiG9w0BAQsFAAOCAQEAfqIOiL+KadGbR4O1z4DEQQEpsgn+vdVIz+GF/IXeZUMjZNcDqR9IlhYblesWZKN0GWIFJREuUHTWRXtJhCvjCTbtK/lnezuDo3RT0e0ri15gCKW6StUVIJ6QrnpPCzjHkK+YZ6nCRC5AGHUoQlbP6rWg5nGZT3An6xHyAlvpTdJ2T3kEdVZ+LYvzCIVWu3LMcYK2Xp4eh5VgWGcrdaiybITp5rrIYGoE9nFIvHuLsgwxcHGRJjRPW5y5ctacLa1zJqs2DFNlK7hXV4yxcw1sQaq1OyXu8whkbUM8+cxkKAWMhTdfv6HJdXhbdH2Tmx4DjqSP6iJPoCd2AE6qoI8EEQ==</ds:X509Certificate></ds:X509Data></ds:KeyInfo></ds:Signature></ext:ExtensionContent>\n    </ext:UBLExtension>\n  </ext:UBLExtensions>\n  <cbc:UBLVersionID>2.1</cbc:UBLVersionID>\n  <cbc:CustomizationID>2.0</cbc:CustomizationID>\n  <cbc:ID>B002-1</cbc:ID>\n  <cbc:IssueDate>2026-11-14</cbc:IssueDate>\n  <cbc:IssueTime>15:48:49</cbc:IssueTime>\n  <cbc:InvoiceTypeCode listID=\"0101\" listAgencyName=\"PE:SUNAT\" listName=\"Tipo de Documento\" listURI=\"urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo01\">03</cbc:InvoiceTypeCode>\n  <cbc:Note languageLocaleID=\"1000\">SON QUINIENTOS SETENTA Y OCHO CON 20/100 SOLES</cbc:Note>\n  <cbc:DocumentCurrencyCode>PEN</cbc:DocumentCurrencyCode>\n  <cac:Signature>\n    <cbc:ID>SignatureSP</cbc:ID>\n    <cac:SignatoryParty>\n      <cac:PartyIdentification>\n        <cbc:ID>20601001005</cbc:ID>\n      </cac:PartyIdentification>\n      <cac:PartyName>\n        <cbc:Name>AGRÍCOLA PUCCHÚN S.A.C.</cbc:Name>\n      </cac:PartyName>\n    </cac:SignatoryParty>\n    <cac:DigitalSignatureAttachment>\n      <cac:ExternalReference>\n        <cbc:URI>#SignatureSP</cbc:URI>\n      </cac:ExternalReference>\n    </cac:DigitalSignatureAttachment>\n  </cac:Signature>\n  <cac:AccountingSupplierParty>\n    <cac:Party>\n      <cac:PartyIdentification>\n        <cbc:ID schemeID=\"6\" schemeName=\"Documento de Identidad\" schemeAgencyName=\"PE:SUNAT\" schemeURI=\"urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo06\">20601001005</cbc:ID>\n      </cac:PartyIdentification>\n      <cac:PartyName>\n        <cbc:Name>Agrícola Pucchún</cbc:Name>\n      </cac:PartyName>\n      <cac:PartyLegalEntity>\n        <cbc:RegistrationName>AGRÍCOLA PUCCHÚN S.A.C.</cbc:RegistrationName>\n        <cac:RegistrationAddress>\n          <cbc:ID schemeAgencyName=\"PE:INEI\" schemeName=\"Ubigeos\">150509</cbc:ID>\n          <cbc:AddressTypeCode>0001</cbc:AddressTypeCode>\n          <cbc:CityName>CAÑETE</cbc:CityName>\n          <cbc:CountrySubentity>LIMA</cbc:CountrySubentity>\n          <cbc:District>MALA</cbc:District>\n          <cac:AddressLine>\n            <cbc:Line>Jr. Grau 312</cbc:Line>\n          </cac:AddressLine>\n          <cac:Country>\n            <cbc:IdentificationCode>PE</cbc:IdentificationCode>\n          </cac:Country>\n        </cac:RegistrationAddress>\n      </cac:PartyLegalEntity>\n    </cac:Party>\n  </cac:AccountingSupplierParty>\n  <cac:AccountingCustomerParty>\n    <cac:Party>\n      <cac:PartyIdentification>\n        <cbc:ID schemeID=\"1\" schemeName=\"Documento de Identidad\" schemeAgencyName=\"PE:SUNAT\" schemeURI=\"urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo06\">41987654</cbc:ID>\n      </cac:PartyIdentification>\n      <cac:PartyLegalEntity>\n        <cbc:RegistrationName>ROJAS MEDINA PEDRO PABLO</cbc:RegistrationName>\n      </cac:PartyLegalEntity>\n    </cac:Party>\n  </cac:AccountingCustomerParty>\n  <cac:TaxTotal>\n    <cbc:TaxAmount currencyID=\"PEN\">88.20</cbc:TaxAmount>\n    <cac:TaxSubtotal>\n      <cbc:TaxableAmount currencyID=\"PEN\">490.00</cbc:TaxableAmount>\n      <cbc:TaxAmount currencyID=\"PEN\">88.20</cbc:TaxAmount>\n      <cac:TaxCategory>\n        <cac:TaxScheme>\n          <cbc:ID>1000</cbc:ID>\n          <cbc:Name>IGV</cbc:Name>\n          <cbc:TaxTypeCode>VAT</cbc:TaxTypeCode>\n        </cac:TaxScheme>\n      </cac:TaxCategory>\n    </cac:TaxSubtotal>\n  </cac:TaxTotal>\n  <cac:LegalMonetaryTotal>\n    <cbc:LineExtensionAmount currencyID=\"PEN\">490.00</cbc:LineExtensionAmount>\n    <cbc:TaxInclusiveAmount currencyID=\"PEN\">578.20</cbc:TaxInclusiveAmount>\n    <cbc:PayableAmount currencyID=\"PEN\">578.20</cbc:PayableAmount>\n  </cac:LegalMonetaryTotal>\n  <cac:InvoiceLine>\n    <cbc:ID>1</cbc:ID>\n    <cbc:InvoicedQuantity unitCode=\"LTR\" unitCodeListID=\"UN/ECE rec 20\" unitCodeListAgencyName=\"United Nations Economic Commission for Europe\">2</cbc:InvoicedQuantity>\n    <cbc:LineExtensionAmount currencyID=\"PEN\">490.00</cbc:LineExtensionAmount>\n    <cac:PricingReference>\n      <cac:AlternativeConditionPrice>\n        <cbc:PriceAmount currencyID=\"PEN\">289.10</cbc:PriceAmount>\n        <cbc:PriceTypeCode>01</cbc:PriceTypeCode>\n      </cac:AlternativeConditionPrice>\n    </cac:PricingReference>\n    <cac:TaxTotal>\n      <cbc:TaxAmount currencyID=\"PEN\">88.20</cbc:TaxAmount>\n      <cac:TaxSubtotal>\n        <cbc:TaxableAmount currencyID=\"PEN\">490.00</cbc:TaxableAmount>\n        <cbc:TaxAmount currencyID=\"PEN\">88.20</cbc:TaxAmount>\n        <cac:TaxCategory>\n          <cbc:Percent>18</cbc:Percent>\n          <cbc:TaxExemptionReasonCode>10</cbc:TaxExemptionReasonCode>\n          <cac:TaxScheme>\n            <cbc:ID>1000</cbc:ID>\n            <cbc:Name>IGV</cbc:Name>\n            <cbc:TaxTypeCode>VAT</cbc:TaxTypeCode>\n          </cac:TaxScheme>\n        </cac:TaxCategory>\n      </cac:TaxSubtotal>\n    </cac:TaxTotal>\n    <cac:Item>\n      <cbc:Description>Insecticida Spirotetramat 150 OD</cbc:Description>\n      <cac:SellersItemIdentification>\n        <cbc:ID>PRD-0002</cbc:ID>\n      </cac:SellersItemIdentification>\n    </cac:Item>\n    <cac:Price>\n      <cbc:PriceAmount currencyID=\"PEN\">245.00</cbc:PriceAmount>\n    </cac:Price>\n  </cac:InvoiceLine>\n</Invoice>\n','RPGO/VVSIscJYcEWdoXrK6Tq25M=','2026-11-14 15:48:49','2026-11-14 15:48:49');
/*!40000 ALTER TABLE `facturacion_xml` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `facturacion_xml_historial`
--

DROP TABLE IF EXISTS `facturacion_xml_historial`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `facturacion_xml_historial` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `comprobante_id` bigint unsigned NOT NULL,
  `nombre_archivo` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `xml` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `hash` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `generado_en` datetime NOT NULL,
  `firmado_en` datetime DEFAULT NULL,
  `reemplazado_en` datetime NOT NULL,
  `motivo` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_xml_historial_comprobante` (`comprobante_id`,`id`),
  CONSTRAINT `fk_xml_historial_comprobante` FOREIGN KEY (`comprobante_id`) REFERENCES `facturacion_comprobantes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `facturacion_xml_historial`
--

LOCK TABLES `facturacion_xml_historial` WRITE;
/*!40000 ALTER TABLE `facturacion_xml_historial` DISABLE KEYS */;
INSERT INTO `facturacion_xml_historial` VALUES (1,3,'20000000001-03-B001-2.xml','<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<Invoice xmlns=\"urn:oasis:names:specification:ubl:schema:xsd:Invoice-2\" xmlns:cac=\"urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2\" xmlns:cbc=\"urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2\" xmlns:ext=\"urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2\" xmlns:ds=\"http://www.w3.org/2000/09/xmldsig#\">\n  <ext:UBLExtensions>\n    <ext:UBLExtension>\n      <ext:ExtensionContent><ds:Signature Id=\"SignatureSP\"><ds:SignedInfo><ds:CanonicalizationMethod Algorithm=\"http://www.w3.org/TR/2001/REC-xml-c14n-20010315\"/><ds:SignatureMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#rsa-sha1\"/><ds:Reference URI=\"\"><ds:Transforms><ds:Transform Algorithm=\"http://www.w3.org/2000/09/xmldsig#enveloped-signature\"/></ds:Transforms><ds:DigestMethod Algorithm=\"http://www.w3.org/2000/09/xmldsig#sha1\"/><ds:DigestValue>tuLzETJJgYSaHqyJF5unG00PgxU=</ds:DigestValue></ds:Reference></ds:SignedInfo><ds:SignatureValue>PzKkMGj66cvuzp/ysbS5Dn/hLL1llgI34z8VAIP56dePnNeMVD0PtJkyUfEF2fw7IBxpmENv5/KtQWE+YjHl4S50UWsF5b0ChjX45+f4xPDdfWFHcS+Saa+CEbiQPIDwiw1q6hGQT1sve1UdYA0F1mUp6O4dxLDvdkCveYsPaKrDpJtP11/fqCq0PvpIAsxF37TXEYIz9zvsR/qTKDla+gOB6v+1/KSeUc5nj1MhD103MI7gGES5knw7UIJ1URthna2NuLzgdmJY1aqfgsc9odQX7hz1sjIUv7CLCzbWL/N6L9kqUbkTN9fakeYwSCJaoHYfV9Ho9u+1NfNJVud1tA==</ds:SignatureValue><ds:KeyInfo><ds:X509Data><ds:X509Certificate>MIIDRTCCAi2gAwIBAgIBADANBgkqhkiG9w0BAQsFADBmMQswCQYDVQQGEwJQRTEnMCUGA1UECgweQUdSSUNPTEEgUFVDQ0hVTiBTLkEuQy4gKERFTU8pMS4wLAYDVQQDDCVDZXJ0aWZpY2FkbyBkZSBwcnVlYmEgRVJQIEZhY3R1cmFjaW9uMB4XDTI2MDkzMDE3Mjg0OVoXDTI3MDkzMDE3Mjg0OVowZjELMAkGA1UEBhMCUEUxJzAlBgNVBAoMHkFHUklDT0xBIFBVQ0NIVU4gUy5BLkMuIChERU1PKTEuMCwGA1UEAwwlQ2VydGlmaWNhZG8gZGUgcHJ1ZWJhIEVSUCBGYWN0dXJhY2lvbjCCASIwDQYJKoZIhvcNAQEBBQADggEPADCCAQoCggEBAMuSbhRTsaO4XShapf0TZxBwC+NTThWSBmBVCRsJMeC6oJB/Qy4KHpLn6kVpT231WjvN9YKfv+JIrTBF2b4NOGENgn86agCeUULJv7Burlg1ARhZalj1KDi3tPY5TzxzXqzS2mXeux/3/hXoj4998mPZcUFn3ii8cPkFNnC5CC2fAcjVCID0Ad4kP7zWd+K6TeXuXk9lWd2kNNDO5fcVWxH1qzPy6LdnGfjTqY345F5UgAPZ45OJh/qQQhGtd/OgwqSEG8qwFnOs/J/XsnXYH2qD0oAMgeiO566cazjRCNIAsivcF12b5dJtUR6W+aTwJtv4Fp/am59zSHU7Oh3CgJ0CAwEAATANBgkqhkiG9w0BAQsFAAOCAQEATTvnG4Ux8dOoRmMTAIAMpyTvoySUsh+zGFKtJ1VwOi2tt/s0kl9X5F3JjaOiUl0iA/Yl7i4T81af5bHydZ2tHZ6EWPXdqUx20eJhnRCloo6jaKndDao4xuJhYbIAlYy59yEytBzMv5t05rnTlAQBS26fISQO7nnhOcC5xilo+ChAZubBGkWkSmt4fkPkfZf++di+t4k6fUdajggK43a7v3fre68lGKCEKD+8SL3O+qtRyWFw79qh8DRd127R8V5Xazq6CrdD5VuxZH2qG0IRZAHWg0B82rXz778ekL3XAqnJ4sGCQkqtIpS5xPT2OI29u0I7POzBN21HZHUQG7B2HA==</ds:X509Certificate></ds:X509Data></ds:KeyInfo></ds:Signature></ext:ExtensionContent>\n    </ext:UBLExtension>\n  </ext:UBLExtensions>\n  <cbc:UBLVersionID>2.1</cbc:UBLVersionID>\n  <cbc:CustomizationID>2.0</cbc:CustomizationID>\n  <cbc:ID>B001-2</cbc:ID>\n  <cbc:IssueDate>2026-10-09</cbc:IssueDate>\n  <cbc:IssueTime>13:54:07</cbc:IssueTime>\n  <cbc:InvoiceTypeCode listID=\"0101\" listAgencyName=\"PE:SUNAT\" listName=\"Tipo de Documento\" listURI=\"urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo01\">03</cbc:InvoiceTypeCode>\n  <cbc:Note languageLocaleID=\"1000\">SON CINCO CON 66/100 SOLES</cbc:Note>\n  <cbc:DocumentCurrencyCode>PEN</cbc:DocumentCurrencyCode>\n  <cac:Signature>\n    <cbc:ID>SignatureSP</cbc:ID>\n    <cac:SignatoryParty>\n      <cac:PartyIdentification>\n        <cbc:ID>20000000001</cbc:ID>\n      </cac:PartyIdentification>\n      <cac:PartyName>\n        <cbc:Name>AGRICOLA PUCCHUN S.A.C.</cbc:Name>\n      </cac:PartyName>\n    </cac:SignatoryParty>\n    <cac:DigitalSignatureAttachment>\n      <cac:ExternalReference>\n        <cbc:URI>#SignatureSP</cbc:URI>\n      </cac:ExternalReference>\n    </cac:DigitalSignatureAttachment>\n  </cac:Signature>\n  <cac:AccountingSupplierParty>\n    <cac:Party>\n      <cac:PartyIdentification>\n        <cbc:ID schemeID=\"6\" schemeName=\"Documento de Identidad\" schemeAgencyName=\"PE:SUNAT\" schemeURI=\"urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo06\">20000000001</cbc:ID>\n      </cac:PartyIdentification>\n      <cac:PartyName>\n        <cbc:Name>Agrícola Pucchún</cbc:Name>\n      </cac:PartyName>\n      <cac:PartyLegalEntity>\n        <cbc:RegistrationName>AGRICOLA PUCCHUN S.A.C.</cbc:RegistrationName>\n        <cac:RegistrationAddress>\n          <cbc:ID schemeAgencyName=\"PE:INEI\" schemeName=\"Ubigeos\">150101</cbc:ID>\n          <cbc:AddressTypeCode>0000</cbc:AddressTypeCode>\n          <cbc:CityName>LIMA</cbc:CityName>\n          <cbc:CountrySubentity>LIMA</cbc:CountrySubentity>\n          <cbc:District>LIMA</cbc:District>\n          <cac:AddressLine>\n            <cbc:Line>Av. Demostración 123</cbc:Line>\n          </cac:AddressLine>\n          <cac:Country>\n            <cbc:IdentificationCode>PE</cbc:IdentificationCode>\n          </cac:Country>\n        </cac:RegistrationAddress>\n      </cac:PartyLegalEntity>\n    </cac:Party>\n  </cac:AccountingSupplierParty>\n  <cac:AccountingCustomerParty>\n    <cac:Party>\n      <cac:PartyIdentification>\n        <cbc:ID schemeID=\"1\" schemeName=\"Documento de Identidad\" schemeAgencyName=\"PE:SUNAT\" schemeURI=\"urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo06\">72664039</cbc:ID>\n      </cac:PartyIdentification>\n      <cac:PartyLegalEntity>\n        <cbc:RegistrationName>2345245345</cbc:RegistrationName>\n      </cac:PartyLegalEntity>\n    </cac:Party>\n  </cac:AccountingCustomerParty>\n  <cac:TaxTotal>\n    <cbc:TaxAmount currencyID=\"PEN\">0.86</cbc:TaxAmount>\n    <cac:TaxSubtotal>\n      <cbc:TaxableAmount currencyID=\"PEN\">4.80</cbc:TaxableAmount>\n      <cbc:TaxAmount currencyID=\"PEN\">0.86</cbc:TaxAmount>\n      <cac:TaxCategory>\n        <cac:TaxScheme>\n          <cbc:ID>1000</cbc:ID>\n          <cbc:Name>IGV</cbc:Name>\n          <cbc:TaxTypeCode>VAT</cbc:TaxTypeCode>\n        </cac:TaxScheme>\n      </cac:TaxCategory>\n    </cac:TaxSubtotal>\n  </cac:TaxTotal>\n  <cac:LegalMonetaryTotal>\n    <cbc:LineExtensionAmount currencyID=\"PEN\">4.80</cbc:LineExtensionAmount>\n    <cbc:TaxInclusiveAmount currencyID=\"PEN\">5.66</cbc:TaxInclusiveAmount>\n    <cbc:PayableAmount currencyID=\"PEN\">5.66</cbc:PayableAmount>\n  </cac:LegalMonetaryTotal>\n  <cac:InvoiceLine>\n    <cbc:ID>1</cbc:ID>\n    <cbc:InvoicedQuantity unitCode=\"NIU\" unitCodeListID=\"UN/ECE rec 20\" unitCodeListAgencyName=\"United Nations Economic Commission for Europe\">1</cbc:InvoicedQuantity>\n    <cbc:LineExtensionAmount currencyID=\"PEN\">4.80</cbc:LineExtensionAmount>\n    <cac:PricingReference>\n      <cac:AlternativeConditionPrice>\n        <cbc:PriceAmount currencyID=\"PEN\">5.66</cbc:PriceAmount>\n        <cbc:PriceTypeCode>01</cbc:PriceTypeCode>\n      </cac:AlternativeConditionPrice>\n    </cac:PricingReference>\n    <cac:TaxTotal>\n      <cbc:TaxAmount currencyID=\"PEN\">0.86</cbc:TaxAmount>\n      <cac:TaxSubtotal>\n        <cbc:TaxableAmount currencyID=\"PEN\">4.80</cbc:TaxableAmount>\n        <cbc:TaxAmount currencyID=\"PEN\">0.86</cbc:TaxAmount>\n        <cac:TaxCategory>\n          <cbc:Percent>18</cbc:Percent>\n          <cbc:TaxExemptionReasonCode>10</cbc:TaxExemptionReasonCode>\n          <cac:TaxScheme>\n            <cbc:ID>1000</cbc:ID>\n            <cbc:Name>IGV</cbc:Name>\n            <cbc:TaxTypeCode>VAT</cbc:TaxTypeCode>\n          </cac:TaxScheme>\n        </cac:TaxCategory>\n      </cac:TaxSubtotal>\n    </cac:TaxTotal>\n    <cac:Item>\n      <cbc:Description>Tomate (kg)</cbc:Description>\n      <cac:SellersItemIdentification>\n        <cbc:ID>PROD-007</cbc:ID>\n      </cac:SellersItemIdentification>\n    </cac:Item>\n    <cac:Price>\n      <cbc:PriceAmount currencyID=\"PEN\">4.80</cbc:PriceAmount>\n    </cac:Price>\n  </cac:InvoiceLine>\n</Invoice>\n','tuLzETJJgYSaHqyJF5unG00PgxU=','2026-10-09 13:54:18','2026-10-09 13:54:24','2026-10-09 14:06:11','se corrigio xml');
/*!40000 ALTER TABLE `facturacion_xml_historial` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `g7_ajustes`
--

DROP TABLE IF EXISTS `g7_ajustes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `g7_ajustes` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `codigo` varchar(35) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `producto_id` bigint NOT NULL,
  `almacen_id` int NOT NULL,
  `cantidad` decimal(14,3) NOT NULL,
  `tipo` enum('MERMA','AJUSTE_POSITIVO','AJUSTE_NEGATIVO') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `motivo` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `observacion` varchar(300) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `fecha` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo` (`codigo`),
  KEY `fk_g7_aj_prod` (`producto_id`),
  KEY `fk_g7_aj_alm` (`almacen_id`),
  CONSTRAINT `fk_g7_aj_alm` FOREIGN KEY (`almacen_id`) REFERENCES `almacenes` (`id`),
  CONSTRAINT `fk_g7_aj_prod` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`),
  CONSTRAINT `ck_g7_aj` CHECK ((`cantidad` > 0))
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `g7_ajustes`
--

LOCK TABLES `g7_ajustes` WRITE;
/*!40000 ALTER TABLE `g7_ajustes` DISABLE KEYS */;
INSERT INTO `g7_ajustes` VALUES (1,'MR-2026-0001',14,2,1.000,'MERMA','Envase dañado','Auditoría final','2026-11-14 15:51:28');
/*!40000 ALTER TABLE `g7_ajustes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `g7_auditoria`
--

DROP TABLE IF EXISTS `g7_auditoria`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `g7_auditoria` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `fecha` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `usuario_id` bigint DEFAULT NULL,
  `accion` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `detalle` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_g7_aud_user` (`usuario_id`),
  CONSTRAINT `fk_g7_aud_user` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=64 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `g7_auditoria`
--

LOCK TABLES `g7_auditoria` WRITE;
/*!40000 ALTER TABLE `g7_auditoria` DISABLE KEYS */;
INSERT INTO `g7_auditoria` VALUES (1,'2026-11-13 19:10:45',1,'ORDEN_COMPRA','{\"codigo\":\"OC-2026-0001\",\"lineas\":5}'),(2,'2026-11-13 19:10:45',1,'RECEPCION','{\"referencia\":\"RC-2026-0001\",\"producto_id\":3,\"cantidad\":120,\"orden_id\":1}'),(3,'2026-11-13 19:10:45',1,'RECEPCION','{\"referencia\":\"RC-2026-0002\",\"producto_id\":4,\"cantidad\":40,\"orden_id\":1}'),(4,'2026-11-13 19:10:45',1,'RECEPCION','{\"referencia\":\"RC-2026-0003\",\"producto_id\":5,\"cantidad\":80,\"orden_id\":1}'),(5,'2026-11-13 19:10:45',1,'RECEPCION','{\"referencia\":\"RC-2026-0004\",\"producto_id\":6,\"cantidad\":50,\"orden_id\":1}'),(6,'2026-11-13 19:10:45',1,'RECEPCION','{\"referencia\":\"RC-2026-0005\",\"producto_id\":7,\"cantidad\":30,\"orden_id\":1}'),(7,'2026-11-13 19:10:45',1,'ORDEN_COMPRA','{\"codigo\":\"OC-2026-0002\",\"lineas\":2}'),(8,'2026-11-13 19:10:45',1,'RECEPCION','{\"referencia\":\"RC-2026-0006\",\"producto_id\":8,\"cantidad\":60,\"orden_id\":2}'),(9,'2026-11-13 19:10:45',1,'RECEPCION','{\"referencia\":\"RC-2026-0007\",\"producto_id\":9,\"cantidad\":70,\"orden_id\":2}'),(10,'2026-11-13 19:10:45',1,'ORDEN_COMPRA','{\"codigo\":\"OC-2026-0003\",\"lineas\":5}'),(11,'2026-11-13 19:10:45',1,'RECEPCION','{\"referencia\":\"RC-2026-0008\",\"producto_id\":10,\"cantidad\":100,\"orden_id\":3}'),(12,'2026-11-13 19:10:45',1,'RECEPCION','{\"referencia\":\"RC-2026-0009\",\"producto_id\":11,\"cantidad\":60,\"orden_id\":3}'),(13,'2026-11-13 19:10:45',1,'RECEPCION','{\"referencia\":\"RC-2026-0010\",\"producto_id\":12,\"cantidad\":150,\"orden_id\":3}'),(14,'2026-11-13 19:10:45',1,'RECEPCION','{\"referencia\":\"RC-2026-0011\",\"producto_id\":13,\"cantidad\":25,\"orden_id\":3}'),(15,'2026-11-13 19:10:45',1,'RECEPCION','{\"referencia\":\"RC-2026-0012\",\"producto_id\":14,\"cantidad\":60,\"orden_id\":3}'),(16,'2026-11-13 19:10:45',1,'ORDEN_COMPRA','{\"codigo\":\"OC-2026-0004\",\"lineas\":3}'),(17,'2026-11-13 19:10:45',1,'RECEPCION','{\"referencia\":\"RC-2026-0013\",\"producto_id\":3,\"cantidad\":60,\"orden_id\":4}'),(18,'2026-11-13 19:10:45',1,'RECEPCION','{\"referencia\":\"RC-2026-0014\",\"producto_id\":6,\"cantidad\":25,\"orden_id\":4}'),(19,'2026-11-13 19:10:45',1,'RECEPCION','{\"referencia\":\"RC-2026-0015\",\"producto_id\":7,\"cantidad\":20,\"orden_id\":4}'),(20,'2026-11-13 19:10:45',1,'ORDEN_COMPRA','{\"codigo\":\"OC-2026-0005\",\"lineas\":3}'),(21,'2026-11-13 19:10:45',1,'RECEPCION','{\"referencia\":\"RC-2026-0016\",\"producto_id\":10,\"cantidad\":60,\"orden_id\":5}'),(22,'2026-11-13 19:10:45',1,'RECEPCION','{\"referencia\":\"RC-2026-0017\",\"producto_id\":12,\"cantidad\":80,\"orden_id\":5}'),(23,'2026-11-13 19:10:45',1,'RECEPCION','{\"referencia\":\"RC-2026-0018\",\"producto_id\":14,\"cantidad\":40,\"orden_id\":5}'),(24,'2026-11-13 19:10:45',1,'ORDEN_COMPRA','{\"codigo\":\"OC-2026-0006\",\"lineas\":1}'),(25,'2026-11-13 19:11:10',1,'DESPACHO_POR_VENTA','{\"venta\":\"VEN001-00000001\",\"despachos\":[\"DP-2026-0001\",\"DP-2026-0002\"]}'),(26,'2026-11-13 19:11:10',1,'DESPACHO_POR_VENTA','{\"venta\":\"VEN001-00000002\",\"despachos\":[\"DP-2026-0003\",\"DP-2026-0004\"]}'),(27,'2026-11-13 19:11:10',1,'DESPACHO_POR_VENTA','{\"venta\":\"VEN001-00000003\",\"despachos\":[\"DP-2026-0005\",\"DP-2026-0006\"]}'),(28,'2026-11-13 19:11:31',1,'DESPACHO_POR_VENTA','{\"venta\":\"VEN002-00000001\",\"despachos\":[\"DP-2026-0007\",\"DP-2026-0008\"]}'),(29,'2026-11-13 19:11:31',1,'DESPACHO_POR_VENTA','{\"venta\":\"VEN002-00000002\",\"despachos\":[\"DP-2026-0009\",\"DP-2026-0010\"]}'),(30,'2026-11-13 19:11:55',1,'DESPACHO_SALIR','{\"codigo\":\"DP-2026-0002\"}'),(31,'2026-11-13 19:11:55',1,'DESPACHO_ENTREGAR','{\"codigo\":\"DP-2026-0002\"}'),(32,'2026-11-13 19:11:55',1,'DESPACHO_SALIR','{\"codigo\":\"DP-2026-0001\"}'),(33,'2026-11-13 19:11:55',1,'DESPACHO_ENTREGAR','{\"codigo\":\"DP-2026-0001\"}'),(34,'2026-11-13 19:11:55',1,'DESPACHO_SALIR','{\"codigo\":\"DP-2026-0006\"}'),(35,'2026-11-13 19:11:55',1,'DESPACHO_ENTREGAR','{\"codigo\":\"DP-2026-0006\"}'),(36,'2026-11-13 19:11:55',1,'DESPACHO_SALIR','{\"codigo\":\"DP-2026-0005\"}'),(37,'2026-11-13 19:11:55',1,'DESPACHO_ENTREGAR','{\"codigo\":\"DP-2026-0005\"}'),(38,'2026-11-13 19:11:55',1,'DESPACHO_SALIR','{\"codigo\":\"DP-2026-0004\"}'),(39,'2026-11-13 19:11:55',1,'DESPACHO_SALIR','{\"codigo\":\"DP-2026-0003\"}'),(40,'2026-11-13 19:11:55',1,'PAGO_PROVEEDOR','{\"cuenta\":\"CP-2026-0007\",\"importe\":2660,\"medio\":\"transferencia\"}'),(41,'2026-11-13 19:11:55',1,'PAGO_PROVEEDOR','{\"cuenta\":\"CP-2026-0006\",\"importe\":3300,\"medio\":\"transferencia\"}'),(42,'2026-11-13 19:11:55',1,'PAGO_PROVEEDOR','{\"cuenta\":\"CP-2026-0018\",\"importe\":150,\"medio\":\"efectivo\"}'),(44,'2026-11-13 20:59:37',1,'PAGO_PROVEEDOR','{\"cuenta\":\"CP-2026-0015\",\"importe\":4600,\"medio\":\"transferencia\"}'),(45,'2026-11-13 20:59:50',1,'DESPACHO_PREPARADO','{\"codigo\":\"DP-2026-0011\",\"cantidad\":2}'),(46,'2026-11-13 20:59:50',1,'DESPACHO_CANCELAR','{\"codigo\":\"DP-2026-0011\"}'),(47,'2026-11-14 15:48:17',1,'DESPACHO_POR_VENTA','{\"venta\":\"VEN001-00000004\",\"despachos\":[\"DP-2026-0012\",\"DP-2026-0013\"]}'),(48,'2026-11-14 15:48:33',1,'DESPACHO_SALIR','{\"codigo\":\"DP-2026-0013\"}'),(49,'2026-11-14 15:48:33',1,'DESPACHO_ENTREGAR','{\"codigo\":\"DP-2026-0013\"}'),(50,'2026-11-14 15:48:33',1,'DESPACHO_SALIR','{\"codigo\":\"DP-2026-0012\"}'),(51,'2026-11-14 15:48:33',1,'DESPACHO_ENTREGAR','{\"codigo\":\"DP-2026-0012\"}'),(52,'2026-11-14 15:48:48',1,'ORDEN_COMPRA','{\"codigo\":\"OC-2026-0008\",\"lineas\":1}'),(53,'2026-11-14 15:48:48',1,'RECEPCION','{\"referencia\":\"RC-2026-0019\",\"producto_id\":4,\"cantidad\":6,\"orden_id\":8}'),(54,'2026-11-14 15:48:48',1,'PAGO_PROVEEDOR','{\"cuenta\":\"CP-2026-0019\",\"importe\":300,\"medio\":\"transferencia\"}'),(55,'2026-11-14 15:48:49',1,'DESPACHO_POR_VENTA','{\"venta\":\"VEN002-00000003\",\"despachos\":[\"DP-2026-0014\"]}'),(58,'2026-11-14 15:51:28',1,'TRASLADO_SALIDA','{\"codigo\":\"TR-2026-0001\",\"cantidad\":5}'),(59,'2026-11-14 15:51:28',1,'TRASLADO_RECEPCION','{\"codigo\":\"TR-2026-0001\"}'),(60,'2026-11-14 15:51:28',1,'AJUSTE','{\"codigo\":\"MR-2026-0001\",\"tipo\":\"MERMA\",\"cantidad\":1}'),(61,'2026-11-14 15:51:28',1,'CONTEO_ABIERTO','{\"codigo\":\"CF-2026-0001\",\"articulos\":1}'),(62,'2026-11-14 15:51:28',1,'CONTEO_REGULARIZADO','{\"codigo\":\"CF-2026-0001\"}');
/*!40000 ALTER TABLE `g7_auditoria` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `g7_conteo_detalles`
--

DROP TABLE IF EXISTS `g7_conteo_detalles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `g7_conteo_detalles` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `conteo_id` bigint NOT NULL,
  `producto_id` bigint NOT NULL,
  `cantidad_sistema` decimal(14,3) NOT NULL,
  `cantidad_contada` decimal(14,3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_g7_conteo_articulo` (`conteo_id`,`producto_id`),
  KEY `fk_g7_cd_producto` (`producto_id`),
  CONSTRAINT `fk_g7_cd_conteo` FOREIGN KEY (`conteo_id`) REFERENCES `g7_conteos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_g7_cd_producto` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`),
  CONSTRAINT `ck_g7_cd` CHECK (((`cantidad_sistema` >= 0) and (`cantidad_contada` >= 0)))
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `g7_conteo_detalles`
--

LOCK TABLES `g7_conteo_detalles` WRITE;
/*!40000 ALTER TABLE `g7_conteo_detalles` DISABLE KEYS */;
INSERT INTO `g7_conteo_detalles` VALUES (1,1,14,44.000,43.000);
/*!40000 ALTER TABLE `g7_conteo_detalles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `g7_conteos`
--

DROP TABLE IF EXISTS `g7_conteos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `g7_conteos` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `codigo` varchar(35) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `almacen_id` int NOT NULL,
  `responsable` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Operador de almac├®n',
  `estado` enum('PENDIENTE','REGULARIZADO') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'PENDIENTE',
  `fecha` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_aplicacion` datetime DEFAULT NULL,
  `observacion` varchar(300) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo` (`codigo`),
  KEY `fk_g7_c_alm` (`almacen_id`),
  CONSTRAINT `fk_g7_c_alm` FOREIGN KEY (`almacen_id`) REFERENCES `almacenes` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `g7_conteos`
--

LOCK TABLES `g7_conteos` WRITE;
/*!40000 ALTER TABLE `g7_conteos` DISABLE KEYS */;
INSERT INTO `g7_conteos` VALUES (1,'CF-2026-0001',2,'Jefe de almacén','REGULARIZADO','2026-11-14 15:51:28','2026-11-14 15:51:28','Conteo cíclico');
/*!40000 ALTER TABLE `g7_conteos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `g7_cuentas_pagar`
--

DROP TABLE IF EXISTS `g7_cuentas_pagar`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `g7_cuentas_pagar` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `codigo` varchar(35) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `recepcion_id` bigint NOT NULL,
  `proveedor_id` bigint NOT NULL,
  `importe` decimal(14,2) NOT NULL,
  `pagado` decimal(14,2) NOT NULL DEFAULT '0.00',
  `vencimiento` date NOT NULL,
  `fecha` date NOT NULL DEFAULT (curdate()),
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo` (`codigo`),
  UNIQUE KEY `recepcion_id` (`recepcion_id`),
  KEY `fk_g7_cp_proveedor` (`proveedor_id`),
  CONSTRAINT `fk_g7_cp_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`),
  CONSTRAINT `fk_g7_cp_recepcion` FOREIGN KEY (`recepcion_id`) REFERENCES `g7_recepciones` (`id`),
  CONSTRAINT `ck_g7_cp` CHECK (((`importe` >= 0) and (`pagado` >= 0) and (`pagado` <= `importe`)))
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `g7_cuentas_pagar`
--

LOCK TABLES `g7_cuentas_pagar` WRITE;
/*!40000 ALTER TABLE `g7_cuentas_pagar` DISABLE KEYS */;
INSERT INTO `g7_cuentas_pagar` VALUES (1,'CP-2026-0001',1,1,8160.00,0.00,'2026-12-14','2026-11-13'),(2,'CP-2026-0002',2,1,7200.00,0.00,'2026-12-14','2026-11-13'),(3,'CP-2026-0003',3,1,3200.00,0.00,'2026-12-14','2026-11-13'),(4,'CP-2026-0004',4,1,10500.00,0.00,'2026-12-14','2026-11-13'),(5,'CP-2026-0005',5,1,6900.00,0.00,'2026-12-14','2026-11-13'),(6,'CP-2026-0006',6,2,3300.00,3300.00,'2026-12-14','2026-11-13'),(7,'CP-2026-0007',7,2,2660.00,2660.00,'2026-12-14','2026-11-13'),(8,'CP-2026-0008',8,3,9500.00,0.00,'2026-12-14','2026-11-13'),(9,'CP-2026-0009',9,3,6600.00,0.00,'2026-12-14','2026-11-13'),(10,'CP-2026-0010',10,3,2700.00,0.00,'2026-12-14','2026-11-13'),(11,'CP-2026-0011',11,3,7000.00,0.00,'2026-12-14','2026-11-13'),(12,'CP-2026-0012',12,3,2700.00,0.00,'2026-12-14','2026-11-13'),(13,'CP-2026-0013',13,1,4080.00,0.00,'2026-12-14','2026-11-13'),(14,'CP-2026-0014',14,1,5250.00,0.00,'2026-12-14','2026-11-13'),(15,'CP-2026-0015',15,1,4600.00,4600.00,'2026-12-14','2026-11-13'),(16,'CP-2026-0016',16,3,5700.00,0.00,'2026-12-14','2026-11-13'),(17,'CP-2026-0017',17,3,1440.00,0.00,'2026-12-14','2026-11-13'),(18,'CP-2026-0018',18,3,1800.00,150.00,'2026-12-14','2026-11-13'),(19,'CP-2026-0019',19,1,1080.00,300.00,'2026-12-14','2026-11-14');
/*!40000 ALTER TABLE `g7_cuentas_pagar` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `g7_despachos`
--

DROP TABLE IF EXISTS `g7_despachos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `g7_despachos` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `codigo` varchar(35) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `producto_id` bigint NOT NULL,
  `almacen_id` int NOT NULL,
  `cliente` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `cliente_id` bigint DEFAULT NULL,
  `cantidad` decimal(14,3) NOT NULL,
  `estado` enum('PREPARACION','EN_RUTA','ENTREGADO','CANCELADO') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'PREPARACION',
  `documento_venta` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `venta_id` bigint DEFAULT NULL,
  `externo_id` varchar(70) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `destino` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `transporte` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `fecha` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_salida` datetime DEFAULT NULL,
  `fecha_entrega` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo` (`codigo`),
  UNIQUE KEY `externo_id` (`externo_id`),
  KEY `fk_g7_d_prod` (`producto_id`),
  KEY `fk_g7_d_alm` (`almacen_id`),
  KEY `idx_g7_d_venta` (`venta_id`),
  KEY `fk_g7_d_cliente` (`cliente_id`),
  CONSTRAINT `fk_g7_d_alm` FOREIGN KEY (`almacen_id`) REFERENCES `almacenes` (`id`),
  CONSTRAINT `fk_g7_d_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`),
  CONSTRAINT `fk_g7_d_prod` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`),
  CONSTRAINT `fk_g7_d_venta` FOREIGN KEY (`venta_id`) REFERENCES `ventas` (`id`),
  CONSTRAINT `ck_g7_d` CHECK ((`cantidad` > 0))
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `g7_despachos`
--

LOCK TABLES `g7_despachos` WRITE;
/*!40000 ALTER TABLE `g7_despachos` DISABLE KEYS */;
INSERT INTO `g7_despachos` VALUES (1,'DP-2026-0001',3,1,'AGROEXPORTADORA VALLE SUR S.A.C.',1,20.000,'ENTREGADO','VEN001-00000001',1,'VEN001-00000001#1','Av. Mariscal Benavides 845','','2026-11-13 19:11:10','2026-11-13 19:11:55','2026-11-13 19:11:55'),(2,'DP-2026-0002',6,1,'AGROEXPORTADORA VALLE SUR S.A.C.',1,10.000,'ENTREGADO','VEN001-00000001',1,'VEN001-00000001#2','Av. Mariscal Benavides 845','','2026-11-13 19:11:10','2026-11-13 19:11:55','2026-11-13 19:11:55'),(3,'DP-2026-0003',10,1,'FUNDO LAS PALMERAS S.A.C.',2,15.000,'EN_RUTA','VEN001-00000002',2,'VEN001-00000002#1','Av. Mariscal Benavides 845','','2026-11-13 19:11:10','2026-11-13 19:11:55',NULL),(4,'DP-2026-0004',12,1,'FUNDO LAS PALMERAS S.A.C.',2,30.000,'EN_RUTA','VEN001-00000002',2,'VEN001-00000002#2','Av. Mariscal Benavides 845','','2026-11-13 19:11:10','2026-11-13 19:11:55',NULL),(5,'DP-2026-0005',14,1,'HUAMÁN TORRES MARÍA ELENA',7,2.000,'ENTREGADO','VEN001-00000003',3,'VEN001-00000003#1','Av. Mariscal Benavides 845','','2026-11-13 19:11:10','2026-11-13 19:11:55','2026-11-13 19:11:55'),(6,'DP-2026-0006',5,1,'HUAMÁN TORRES MARÍA ELENA',7,1.000,'ENTREGADO','VEN001-00000003',3,'VEN001-00000003#2','Av. Mariscal Benavides 845','','2026-11-13 19:11:10','2026-11-13 19:11:55','2026-11-13 19:11:55'),(7,'DP-2026-0007',3,2,'INVERSIONES AGRÍCOLAS MALA E.I.R.L.',4,10.000,'PREPARACION','VEN002-00000001',4,'VEN002-00000001#1','Jr. Grau 312','','2026-11-13 19:11:31',NULL,NULL),(8,'DP-2026-0008',7,2,'INVERSIONES AGRÍCOLAS MALA E.I.R.L.',4,5.000,'PREPARACION','VEN002-00000001',4,'VEN002-00000001#2','Jr. Grau 312','','2026-11-13 19:11:31',NULL,NULL),(9,'DP-2026-0009',6,2,'AGRÍCOLA CHINCHA DEL SUR S.A.C.',5,8.000,'PREPARACION','VEN002-00000002',5,'VEN002-00000002#1','Jr. Grau 312','','2026-11-13 19:11:31',NULL,NULL),(10,'DP-2026-0010',10,2,'AGRÍCOLA CHINCHA DEL SUR S.A.C.',5,20.000,'PREPARACION','VEN002-00000002',5,'VEN002-00000002#2','Jr. Grau 312','','2026-11-13 19:11:31',NULL,NULL),(11,'DP-2026-0011',14,1,'AGRÍCOLA CHINCHA DEL SUR S.A.C.',5,2.000,'CANCELADO','Muestra técnica',NULL,NULL,'Fundo del cliente','','2026-11-13 20:59:50',NULL,NULL),(12,'DP-2026-0012',7,1,'RAMÍREZ QUISPE JUAN CARLOS',6,3.000,'ENTREGADO','VEN001-00000004',6,'VEN001-00000004#1','Av. Mariscal Benavides 845','','2026-11-14 15:48:17','2026-11-14 15:48:33','2026-11-14 15:48:33'),(13,'DP-2026-0013',11,1,'RAMÍREZ QUISPE JUAN CARLOS',6,5.000,'ENTREGADO','VEN001-00000004',6,'VEN001-00000004#2','Av. Mariscal Benavides 845','','2026-11-14 15:48:17','2026-11-14 15:48:33','2026-11-14 15:48:33'),(14,'DP-2026-0014',4,2,'ROJAS MEDINA PEDRO PABLO',8,2.000,'PREPARACION','VEN002-00000003',7,'VEN002-00000003#1','Jr. Grau 312','','2026-11-14 15:48:49',NULL,NULL);
/*!40000 ALTER TABLE `g7_despachos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `g7_kardex`
--

DROP TABLE IF EXISTS `g7_kardex`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `g7_kardex` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `fecha` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `producto_id` bigint NOT NULL,
  `almacen_id` int NOT NULL,
  `tipo` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `entrada` decimal(14,3) NOT NULL DEFAULT '0.000',
  `salida` decimal(14,3) NOT NULL DEFAULT '0.000',
  `saldo` decimal(14,3) NOT NULL DEFAULT '0.000',
  `costo_unitario` decimal(14,5) NOT NULL DEFAULT '0.00000',
  `documento` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `tercero` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `observacion` varchar(400) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `actor_usuario_id` bigint DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_g7_kardex_prod_fecha` (`producto_id`,`fecha`,`id`),
  KEY `ix_g7_kardex_alm` (`almacen_id`,`fecha`),
  KEY `fk_g7_kardex_usuario` (`actor_usuario_id`),
  CONSTRAINT `fk_g7_kardex_almacen` FOREIGN KEY (`almacen_id`) REFERENCES `almacenes` (`id`),
  CONSTRAINT `fk_g7_kardex_producto` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`),
  CONSTRAINT `fk_g7_kardex_usuario` FOREIGN KEY (`actor_usuario_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `ck_g7_kardex_valores` CHECK (((`entrada` >= 0) and (`salida` >= 0) and (`saldo` >= 0) and ((`entrada` <= 0) or (`salida` <= 0))))
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `g7_kardex`
--

LOCK TABLES `g7_kardex` WRITE;
/*!40000 ALTER TABLE `g7_kardex` DISABLE KEYS */;
INSERT INTO `g7_kardex` VALUES (1,'2026-11-13 19:10:45',3,1,'COMPRA',120.000,0.000,120.000,68.00000,'RC-2026-0001','BAYER S.A. - DIVISIÓN CROP SCIENCE','Recepción de OC-2026-0001',1),(2,'2026-11-13 19:10:45',4,1,'COMPRA',40.000,0.000,40.000,180.00000,'RC-2026-0002','BAYER S.A. - DIVISIÓN CROP SCIENCE','Recepción de OC-2026-0001',1),(3,'2026-11-13 19:10:45',5,1,'COMPRA',80.000,0.000,80.000,40.00000,'RC-2026-0003','BAYER S.A. - DIVISIÓN CROP SCIENCE','Recepción de OC-2026-0001',1),(4,'2026-11-13 19:10:45',6,1,'COMPRA',50.000,0.000,50.000,210.00000,'RC-2026-0004','BAYER S.A. - DIVISIÓN CROP SCIENCE','Recepción de OC-2026-0001',1),(5,'2026-11-13 19:10:45',7,1,'COMPRA',30.000,0.000,30.000,230.00000,'RC-2026-0005','BAYER S.A. - DIVISIÓN CROP SCIENCE','Recepción de OC-2026-0001',1),(6,'2026-11-13 19:10:45',8,1,'COMPRA',60.000,0.000,60.000,55.00000,'RC-2026-0006','DISTRIBUIDORA AGROQUÍMICA DEL SUR S.A.C.','Recepción de OC-2026-0002',1),(7,'2026-11-13 19:10:45',9,1,'COMPRA',70.000,0.000,70.000,38.00000,'RC-2026-0007','DISTRIBUIDORA AGROQUÍMICA DEL SUR S.A.C.','Recepción de OC-2026-0002',1),(8,'2026-11-13 19:10:45',10,1,'COMPRA',100.000,0.000,100.000,95.00000,'RC-2026-0008','FERTILIZANTES Y SEMILLAS DEL PACÍFICO S.A.','Recepción de OC-2026-0003',1),(9,'2026-11-13 19:10:45',11,1,'COMPRA',60.000,0.000,60.000,110.00000,'RC-2026-0009','FERTILIZANTES Y SEMILLAS DEL PACÍFICO S.A.','Recepción de OC-2026-0003',1),(10,'2026-11-13 19:10:45',12,1,'COMPRA',150.000,0.000,150.000,18.00000,'RC-2026-0010','FERTILIZANTES Y SEMILLAS DEL PACÍFICO S.A.','Recepción de OC-2026-0003',1),(11,'2026-11-13 19:10:45',13,1,'COMPRA',25.000,0.000,25.000,280.00000,'RC-2026-0011','FERTILIZANTES Y SEMILLAS DEL PACÍFICO S.A.','Recepción de OC-2026-0003',1),(12,'2026-11-13 19:10:45',14,1,'COMPRA',60.000,0.000,60.000,45.00000,'RC-2026-0012','FERTILIZANTES Y SEMILLAS DEL PACÍFICO S.A.','Recepción de OC-2026-0003',1),(13,'2026-11-13 19:10:45',3,2,'COMPRA',60.000,0.000,60.000,68.00000,'RC-2026-0013','BAYER S.A. - DIVISIÓN CROP SCIENCE','Recepción de OC-2026-0004',1),(14,'2026-11-13 19:10:45',6,2,'COMPRA',25.000,0.000,25.000,210.00000,'RC-2026-0014','BAYER S.A. - DIVISIÓN CROP SCIENCE','Recepción de OC-2026-0004',1),(15,'2026-11-13 19:10:45',7,2,'COMPRA',20.000,0.000,20.000,230.00000,'RC-2026-0015','BAYER S.A. - DIVISIÓN CROP SCIENCE','Recepción de OC-2026-0004',1),(16,'2026-11-13 19:10:45',10,2,'COMPRA',60.000,0.000,60.000,95.00000,'RC-2026-0016','FERTILIZANTES Y SEMILLAS DEL PACÍFICO S.A.','Recepción de OC-2026-0005',1),(17,'2026-11-13 19:10:45',12,2,'COMPRA',80.000,0.000,80.000,18.00000,'RC-2026-0017','FERTILIZANTES Y SEMILLAS DEL PACÍFICO S.A.','Recepción de OC-2026-0005',1),(18,'2026-11-13 19:10:45',14,2,'COMPRA',40.000,0.000,40.000,45.00000,'RC-2026-0018','FERTILIZANTES Y SEMILLAS DEL PACÍFICO S.A.','Recepción de OC-2026-0005',1),(19,'2026-11-13 19:11:55',6,1,'VENTA_DESPACHO',0.000,10.000,40.000,210.00000,'DP-2026-0002','AGROEXPORTADORA VALLE SUR S.A.C.','',1),(20,'2026-11-13 19:11:55',3,1,'VENTA_DESPACHO',0.000,20.000,100.000,68.00000,'DP-2026-0001','AGROEXPORTADORA VALLE SUR S.A.C.','',1),(21,'2026-11-13 19:11:55',5,1,'VENTA_DESPACHO',0.000,1.000,79.000,40.00000,'DP-2026-0006','HUAMÁN TORRES MARÍA ELENA','',1),(22,'2026-11-13 19:11:55',14,1,'VENTA_DESPACHO',0.000,2.000,58.000,45.00000,'DP-2026-0005','HUAMÁN TORRES MARÍA ELENA','',1),(23,'2026-11-13 19:11:55',12,1,'VENTA_DESPACHO',0.000,30.000,120.000,18.00000,'DP-2026-0004','FUNDO LAS PALMERAS S.A.C.','',1),(24,'2026-11-13 19:11:55',10,1,'VENTA_DESPACHO',0.000,15.000,85.000,95.00000,'DP-2026-0003','FUNDO LAS PALMERAS S.A.C.','',1),(25,'2026-11-14 15:48:33',11,1,'VENTA_DESPACHO',0.000,5.000,55.000,110.00000,'DP-2026-0013','RAMÍREZ QUISPE JUAN CARLOS','',1),(26,'2026-11-14 15:48:33',7,1,'VENTA_DESPACHO',0.000,3.000,27.000,230.00000,'DP-2026-0012','RAMÍREZ QUISPE JUAN CARLOS','',1),(27,'2026-11-14 15:48:48',4,2,'COMPRA',6.000,0.000,6.000,180.00000,'RC-2026-0019','BAYER S.A. - DIVISIÓN CROP SCIENCE','Recepción de OC-2026-0008',1),(28,'2026-11-14 15:51:28',14,1,'TRASLADO_SALIDA',0.000,5.000,53.000,45.00000,'TR-2026-0001','Traslado a otra sede','',1),(29,'2026-11-14 15:51:28',14,2,'TRASLADO_ENTRADA',5.000,0.000,45.000,45.00000,'TR-2026-0001','Recepción entre sedes','',1),(30,'2026-11-14 15:51:28',14,2,'MERMA',0.000,1.000,44.000,45.00000,'MR-2026-0001','Envase dañado','',1),(31,'2026-11-14 15:51:28',14,2,'CONTEO_AJUSTE',0.000,1.000,43.000,45.00000,'CF-2026-0001','Inventario físico','Regularización de conteo',1);
/*!40000 ALTER TABLE `g7_kardex` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `g7_orden_detalles`
--

DROP TABLE IF EXISTS `g7_orden_detalles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `g7_orden_detalles` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `orden_id` bigint NOT NULL,
  `producto_id` bigint NOT NULL,
  `cantidad` decimal(14,3) NOT NULL,
  `recibido` decimal(14,3) NOT NULL DEFAULT '0.000',
  `costo_unitario` decimal(14,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`id`),
  KEY `fk_g7_od_orden` (`orden_id`),
  KEY `fk_g7_od_producto` (`producto_id`),
  CONSTRAINT `fk_g7_od_orden` FOREIGN KEY (`orden_id`) REFERENCES `g7_ordenes_compra` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_g7_od_producto` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`),
  CONSTRAINT `ck_g7_od` CHECK (((`cantidad` > 0) and (`recibido` >= 0) and (`recibido` <= `cantidad`) and (`costo_unitario` >= 0)))
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `g7_orden_detalles`
--

LOCK TABLES `g7_orden_detalles` WRITE;
/*!40000 ALTER TABLE `g7_orden_detalles` DISABLE KEYS */;
INSERT INTO `g7_orden_detalles` VALUES (1,1,3,120.000,120.000,68.00),(2,1,4,40.000,40.000,180.00),(3,1,5,80.000,80.000,40.00),(4,1,6,50.000,50.000,210.00),(5,1,7,30.000,30.000,230.00),(6,2,8,60.000,60.000,55.00),(7,2,9,70.000,70.000,38.00),(8,3,10,100.000,100.000,95.00),(9,3,11,60.000,60.000,110.00),(10,3,12,150.000,150.000,18.00),(11,3,13,25.000,25.000,280.00),(12,3,14,60.000,60.000,45.00),(13,4,3,60.000,60.000,68.00),(14,4,6,25.000,25.000,210.00),(15,4,7,20.000,20.000,230.00),(16,5,10,60.000,60.000,95.00),(17,5,12,80.000,80.000,18.00),(18,5,14,40.000,40.000,45.00),(19,6,8,20.000,0.000,55.00),(21,8,4,6.000,6.000,180.00);
/*!40000 ALTER TABLE `g7_orden_detalles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `g7_ordenes_compra`
--

DROP TABLE IF EXISTS `g7_ordenes_compra`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `g7_ordenes_compra` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `codigo` varchar(35) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `proveedor_id` bigint NOT NULL,
  `almacen_id` int NOT NULL,
  `fecha` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `estado` enum('PENDIENTE','PARCIAL','RECIBIDA','CANCELADA') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'PENDIENTE',
  `observacion` varchar(300) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `fecha_entrega` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo` (`codigo`),
  KEY `ix_g7_oc_estado` (`estado`,`fecha`),
  KEY `fk_g7_oc_proveedor` (`proveedor_id`),
  KEY `fk_g7_oc_almacen` (`almacen_id`),
  CONSTRAINT `fk_g7_oc_almacen` FOREIGN KEY (`almacen_id`) REFERENCES `almacenes` (`id`),
  CONSTRAINT `fk_g7_oc_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `g7_ordenes_compra`
--

LOCK TABLES `g7_ordenes_compra` WRITE;
/*!40000 ALTER TABLE `g7_ordenes_compra` DISABLE KEYS */;
INSERT INTO `g7_ordenes_compra` VALUES (1,'OC-2026-0001',1,1,'2026-11-13 19:10:45','RECIBIDA','Reposición de stock',NULL),(2,'OC-2026-0002',2,1,'2026-11-13 19:10:45','RECIBIDA','Reposición de stock',NULL),(3,'OC-2026-0003',3,1,'2026-11-13 19:10:45','RECIBIDA','Reposición de stock',NULL),(4,'OC-2026-0004',1,2,'2026-11-13 19:10:45','RECIBIDA','Reposición de stock',NULL),(5,'OC-2026-0005',3,2,'2026-11-13 19:10:45','RECIBIDA','Reposición de stock',NULL),(6,'OC-2026-0006',2,1,'2026-11-13 19:10:45','PENDIENTE','Reposición de stock',NULL),(8,'OC-2026-0008',1,2,'2026-11-14 15:48:48','RECIBIDA','Reposición Sede Mala',NULL);
/*!40000 ALTER TABLE `g7_ordenes_compra` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `g7_recepciones`
--

DROP TABLE IF EXISTS `g7_recepciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `g7_recepciones` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `codigo` varchar(35) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `orden_id` bigint DEFAULT NULL,
  `proveedor_id` bigint NOT NULL,
  `almacen_id` int NOT NULL,
  `producto_id` bigint NOT NULL,
  `cantidad` decimal(14,3) NOT NULL,
  `costo_unitario` decimal(14,2) NOT NULL DEFAULT '0.00',
  `fecha` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `observacion` varchar(300) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo` (`codigo`),
  KEY `fk_g7_rec_orden` (`orden_id`),
  KEY `fk_g7_rec_prov` (`proveedor_id`),
  KEY `fk_g7_rec_alm` (`almacen_id`),
  KEY `fk_g7_rec_prod` (`producto_id`),
  CONSTRAINT `fk_g7_rec_alm` FOREIGN KEY (`almacen_id`) REFERENCES `almacenes` (`id`),
  CONSTRAINT `fk_g7_rec_orden` FOREIGN KEY (`orden_id`) REFERENCES `g7_ordenes_compra` (`id`),
  CONSTRAINT `fk_g7_rec_prod` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`),
  CONSTRAINT `fk_g7_rec_prov` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`),
  CONSTRAINT `ck_g7_rec` CHECK (((`cantidad` > 0) and (`costo_unitario` >= 0)))
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `g7_recepciones`
--

LOCK TABLES `g7_recepciones` WRITE;
/*!40000 ALTER TABLE `g7_recepciones` DISABLE KEYS */;
INSERT INTO `g7_recepciones` VALUES (1,'RC-2026-0001',1,1,1,3,120.000,68.00,'2026-11-13 19:10:45','Recepción de OC-2026-0001'),(2,'RC-2026-0002',1,1,1,4,40.000,180.00,'2026-11-13 19:10:45','Recepción de OC-2026-0001'),(3,'RC-2026-0003',1,1,1,5,80.000,40.00,'2026-11-13 19:10:45','Recepción de OC-2026-0001'),(4,'RC-2026-0004',1,1,1,6,50.000,210.00,'2026-11-13 19:10:45','Recepción de OC-2026-0001'),(5,'RC-2026-0005',1,1,1,7,30.000,230.00,'2026-11-13 19:10:45','Recepción de OC-2026-0001'),(6,'RC-2026-0006',2,2,1,8,60.000,55.00,'2026-11-13 19:10:45','Recepción de OC-2026-0002'),(7,'RC-2026-0007',2,2,1,9,70.000,38.00,'2026-11-13 19:10:45','Recepción de OC-2026-0002'),(8,'RC-2026-0008',3,3,1,10,100.000,95.00,'2026-11-13 19:10:45','Recepción de OC-2026-0003'),(9,'RC-2026-0009',3,3,1,11,60.000,110.00,'2026-11-13 19:10:45','Recepción de OC-2026-0003'),(10,'RC-2026-0010',3,3,1,12,150.000,18.00,'2026-11-13 19:10:45','Recepción de OC-2026-0003'),(11,'RC-2026-0011',3,3,1,13,25.000,280.00,'2026-11-13 19:10:45','Recepción de OC-2026-0003'),(12,'RC-2026-0012',3,3,1,14,60.000,45.00,'2026-11-13 19:10:45','Recepción de OC-2026-0003'),(13,'RC-2026-0013',4,1,2,3,60.000,68.00,'2026-11-13 19:10:45','Recepción de OC-2026-0004'),(14,'RC-2026-0014',4,1,2,6,25.000,210.00,'2026-11-13 19:10:45','Recepción de OC-2026-0004'),(15,'RC-2026-0015',4,1,2,7,20.000,230.00,'2026-11-13 19:10:45','Recepción de OC-2026-0004'),(16,'RC-2026-0016',5,3,2,10,60.000,95.00,'2026-11-13 19:10:45','Recepción de OC-2026-0005'),(17,'RC-2026-0017',5,3,2,12,80.000,18.00,'2026-11-13 19:10:45','Recepción de OC-2026-0005'),(18,'RC-2026-0018',5,3,2,14,40.000,45.00,'2026-11-13 19:10:45','Recepción de OC-2026-0005'),(19,'RC-2026-0019',8,1,2,4,6.000,180.00,'2026-11-14 15:48:48','Recepción de OC-2026-0008');
/*!40000 ALTER TABLE `g7_recepciones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `g7_saldos`
--

DROP TABLE IF EXISTS `g7_saldos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `g7_saldos` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `producto_id` bigint NOT NULL,
  `almacen_id` int NOT NULL,
  `cantidad` decimal(14,3) NOT NULL DEFAULT '0.000',
  `reservado` decimal(14,3) NOT NULL DEFAULT '0.000',
  `minimo` decimal(14,3) NOT NULL DEFAULT '0.000',
  `ubicacion` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `costo_promedio` decimal(14,5) NOT NULL DEFAULT '0.00000',
  `actualizado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_g7_saldo_producto_almacen` (`producto_id`,`almacen_id`),
  KEY `ix_g7_saldos_almacen` (`almacen_id`),
  CONSTRAINT `fk_g7_saldos_almacen` FOREIGN KEY (`almacen_id`) REFERENCES `almacenes` (`id`),
  CONSTRAINT `fk_g7_saldos_producto` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`),
  CONSTRAINT `ck_g7_saldos` CHECK (((`cantidad` >= 0) and (`reservado` >= 0) and (`reservado` <= `cantidad`) and (`minimo` >= 0) and (`costo_promedio` >= 0)))
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `g7_saldos`
--

LOCK TABLES `g7_saldos` WRITE;
/*!40000 ALTER TABLE `g7_saldos` DISABLE KEYS */;
INSERT INTO `g7_saldos` VALUES (1,3,1,100.000,0.000,0.000,'',68.00000,'2026-11-14 16:09:41'),(2,4,1,40.000,0.000,0.000,'',180.00000,'2026-11-13 19:10:45'),(3,5,1,79.000,0.000,0.000,'',40.00000,'2026-11-13 19:11:55'),(4,6,1,40.000,0.000,0.000,'',210.00000,'2026-11-13 19:11:55'),(5,7,1,27.000,0.000,0.000,'',230.00000,'2026-11-14 15:48:33'),(6,8,1,60.000,0.000,0.000,'',55.00000,'2026-11-13 19:10:45'),(7,9,1,70.000,0.000,0.000,'',38.00000,'2026-11-13 19:10:45'),(8,10,1,85.000,0.000,0.000,'',95.00000,'2026-11-13 19:11:55'),(9,11,1,55.000,0.000,0.000,'',110.00000,'2026-11-14 15:48:33'),(10,12,1,120.000,0.000,0.000,'',18.00000,'2026-11-13 19:11:55'),(11,13,1,25.000,0.000,0.000,'',280.00000,'2026-11-13 19:10:45'),(12,14,1,53.000,0.000,0.000,'',45.00000,'2026-11-14 15:51:28'),(13,3,2,60.000,10.000,0.000,'',68.00000,'2026-11-13 19:11:31'),(14,6,2,25.000,8.000,0.000,'',210.00000,'2026-11-13 19:11:31'),(15,7,2,20.000,5.000,0.000,'',230.00000,'2026-11-13 19:11:31'),(16,10,2,60.000,20.000,0.000,'',95.00000,'2026-11-13 19:11:31'),(17,12,2,80.000,0.000,0.000,'',18.00000,'2026-11-13 19:10:45'),(18,14,2,43.000,0.000,0.000,'',45.00000,'2026-11-14 15:51:28'),(19,4,2,6.000,2.000,0.000,'',180.00000,'2026-11-14 15:48:49');
/*!40000 ALTER TABLE `g7_saldos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `g7_transferencias`
--

DROP TABLE IF EXISTS `g7_transferencias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `g7_transferencias` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `codigo` varchar(35) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `producto_id` bigint NOT NULL,
  `origen_id` int NOT NULL,
  `destino_id` int NOT NULL,
  `cantidad` decimal(14,3) NOT NULL,
  `estado` enum('EN_TRANSITO','RECIBIDA') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'EN_TRANSITO',
  `fecha_salida` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_recepcion` datetime DEFAULT NULL,
  `observacion` varchar(300) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo` (`codigo`),
  KEY `fk_g7_t_prod` (`producto_id`),
  KEY `fk_g7_t_alm_o` (`origen_id`),
  KEY `fk_g7_t_alm_d` (`destino_id`),
  CONSTRAINT `fk_g7_t_alm_d` FOREIGN KEY (`destino_id`) REFERENCES `almacenes` (`id`),
  CONSTRAINT `fk_g7_t_alm_o` FOREIGN KEY (`origen_id`) REFERENCES `almacenes` (`id`),
  CONSTRAINT `fk_g7_t_prod` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`),
  CONSTRAINT `ck_g7_transfer` CHECK (((`origen_id` <> `destino_id`) and (`cantidad` > 0)))
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `g7_transferencias`
--

LOCK TABLES `g7_transferencias` WRITE;
/*!40000 ALTER TABLE `g7_transferencias` DISABLE KEYS */;
INSERT INTO `g7_transferencias` VALUES (1,'TR-2026-0001',14,1,2,5.000,'RECIBIDA','2026-11-14 15:51:28','2026-11-14 15:51:28','Reposición entre sedes');
/*!40000 ALTER TABLE `g7_transferencias` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `guias_cabecera`
--

DROP TABLE IF EXISTS `guias_cabecera`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `guias_cabecera` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `numero` varchar(25) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha` date NOT NULL,
  `cliente_id` bigint NOT NULL,
  `vendedor_id` bigint NOT NULL,
  `sucursal_id` int NOT NULL,
  `departamento_id` int DEFAULT NULL,
  `provincia_id` int DEFAULT NULL,
  `distrito_id` int DEFAULT NULL,
  `estado_registro` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'BORRADOR',
  `created_by` bigint DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `published_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `updated_by` bigint DEFAULT NULL,
  `version` int unsigned NOT NULL DEFAULT '1',
  `validated_at` datetime DEFAULT NULL,
  `validated_by` bigint DEFAULT NULL,
  `observed_at` datetime DEFAULT NULL,
  `observed_by` bigint DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancelled_by` bigint DEFAULT NULL,
  `published_by` bigint DEFAULT NULL,
  `observation_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `cancellation_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `origen` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `origen_ref` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `origen_hash` char(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `documento_id` bigint DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `numero` (`numero`),
  UNIQUE KEY `uq_guias_cabecera_origen` (`origen`,`origen_ref`),
  KEY `cliente_id` (`cliente_id`),
  KEY `vendedor_id` (`vendedor_id`),
  KEY `departamento_id` (`departamento_id`),
  KEY `provincia_id` (`provincia_id`),
  KEY `distrito_id` (`distrito_id`),
  KEY `created_by` (`created_by`),
  KEY `fk_guias_updated_by` (`updated_by`),
  KEY `fk_guias_validated_by` (`validated_by`),
  KEY `fk_guias_observed_by` (`observed_by`),
  KEY `fk_guias_cancelled_by` (`cancelled_by`),
  KEY `fk_guias_published_by` (`published_by`),
  KEY `ix_guias_estado_fecha` (`estado_registro`,`fecha`,`id`),
  KEY `ix_guias_ubicacion_fecha` (`sucursal_id`,`fecha`,`id`),
  KEY `idx_guias_cabecera_estado` (`estado_registro`),
  KEY `fk_guia_documento` (`documento_id`),
  KEY `idx_guia_fecha` (`fecha`),
  CONSTRAINT `fk_guia_documento` FOREIGN KEY (`documento_id`) REFERENCES `documentos_cabecera` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_guias_cancelled_by` FOREIGN KEY (`cancelled_by`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_guias_observed_by` FOREIGN KEY (`observed_by`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_guias_published_by` FOREIGN KEY (`published_by`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_guias_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_guias_validated_by` FOREIGN KEY (`validated_by`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `guias_cabecera_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`),
  CONSTRAINT `guias_cabecera_ibfk_2` FOREIGN KEY (`vendedor_id`) REFERENCES `vendedores` (`id`),
  CONSTRAINT `guias_cabecera_ibfk_3` FOREIGN KEY (`sucursal_id`) REFERENCES `sucursales` (`id`),
  CONSTRAINT `guias_cabecera_ibfk_4` FOREIGN KEY (`departamento_id`) REFERENCES `departamentos` (`id`),
  CONSTRAINT `guias_cabecera_ibfk_5` FOREIGN KEY (`provincia_id`) REFERENCES `provincias` (`id`),
  CONSTRAINT `guias_cabecera_ibfk_6` FOREIGN KEY (`distrito_id`) REFERENCES `distritos` (`id`),
  CONSTRAINT `guias_cabecera_ibfk_7` FOREIGN KEY (`created_by`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `ck_guias_estado` CHECK ((cast(`estado_registro` as char charset binary) in (_utf8mb4'BORRADOR',_utf8mb4'VALIDADO',_utf8mb4'PUBLICADO',_utf8mb4'OBSERVADO',_utf8mb4'ANULADO'))),
  CONSTRAINT `ck_guias_fecha` CHECK (((`fecha` >= _utf8mb4'1000-01-01') and (dayofmonth(`fecha`) >= 1))),
  CONSTRAINT `ck_guias_version` CHECK ((`version` >= 1))
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `guias_cabecera`
--

LOCK TABLES `guias_cabecera` WRITE;
/*!40000 ALTER TABLE `guias_cabecera` DISABLE KEYS */;
INSERT INTO `guias_cabecera` VALUES (1,'DP-2026-0001','2026-11-13',1,1,1,NULL,NULL,1,'PUBLICADO',NULL,'2026-11-13 19:12:07','2026-11-13 19:12:07','2026-11-13 19:12:07',NULL,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'ERP_G7','1','0ede5790375248607e54ce66da4b5b8bbdf817cb',1),(2,'DP-2026-0002','2026-11-13',1,1,1,NULL,NULL,1,'PUBLICADO',NULL,'2026-11-13 19:12:07','2026-11-13 19:12:07','2026-11-13 19:12:07',NULL,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'ERP_G7','2','662aab306c0bb81b75fc400ec7accaa3387c5c9c',1),(3,'DP-2026-0003','2026-11-13',2,1,1,NULL,NULL,1,'PUBLICADO',NULL,'2026-11-13 19:12:07','2026-11-13 19:12:07','2026-11-13 19:12:07',NULL,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'ERP_G7','3','b10f1e8c981b30cfdde75a96b7c463efa0df31b4',2),(4,'DP-2026-0004','2026-11-13',2,1,1,NULL,NULL,1,'PUBLICADO',NULL,'2026-11-13 19:12:07','2026-11-13 19:12:07','2026-11-13 19:12:07',NULL,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'ERP_G7','4','71557a72db529a25ec59191609c83462effc9e60',2),(5,'DP-2026-0005','2026-11-13',7,1,1,NULL,NULL,1,'PUBLICADO',NULL,'2026-11-13 19:12:07','2026-11-13 19:12:07','2026-11-13 19:12:07',NULL,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'ERP_G7','5','565e49d8830069d5c907006f1dc7ae361a872a36',3),(6,'DP-2026-0006','2026-11-13',7,1,1,NULL,NULL,1,'PUBLICADO',NULL,'2026-11-13 19:12:07','2026-11-13 19:12:07','2026-11-13 19:12:07',NULL,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'ERP_G7','6','7580fd0236788075b9db45e288c6673a50216130',3),(7,'DP-2026-0007','2026-11-13',4,2,2,NULL,NULL,4,'BORRADOR',NULL,'2026-11-13 19:12:07',NULL,'2026-11-13 19:12:07',NULL,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'ERP_G7','7','bbad2dc8a68b7db1fa3723d119781f8f4c8fa911',4),(8,'DP-2026-0008','2026-11-13',4,2,2,NULL,NULL,4,'BORRADOR',NULL,'2026-11-13 19:12:07',NULL,'2026-11-13 19:12:07',NULL,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'ERP_G7','8','dee82ec04892a2d49d4c81836a836120e6f5856e',4),(9,'DP-2026-0009','2026-11-13',5,2,2,NULL,NULL,4,'BORRADOR',NULL,'2026-11-13 19:12:07',NULL,'2026-11-14 01:09:54',NULL,2,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'ERP_G7','9','56a98fb0cf86c69104a237eaedcc860d014452bd',9),(10,'DP-2026-0010','2026-11-13',5,2,2,NULL,NULL,4,'BORRADOR',NULL,'2026-11-13 19:12:07',NULL,'2026-11-14 01:09:54',NULL,2,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'ERP_G7','10','5b4eaea311f51a2d649c2706fd034af76818931b',9),(11,'DP-2026-0012','2026-11-14',6,1,1,NULL,NULL,1,'PUBLICADO',NULL,'2026-11-14 15:49:33','2026-11-14 15:49:33','2026-11-14 15:49:33',NULL,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'ERP_G7','12','7188d92d23cf984852a88de87aceab7104bb945b',10),(12,'DP-2026-0013','2026-11-14',6,1,1,NULL,NULL,1,'PUBLICADO',NULL,'2026-11-14 15:49:33','2026-11-14 15:49:33','2026-11-14 15:49:33',NULL,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'ERP_G7','13','251a449081f1b5e12cb54729e36d49089b08ca11',10),(13,'DP-2026-0014','2026-11-14',8,2,2,NULL,NULL,4,'BORRADOR',NULL,'2026-11-14 15:49:33',NULL,'2026-11-14 15:49:33',NULL,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'ERP_G7','14','e804df93a615f39ed86ebf34c1c0c42419353763',11);
/*!40000 ALTER TABLE `guias_cabecera` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `guias_detalle`
--

DROP TABLE IF EXISTS `guias_detalle`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `guias_detalle` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `guia_id` bigint NOT NULL,
  `producto_id` bigint NOT NULL,
  `unidad_id` int NOT NULL,
  `cantidad` decimal(14,3) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `guia_id` (`guia_id`),
  KEY `producto_id` (`producto_id`),
  KEY `unidad_id` (`unidad_id`),
  CONSTRAINT `guias_detalle_ibfk_1` FOREIGN KEY (`guia_id`) REFERENCES `guias_cabecera` (`id`) ON DELETE CASCADE,
  CONSTRAINT `guias_detalle_ibfk_2` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`),
  CONSTRAINT `guias_detalle_ibfk_3` FOREIGN KEY (`unidad_id`) REFERENCES `unidades_medida` (`id`),
  CONSTRAINT `ck_guias_cantidad` CHECK ((`cantidad` > 0))
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `guias_detalle`
--

LOCK TABLES `guias_detalle` WRITE;
/*!40000 ALTER TABLE `guias_detalle` DISABLE KEYS */;
INSERT INTO `guias_detalle` VALUES (1,1,3,5,20.000),(2,2,6,4,10.000),(3,3,10,7,15.000),(4,4,12,4,30.000),(5,5,14,5,2.000),(6,6,5,5,1.000),(7,7,3,5,10.000),(8,8,7,5,5.000),(11,9,6,4,8.000),(12,10,10,7,20.000),(13,11,7,5,3.000),(14,12,11,7,5.000),(15,13,4,5,2.000);
/*!40000 ALTER TABLE `guias_detalle` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `homologacion_almacenes`
--

DROP TABLE IF EXISTS `homologacion_almacenes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `homologacion_almacenes` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `partner_id` int NOT NULL,
  `almacen_id` int NOT NULL,
  `warehouse_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `warehouse_name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` tinyint NOT NULL DEFAULT '1',
  `valid_from` date DEFAULT NULL,
  `valid_until` date DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` bigint DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `updated_by` bigint DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ha` (`partner_id`,`almacen_id`),
  KEY `almacen_id` (`almacen_id`),
  KEY `fk_hom_almacenes_created` (`created_by`),
  KEY `fk_hom_almacenes_updated` (`updated_by`),
  KEY `ix_hom_almacenes_externo` (`partner_id`,`warehouse_id`,`estado`),
  CONSTRAINT `fk_hom_almacenes_created` FOREIGN KEY (`created_by`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_hom_almacenes_updated` FOREIGN KEY (`updated_by`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `homologacion_almacenes_ibfk_1` FOREIGN KEY (`partner_id`) REFERENCES `partners` (`id`),
  CONSTRAINT `homologacion_almacenes_ibfk_2` FOREIGN KEY (`almacen_id`) REFERENCES `almacenes` (`id`),
  CONSTRAINT `ck_hom_almacenes_estado` CHECK ((`estado` in (0,1))),
  CONSTRAINT `ck_hom_almacenes_vigencia` CHECK (((`valid_until` is null) or (`valid_from` is null) or (`valid_until` >= `valid_from`)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `homologacion_almacenes`
--

LOCK TABLES `homologacion_almacenes` WRITE;
/*!40000 ALTER TABLE `homologacion_almacenes` DISABLE KEYS */;
/*!40000 ALTER TABLE `homologacion_almacenes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `homologacion_clientes`
--

DROP TABLE IF EXISTS `homologacion_clientes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `homologacion_clientes` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `partner_id` int NOT NULL,
  `cliente_id` bigint NOT NULL,
  `customer_id` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `customer_name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `partner_id` (`partner_id`),
  KEY `cliente_id` (`cliente_id`),
  CONSTRAINT `homologacion_clientes_ibfk_1` FOREIGN KEY (`partner_id`) REFERENCES `partners` (`id`),
  CONSTRAINT `homologacion_clientes_ibfk_2` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `homologacion_clientes`
--

LOCK TABLES `homologacion_clientes` WRITE;
/*!40000 ALTER TABLE `homologacion_clientes` DISABLE KEYS */;
/*!40000 ALTER TABLE `homologacion_clientes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `homologacion_productos_bayer`
--

DROP TABLE IF EXISTS `homologacion_productos_bayer`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `homologacion_productos_bayer` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `partner_id` int NOT NULL,
  `producto_id` bigint NOT NULL,
  `material_id` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `material_name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `unidad_bayer_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `partner_id` (`partner_id`),
  KEY `producto_id` (`producto_id`),
  KEY `unidad_bayer_id` (`unidad_bayer_id`),
  CONSTRAINT `homologacion_productos_bayer_ibfk_1` FOREIGN KEY (`partner_id`) REFERENCES `partners` (`id`),
  CONSTRAINT `homologacion_productos_bayer_ibfk_2` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`),
  CONSTRAINT `homologacion_productos_bayer_ibfk_3` FOREIGN KEY (`unidad_bayer_id`) REFERENCES `unidades_medida` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `homologacion_productos_bayer`
--

LOCK TABLES `homologacion_productos_bayer` WRITE;
/*!40000 ALTER TABLE `homologacion_productos_bayer` DISABLE KEYS */;
INSERT INTO `homologacion_productos_bayer` VALUES (1,1,1,'BAY-MAT-ASP100','Aspirina Standard LATAM',1),(2,1,2,'BAY-MAT-RED01','Redoxon Effervescent',2),(3,1,3,'BAY-MAT-0001','Insecticida Imidacloprid 350 SC',5),(4,1,4,'BAY-MAT-0002','Insecticida Spirotetramat 150 OD',5),(5,1,5,'BAY-MAT-0003','Insecticida Deltametrina 25 EC',5),(6,1,6,'BAY-MAT-0004','Fungicida Trifloxistrobina + Tebuconazol 75 WG',4),(7,1,7,'BAY-MAT-0005','Fungicida Fluopyram + Tebuconazol 400 SC',5);
/*!40000 ALTER TABLE `homologacion_productos_bayer` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `homologacion_sucursales`
--

DROP TABLE IF EXISTS `homologacion_sucursales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `homologacion_sucursales` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `partner_id` int NOT NULL,
  `sucursal_id` int NOT NULL,
  `branch_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `branch_name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `partner_id` (`partner_id`),
  KEY `sucursal_id` (`sucursal_id`),
  CONSTRAINT `homologacion_sucursales_ibfk_1` FOREIGN KEY (`partner_id`) REFERENCES `partners` (`id`),
  CONSTRAINT `homologacion_sucursales_ibfk_2` FOREIGN KEY (`sucursal_id`) REFERENCES `sucursales` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `homologacion_sucursales`
--

LOCK TABLES `homologacion_sucursales` WRITE;
/*!40000 ALTER TABLE `homologacion_sucursales` DISABLE KEYS */;
/*!40000 ALTER TABLE `homologacion_sucursales` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `homologacion_unidades`
--

DROP TABLE IF EXISTS `homologacion_unidades`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `homologacion_unidades` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `partner_id` int NOT NULL,
  `unidad_id` int NOT NULL,
  `external_unit_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `external_unit_name` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `partner_id` (`partner_id`),
  KEY `unidad_id` (`unidad_id`),
  CONSTRAINT `homologacion_unidades_ibfk_1` FOREIGN KEY (`partner_id`) REFERENCES `partners` (`id`),
  CONSTRAINT `homologacion_unidades_ibfk_2` FOREIGN KEY (`unidad_id`) REFERENCES `unidades_medida` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `homologacion_unidades`
--

LOCK TABLES `homologacion_unidades` WRITE;
/*!40000 ALTER TABLE `homologacion_unidades` DISABLE KEYS */;
/*!40000 ALTER TABLE `homologacion_unidades` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `homologacion_vendedores`
--

DROP TABLE IF EXISTS `homologacion_vendedores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `homologacion_vendedores` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `partner_id` int NOT NULL,
  `vendedor_id` bigint NOT NULL,
  `sales_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `sales_name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `partner_id` (`partner_id`),
  KEY `vendedor_id` (`vendedor_id`),
  CONSTRAINT `homologacion_vendedores_ibfk_1` FOREIGN KEY (`partner_id`) REFERENCES `partners` (`id`),
  CONSTRAINT `homologacion_vendedores_ibfk_2` FOREIGN KEY (`vendedor_id`) REFERENCES `vendedores` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `homologacion_vendedores`
--

LOCK TABLES `homologacion_vendedores` WRITE;
/*!40000 ALTER TABLE `homologacion_vendedores` DISABLE KEYS */;
/*!40000 ALTER TABLE `homologacion_vendedores` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `importaciones`
--

DROP TABLE IF EXISTS `importaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `importaciones` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `archivo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `hash_sha256` char(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `dataset` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `modo_estado` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `estado` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `filas_leidas` int NOT NULL DEFAULT '0',
  `insertados` int NOT NULL DEFAULT '0',
  `actualizados` int NOT NULL DEFAULT '0',
  `sin_cambios` int NOT NULL DEFAULT '0',
  `omitidos` int NOT NULL DEFAULT '0',
  `resumen` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `usuario_id` bigint DEFAULT NULL,
  `started_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `finished_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_imp_hash` (`hash_sha256`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `importaciones_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `importaciones`
--

LOCK TABLES `importaciones` WRITE;
/*!40000 ALTER TABLE `importaciones` DISABLE KEYS */;
/*!40000 ALTER TABLE `importaciones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lista_precios`
--

DROP TABLE IF EXISTS `lista_precios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `lista_precios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `producto_id` bigint NOT NULL,
  `sucursal_id` int DEFAULT NULL,
  `precio` decimal(14,4) NOT NULL,
  `vigente_desde` date NOT NULL,
  `vigente_hasta` date DEFAULT NULL,
  `observacion` varchar(150) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_precio_vigencia` (`producto_id`,`vigente_desde`),
  KEY `fk_precio_sucursal` (`sucursal_id`),
  CONSTRAINT `fk_precio_producto` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`),
  CONSTRAINT `fk_precio_sucursal` FOREIGN KEY (`sucursal_id`) REFERENCES `sucursales` (`id`),
  CONSTRAINT `ck_precio_valores` CHECK (((`precio` >= 0) and ((`vigente_hasta` is null) or (`vigente_hasta` >= `vigente_desde`))))
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lista_precios`
--

LOCK TABLES `lista_precios` WRITE;
/*!40000 ALTER TABLE `lista_precios` DISABLE KEYS */;
INSERT INTO `lista_precios` VALUES (1,3,NULL,90.2500,'2026-01-01','2026-12-31','Precio campaña 2026'),(2,4,NULL,232.7500,'2026-01-01','2026-12-31','Precio campaña 2026');
/*!40000 ALTER TABLE `lista_precios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lotes`
--

DROP TABLE IF EXISTS `lotes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `lotes` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `producto_id` bigint NOT NULL,
  `codigo_lote` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha_vencimiento` date DEFAULT NULL,
  `estado` tinyint NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_lote` (`producto_id`,`codigo_lote`),
  UNIQUE KEY `uq_lote_id_producto` (`id`,`producto_id`),
  CONSTRAINT `lotes_ibfk_1` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`),
  CONSTRAINT `ck_lotes_fecha` CHECK (((`fecha_vencimiento` is null) or ((`fecha_vencimiento` >= _utf8mb4'1000-01-01') and (dayofmonth(`fecha_vencimiento`) >= 1))))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lotes`
--

LOCK TABLES `lotes` WRITE;
/*!40000 ALTER TABLE `lotes` DISABLE KEYS */;
/*!40000 ALTER TABLE `lotes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `marcas`
--

DROP TABLE IF EXISTS `marcas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `marcas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `fabricante` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_marca_nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `marcas`
--

LOCK TABLES `marcas` WRITE;
/*!40000 ALTER TABLE `marcas` DISABLE KEYS */;
INSERT INTO `marcas` VALUES (1,'Aspirina','Bayer S.A.'),(2,'Redoxon','Bayer S.A.'),(3,'Bayer','Bayer S.A.'),(4,'AgroSur','Distribuidora Agroquímica del Sur S.A.C.'),(5,'NutriCampo','Fertilizantes y Semillas del Pacífico S.A.'),(6,'SemillaPac','Fertilizantes y Semillas del Pacífico S.A.');
/*!40000 ALTER TABLE `marcas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `parametros`
--

DROP TABLE IF EXISTS `parametros`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `parametros` (
  `clave` varchar(60) NOT NULL,
  `valor` varchar(255) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `actualizado_por` bigint DEFAULT NULL,
  `actualizado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`clave`),
  KEY `fk_parametro_usuario` (`actualizado_por`),
  CONSTRAINT `fk_parametro_usuario` FOREIGN KEY (`actualizado_por`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `parametros`
--

LOCK TABLES `parametros` WRITE;
/*!40000 ALTER TABLE `parametros` DISABLE KEYS */;
INSERT INTO `parametros` VALUES ('igv_tasa','18','Tasa de IGV en % para Comercial (cotizaciones) y Facturación (comprobantes)',1,'2026-11-14 16:01:10'),('moneda_base','PEN','Moneda de la contabilidad y de la facturación electrónica',NULL,'2026-11-14 15:58:24'),('vigencia_cotizacion_dias','15','Días de vigencia sugeridos para una cotización nueva',NULL,'2026-11-14 15:58:24');
/*!40000 ALTER TABLE `parametros` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `partners`
--

DROP TABLE IF EXISTS `partners`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `partners` (
  `id` int NOT NULL AUTO_INCREMENT,
  `codigo` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `nombre` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `tipo` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `partners`
--

LOCK TABLES `partners` WRITE;
/*!40000 ALTER TABLE `partners` DISABLE KEYS */;
INSERT INTO `partners` VALUES (1,'PRT-BAYER','Bayer S.A.','Principal'),(2,'BAYER','Bayer','FABRICANTE');
/*!40000 ALTER TABLE `partners` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pedido_detalles`
--

DROP TABLE IF EXISTS `pedido_detalles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pedido_detalles` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `pedido_id` bigint NOT NULL,
  `linea` int NOT NULL,
  `producto_id` bigint NOT NULL,
  `unidad_id` int NOT NULL,
  `codigo_producto` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `descripcion` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `cantidad` decimal(18,4) NOT NULL,
  `precio_unitario` decimal(18,4) NOT NULL,
  `descuento` decimal(18,2) NOT NULL DEFAULT '0.00',
  `subtotal` decimal(18,2) NOT NULL,
  `tasa_impuesto` decimal(7,4) NOT NULL DEFAULT '0.0000',
  `impuesto` decimal(18,2) NOT NULL DEFAULT '0.00',
  `total` decimal(18,2) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pedido_linea` (`pedido_id`,`linea`),
  KEY `fk_pedido_detalle_producto` (`producto_id`),
  KEY `fk_pedido_detalle_unidad` (`unidad_id`),
  CONSTRAINT `fk_pedido_detalle_documento` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`),
  CONSTRAINT `fk_pedido_detalle_producto` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`),
  CONSTRAINT `fk_pedido_detalle_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `unidades_medida` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pedido_detalles`
--

LOCK TABLES `pedido_detalles` WRITE;
/*!40000 ALTER TABLE `pedido_detalles` DISABLE KEYS */;
INSERT INTO `pedido_detalles` VALUES (1,1,1,3,5,'PRD-0001','Insecticida Imidacloprid 350 SC',20.0000,95.0000,0.00,1900.00,18.0000,342.00,2242.00),(2,1,2,6,4,'PRD-0004','Fungicida Trifloxistrobina + Tebuconazol 75 WG',10.0000,289.0000,50.00,2890.00,18.0000,511.20,3351.20),(3,2,1,10,7,'PRD-0008','Fertilizante Urea 46% N',15.0000,128.0000,0.00,1920.00,18.0000,345.60,2265.60),(4,2,2,12,4,'PRD-0010','Fertilizante foliar NPK 20-20-20',30.0000,27.0000,0.00,810.00,18.0000,145.80,955.80),(5,3,1,14,5,'PRD-0012','Bioestimulante a base de extracto de algas',2.0000,65.0000,0.00,130.00,18.0000,23.40,153.40),(6,3,2,5,5,'PRD-0003','Insecticida Deltametrina 25 EC',1.0000,58.0000,0.00,58.00,18.0000,10.44,68.44),(7,4,1,3,5,'PRD-0001','Insecticida Imidacloprid 350 SC',10.0000,95.0000,0.00,950.00,18.0000,171.00,1121.00),(8,4,2,7,5,'PRD-0005','Fungicida Fluopyram + Tebuconazol 400 SC',5.0000,315.0000,0.00,1575.00,18.0000,283.50,1858.50),(9,5,1,6,4,'PRD-0004','Fungicida Trifloxistrobina + Tebuconazol 75 WG',8.0000,289.0000,100.00,2312.00,18.0000,398.16,2610.16),(10,5,2,10,7,'PRD-0008','Fertilizante Urea 46% N',20.0000,128.0000,0.00,2560.00,18.0000,460.80,3020.80),(11,6,1,10,7,'PRD-0008','Fertilizante Urea 46% N',4.0000,128.0000,0.00,512.00,18.0000,92.16,604.16),(12,6,2,14,5,'PRD-0012','Bioestimulante a base de extracto de algas',3.0000,65.0000,0.00,195.00,18.0000,35.10,230.10),(13,7,1,4,5,'PRD-0002','Insecticida Spirotetramat 150 OD',5.0000,245.0000,0.00,1225.00,18.0000,220.50,1445.50),(14,8,1,7,5,'PRD-0005','Fungicida Fluopyram + Tebuconazol 400 SC',3.0000,315.0000,0.00,945.00,18.0000,170.10,1115.10),(15,8,2,11,7,'PRD-0009','Fertilizante Nitrato de Potasio',5.0000,149.0000,20.00,745.00,18.0000,130.50,855.50),(16,9,1,4,5,'PRD-0002','Insecticida Spirotetramat 150 OD',2.0000,245.0000,0.00,490.00,18.0000,88.20,578.20);
/*!40000 ALTER TABLE `pedido_detalles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pedidos`
--

DROP TABLE IF EXISTS `pedidos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pedidos` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `sucursal_id` int NOT NULL,
  `cliente_id` bigint NOT NULL,
  `vendedor_id` bigint DEFAULT NULL,
  `numero` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `fecha` date NOT NULL,
  `moneda` char(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'PEN',
  `estado` enum('borrador','confirmado','convertido','anulado') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'borrador',
  `subtotal` decimal(18,2) NOT NULL DEFAULT '0.00',
  `descuento` decimal(18,2) NOT NULL DEFAULT '0.00',
  `impuesto` decimal(18,2) NOT NULL DEFAULT '0.00',
  `total` decimal(18,2) NOT NULL DEFAULT '0.00',
  `observaciones` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `anulado_por` bigint DEFAULT NULL,
  `anulado_en` datetime DEFAULT NULL,
  `motivo_anulacion` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `creado_por` bigint NOT NULL,
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `cotizacion_id` bigint DEFAULT NULL,
  `condicion_pago` enum('contado','credito') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'contado',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pedido_numero` (`sucursal_id`,`numero`),
  UNIQUE KEY `uq_pedido_cotizacion` (`cotizacion_id`),
  KEY `idx_pedido_cliente_fecha` (`cliente_id`,`fecha`),
  KEY `idx_pedido_estado_fecha` (`estado`,`fecha`),
  KEY `fk_pedido_vendedor` (`vendedor_id`),
  KEY `fk_pedido_autor` (`creado_por`),
  KEY `fk_pedido_anulador` (`anulado_por`),
  CONSTRAINT `fk_pedido_anulador` FOREIGN KEY (`anulado_por`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_pedido_autor` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_pedido_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`),
  CONSTRAINT `fk_pedido_cotizacion` FOREIGN KEY (`cotizacion_id`) REFERENCES `cotizaciones` (`id`),
  CONSTRAINT `fk_pedido_sucursal` FOREIGN KEY (`sucursal_id`) REFERENCES `sucursales` (`id`),
  CONSTRAINT `fk_pedido_vendedor` FOREIGN KEY (`vendedor_id`) REFERENCES `vendedores` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pedidos`
--

LOCK TABLES `pedidos` WRITE;
/*!40000 ALTER TABLE `pedidos` DISABLE KEYS */;
INSERT INTO `pedidos` VALUES (1,1,1,1,'PED001-00000001','2026-11-13','PEN','confirmado',4790.00,50.00,853.20,5593.20,'Campaña de palta',NULL,NULL,NULL,1,'2026-11-13 19:11:10','2026-11-13 19:11:10',1,'contado'),(2,1,2,1,'PED001-00000002','2026-11-13','PEN','confirmado',2730.00,0.00,491.40,3221.40,'Fertilización de frutales',NULL,NULL,NULL,1,'2026-11-13 19:11:10','2026-11-13 19:11:10',2,'credito'),(3,1,7,1,'PED001-00000003','2026-11-13','PEN','confirmado',188.00,0.00,33.84,221.84,'Venta mostrador',NULL,NULL,NULL,1,'2026-11-13 19:11:10','2026-11-13 19:11:10',3,'contado'),(4,2,4,2,'PED002-00000001','2026-11-13','PEN','confirmado',2525.00,0.00,454.50,2979.50,'Control sanitario vid',NULL,NULL,NULL,1,'2026-11-13 19:11:31','2026-11-13 19:11:31',4,'contado'),(5,2,5,2,'PED002-00000002','2026-11-13','PEN','confirmado',4872.00,100.00,858.96,5630.96,'Programa fitosanitario',NULL,NULL,NULL,1,'2026-11-13 19:11:31','2026-11-13 19:11:31',5,'credito'),(6,2,8,2,'PED002-00000003','2026-11-13','PEN','borrador',707.00,0.00,127.26,834.26,'Pedido de fertilizantes',NULL,NULL,NULL,1,'2026-11-13 19:11:31','2026-11-13 19:11:31',7,'contado'),(7,2,4,2,'PED002-00000004','2026-11-13','PEN','confirmado',1225.00,0.00,220.50,1445.50,'Programa de control de ácaros',NULL,NULL,NULL,1,'2026-11-13 19:27:42','2026-11-13 19:27:42',8,'contado'),(8,1,6,1,'PED001-00000004','2026-11-14','PEN','confirmado',1690.00,20.00,300.60,1970.60,'Auditoría final',NULL,NULL,NULL,1,'2026-11-14 15:48:17','2026-11-14 15:48:17',10,'contado'),(9,2,8,2,'PED002-00000005','2026-11-14','PEN','confirmado',490.00,0.00,88.20,578.20,'Auditoría final',NULL,NULL,NULL,1,'2026-11-14 15:48:48','2026-11-14 15:48:48',11,'credito');
/*!40000 ALTER TABLE `pedidos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `permisos`
--

DROP TABLE IF EXISTS `permisos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permisos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `descripcion` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permisos`
--

LOCK TABLES `permisos` WRITE;
/*!40000 ALTER TABLE `permisos` DISABLE KEYS */;
INSERT INTO `permisos` VALUES (1,'ver','Consultar información'),(2,'crear','Registrar documentos y maestros'),(3,'editar','Modificar, confirmar y cambiar estados'),(4,'eliminar','Eliminar registros');
/*!40000 ALTER TABLE `permisos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `productos`
--

DROP TABLE IF EXISTS `productos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `productos` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `codigo` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `nombre` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `presentacion` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `es_bayer` tinyint NOT NULL DEFAULT '0',
  `categoria_id` int DEFAULT NULL,
  `marca_id` int DEFAULT NULL,
  `unidad_base_id` int DEFAULT NULL,
  `estado` tinyint DEFAULT '1',
  `costo_unitario` decimal(12,2) DEFAULT NULL,
  `precio_venta` decimal(12,2) DEFAULT NULL,
  `moneda` char(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'PEN',
  `observacion` varchar(300) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `categoria_id` (`categoria_id`),
  KEY `marca_id` (`marca_id`),
  KEY `unidad_base_id` (`unidad_base_id`),
  CONSTRAINT `productos_ibfk_1` FOREIGN KEY (`categoria_id`) REFERENCES `categorias_producto` (`id`),
  CONSTRAINT `productos_ibfk_2` FOREIGN KEY (`marca_id`) REFERENCES `marcas` (`id`),
  CONSTRAINT `productos_ibfk_3` FOREIGN KEY (`unidad_base_id`) REFERENCES `unidades_medida` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `productos`
--

LOCK TABLES `productos` WRITE;
/*!40000 ALTER TABLE `productos` DISABLE KEYS */;
INSERT INTO `productos` VALUES (1,'PROD-001','Aspirina 100mg Caja x 100',NULL,0,1,1,1,1,NULL,NULL,'PEN',NULL,NULL),(2,'PROD-002','Redoxon Doble Acción Tubo',NULL,0,2,2,2,1,NULL,NULL,'PEN',NULL,NULL),(3,'PRD-0001','Insecticida Imidacloprid 350 SC','Frasco 1 L',1,3,3,5,1,68.00,95.00,'PEN',NULL,NULL),(4,'PRD-0002','Insecticida Spirotetramat 150 OD','Frasco 1 L',1,3,3,5,1,180.00,245.00,'PEN',NULL,NULL),(5,'PRD-0003','Insecticida Deltametrina 25 EC','Frasco 1 L',1,3,3,5,1,40.00,58.00,'PEN',NULL,NULL),(6,'PRD-0004','Fungicida Trifloxistrobina + Tebuconazol 75 WG','Bolsa 1 kg',1,4,3,4,1,210.00,289.00,'PEN',NULL,NULL),(7,'PRD-0005','Fungicida Fluopyram + Tebuconazol 400 SC','Frasco 1 L',1,4,3,5,1,230.00,315.00,'PEN',NULL,NULL),(8,'PRD-0006','Herbicida Glifosato 480 SL','Galón 4 L',0,5,4,6,1,55.00,78.00,'PEN',NULL,NULL),(9,'PRD-0007','Herbicida Pendimetalina 400 EC','Frasco 1 L',0,5,4,5,1,38.00,54.00,'PEN',NULL,NULL),(10,'PRD-0008','Fertilizante Urea 46% N','Saco 50 kg',0,6,5,7,1,95.00,128.00,'PEN',NULL,NULL),(11,'PRD-0009','Fertilizante Nitrato de Potasio','Saco 25 kg',0,6,5,7,1,110.00,149.00,'PEN',NULL,NULL),(12,'PRD-0010','Fertilizante foliar NPK 20-20-20','Bolsa 1 kg',0,6,5,4,1,18.00,27.00,'PEN',NULL,NULL),(13,'PRD-0011','Semilla de maíz amarillo duro híbrido','Bolsa 20 kg',0,7,6,7,1,280.00,360.00,'PEN',NULL,NULL),
(14,'PRD-0012','Bioestimulante a base de extracto de algas','Frasco 1 L',0,8,5,5,1,45.00,65.00,'PEN',NULL,NULL);
INSERT INTO productos (id, codigo, nombre, presentacion, es_bayer, categoria_id, marca_id, unidad_base_id, estado, costo_unitario, precio_venta, moneda, observacion, updated_at) VALUES 
(15, '85776193', 'ABACUS EC18', 'Botella 1 L', 1, 3, 3, 5, 1, 57.02, 67.28, 'PEN', NULL, CURRENT_TIMESTAMP),
(16, '86216353', 'ABACUS EC18', 'Botella 4 L', 1, 3, 3, 5, 1, 207.95, 245.38, 'PEN', NULL, CURRENT_TIMESTAMP),
(17, '92791860', 'ALDEBARAN SC480', 'Botella 250 ML', 1, 3, 3, 5, 1, 320.15, 377.78, 'PEN', NULL, CURRENT_TIMESTAMP),
(18, '84939862', 'ALIETTE WG80', 'Bolsa 1 KG', 1, 4, 3, 4, 1, 126.44, 149.20, 'PEN', NULL, CURRENT_TIMESTAMP),
(19, '81750831', 'ALIETTE WG80', 'Bolsa 12 KG', 1, 4, 3, 4, 1, 1356.13, 1600.23, 'PEN', NULL, CURRENT_TIMESTAMP),
(20, '80993315', 'ALION SC500', 'Botella 1 L', 1, 5, 3, 5, 1, 923.50, 1089.73, 'PEN', NULL, CURRENT_TIMESTAMP),
(21, '91957137', 'AMINOFOL PLUS SL51', 'Botella 1 L', 1, 6, 3, 5, 1, 236.24, 236.24, 'PEN', NULL, CURRENT_TIMESTAMP),
(22, '572857', 'ANTRACOL WP70', 'Bolsa 1 KG', 1, 4, 3, 4, 1, 46.64, 55.04, 'PEN', NULL, CURRENT_TIMESTAMP),
(23, '91121470', 'ANTRACOL', 'Bolsa 5 KG', 1, 4, 3, 4, 1, 194.02, 228.94, 'PEN', NULL, CURRENT_TIMESTAMP),
(24, '91214908', 'BATALLA SL480', 'Botella 1 L', 1, 5, 3, 5, 1, 25.03, 29.54, 'PEN', NULL, CURRENT_TIMESTAMP),
(25, '89764114', 'BATALLA SL480', 'Tambor 200 L', 1, 5, 3, 5, 1, 3921.00, 4626.78, 'PEN', NULL, CURRENT_TIMESTAMP),
(26, '91214924', 'BATALLA SL480', 'Tambor 20 L', 1, 5, 3, 5, 1, 426.86, 503.69, 'PEN', NULL, CURRENT_TIMESTAMP),
(27, '86774844', 'BATALLA SL480', 'Botella 4 L', 1, 5, 3, 5, 1, 93.32, 110.12, 'PEN', NULL, CURRENT_TIMESTAMP),
(28, '91113516', 'BAYFOLAN (11-8-6)', 'Botella 1 L', 1, 6, 3, 5, 1, 32.19, 32.19, 'PEN', NULL, CURRENT_TIMESTAMP),
(29, '91113494', 'BAYFOLAN (11-8-6)', 'Tambor 10 L', 1, 6, 3, 5, 1, 228.55, 228.55, 'PEN', NULL, CURRENT_TIMESTAMP),
(30, '91113508', 'BAYFOLAN (11-8-6)', 'Tambor 200 L', 1, 6, 3, 5, 1, 3973.16, 3973.16, 'PEN', NULL, CURRENT_TIMESTAMP),
(31, '91191177', 'BAYFOLAN AKTIVATOR XL', 'Tambor 200 L', 1, 6, 3, 5, 1, 8744.00, 8744.00, 'PEN', NULL, CURRENT_TIMESTAMP),
(32, '87333744', 'BAYFOLAN AKTIVATOR XL', 'Tambor 20 L', 1, 6, 3, 5, 1, 876.20, 1033.92, 'PEN', NULL, CURRENT_TIMESTAMP),
(33, '87302490', 'BAYFOLAN AKTIVATOR XL', 'Botella 1 L', 1, 6, 3, 5, 1, 52.38, 61.81, 'PEN', NULL, CURRENT_TIMESTAMP),
(34, '87330044', 'BAYFOLAN AKTIVATOR XL', 'IBC 1000 L', 1, 6, 3, 5, 1, 42718.58, 50407.92, 'PEN', NULL, CURRENT_TIMESTAMP),
(35, '87260321', 'BAYFOLAN ALGAE (REV)', 'Botella 1 L', 1, 6, 3, 5, 1, 65.81, 65.81, 'PEN', NULL, CURRENT_TIMESTAMP),
(36, '87289966', 'BAYFOLAN ALGAE', 'Tambor 20 L', 1, 6, 3, 5, 1, 1054.42, 1054.42, 'PEN', NULL, CURRENT_TIMESTAMP),
(37, '92271492', 'BAYFOLAN SOIL (MX) FS', 'Botella 1 L', 1, 6, 3, 5, 1, 94.90, 111.98, 'PEN', NULL, CURRENT_TIMESTAMP),
(38, '91331068', 'BAYFOLAN SOIL (MX) FS', 'Botella 5 L', 1, 6, 3, 5, 1, 381.36, 450.00, 'PEN', NULL, CURRENT_TIMESTAMP),
(39, '91331041', 'BAYFOLAN SOIL (MX) FS', 'Botella 10 L', 1, 6, 3, 5, 1, 762.70, 899.99, 'PEN', NULL, CURRENT_TIMESTAMP),
(40, '80948018', 'BETA-BAYTROIDE SC125', 'Botella 1 L', 1, 3, 3, 5, 1, 151.84, 179.17, 'PEN', NULL, CURRENT_TIMESTAMP),
(41, '80849362', 'BETA-BAYTROIDE SC125', 'Botella 250 ML', 1, 3, 3, 5, 1, 41.81, 49.34, 'PEN', NULL, CURRENT_TIMESTAMP),
(42, '5589990', 'CONFIDOR SC350', 'Botella 1 L', 1, 3, 3, 5, 1, 149.24, 176.10, 'PEN', NULL, CURRENT_TIMESTAMP),
(43, '80018592', 'CONFIDOR SC350', 'Botella 5 L', 1, 3, 3, 5, 1, 602.00, 710.36, 'PEN', NULL, CURRENT_TIMESTAMP),
(44, '89359376', 'CONFIDOR WG70', 'Bolsa 20 KG', 1, 3, 3, 4, 1, 4635.00, 5469.30, 'PEN', NULL, CURRENT_TIMESTAMP),
(45, '90879140', 'CONFIDOR WG70', 'Bolsa 50 GR', 1, 3, 3, 4, 1, 23.08, 27.23, 'PEN', NULL, CURRENT_TIMESTAMP),
(46, '80907354', 'CUPRAVIT OB21 WP50', 'Bolsa 25 KG', 1, 4, 3, 4, 1, 1358.40, 1602.91, 'PEN', NULL, CURRENT_TIMESTAMP),
(47, '79247729', 'CUPRAVIT OB21 WP50', 'Bolsa 1 KG', 1, 4, 3, 4, 1, 57.70, 68.09, 'PEN', NULL, CURRENT_TIMESTAMP),
(48, '4158503', 'DECIS EC25', 'Botella 1 L', 1, 3, 3, 5, 1, 123.98, 146.30, 'PEN', NULL, CURRENT_TIMESTAMP),
(49, '80895844', 'DECIS EC25', 'Botella 5 L', 1, 3, 3, 5, 1, 570.51, 673.20, 'PEN', NULL, CURRENT_TIMESTAMP),
(50, '89983355', 'DOMARK EC100', 'Botella 1 L', 1, 4, 3, 5, 1, 204.92, 241.81, 'PEN', NULL, CURRENT_TIMESTAMP),
(51, '89983347', 'EMINENT EXCELL SC180', 'Botella 500 ML', 1, 4, 3, 5, 1, 115.01, 135.71, 'PEN', NULL, CURRENT_TIMESTAMP),
(52, '85765264', 'EQUIPO DE PROTECCION', 'Unidad', 1, NULL, 3, 3, 1, 124.39, 146.78, 'PEN', NULL, CURRENT_TIMESTAMP),
(53, '86278693', 'ESFERA SC300', 'Botella 1 L', 1, 4, 3, 5, 1, 224.38, 264.77, 'PEN', NULL, CURRENT_TIMESTAMP),
(54, '90224799', 'FINISH (BR) SC540', 'Botella 5 L', 1, 8, 3, 5, 1, 493.00, 581.74, 'PEN', NULL, CURRENT_TIMESTAMP),
(55, '79277350', 'FITOBROT SL535', 'Tambor 200 L', 1, 4, 3, 5, 1, 3875.99, 4573.67, 'PEN', NULL, CURRENT_TIMESTAMP),
(56, '699563', 'FITORAZ WP76', 'Bolsa 1 KG', 1, 4, 3, 4, 1, 72.80, 85.90, 'PEN', NULL, CURRENT_TIMESTAMP),
(57, '699520', 'FITORAZ WP76', 'Bolsa 500 GR', 1, 4, 3, 4, 1, 38.78, 45.76, 'PEN', NULL, CURRENT_TIMESTAMP),
(58, '81742030', 'FITORAZ WP76', 'Bolsa 5 KG', 1, 4, 3, 4, 1, 360.00, 424.80, 'PEN', NULL, CURRENT_TIMESTAMP),
(59, '1026627', 'FOLICUR EW250', 'Botella 1 L', 1, 4, 3, 5, 1, 178.88, 211.08, 'PEN', NULL, CURRENT_TIMESTAMP),
(60, '80521731', 'FOLICUR EW250', 'Botella 250 ML', 1, 4, 3, 5, 1, 53.45, 63.07, 'PEN', NULL, CURRENT_TIMESTAMP),
(61, '80014953', 'FOLICUR EW250', 'Botella 5 L', 1, 4, 3, 5, 1, 775.72, 915.35, 'PEN', NULL, CURRENT_TIMESTAMP),
(62, '81784248', 'INFINITO SC687,5', 'Botella 1 L', 1, 4, 3, 5, 1, 142.77, 168.47, 'PEN', NULL, CURRENT_TIMESTAMP),
(63, '90295971', 'KANEMITE SC150', 'Botella 1 L', 1, 3, 3, 5, 1, 306.48, 361.65, 'PEN', NULL, CURRENT_TIMESTAMP),
(64, '86234459', 'LARVIN PLUS SC480', 'Botella 1 L', 1, 3, 3, 5, 1, 139.76, 164.92, 'PEN', NULL, CURRENT_TIMESTAMP),
(65, '88828976', 'LUNA EXPERIENCE (CO) SC400', 'Botella 1 L', 1, 4, 3, 5, 1, 530.34, 625.80, 'PEN', NULL, CURRENT_TIMESTAMP),
(66, '88595688', 'LUNA EXPERIENCE (CO) SC400', 'Botella 5 L', 1, 4, 3, 5, 1, 2571.94, 3034.89, 'PEN', NULL, CURRENT_TIMESTAMP),
(67, '85836153', 'LUNA TRANQUILITY SC500', 'Botella 1 L', 1, 4, 3, 5, 1, 313.53, 369.97, 'PEN', NULL, CURRENT_TIMESTAMP),
(68, '89240492', 'LUNA TRANQUILITY SC500', 'Botella 5 L', 1, 4, 3, 5, 1, 1489.29, 1757.36, 'PEN', NULL, CURRENT_TIMESTAMP),
(69, '85825240', 'LUNA TRANQUILITY SC500', 'Botella 250 ML', 1, 4, 3, 5, 1, 80.21, 94.65, 'PEN', NULL, CURRENT_TIMESTAMP),
(70, '88828852', 'MOVENTO (CO) OD150', 'Botella 1 L', 1, 3, 3, 5, 1, 554.33, 654.11, 'PEN', NULL, CURRENT_TIMESTAMP),
(71, '88595661', 'MOVENTO (CO) OD150', 'Botella 250 ML', 1, 3, 3, 5, 1, 173.51, 204.74, 'PEN', NULL, CURRENT_TIMESTAMP),
(72, '88595645', 'MOVENTO (CO) OD150', 'Botella 5 L', 1, 3, 3, 5, 1, 2647.51, 3124.06, 'PEN', NULL, CURRENT_TIMESTAMP),
(73, '86203790', 'MOVENTO SC240', 'Botella 1 L', 1, 3, 3, 5, 1, 686.21, 809.73, 'PEN', NULL, CURRENT_TIMESTAMP),
(74, '89324300', 'NATIVO WG75', 'Bolsa 1 KG', 1, 4, 3, 4, 1, 499.69, 589.63, 'PEN', NULL, CURRENT_TIMESTAMP),
(75, '90435900', 'NATIVO WG75', 'Caja 100 GR', 1, 4, 3, 4, 1, 59.64, 70.38, 'PEN', NULL, CURRENT_TIMESTAMP),
(76, '89324297', 'NATIVO WG75', 'Bolsa 5 KG', 1, 4, 3, 4, 1, 2437.06, 2875.73, 'PEN', NULL, CURRENT_TIMESTAMP),
(77, '85385186', 'OBERON SC240', 'Botella 1 L', 1, 3, 3, 5, 1, 335.60, 396.01, 'PEN', NULL, CURRENT_TIMESTAMP),
(78, '85329448', 'OBERON SC240', 'Botella 5 L', 1, 3, 3, 5, 1, 1621.91, 1913.85, 'PEN', NULL, CURRENT_TIMESTAMP),
(79, '81695849', 'GUANTES PARA EQUIPO', 'Unidad', 1, NULL, 3, 3, 1, 4.69, 5.53, 'PEN', NULL, CURRENT_TIMESTAMP),
(80, '91210791', 'PREP SL720', 'Botella 10 L', 1, 8, 3, 5, 1, 2113.39, 2493.80, 'PEN', NULL, CURRENT_TIMESTAMP),
(81, '79716079', 'PREVICUR ENERGY SL840', 'Botella 1 L', 1, 4, 3, 5, 1, 142.37, 168.00, 'PEN', NULL, CURRENT_TIMESTAMP),
(82, '79283229', 'PROMALINA SL37,6', 'Botella 500 ML', 1, 8, 3, 5, 1, 600.15, 708.18, 'PEN', NULL, CURRENT_TIMESTAMP),
(83, '79201745', 'PROMALINA SL37,6', 'Botella 125 ML', 1, 8, 3, 5, 1, 197.66, 233.24, 'PEN', NULL, CURRENT_TIMESTAMP),
(84, '79236123', 'PROMALINA SL37,6', 'Botella 30 ML', 1, 8, 3, 5, 1, 52.19, 61.58, 'PEN', NULL, CURRENT_TIMESTAMP),
(85, '85798197', 'PROSPER EC500', 'Botella 1 L', 1, 4, 3, 5, 1, 293.05, 345.80, 'PEN', NULL, CURRENT_TIMESTAMP),
(86, '85816977', 'PROSPER EC500', 'Botella 5 L', 1, 4, 3, 5, 1, 1325.86, 1564.51, 'PEN', NULL, CURRENT_TIMESTAMP),
(87, '90442109', 'PROSPER OPTI (AG) CS300', 'Botella 1 L', 1, 4, 3, 5, 1, 193.41, 228.22, 'PEN', NULL, CURRENT_TIMESTAMP),
(88, '90442095', 'PROSPER OPTI (AG) CS300', 'Botella 5 L', 1, 4, 3, 5, 1, 875.07, 1032.58, 'PEN', NULL, CURRENT_TIMESTAMP),
(89, '4213490', 'REGENT SC200', 'Botella 1 L', 1, 3, 3, 5, 1, 249.93, 294.92, 'PEN', NULL, CURRENT_TIMESTAMP),
(90, '80480253', 'REGENT SC200', 'Botella 250 ML', 1, 3, 3, 5, 1, 72.50, 85.55, 'PEN', NULL, CURRENT_TIMESTAMP),
(91, '4215612', 'RONSTAR EC250', 'Botella 1 L', 1, 5, 3, 5, 1, 143.60, 169.45, 'PEN', NULL, CURRENT_TIMESTAMP),
(92, '79204361', 'RYZ UP SL40', 'Botella 5 L', 1, 8, 3, 5, 1, 1279.79, 1510.15, 'PEN', NULL, CURRENT_TIMESTAMP),
(93, '79246544', 'RYZ UP SL40', 'Botella 25 ML', 1, 8, 3, 5, 1, 15.85, 18.70, 'PEN', NULL, CURRENT_TIMESTAMP),
(94, '4261967', 'SCALA SC400', 'Botella 1 L', 1, 4, 3, 5, 1, 206.96, 244.21, 'PEN', NULL, CURRENT_TIMESTAMP),
(95, '80581505', 'SCALA SC400', 'Tambor 20 L', 1, 4, 3, 5, 1, 2508.26, 2959.75, 'PEN', NULL, CURRENT_TIMESTAMP),
(96, '80587384', 'SEMEVIN SC350', 'Botella 250 ML', 1, 3, 3, 5, 1, 40.79, 48.13, 'PEN', NULL, CURRENT_TIMESTAMP),
(97, '80556853', 'SEMEVIN SC350', 'Botella 1 L', 1, 3, 3, 5, 1, 130.97, 154.54, 'PEN', NULL, CURRENT_TIMESTAMP),
(98, '84428094', 'SERENADE ASO', 'Botella 1 L', 1, 4, 3, 5, 1, 61.02, 72.00, 'PEN', NULL, CURRENT_TIMESTAMP),
(99, '84977837', 'SERENADE ASO', 'Botella 10 L', 1, 4, 3, 5, 1, 526.29, 621.02, 'PEN', NULL, CURRENT_TIMESTAMP),
(100, '80929854', 'SIVANTO PRIME SL200', 'Botella 1 L', 1, 3, 3, 5, 1, 169.76, 200.32, 'PEN', NULL, CURRENT_TIMESTAMP),
(101, '84132624', 'SIVANTO PRIME SL200', 'Botella 5 L', 1, 3, 3, 5, 1, 841.58, 993.06, 'PEN', NULL, CURRENT_TIMESTAMP),
(102, '80507526', 'SULFODIN WG80', 'Bolsa 25 KG', 1, 4, 3, 4, 1, 324.40, 382.79, 'PEN', NULL, CURRENT_TIMESTAMP),
(103, '79239165', 'SULFODIN WG80', 'Bolsa 1 KG', 1, 4, 3, 4, 1, 18.25, 21.54, 'PEN', NULL, CURRENT_TIMESTAMP),
(104, '79556438', 'SUMISCLEX WP50', 'Bolsa 200 GR', 1, 4, 3, 4, 1, 74.43, 87.83, 'PEN', NULL, CURRENT_TIMESTAMP),
(105, '79470479', 'SUMISCLEX WP50', 'Bolsa 1 KG', 1, 4, 3, 4, 1, 315.51, 372.30, 'PEN', NULL, CURRENT_TIMESTAMP),
(106, '86747294', 'TELDOR SC500', 'Botella 5 L', 1, 4, 3, 5, 1, 1151.34, 1358.58, 'PEN', NULL, CURRENT_TIMESTAMP),
(107, '79248326', 'TRIVIA WP72,7', 'Bolsa 1 KG', 1, 4, 3, 4, 1, 100.70, 118.83, 'PEN', NULL, CURRENT_TIMESTAMP),
(108, '85800523', 'VAYEGO SC200', 'Botella 250 ML', 1, 3, 3, 5, 1, 167.50, 197.65, 'PEN', NULL, CURRENT_TIMESTAMP),
(109, '85831402', 'VAYEGO SC200', 'Botella 1 L', 1, 3, 3, 5, 1, 620.15, 731.78, 'PEN', NULL, CURRENT_TIMESTAMP),
(110, '85849468', 'VERANGO PRIME SC500', 'Botella 1 L', 1, 3, 3, 5, 1, 708.39, 835.90, 'PEN', NULL, CURRENT_TIMESTAMP),
(111, '89007046', 'XIVANA SMART (CO) SC230', 'Botella 1 L', 1, 4, 3, 5, 1, 400.00, 472.00, 'PEN', NULL, CURRENT_TIMESTAMP),
(112, '91050700', 'XIVANA SMART (CO) SC230', 'Botella 250 ML', 1, 4, 3, 5, 1, 101.69, 119.99, 'PEN', NULL, CURRENT_TIMESTAMP),
(113, '89326273', 'ZANTARA (CO) EC216', 'Botella 1 L', 1, 4, 3, 5, 1, 162.57, 191.83, 'PEN', NULL, CURRENT_TIMESTAMP);
/*!40000 ALTER TABLE `productos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `proveedores`
--

DROP TABLE IF EXISTS `proveedores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `proveedores` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `codigo` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ruc` char(11) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nombre` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `contacto` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefono` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `correo` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dias_pago` int DEFAULT NULL,
  `estado` tinyint NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo` (`codigo`),
  UNIQUE KEY `uq_proveedor_ruc` (`ruc`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `proveedores`
--

LOCK TABLES `proveedores` WRITE;
/*!40000 ALTER TABLE `proveedores` DISABLE KEYS */;
INSERT INTO `proveedores` VALUES (1,'PRV-0001','20607666777','BAYER S.A. - DIVISIÓN CROP SCIENCE','Ejecutivo de cuenta',NULL,NULL,30,1,'2026-11-13 19:10:06'),(2,'PRV-0002','20608777882','DISTRIBUIDORA AGROQUÍMICA DEL SUR S.A.C.','Ventas corporativas',NULL,NULL,30,1,'2026-11-13 19:10:06'),(3,'PRV-0003','20609888998','FERTILIZANTES Y SEMILLAS DEL PACÍFICO S.A.','Atención a distribuidores',NULL,NULL,45,1,'2026-11-13 19:10:06');
/*!40000 ALTER TABLE `proveedores` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `provincias`
--

DROP TABLE IF EXISTS `provincias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `provincias` (
  `id` int NOT NULL AUTO_INCREMENT,
  `departamento_id` int NOT NULL,
  `nombre` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `departamento_id` (`departamento_id`),
  CONSTRAINT `provincias_ibfk_1` FOREIGN KEY (`departamento_id`) REFERENCES `departamentos` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `provincias`
--

LOCK TABLES `provincias` WRITE;
/*!40000 ALTER TABLE `provincias` DISABLE KEYS */;
INSERT INTO `provincias` VALUES (1,1,'Cañete'),(2,1,'Lima'),(3,2,'Chincha');
/*!40000 ALTER TABLE `provincias` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `publicaciones`
--

DROP TABLE IF EXISTS `publicaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `publicaciones` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `modulo` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha_publicacion` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `usuario_id` bigint NOT NULL,
  `estado` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `cancellation_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `cancelled_at` datetime DEFAULT NULL,
  `cancelled_by` bigint DEFAULT NULL,
  `correlation_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `fk_publicaciones_cancelled` (`cancelled_by`),
  KEY `ix_publicaciones_modulo_fecha` (`modulo`,`fecha_publicacion`),
  CONSTRAINT `fk_publicaciones_cancelled` FOREIGN KEY (`cancelled_by`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `publicaciones_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `publicaciones`
--

LOCK TABLES `publicaciones` WRITE;
/*!40000 ALTER TABLE `publicaciones` DISABLE KEYS */;
/*!40000 ALTER TABLE `publicaciones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rol_permiso`
--

DROP TABLE IF EXISTS `rol_permiso`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rol_permiso` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `rol_id` int NOT NULL,
  `permiso_id` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rol_permiso` (`rol_id`,`permiso_id`),
  KEY `rol_id` (`rol_id`),
  KEY `permiso_id` (`permiso_id`),
  CONSTRAINT `rol_permiso_ibfk_1` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`),
  CONSTRAINT `rol_permiso_ibfk_2` FOREIGN KEY (`permiso_id`) REFERENCES `permisos` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rol_permiso`
--

LOCK TABLES `rol_permiso` WRITE;
/*!40000 ALTER TABLE `rol_permiso` DISABLE KEYS */;
INSERT INTO `rol_permiso` VALUES (25,1,1),(26,1,2),(27,1,3),(28,1,4),(29,2,1),(30,2,2),(31,2,3),(32,3,1),(33,3,3),(34,4,1);
/*!40000 ALTER TABLE `rol_permiso` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `descripcion` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `estado` tinyint NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'Administrador','Gobierno total del sistema',1),(2,'Digitador','Captura operativa',1),(3,'Supervisor','Valida y publica',1),(4,'Gerencia','Análisis y decisiones',1),(5,'Bayer','Consulta externa',1);
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `schema_migrations`
--

DROP TABLE IF EXISTS `schema_migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `schema_migrations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `migracion` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `applied_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `migracion` (`migracion`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `schema_migrations`
--

LOCK TABLES `schema_migrations` WRITE;
/*!40000 ALTER TABLE `schema_migrations` DISABLE KEYS */;
INSERT INTO `schema_migrations` VALUES (1,'002_ingesta_incremental.php','2026-11-13 12:41:41'),(2,'003_valor_unitario_neto.php','2026-11-13 19:24:52'),(3,'004_importe_detalle.php','2026-11-13 20:57:38'),(4,'005_restriccion_notas.php','2026-11-13 20:58:03');
/*!40000 ALTER TABLE `schema_migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `schema_versiones_g4`
--

DROP TABLE IF EXISTS `schema_versiones_g4`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `schema_versiones_g4` (
  `version` int NOT NULL,
  `description` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `applied_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `schema_versiones_g4`
--

LOCK TABLES `schema_versiones_g4` WRITE;
/*!40000 ALTER TABLE `schema_versiones_g4` DISABLE KEYS */;
INSERT INTO `schema_versiones_g4` VALUES (2,'Schema v2 - Dia 1 Pedro','2026-11-13 17:41:39');
/*!40000 ALTER TABLE `schema_versiones_g4` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `stock_cabecera`
--

DROP TABLE IF EXISTS `stock_cabecera`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `stock_cabecera` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `fecha_stock` date NOT NULL,
  `almacen_id` int NOT NULL,
  `estado_registro` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'BORRADOR',
  `created_by` bigint DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `published_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `updated_by` bigint DEFAULT NULL,
  `version` int unsigned NOT NULL DEFAULT '1',
  `validated_at` datetime DEFAULT NULL,
  `validated_by` bigint DEFAULT NULL,
  `observed_at` datetime DEFAULT NULL,
  `observed_by` bigint DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancelled_by` bigint DEFAULT NULL,
  `published_by` bigint DEFAULT NULL,
  `observation_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `cancellation_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `idempotency_key` varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `request_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `origen` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `origen_ref` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `origen_hash` char(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_stock_idempotency` (`idempotency_key`),
  UNIQUE KEY `uq_stock_contexto` (`id`,`fecha_stock`,`almacen_id`),
  UNIQUE KEY `uq_stock_cabecera_origen` (`origen`,`origen_ref`),
  KEY `created_by` (`created_by`),
  KEY `fk_stock_updated_by` (`updated_by`),
  KEY `fk_stock_validated_by` (`validated_by`),
  KEY `fk_stock_observed_by` (`observed_by`),
  KEY `fk_stock_cancelled_by` (`cancelled_by`),
  KEY `fk_stock_published_by` (`published_by`),
  KEY `ix_stock_estado_fecha` (`estado_registro`,`fecha_stock`,`id`),
  KEY `ix_stock_ubicacion_fecha` (`almacen_id`,`fecha_stock`,`id`),
  KEY `ix_stock_fecha_almacen` (`fecha_stock`,`almacen_id`),
  KEY `idx_stock_cabecera_estado` (`estado_registro`),
  KEY `idx_stock_fecha` (`fecha_stock`),
  CONSTRAINT `fk_stock_cancelled_by` FOREIGN KEY (`cancelled_by`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_stock_observed_by` FOREIGN KEY (`observed_by`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_stock_published_by` FOREIGN KEY (`published_by`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_stock_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_stock_validated_by` FOREIGN KEY (`validated_by`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `stock_cabecera_ibfk_1` FOREIGN KEY (`almacen_id`) REFERENCES `almacenes` (`id`),
  CONSTRAINT `stock_cabecera_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `ck_stock_estado` CHECK ((cast(`estado_registro` as char charset binary) in (_utf8mb4'BORRADOR',_utf8mb4'VALIDADO',_utf8mb4'PUBLICADO',_utf8mb4'OBSERVADO',_utf8mb4'ANULADO'))),
  CONSTRAINT `ck_stock_fecha` CHECK (((`fecha_stock` >= _utf8mb4'1000-01-01') and (dayofmonth(`fecha_stock`) >= 1))),
  CONSTRAINT `ck_stock_idempotency` CHECK ((char_length(`idempotency_key`) between 1 and 64)),
  CONSTRAINT `ck_stock_request_hash` CHECK (((char_length(`request_hash`) = 64) and regexp_like(`request_hash`,_ascii'^[0-9a-f]+$'))),
  CONSTRAINT `ck_stock_version` CHECK ((`version` >= 1))
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stock_cabecera`
--

LOCK TABLES `stock_cabecera` WRITE;
/*!40000 ALTER TABLE `stock_cabecera` DISABLE KEYS */;
INSERT INTO `stock_cabecera` VALUES (1,'2026-11-13',1,'PUBLICADO',NULL,'2026-11-13 19:12:07','2026-11-13 19:12:07','2026-11-13 19:12:07',NULL,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'ERP_G7:1@2026-11-13','84ff8624e71cfafc9f1226e73e62430ed281734303824f5edea82e1691fc98f9','ERP_G7_STOCK','1@2026-11-13','f9e0c632abc9d178abdb34d69eda4868e2dc5a17'),(2,'2026-11-13',2,'PUBLICADO',NULL,'2026-11-13 19:12:07','2026-11-13 19:12:07','2026-11-13 19:12:07',NULL,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'ERP_G7:2@2026-11-13','60e1876063095562fd0dfdca7aebcc72a592e841345060d6c3a2d0df1a090ffe','ERP_G7_STOCK','2@2026-11-13','615f8baec75d41071764cddea0e55741a102ce76'),(3,'2026-11-14',1,'PUBLICADO',NULL,'2026-11-14 01:09:54','2026-11-14 01:09:54','2026-11-14 15:51:39',NULL,3,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'ERP_G7:1@2026-11-14','b4944b94f49f4d2ad96b8b56bba54ebfa33a112dc4a712f179daaa71725d76de','ERP_G7_STOCK','1@2026-11-14','9b03e2344c23dbd1e2f35204e44ad081e0292dca'),(4,'2026-11-14',2,'PUBLICADO',NULL,'2026-11-14 01:09:54','2026-11-14 01:09:54','2026-11-14 15:51:39',NULL,3,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'ERP_G7:2@2026-11-14','c65a1d4c71a58bea42684c9fa98f1654fa6b2549a484fbef7b951ebb46a00be4','ERP_G7_STOCK','2@2026-11-14','e897ad6cf8eac5ae61c98a1c3330092312bdbb71'),(5,'2026-11-15',1,'PUBLICADO',NULL,'2026-11-15 15:20:35','2026-11-15 15:20:35','2026-11-15 15:20:35',NULL,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'ERP_G7:1@2026-11-15','d2c36b5de37bd23f69c5ebb58661b95a7df904d41e2c25af82d787bf61d15150','ERP_G7_STOCK','1@2026-11-15','9b03e2344c23dbd1e2f35204e44ad081e0292dca'),(6,'2026-11-15',2,'PUBLICADO',NULL,'2026-11-15 15:20:35','2026-11-15 15:20:35','2026-11-15 15:20:35',NULL,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'ERP_G7:2@2026-11-15','b510c102ea695be895d7b446e65b86f7e8ec7dbc4a753f403c7add5b17ae12a1','ERP_G7_STOCK','2@2026-11-15','e897ad6cf8eac5ae61c98a1c3330092312bdbb71');
/*!40000 ALTER TABLE `stock_cabecera` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `stock_detalle`
--

DROP TABLE IF EXISTS `stock_detalle`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `stock_detalle` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `stock_id` bigint NOT NULL,
  `lote_id` bigint DEFAULT NULL,
  `producto_id` bigint NOT NULL,
  `unidad_id` int NOT NULL,
  `cantidad` decimal(14,3) NOT NULL,
  `lote_clave` bigint GENERATED ALWAYS AS (ifnull(`lote_id`,0)) STORED,
  `fecha_stock` date NOT NULL,
  `almacen_id` int NOT NULL,
  `stock_minimo` decimal(14,3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_stock_existencia` (`fecha_stock`,`almacen_id`,`producto_id`,`lote_clave`,`unidad_id`),
  KEY `producto_id` (`producto_id`),
  KEY `unidad_id` (`unidad_id`),
  KEY `fk_stock_lote_producto` (`lote_id`,`producto_id`),
  KEY `fk_stock_detalle_contexto` (`stock_id`,`fecha_stock`,`almacen_id`),
  CONSTRAINT `fk_stock_detalle_contexto` FOREIGN KEY (`stock_id`, `fecha_stock`, `almacen_id`) REFERENCES `stock_cabecera` (`id`, `fecha_stock`, `almacen_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_stock_lote_producto` FOREIGN KEY (`lote_id`, `producto_id`) REFERENCES `lotes` (`id`, `producto_id`),
  CONSTRAINT `stock_detalle_ibfk_1` FOREIGN KEY (`stock_id`) REFERENCES `stock_cabecera` (`id`) ON DELETE CASCADE,
  CONSTRAINT `stock_detalle_ibfk_2` FOREIGN KEY (`lote_id`) REFERENCES `lotes` (`id`),
  CONSTRAINT `stock_detalle_ibfk_3` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`),
  CONSTRAINT `stock_detalle_ibfk_4` FOREIGN KEY (`unidad_id`) REFERENCES `unidades_medida` (`id`),
  CONSTRAINT `ck_stock_cantidad` CHECK ((`cantidad` >= 0)),
  CONSTRAINT `ck_stock_lote` CHECK (((`lote_id` is null) or (`lote_id` > 0)))
) ENGINE=InnoDB AUTO_INCREMENT=94 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stock_detalle`
--

LOCK TABLES `stock_detalle` WRITE;
/*!40000 ALTER TABLE `stock_detalle` DISABLE KEYS */;
INSERT INTO `stock_detalle` (`id`, `stock_id`, `lote_id`, `producto_id`, `unidad_id`, `cantidad`, `fecha_stock`, `almacen_id`, `stock_minimo`) VALUES (1,1,NULL,3,5,100.000,'2026-11-13',1,0.000),(2,1,NULL,4,5,40.000,'2026-11-13',1,0.000),(3,1,NULL,5,5,79.000,'2026-11-13',1,0.000),(4,1,NULL,6,4,40.000,'2026-11-13',1,0.000),(5,1,NULL,7,5,30.000,'2026-11-13',1,0.000),(6,1,NULL,8,6,60.000,'2026-11-13',1,0.000),(7,1,NULL,9,5,70.000,'2026-11-13',1,0.000),(8,1,NULL,10,7,85.000,'2026-11-13',1,0.000),(9,1,NULL,11,7,60.000,'2026-11-13',1,0.000),(10,1,NULL,12,4,120.000,'2026-11-13',1,0.000),(11,1,NULL,13,7,25.000,'2026-11-13',1,0.000),(12,1,NULL,14,5,58.000,'2026-11-13',1,0.000),(13,2,NULL,3,5,60.000,'2026-11-13',2,0.000),(14,2,NULL,6,4,25.000,'2026-11-13',2,0.000),(15,2,NULL,7,5,20.000,'2026-11-13',2,0.000),(16,2,NULL,10,7,60.000,'2026-11-13',2,0.000),(17,2,NULL,12,4,80.000,'2026-11-13',2,0.000),(18,2,NULL,14,5,40.000,'2026-11-13',2,0.000),(56,3,NULL,3,5,100.000,'2026-11-14',1,0.000),(57,3,NULL,4,5,40.000,'2026-11-14',1,0.000),(58,3,NULL,5,5,79.000,'2026-11-14',1,0.000),(59,3,NULL,6,4,40.000,'2026-11-14',1,0.000),(60,3,NULL,7,5,27.000,'2026-11-14',1,0.000),(61,3,NULL,8,6,60.000,'2026-11-14',1,0.000),(62,3,NULL,9,5,70.000,'2026-11-14',1,0.000),(63,3,NULL,10,7,85.000,'2026-11-14',1,0.000),(64,3,NULL,11,7,55.000,'2026-11-14',1,0.000),(65,3,NULL,12,4,120.000,'2026-11-14',1,0.000),(66,3,NULL,13,7,25.000,'2026-11-14',1,0.000),(67,3,NULL,14,5,53.000,'2026-11-14',1,0.000),(68,4,NULL,3,5,60.000,'2026-11-14',2,0.000),(69,4,NULL,4,5,6.000,'2026-11-14',2,0.000),(70,4,NULL,6,4,25.000,'2026-11-14',2,0.000),(71,4,NULL,7,5,20.000,'2026-11-14',2,0.000),(72,4,NULL,10,7,60.000,'2026-11-14',2,0.000),(73,4,NULL,12,4,80.000,'2026-11-14',2,0.000),(74,4,NULL,14,5,43.000,'2026-11-14',2,0.000),(75,5,NULL,3,5,100.000,'2026-11-15',1,0.000),(76,5,NULL,4,5,40.000,'2026-11-15',1,0.000),(77,5,NULL,5,5,79.000,'2026-11-15',1,0.000),(78,5,NULL,6,4,40.000,'2026-11-15',1,0.000),(79,5,NULL,7,5,27.000,'2026-11-15',1,0.000),(80,5,NULL,8,6,60.000,'2026-11-15',1,0.000),(81,5,NULL,9,5,70.000,'2026-11-15',1,0.000),(82,5,NULL,10,7,85.000,'2026-11-15',1,0.000),(83,5,NULL,11,7,55.000,'2026-11-15',1,0.000),(84,5,NULL,12,4,120.000,'2026-11-15',1,0.000),(85,5,NULL,13,7,25.000,'2026-11-15',1,0.000),(86,5,NULL,14,5,53.000,'2026-11-15',1,0.000),(87,6,NULL,3,5,60.000,'2026-11-15',2,0.000),(88,6,NULL,4,5,6.000,'2026-11-15',2,0.000),(89,6,NULL,6,4,25.000,'2026-11-15',2,0.000),(90,6,NULL,7,5,20.000,'2026-11-15',2,0.000),(91,6,NULL,10,7,60.000,'2026-11-15',2,0.000),(92,6,NULL,12,4,80.000,'2026-11-15',2,0.000),(93,6,NULL,14,5,43.000,'2026-11-15',2,0.000);
/*!40000 ALTER TABLE `stock_detalle` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sucursales`
--

DROP TABLE IF EXISTS `sucursales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sucursales` (
  `id` int NOT NULL AUTO_INCREMENT,
  `empresa_id` int NOT NULL,
  `codigo` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `nombre` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `direccion` varchar(180) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `distrito_id` int DEFAULT NULL,
  `codigo_establecimiento` char(4) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '0000',
  `estado` tinyint DEFAULT '1',
  `ciudad` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `empresa_id` (`empresa_id`),
  KEY `distrito_id` (`distrito_id`),
  CONSTRAINT `sucursales_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`),
  CONSTRAINT `sucursales_ibfk_2` FOREIGN KEY (`distrito_id`) REFERENCES `distritos` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sucursales`
--

LOCK TABLES `sucursales` WRITE;
/*!40000 ALTER TABLE `sucursales` DISABLE KEYS */;
INSERT INTO `sucursales` VALUES (1,1,'SED-CAN','Sede Cañete','Av. Mariscal Benavides 845',1,'0000',1,'Cañete'),(2,1,'SED-MAL','Sede Mala','Jr. Grau 312',4,'0001',1,'Mala');
/*!40000 ALTER TABLE `sucursales` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tesoreria_auditoria`
--

DROP TABLE IF EXISTS `tesoreria_auditoria`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tesoreria_auditoria` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `usuario_id` bigint NOT NULL,
  `fecha` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `cuenta_id` bigint DEFAULT NULL,
  `movimiento_id` bigint DEFAULT NULL,
  `transferencia_id` bigint DEFAULT NULL,
  `accion` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `estado_anterior` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `estado_nuevo` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `motivo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tesoreria_auditoria_fecha` (`fecha`,`usuario_id`),
  KEY `fk_tesoreria_auditoria_usuario` (`usuario_id`),
  KEY `fk_tesoreria_auditoria_cuenta` (`cuenta_id`),
  KEY `fk_tesoreria_auditoria_movimiento` (`movimiento_id`),
  KEY `fk_tesoreria_auditoria_transferencia` (`transferencia_id`),
  CONSTRAINT `fk_tesoreria_auditoria_cuenta` FOREIGN KEY (`cuenta_id`) REFERENCES `tesoreria_cuentas` (`id`),
  CONSTRAINT `fk_tesoreria_auditoria_movimiento` FOREIGN KEY (`movimiento_id`) REFERENCES `tesoreria_movimientos` (`id`),
  CONSTRAINT `fk_tesoreria_auditoria_transferencia` FOREIGN KEY (`transferencia_id`) REFERENCES `tesoreria_transferencias` (`id`),
  CONSTRAINT `fk_tesoreria_auditoria_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tesoreria_auditoria`
--

LOCK TABLES `tesoreria_auditoria` WRITE;
/*!40000 ALTER TABLE `tesoreria_auditoria` DISABLE KEYS */;
/*!40000 ALTER TABLE `tesoreria_auditoria` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tesoreria_cuentas`
--

DROP TABLE IF EXISTS `tesoreria_cuentas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tesoreria_cuentas` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `sucursal_id` int NOT NULL,
  `codigo` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `nombre` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `tipo` enum('bancaria','fondo') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `banco` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `numero_cuenta` varchar(34) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `moneda` char(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `estado` enum('activa','inactiva') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'activa',
  `creado_por` bigint NOT NULL,
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tesoreria_cuenta_codigo` (`sucursal_id`,`codigo`),
  UNIQUE KEY `uq_tesoreria_cuenta_moneda` (`id`,`moneda`),
  UNIQUE KEY `uq_tesoreria_cuenta_banco` (`banco`,`numero_cuenta`),
  KEY `idx_tesoreria_cuenta_estado` (`estado`,`tipo`),
  KEY `fk_tesoreria_cuenta_usuario` (`creado_por`),
  CONSTRAINT `fk_tesoreria_cuenta_sucursal` FOREIGN KEY (`sucursal_id`) REFERENCES `sucursales` (`id`),
  CONSTRAINT `fk_tesoreria_cuenta_usuario` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tesoreria_cuentas`
--

LOCK TABLES `tesoreria_cuentas` WRITE;
/*!40000 ALTER TABLE `tesoreria_cuentas` DISABLE KEYS */;
INSERT INTO `tesoreria_cuentas` VALUES (1,1,'CTA-CAN-01','Cuenta corriente soles Cañete','bancaria','Banco de ejemplo','000-0000001-0-01','PEN','activa',1,'2026-11-13 20:59:29','2026-11-13 20:59:29'),(2,2,'CTA-MAL-01','Cuenta corriente soles Mala','bancaria','Banco de ejemplo','000-0000002-0-01','PEN','activa',1,'2026-11-13 20:59:29','2026-11-13 20:59:29');
/*!40000 ALTER TABLE `tesoreria_cuentas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tesoreria_movimientos`
--

DROP TABLE IF EXISTS `tesoreria_movimientos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tesoreria_movimientos` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `cuenta_id` bigint NOT NULL,
  `moneda` char(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `clave_operacion` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `fecha` date NOT NULL,
  `tipo` enum('ingreso','egreso') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `origen` enum('manual','apertura','cobranza','caja','transferencia','reversion','pago_proveedor') COLLATE utf8mb4_general_ci NOT NULL,
  `importe` decimal(18,2) NOT NULL,
  `referencia` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `concepto` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `estado` enum('borrador','confirmado','anulado') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'borrador',
  `cobranza_id` bigint DEFAULT NULL,
  `g7_cuenta_pagar_id` bigint DEFAULT NULL,
  `caja_movimiento_id` bigint DEFAULT NULL,
  `transferencia_id` bigint DEFAULT NULL,
  `reversa_de_id` bigint DEFAULT NULL,
  `creado_por` bigint NOT NULL,
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `confirmado_por` bigint DEFAULT NULL,
  `confirmado_en` datetime DEFAULT NULL,
  `anulado_por` bigint DEFAULT NULL,
  `anulado_en` datetime DEFAULT NULL,
  `motivo_anulacion` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `cuenta_apertura_id` bigint GENERATED ALWAYS AS ((case when (`origen` = _utf8mb4'apertura') then `cuenta_id` else NULL end)) STORED,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tesoreria_movimiento_clave` (`clave_operacion`),
  UNIQUE KEY `uq_tesoreria_movimiento_referencia` (`cuenta_id`,`referencia`),
  UNIQUE KEY `uq_tesoreria_movimiento_cobranza` (`cobranza_id`),
  UNIQUE KEY `uq_tesoreria_movimiento_caja` (`caja_movimiento_id`),
  UNIQUE KEY `uq_tesoreria_movimiento_transferencia` (`transferencia_id`,`tipo`),
  UNIQUE KEY `uq_tesoreria_movimiento_reversion` (`reversa_de_id`),
  UNIQUE KEY `uq_tesoreria_movimiento_apertura` (`cuenta_apertura_id`),
  KEY `idx_tesoreria_movimiento_cuenta_fecha` (`cuenta_id`,`fecha`,`estado`),
  KEY `idx_tesoreria_movimiento_fecha_tipo` (`fecha`,`tipo`),
  KEY `fk_tesoreria_movimiento_cuenta` (`cuenta_id`,`moneda`),
  KEY `fk_tesoreria_movimiento_transferencia` (`transferencia_id`,`moneda`,`importe`),
  KEY `fk_tesoreria_movimiento_autor` (`creado_por`),
  KEY `fk_tesoreria_movimiento_confirmador` (`confirmado_por`),
  KEY `fk_tesoreria_movimiento_anulador` (`anulado_por`),
  KEY `fk_tmov_cuenta_pagar` (`g7_cuenta_pagar_id`),
  CONSTRAINT `fk_tesoreria_movimiento_anulador` FOREIGN KEY (`anulado_por`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_tesoreria_movimiento_autor` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_tesoreria_movimiento_caja` FOREIGN KEY (`caja_movimiento_id`) REFERENCES `caja_movimientos` (`id`),
  CONSTRAINT `fk_tesoreria_movimiento_cobranza` FOREIGN KEY (`cobranza_id`) REFERENCES `cobranzas` (`id`),
  CONSTRAINT `fk_tesoreria_movimiento_confirmador` FOREIGN KEY (`confirmado_por`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_tesoreria_movimiento_cuenta` FOREIGN KEY (`cuenta_id`, `moneda`) REFERENCES `tesoreria_cuentas` (`id`, `moneda`),
  CONSTRAINT `fk_tesoreria_movimiento_reversion` FOREIGN KEY (`reversa_de_id`) REFERENCES `tesoreria_movimientos` (`id`),
  CONSTRAINT `fk_tesoreria_movimiento_transferencia` FOREIGN KEY (`transferencia_id`, `moneda`, `importe`) REFERENCES `tesoreria_transferencias` (`id`, `moneda`, `importe`),
  CONSTRAINT `fk_tmov_cuenta_pagar` FOREIGN KEY (`g7_cuenta_pagar_id`) REFERENCES `g7_cuentas_pagar` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tesoreria_movimientos`
--

LOCK TABLES `tesoreria_movimientos` WRITE;
/*!40000 ALTER TABLE `tesoreria_movimientos` DISABLE KEYS */;
INSERT INTO `tesoreria_movimientos` (`id`, `cuenta_id`, `moneda`, `clave_operacion`, `fecha`, `tipo`, `origen`, `importe`, `referencia`, `concepto`, `estado`, `cobranza_id`, `g7_cuenta_pagar_id`, `caja_movimiento_id`, `transferencia_id`, `reversa_de_id`, `creado_por`, `creado_en`, `confirmado_por`, `confirmado_en`, `anulado_por`, `anulado_en`, `motivo_anulacion`) VALUES (1,1,'PEN','c8baedf4-9cbe-3069-3bc8-447630e78c00','2026-11-13','ingreso','apertura',20000.00,'APERTURA','Saldo inicial de la cuenta','confirmado',NULL,NULL,NULL,NULL,NULL,1,'2026-11-13 20:59:29',1,'2026-11-13 20:59:29',NULL,NULL,NULL),(2,2,'PEN','a0685462-37cf-2e08-6c82-76fe4cd4521d','2026-11-13','ingreso','apertura',5000.00,'APERTURA','Saldo inicial de la cuenta','confirmado',NULL,NULL,NULL,NULL,NULL,1,'2026-11-13 20:59:29',1,'2026-11-13 20:59:29',NULL,NULL,NULL),(3,2,'PEN','e97d3b0c-a301-3acd-2ff6-205c0c2d7616','2026-11-13','ingreso','cobranza',500.00,'COB002-00000001','Cobranza COB002-00000001 · OP 552190','confirmado',4,NULL,NULL,NULL,NULL,1,'2026-11-13 20:59:29',1,'2026-11-13 20:59:29',NULL,NULL,NULL),(4,2,'PEN','b0fc9ae4-dc67-e0a3-db47-479c5f3caa8f','2026-11-13','egreso','reversion',500.00,'COB002-00000001-ANUL','Anulación de cobranza COB002-00000001: Transferencia rechazada por el banco','confirmado',NULL,NULL,NULL,NULL,3,1,'2026-11-13 20:59:37',1,'2026-11-13 20:59:37',NULL,NULL,NULL),(5,2,'PEN','cb43f2f8-1213-b274-ec9f-385383dbcfb1','2026-11-13','egreso','pago_proveedor',4600.00,'PAGO-CP-2026-0015-1','Pago CP-2026-0015 a BAYER S.A. - DIVISIÓN CROP SCIENCE (transferencia)','confirmado',NULL,15,NULL,NULL,NULL,1,'2026-11-13 20:59:37',1,'2026-11-13 20:59:37',NULL,NULL,NULL),(6,2,'PEN','128c4dc8-7d6a-0d78-b2f7-69769f7a2aeb','2026-11-14','egreso','pago_proveedor',300.00,'PAGO-CP-2026-0019-1','Pago CP-2026-0019 a BAYER S.A. - DIVISIÓN CROP SCIENCE (transferencia)','confirmado',NULL,19,NULL,NULL,NULL,1,'2026-11-14 15:48:48',1,'2026-11-14 15:48:48',NULL,NULL,NULL),(7,2,'PEN','7c42a67c-8e3c-5cd6-8c7c-0d466afa9451','2026-11-14','ingreso','cobranza',200.00,'COB002-00000002','Cobranza COB002-00000002 · DEP 991204','confirmado',6,NULL,NULL,NULL,NULL,1,'2026-11-14 15:48:49',1,'2026-11-14 15:48:49',NULL,NULL,NULL);
/*!40000 ALTER TABLE `tesoreria_movimientos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tesoreria_transferencias`
--

DROP TABLE IF EXISTS `tesoreria_transferencias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tesoreria_transferencias` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `clave_operacion` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `cuenta_origen_id` bigint NOT NULL,
  `cuenta_destino_id` bigint NOT NULL,
  `moneda` char(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `importe` decimal(18,2) NOT NULL,
  `fecha` date NOT NULL,
  `referencia` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `concepto` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `estado` enum('borrador','confirmada','anulada') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'borrador',
  `creado_por` bigint NOT NULL,
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `confirmado_por` bigint DEFAULT NULL,
  `confirmado_en` datetime DEFAULT NULL,
  `anulado_por` bigint DEFAULT NULL,
  `anulado_en` datetime DEFAULT NULL,
  `motivo_anulacion` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tesoreria_transferencia_clave` (`clave_operacion`),
  UNIQUE KEY `uq_tesoreria_transferencia_importe` (`id`,`moneda`,`importe`),
  UNIQUE KEY `uq_tesoreria_transferencia_referencia` (`cuenta_origen_id`,`referencia`),
  KEY `idx_tesoreria_transferencia_fecha` (`fecha`,`estado`),
  KEY `fk_tesoreria_transferencia_origen` (`cuenta_origen_id`,`moneda`),
  KEY `fk_tesoreria_transferencia_destino` (`cuenta_destino_id`,`moneda`),
  KEY `fk_tesoreria_transferencia_autor` (`creado_por`),
  KEY `fk_tesoreria_transferencia_confirmador` (`confirmado_por`),
  KEY `fk_tesoreria_transferencia_anulador` (`anulado_por`),
  CONSTRAINT `fk_tesoreria_transferencia_anulador` FOREIGN KEY (`anulado_por`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_tesoreria_transferencia_autor` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_tesoreria_transferencia_confirmador` FOREIGN KEY (`confirmado_por`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_tesoreria_transferencia_destino` FOREIGN KEY (`cuenta_destino_id`, `moneda`) REFERENCES `tesoreria_cuentas` (`id`, `moneda`),
  CONSTRAINT `fk_tesoreria_transferencia_origen` FOREIGN KEY (`cuenta_origen_id`, `moneda`) REFERENCES `tesoreria_cuentas` (`id`, `moneda`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tesoreria_transferencias`
--

LOCK TABLES `tesoreria_transferencias` WRITE;
/*!40000 ALTER TABLE `tesoreria_transferencias` DISABLE KEYS */;
/*!40000 ALTER TABLE `tesoreria_transferencias` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tipos_documento`
--

DROP TABLE IF EXISTS `tipos_documento`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tipos_documento` (
  `id` int NOT NULL AUTO_INCREMENT,
  `codigo` varchar(4) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `nombre` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `sunat_code` varchar(4) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tipos_documento`
--

LOCK TABLES `tipos_documento` WRITE;
/*!40000 ALTER TABLE `tipos_documento` DISABLE KEYS */;
INSERT INTO `tipos_documento` VALUES (1,'RUC','Registro Único de Contribuyentes','6'),(2,'DNI','Documento Nacional de Identidad','1'),(3,'01','Factura','01'),(4,'03','Boleta de venta','03'),(5,'07','Nota de crédito','07'),(6,'09','Guía de remisión remitente','09'),(7,'08','Nota de débito','08');
/*!40000 ALTER TABLE `tipos_documento` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `unidades_medida`
--

DROP TABLE IF EXISTS `unidades_medida`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `unidades_medida` (
  `id` int NOT NULL AUTO_INCREMENT,
  `codigo` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `nombre` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `abreviatura` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `codigo_sunat` varchar(3) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `factor_base` decimal(12,4) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `unidades_medida`
--

LOCK TABLES `unidades_medida` WRITE;
/*!40000 ALTER TABLE `unidades_medida` DISABLE KEYS */;
INSERT INTO `unidades_medida` VALUES (1,'CAJ','Caja','Cj','BX',1.0000),(2,'TUB','Tubo','Tb','TU',1.0000),(3,'UND','Unidad','und','NIU',1.0000),(4,'KG','Kilogramo','kg','KGM',1.0000),(5,'LT','Litro','L','LTR',1.0000),(6,'GL','Galón','gal','GLL',1.0000),(7,'SAC','Saco','sc','BG',1.0000);
/*!40000 ALTER TABLE `unidades_medida` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuario_rol`
--

DROP TABLE IF EXISTS `usuario_rol`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuario_rol` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `usuario_id` bigint NOT NULL,
  `rol_id` int NOT NULL,
  `assigned_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `rol_id` (`rol_id`),
  CONSTRAINT `usuario_rol_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `usuario_rol_ibfk_2` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuario_rol`
--

LOCK TABLES `usuario_rol` WRITE;
/*!40000 ALTER TABLE `usuario_rol` DISABLE KEYS */;
INSERT INTO `usuario_rol` VALUES (1,1,1,'2026-10-10 18:13:03');
/*!40000 ALTER TABLE `usuario_rol` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuario_sucursal`
--

DROP TABLE IF EXISTS `usuario_sucursal`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuario_sucursal` (
  `id` int NOT NULL AUTO_INCREMENT,
  `usuario_id` bigint NOT NULL,
  `sucursal_id` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_usuario_sucursal` (`usuario_id`,`sucursal_id`),
  KEY `fk_us_sucursal` (`sucursal_id`),
  CONSTRAINT `fk_us_sucursal` FOREIGN KEY (`sucursal_id`) REFERENCES `sucursales` (`id`),
  CONSTRAINT `fk_us_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuario_sucursal`
--

LOCK TABLES `usuario_sucursal` WRITE;
/*!40000 ALTER TABLE `usuario_sucursal` DISABLE KEYS */;
/*!40000 ALTER TABLE `usuario_sucursal` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuarios` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `nombre` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `password_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `estado` tinyint NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuarios`
--

LOCK TABLES `usuarios` WRITE;
/*!40000 ALTER TABLE `usuarios` DISABLE KEYS */;
INSERT INTO `usuarios` VALUES (1,'Administrador','admin@pucchun.com','$2y$12$aCeEgMEHF7GAxYz31Xl2HOUw3oFNzG3GvW6n4C94b7CMGNCydvH2m',1,'2026-10-10 18:13:03');
/*!40000 ALTER TABLE `usuarios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `validaciones`
--

DROP TABLE IF EXISTS `validaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `validaciones` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `modulo` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `registro_id` bigint NOT NULL,
  `usuario_id` bigint NOT NULL,
  `resultado` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `observacion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `validated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `version` int unsigned NOT NULL DEFAULT '1',
  `correlation_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `ix_validaciones_registro` (`modulo`,`registro_id`,`version`,`validated_at`),
  CONSTRAINT `validaciones_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `ck_validaciones_version` CHECK ((`version` >= 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `validaciones`
--

LOCK TABLES `validaciones` WRITE;
/*!40000 ALTER TABLE `validaciones` DISABLE KEYS */;
/*!40000 ALTER TABLE `validaciones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `validaciones_detalle`
--

DROP TABLE IF EXISTS `validaciones_detalle`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `validaciones_detalle` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `validacion_id` bigint NOT NULL,
  `codigo` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `campo` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `severidad` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mensaje` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `resultado` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_validacion_regla` (`validacion_id`,`codigo`),
  CONSTRAINT `fk_validaciones_detalle` FOREIGN KEY (`validacion_id`) REFERENCES `validaciones` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ck_validacion_codigo` CHECK ((cast(`codigo` as char charset binary) in (_utf8mb4'VAL-001',_utf8mb4'VAL-002',_utf8mb4'VAL-003',_utf8mb4'VAL-004',_utf8mb4'VAL-005',_utf8mb4'VAL-006',_utf8mb4'VAL-007'))),
  CONSTRAINT `ck_validacion_resultado` CHECK ((cast(`resultado` as char charset binary) in (_utf8mb4'OK',_utf8mb4'ERROR'))),
  CONSTRAINT `ck_validacion_severidad` CHECK ((cast(`severidad` as char charset binary) in (_utf8mb4'BLOQUEANTE',_utf8mb4'ADVERTENCIA')))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `validaciones_detalle`
--

LOCK TABLES `validaciones_detalle` WRITE;
/*!40000 ALTER TABLE `validaciones_detalle` DISABLE KEYS */;
/*!40000 ALTER TABLE `validaciones_detalle` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vendedores`
--

DROP TABLE IF EXISTS `vendedores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vendedores` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `codigo` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `nombres` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `apellidos` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `email` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `estado` tinyint DEFAULT '1',
  `cargo` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `telefono` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `estado_laboral` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vendedores`
--

LOCK TABLES `vendedores` WRITE;
/*!40000 ALTER TABLE `vendedores` DISABLE KEYS */;
INSERT INTO `vendedores` VALUES (1,'VEN-0001','Carlos Alberto','Mendoza Ríos',NULL,1,'Asesor comercial',NULL,'ACTIVO'),(2,'VEN-0002','Lucía Fernanda','Paredes Soto',NULL,1,'Asesora comercial',NULL,'ACTIVO'),(3,'VEN-0003','Jorge Luis','Vásquez Huerta',NULL,1,'Jefe de ventas',NULL,'ACTIVO');
/*!40000 ALTER TABLE `vendedores` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `venta_detalles`
--

DROP TABLE IF EXISTS `venta_detalles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `venta_detalles` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `venta_id` bigint NOT NULL,
  `linea` int NOT NULL,
  `producto_id` bigint NOT NULL,
  `unidad_id` int NOT NULL,
  `codigo_producto` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `descripcion` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `cantidad` decimal(18,4) NOT NULL,
  `precio_unitario` decimal(18,4) NOT NULL,
  `descuento` decimal(18,2) NOT NULL DEFAULT '0.00',
  `subtotal` decimal(18,2) NOT NULL,
  `tasa_impuesto` decimal(7,4) NOT NULL DEFAULT '0.0000',
  `impuesto` decimal(18,2) NOT NULL DEFAULT '0.00',
  `total` decimal(18,2) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_venta_linea` (`venta_id`,`linea`),
  KEY `fk_venta_detalle_producto` (`producto_id`),
  KEY `fk_venta_detalle_unidad` (`unidad_id`),
  CONSTRAINT `fk_venta_detalle_documento` FOREIGN KEY (`venta_id`) REFERENCES `ventas` (`id`),
  CONSTRAINT `fk_venta_detalle_producto` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`),
  CONSTRAINT `fk_venta_detalle_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `unidades_medida` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `venta_detalles`
--

LOCK TABLES `venta_detalles` WRITE;
/*!40000 ALTER TABLE `venta_detalles` DISABLE KEYS */;
INSERT INTO `venta_detalles` VALUES (1,1,1,3,5,'PRD-0001','Insecticida Imidacloprid 350 SC',20.0000,95.0000,0.00,1900.00,18.0000,342.00,2242.00),(2,1,2,6,4,'PRD-0004','Fungicida Trifloxistrobina + Tebuconazol 75 WG',10.0000,289.0000,50.00,2890.00,18.0000,511.20,3351.20),(3,2,1,10,7,'PRD-0008','Fertilizante Urea 46% N',15.0000,128.0000,0.00,1920.00,18.0000,345.60,2265.60),(4,2,2,12,4,'PRD-0010','Fertilizante foliar NPK 20-20-20',30.0000,27.0000,0.00,810.00,18.0000,145.80,955.80),(5,3,1,14,5,'PRD-0012','Bioestimulante a base de extracto de algas',2.0000,65.0000,0.00,130.00,18.0000,23.40,153.40),(6,3,2,5,5,'PRD-0003','Insecticida Deltametrina 25 EC',1.0000,58.0000,0.00,58.00,18.0000,10.44,68.44),(7,4,1,3,5,'PRD-0001','Insecticida Imidacloprid 350 SC',10.0000,95.0000,0.00,950.00,18.0000,171.00,1121.00),(8,4,2,7,5,'PRD-0005','Fungicida Fluopyram + Tebuconazol 400 SC',5.0000,315.0000,0.00,1575.00,18.0000,283.50,1858.50),(9,5,1,6,4,'PRD-0004','Fungicida Trifloxistrobina + Tebuconazol 75 WG',8.0000,289.0000,100.00,2312.00,18.0000,398.16,2610.16),(10,5,2,10,7,'PRD-0008','Fertilizante Urea 46% N',20.0000,128.0000,0.00,2560.00,18.0000,460.80,3020.80),(11,6,1,7,5,'PRD-0005','Fungicida Fluopyram + Tebuconazol 400 SC',3.0000,315.0000,0.00,945.00,18.0000,170.10,1115.10),(12,6,2,11,7,'PRD-0009','Fertilizante Nitrato de Potasio',5.0000,149.0000,20.00,745.00,18.0000,130.50,855.50),(13,7,1,4,5,'PRD-0002','Insecticida Spirotetramat 150 OD',2.0000,245.0000,0.00,490.00,18.0000,88.20,578.20);
/*!40000 ALTER TABLE `venta_detalles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ventas`
--

DROP TABLE IF EXISTS `ventas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ventas` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `sucursal_id` int NOT NULL,
  `cliente_id` bigint NOT NULL,
  `vendedor_id` bigint DEFAULT NULL,
  `numero` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `fecha` date NOT NULL,
  `moneda` char(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'PEN',
  `estado` enum('borrador','confirmada','anulada') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'borrador',
  `subtotal` decimal(18,2) NOT NULL DEFAULT '0.00',
  `descuento` decimal(18,2) NOT NULL DEFAULT '0.00',
  `impuesto` decimal(18,2) NOT NULL DEFAULT '0.00',
  `total` decimal(18,2) NOT NULL DEFAULT '0.00',
  `observaciones` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `anulado_por` bigint DEFAULT NULL,
  `anulado_en` datetime DEFAULT NULL,
  `motivo_anulacion` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `creado_por` bigint NOT NULL,
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `pedido_id` bigint DEFAULT NULL,
  `condicion_pago` enum('contado','credito') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `fecha_vencimiento` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_venta_numero` (`sucursal_id`,`numero`),
  UNIQUE KEY `uq_venta_pedido` (`pedido_id`),
  KEY `idx_venta_cliente_fecha` (`cliente_id`,`fecha`),
  KEY `idx_venta_estado_fecha` (`estado`,`fecha`),
  KEY `fk_venta_vendedor` (`vendedor_id`),
  KEY `fk_venta_autor` (`creado_por`),
  KEY `fk_venta_anulador` (`anulado_por`),
  CONSTRAINT `fk_venta_anulador` FOREIGN KEY (`anulado_por`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_venta_autor` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_venta_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`),
  CONSTRAINT `fk_venta_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`),
  CONSTRAINT `fk_venta_sucursal` FOREIGN KEY (`sucursal_id`) REFERENCES `sucursales` (`id`),
  CONSTRAINT `fk_venta_vendedor` FOREIGN KEY (`vendedor_id`) REFERENCES `vendedores` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ventas`
--

LOCK TABLES `ventas` WRITE;
/*!40000 ALTER TABLE `ventas` DISABLE KEYS */;
INSERT INTO `ventas` VALUES (1,1,1,1,'VEN001-00000001','2026-11-13','PEN','confirmada',4790.00,50.00,853.20,5593.20,'Campaña de palta',NULL,NULL,NULL,1,'2026-11-13 19:11:10','2026-11-13 19:11:10',1,'contado',NULL),(2,1,2,1,'VEN001-00000002','2026-11-13','PEN','confirmada',2730.00,0.00,491.40,3221.40,'Fertilización de frutales',NULL,NULL,NULL,1,'2026-11-13 19:11:10','2026-11-13 19:11:10',2,'credito','2026-12-13'),(3,1,7,1,'VEN001-00000003','2026-11-13','PEN','confirmada',188.00,0.00,33.84,221.84,'Venta mostrador',NULL,NULL,NULL,1,'2026-11-13 19:11:10','2026-11-13 19:11:10',3,'contado',NULL),(4,2,4,2,'VEN002-00000001','2026-11-13','PEN','confirmada',2525.00,0.00,454.50,2979.50,'Control sanitario vid',NULL,NULL,NULL,1,'2026-11-13 19:11:31','2026-11-13 19:11:31',4,'contado',NULL),(5,2,5,2,'VEN002-00000002','2026-11-13','PEN','confirmada',4872.00,100.00,858.96,5630.96,'Programa fitosanitario',NULL,NULL,NULL,1,'2026-11-13 19:11:31','2026-11-13 19:11:31',5,'credito','2026-12-28'),(6,1,6,1,'VEN001-00000004','2026-11-14','PEN','confirmada',1690.00,20.00,300.60,1970.60,'Auditoría final',NULL,NULL,NULL,1,'2026-11-14 15:48:17','2026-11-14 15:48:17',8,'contado',NULL),(7,2,8,2,'VEN002-00000003','2026-11-14','PEN','confirmada',490.00,0.00,88.20,578.20,'Auditoría final',NULL,NULL,NULL,1,'2026-11-14 15:48:49','2026-11-14 15:48:49',9,'credito','2026-12-14');
/*!40000 ALTER TABLE `ventas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping events for database 'bayer_pucchun_erp_v2'
--

--
-- Dumping routines for database 'bayer_pucchun_erp_v2'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed
