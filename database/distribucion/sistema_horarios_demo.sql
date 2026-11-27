-- PROSEC. Base de demostración para importar en una base vacía.
-- Incluye esquema, migraciones y una programación ficticia.
-- Crea el acceso administrativo mediante php artisan prosec:inicializar.


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `actividades_seccion` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `seccion_id` bigint(20) unsigned NOT NULL,
  `tipo` varchar(50) NOT NULL,
  `cantidad_horas` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `cantidad_estudiantes` smallint(5) unsigned DEFAULT NULL,
  `modalidad` varchar(50) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `actividades_seccion_seccion_id_foreign` (`seccion_id`),
  CONSTRAINT `actividades_seccion_seccion_id_foreign` FOREIGN KEY (`seccion_id`) REFERENCES `secciones` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

INSERT INTO `actividades_seccion` VALUES
(1,1,'Cátedra',1,20,NULL,NULL,1,'2026-11-27 17:15:11','2026-11-27 17:15:11'),
(2,1,'Laboratorio',1,20,NULL,NULL,1,'2026-11-27 17:15:11','2026-11-27 17:15:11');
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `asignaciones_laboratorios` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `horario_id` bigint(20) unsigned NOT NULL,
  `laboratorio_id` bigint(20) unsigned NOT NULL,
  `capacidad_asignada` smallint(5) unsigned DEFAULT NULL,
  `observaciones` varchar(255) DEFAULT NULL,
  `es_principal` tinyint(1) NOT NULL DEFAULT 1,
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `horario_laboratorio_unique` (`horario_id`,`laboratorio_id`),
  KEY `asignaciones_laboratorios_laboratorio_id_foreign` (`laboratorio_id`),
  CONSTRAINT `asignaciones_laboratorios_horario_id_foreign` FOREIGN KEY (`horario_id`) REFERENCES `horarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `asignaciones_laboratorios_laboratorio_id_foreign` FOREIGN KEY (`laboratorio_id`) REFERENCES `laboratorios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

INSERT INTO `asignaciones_laboratorios` VALUES
(1,2,1,20,NULL,1,1,'2026-11-27 17:15:11','2026-11-27 17:15:11');
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `asignaciones_laboratorios_moviles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `horario_id` bigint(20) unsigned NOT NULL,
  `laboratorio_movil_id` bigint(20) unsigned NOT NULL,
  `capacidad_asignada` smallint(5) unsigned DEFAULT NULL,
  `observaciones` varchar(255) DEFAULT NULL,
  `es_principal` tinyint(1) NOT NULL DEFAULT 1,
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `horario_lab_movil_unique` (`horario_id`,`laboratorio_movil_id`),
  KEY `asignaciones_laboratorios_moviles_laboratorio_movil_id_foreign` (`laboratorio_movil_id`),
  CONSTRAINT `asignaciones_laboratorios_moviles_horario_id_foreign` FOREIGN KEY (`horario_id`) REFERENCES `horarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `asignaciones_laboratorios_moviles_laboratorio_movil_id_foreign` FOREIGN KEY (`laboratorio_movil_id`) REFERENCES `laboratorios_moviles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `asignaciones_salas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `horario_id` bigint(20) unsigned NOT NULL,
  `sala_id` bigint(20) unsigned NOT NULL,
  `capacidad_asignada` smallint(5) unsigned DEFAULT NULL,
  `observaciones` varchar(255) DEFAULT NULL,
  `es_principal` tinyint(1) NOT NULL DEFAULT 1,
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `horario_sala_unique` (`horario_id`,`sala_id`),
  KEY `asignaciones_salas_sala_id_foreign` (`sala_id`),
  CONSTRAINT `asignaciones_salas_horario_id_foreign` FOREIGN KEY (`horario_id`) REFERENCES `horarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `asignaciones_salas_sala_id_foreign` FOREIGN KEY (`sala_id`) REFERENCES `salas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

INSERT INTO `asignaciones_salas` VALUES
(1,1,1,20,NULL,1,1,'2026-11-27 17:15:11','2026-11-27 17:15:11');
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `asignaturas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `universidad_id` bigint(20) unsigned NOT NULL,
  `codigo` varchar(30) NOT NULL,
  `nombre` varchar(200) NOT NULL,
  `creditos` tinyint(3) unsigned DEFAULT NULL,
  `horas_catedra` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `horas_laboratorio` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `horas_ayudantia` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `horas_taller` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `horas_clinica` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `horas_practica` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `horas_otros` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `modalidad` varchar(50) DEFAULT NULL,
  `categoria` varchar(50) DEFAULT NULL,
  `tipo_dictacion` varchar(50) DEFAULT NULL,
  `es_libre_eleccion` tinyint(1) NOT NULL DEFAULT 0,
  `ramo_terminal` tinyint(1) NOT NULL DEFAULT 0,
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `asignaturas_universidad_id_codigo_unique` (`universidad_id`,`codigo`),
  CONSTRAINT `asignaturas_universidad_id_foreign` FOREIGN KEY (`universidad_id`) REFERENCES `universidades` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

INSERT INTO `asignaturas` VALUES
(1,1,'DEMO-101','Desarrollo de aplicaciones',NULL,1,1,0,0,0,0,0,NULL,NULL,NULL,0,0,1,'2026-11-27 17:15:11','2026-11-27 17:15:11');
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `asignaturas_docentes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `asignatura_id` bigint(20) unsigned NOT NULL,
  `profesor_id` bigint(20) unsigned NOT NULL,
  `periodo_academico_id` bigint(20) unsigned NOT NULL,
  `tipo_participacion` varchar(50) NOT NULL DEFAULT 'Profesor responsable',
  `es_principal` tinyint(1) NOT NULL DEFAULT 0,
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `asig_prof_periodo_unique` (`asignatura_id`,`profesor_id`,`periodo_academico_id`),
  KEY `asignaturas_docentes_profesor_id_foreign` (`profesor_id`),
  KEY `asignaturas_docentes_periodo_academico_id_foreign` (`periodo_academico_id`),
  CONSTRAINT `asignaturas_docentes_asignatura_id_foreign` FOREIGN KEY (`asignatura_id`) REFERENCES `asignaturas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `asignaturas_docentes_periodo_academico_id_foreign` FOREIGN KEY (`periodo_academico_id`) REFERENCES `periodos_academicos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `asignaturas_docentes_profesor_id_foreign` FOREIGN KEY (`profesor_id`) REFERENCES `profesores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `bloques_horarios` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `numero` tinyint(3) unsigned NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL,
  `es_almuerzo` tinyint(1) NOT NULL DEFAULT 0,
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

INSERT INTO `bloques_horarios` VALUES
(1,1,'08:10:00','09:00:00',0,1,NULL,NULL),
(2,2,'09:10:00','10:00:00',0,1,NULL,NULL),
(3,3,'10:10:00','11:00:00',0,1,NULL,NULL),
(4,4,'11:10:00','12:00:00',0,1,NULL,NULL),
(5,5,'12:10:00','13:00:00',0,1,NULL,NULL),
(6,6,'13:10:00','14:00:00',1,1,NULL,NULL),
(7,7,'14:10:00','15:00:00',0,1,NULL,NULL),
(8,8,'15:10:00','16:00:00',0,1,NULL,NULL),
(9,9,'16:10:00','17:00:00',0,1,NULL,NULL),
(10,10,'17:10:00','18:00:00',0,1,NULL,NULL),
(11,11,'18:10:00','19:00:00',0,1,NULL,NULL),
(12,12,'19:10:00','20:00:00',0,1,NULL,NULL),
(13,13,'20:10:00','21:00:00',0,1,NULL,NULL),
(14,14,'21:10:00','22:00:00',0,1,NULL,NULL),
(15,15,'22:10:00','23:00:00',0,1,NULL,NULL);
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cambios_importacion_profesores` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `importacion_profesores_id` bigint(20) unsigned NOT NULL,
  `profesor_id` bigint(20) unsigned DEFAULT NULL,
  `tipo_cambio` varchar(50) NOT NULL,
  `campo` varchar(100) DEFAULT NULL,
  `valor_anterior` text DEFAULT NULL,
  `valor_nuevo` text DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `estado` varchar(30) NOT NULL DEFAULT 'Pendiente',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cambios_importacion_profesores_importacion_profesores_id_foreign` (`importacion_profesores_id`),
  KEY `cambios_importacion_profesores_profesor_id_foreign` (`profesor_id`),
  CONSTRAINT `cambios_importacion_profesores_importacion_profesores_id_foreign` FOREIGN KEY (`importacion_profesores_id`) REFERENCES `importaciones_profesores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cambios_importacion_profesores_profesor_id_foreign` FOREIGN KEY (`profesor_id`) REFERENCES `profesores` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `candidatos_reemplazo` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `asignatura_id` bigint(20) unsigned NOT NULL,
  `profesor_id` bigint(20) unsigned NOT NULL,
  `periodo_academico_id` bigint(20) unsigned NOT NULL,
  `motivo` varchar(100) DEFAULT NULL,
  `nivel_compatibilidad` tinyint(3) unsigned DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `estado` varchar(30) NOT NULL DEFAULT 'Pendiente',
  `estado_activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `candidato_asig_periodo_unique` (`asignatura_id`,`profesor_id`,`periodo_academico_id`),
  KEY `candidatos_reemplazo_profesor_id_foreign` (`profesor_id`),
  KEY `candidatos_reemplazo_periodo_academico_id_foreign` (`periodo_academico_id`),
  CONSTRAINT `candidatos_reemplazo_asignatura_id_foreign` FOREIGN KEY (`asignatura_id`) REFERENCES `asignaturas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `candidatos_reemplazo_periodo_academico_id_foreign` FOREIGN KEY (`periodo_academico_id`) REFERENCES `periodos_academicos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `candidatos_reemplazo_profesor_id_foreign` FOREIGN KEY (`profesor_id`) REFERENCES `profesores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `carreras` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `universidad_id` bigint(20) unsigned NOT NULL,
  `facultad_instituto_id` bigint(20) unsigned DEFAULT NULL,
  `sede_id` bigint(20) unsigned NOT NULL,
  `codigo` varchar(30) DEFAULT NULL,
  `nombre` varchar(150) NOT NULL,
  `regimen` varchar(50) DEFAULT NULL,
  `tipo_estudio` varchar(100) DEFAULT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `carreras_universidad_id_foreign` (`universidad_id`),
  KEY `carreras_facultad_instituto_id_foreign` (`facultad_instituto_id`),
  KEY `carreras_sede_id_foreign` (`sede_id`),
  CONSTRAINT `carreras_facultad_instituto_id_foreign` FOREIGN KEY (`facultad_instituto_id`) REFERENCES `facultades_institutos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `carreras_sede_id_foreign` FOREIGN KEY (`sede_id`) REFERENCES `sedes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `carreras_universidad_id_foreign` FOREIGN KEY (`universidad_id`) REFERENCES `universidades` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

INSERT INTO `carreras` VALUES
(1,1,NULL,1,'DEMO-INF','Informática de demostración',NULL,NULL,1,'2026-11-27 17:15:11','2026-11-27 17:15:11');
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `carreras_sedes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `carrera_id` bigint(20) unsigned NOT NULL,
  `sede_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `carrera_sede_unique` (`carrera_id`,`sede_id`),
  KEY `carreras_sedes_sede_id_foreign` (`sede_id`),
  CONSTRAINT `carreras_sedes_carrera_id_foreign` FOREIGN KEY (`carrera_id`) REFERENCES `carreras` (`id`) ON DELETE CASCADE,
  CONSTRAINT `carreras_sedes_sede_id_foreign` FOREIGN KEY (`sede_id`) REFERENCES `sedes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cierres_programacion` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `version_planificacion_id` bigint(20) unsigned NOT NULL,
  `etapa` varchar(20) NOT NULL,
  `usuario_id` bigint(20) unsigned DEFAULT NULL,
  `firma` char(64) NOT NULL,
  `datos` longtext NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cierres_programacion_version_planificacion_id_etapa_unique` (`version_planificacion_id`,`etapa`),
  KEY `cierres_programacion_usuario_id_foreign` (`usuario_id`),
  CONSTRAINT `cierres_programacion_usuario_id_foreign` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cierres_programacion_version_planificacion_id_foreign` FOREIGN KEY (`version_planificacion_id`) REFERENCES `versiones_planificacion` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `configuracion_institucion` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `universidad_id` bigint(20) unsigned NOT NULL,
  `nombre_sistema` varchar(150) NOT NULL DEFAULT 'PROSEC',
  `nombre_institucion` varchar(150) DEFAULT NULL,
  `sigla` varchar(30) DEFAULT NULL,
  `color_principal` varchar(20) DEFAULT NULL,
  `color_secundario` varchar(20) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `identidad_activa` tinyint(1) NOT NULL DEFAULT 0,
  `lema_sistema` varchar(150) DEFAULT NULL,
  `color_fondo` varchar(20) DEFAULT NULL,
  `logo_compacto` varchar(255) DEFAULT NULL,
  `logo_institucion` varchar(255) DEFAULT NULL,
  `favicon` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `configuracion_institucion_universidad_id_foreign` (`universidad_id`),
  KEY `configuracion_institucion_identidad_activa_index` (`identidad_activa`),
  CONSTRAINT `configuracion_institucion_universidad_id_foreign` FOREIGN KEY (`universidad_id`) REFERENCES `universidades` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `confirmaciones_grupo_programacion` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `version_planificacion_id` bigint(20) unsigned NOT NULL,
  `grupo_seccion_id` bigint(20) unsigned NOT NULL,
  `profesor_id` bigint(20) unsigned NOT NULL,
  `usuario_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `confirmacion_grupo_version_unique` (`version_planificacion_id`,`grupo_seccion_id`),
  KEY `conf_grupo_grupo_fk` (`grupo_seccion_id`),
  KEY `conf_grupo_profesor_fk` (`profesor_id`),
  KEY `conf_grupo_usuario_fk` (`usuario_id`),
  CONSTRAINT `conf_grupo_grupo_fk` FOREIGN KEY (`grupo_seccion_id`) REFERENCES `grupos_seccion` (`id`) ON DELETE CASCADE,
  CONSTRAINT `conf_grupo_profesor_fk` FOREIGN KEY (`profesor_id`) REFERENCES `profesores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `conf_grupo_usuario_fk` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `conf_grupo_version_fk` FOREIGN KEY (`version_planificacion_id`) REFERENCES `versiones_planificacion` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `dias` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(20) NOT NULL,
  `abreviatura` varchar(5) NOT NULL,
  `numero` tinyint(3) unsigned NOT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

INSERT INTO `dias` VALUES
(1,'Lunes','LU',1,1,NULL,NULL),
(2,'Martes','MA',2,1,NULL,NULL),
(3,'Miércoles','MI',3,1,NULL,NULL),
(4,'Jueves','JU',4,1,NULL,NULL),
(5,'Viernes','VI',5,1,NULL,NULL),
(6,'Sábado','SA',6,1,NULL,NULL);
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `disponibilidad_profesores` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `profesor_id` bigint(20) unsigned NOT NULL,
  `periodo_academico_id` bigint(20) unsigned NOT NULL,
  `dia_id` bigint(20) unsigned NOT NULL,
  `bloque_horario_id` bigint(20) unsigned NOT NULL,
  `tipo_disponibilidad` varchar(30) NOT NULL DEFAULT 'Disponible',
  `observaciones` text DEFAULT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `disp_profesor_horario_unique` (`profesor_id`,`periodo_academico_id`,`dia_id`,`bloque_horario_id`),
  KEY `disponibilidad_profesores_periodo_academico_id_foreign` (`periodo_academico_id`),
  KEY `disponibilidad_profesores_dia_id_foreign` (`dia_id`),
  KEY `disponibilidad_profesores_bloque_horario_id_foreign` (`bloque_horario_id`),
  CONSTRAINT `disponibilidad_profesores_bloque_horario_id_foreign` FOREIGN KEY (`bloque_horario_id`) REFERENCES `bloques_horarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `disponibilidad_profesores_dia_id_foreign` FOREIGN KEY (`dia_id`) REFERENCES `dias` (`id`) ON DELETE CASCADE,
  CONSTRAINT `disponibilidad_profesores_periodo_academico_id_foreign` FOREIGN KEY (`periodo_academico_id`) REFERENCES `periodos_academicos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `disponibilidad_profesores_profesor_id_foreign` FOREIGN KEY (`profesor_id`) REFERENCES `profesores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `envios_accesos_docentes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `profesor_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `periodo_academico_id` bigint(20) unsigned NOT NULL,
  `solicitud_disponibilidad_id` bigint(20) unsigned DEFAULT NULL,
  `enviado_por` bigint(20) unsigned DEFAULT NULL,
  `lote` char(36) NOT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'pendiente',
  `correo` varchar(255) DEFAULT NULL,
  `enviado_en` timestamp NULL DEFAULT NULL,
  `error` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `envios_accesos_docentes_lote_profesor_id_unique` (`lote`,`profesor_id`),
  KEY `envios_accesos_docentes_profesor_id_foreign` (`profesor_id`),
  KEY `envios_accesos_docentes_user_id_foreign` (`user_id`),
  KEY `envios_accesos_docentes_solicitud_disponibilidad_id_foreign` (`solicitud_disponibilidad_id`),
  KEY `envios_accesos_docentes_enviado_por_foreign` (`enviado_por`),
  KEY `envios_accesos_docentes_periodo_academico_id_profesor_id_index` (`periodo_academico_id`,`profesor_id`),
  CONSTRAINT `envios_accesos_docentes_enviado_por_foreign` FOREIGN KEY (`enviado_por`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `envios_accesos_docentes_periodo_academico_id_foreign` FOREIGN KEY (`periodo_academico_id`) REFERENCES `periodos_academicos` (`id`),
  CONSTRAINT `envios_accesos_docentes_profesor_id_foreign` FOREIGN KEY (`profesor_id`) REFERENCES `profesores` (`id`) ON DELETE SET NULL,
  CONSTRAINT `envios_accesos_docentes_solicitud_disponibilidad_id_foreign` FOREIGN KEY (`solicitud_disponibilidad_id`) REFERENCES `solicitudes_disponibilidad` (`id`) ON DELETE SET NULL,
  CONSTRAINT `envios_accesos_docentes_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `equipamientos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `universidad_id` bigint(20) unsigned NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `tipo` varchar(100) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `cantidad` smallint(5) unsigned NOT NULL DEFAULT 1,
  `disponible` tinyint(1) NOT NULL DEFAULT 1,
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `equipamientos_universidad_id_foreign` (`universidad_id`),
  CONSTRAINT `equipamientos_universidad_id_foreign` FOREIGN KEY (`universidad_id`) REFERENCES `universidades` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `equipamientos_espacios` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `equipamiento_id` bigint(20) unsigned NOT NULL,
  `sala_id` bigint(20) unsigned DEFAULT NULL,
  `laboratorio_movil_id` bigint(20) unsigned DEFAULT NULL,
  `cantidad` smallint(5) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `equip_espacio_sala_unique` (`equipamiento_id`,`sala_id`),
  UNIQUE KEY `equip_espacio_movil_unique` (`equipamiento_id`,`laboratorio_movil_id`),
  KEY `equipamientos_espacios_sala_id_foreign` (`sala_id`),
  KEY `equipamientos_espacios_laboratorio_movil_id_foreign` (`laboratorio_movil_id`),
  CONSTRAINT `equipamientos_espacios_equipamiento_id_foreign` FOREIGN KEY (`equipamiento_id`) REFERENCES `equipamientos` (`id`),
  CONSTRAINT `equipamientos_espacios_laboratorio_movil_id_foreign` FOREIGN KEY (`laboratorio_movil_id`) REFERENCES `laboratorios_moviles` (`id`),
  CONSTRAINT `equipamientos_espacios_sala_id_foreign` FOREIGN KEY (`sala_id`) REFERENCES `salas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `facultades_institutos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `universidad_id` bigint(20) unsigned NOT NULL,
  `codigo` varchar(20) DEFAULT NULL,
  `nombre` varchar(150) NOT NULL,
  `tipo` varchar(30) NOT NULL DEFAULT 'Instituto',
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `facultades_institutos_universidad_id_foreign` (`universidad_id`),
  CONSTRAINT `facultades_institutos_universidad_id_foreign` FOREIGN KEY (`universidad_id`) REFERENCES `universidades` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `grupos_profesores` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `grupo_seccion_id` bigint(20) unsigned NOT NULL,
  `profesor_id` bigint(20) unsigned NOT NULL,
  `tipo_participacion` varchar(50) NOT NULL DEFAULT 'PROFESOR RESPONSABLE',
  `es_principal` tinyint(1) NOT NULL DEFAULT 0,
  `observaciones` text DEFAULT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `grupo_profesor_unique` (`grupo_seccion_id`,`profesor_id`),
  KEY `grupos_profesores_profesor_id_foreign` (`profesor_id`),
  CONSTRAINT `grupos_profesores_grupo_seccion_id_foreign` FOREIGN KEY (`grupo_seccion_id`) REFERENCES `grupos_seccion` (`id`) ON DELETE CASCADE,
  CONSTRAINT `grupos_profesores_profesor_id_foreign` FOREIGN KEY (`profesor_id`) REFERENCES `profesores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

INSERT INTO `grupos_profesores` VALUES
(1,1,1,'PROFESOR RESPONSABLE',1,NULL,1,'2026-11-27 17:15:11','2026-11-27 17:15:11'),
(2,2,1,'PROFESOR RESPONSABLE',1,NULL,1,'2026-11-27 17:15:11','2026-11-27 17:15:11');
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `grupos_seccion` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(20) NOT NULL,
  `nombre` varchar(255) DEFAULT NULL,
  `numero` int(10) unsigned DEFAULT NULL,
  `cupos` int(10) unsigned DEFAULT NULL,
  `inscritos` int(10) unsigned NOT NULL DEFAULT 0,
  `estado` varchar(255) NOT NULL DEFAULT 'Activo',
  `estado_activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `actividad_seccion_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `grupo_actividad_unique` (`actividad_seccion_id`,`codigo`),
  CONSTRAINT `grupos_seccion_actividad_seccion_id_foreign` FOREIGN KEY (`actividad_seccion_id`) REFERENCES `actividades_seccion` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

INSERT INTO `grupos_seccion` VALUES
(1,'1',NULL,1,24,20,'Activo',1,'2026-11-27 17:15:11','2026-11-27 17:15:11',1),
(2,'1',NULL,1,24,20,'Activo',1,'2026-11-27 17:15:11','2026-11-27 17:15:11',2);
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `historial_acciones` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` bigint(20) unsigned DEFAULT NULL,
  `accion` varchar(100) NOT NULL,
  `modulo` varchar(100) DEFAULT NULL,
  `tabla_afectada` varchar(100) DEFAULT NULL,
  `registro_id` bigint(20) unsigned DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `datos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`datos`)),
  `direccion_ip` varchar(45) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `historial_acciones_usuario_id_foreign` (`usuario_id`),
  CONSTRAINT `historial_acciones_usuario_id_foreign` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `horarios` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `actividad_seccion_id` bigint(20) unsigned NOT NULL,
  `grupo_seccion_id` bigint(20) unsigned DEFAULT NULL,
  `version_planificacion_id` bigint(20) unsigned NOT NULL,
  `dia_id` bigint(20) unsigned NOT NULL,
  `bloque_horario_id` bigint(20) unsigned NOT NULL,
  `observaciones` varchar(255) DEFAULT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `horario_actividad_unique` (`actividad_seccion_id`,`grupo_seccion_id`,`version_planificacion_id`,`dia_id`,`bloque_horario_id`),
  KEY `horarios_version_planificacion_id_foreign` (`version_planificacion_id`),
  KEY `horarios_dia_id_foreign` (`dia_id`),
  KEY `horarios_bloque_horario_id_foreign` (`bloque_horario_id`),
  KEY `horarios_grupo_seccion_id_foreign` (`grupo_seccion_id`),
  KEY `horarios_actividad_seccion_id_index` (`actividad_seccion_id`),
  CONSTRAINT `horarios_actividad_seccion_id_foreign` FOREIGN KEY (`actividad_seccion_id`) REFERENCES `actividades_seccion` (`id`) ON DELETE CASCADE,
  CONSTRAINT `horarios_bloque_horario_id_foreign` FOREIGN KEY (`bloque_horario_id`) REFERENCES `bloques_horarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `horarios_dia_id_foreign` FOREIGN KEY (`dia_id`) REFERENCES `dias` (`id`) ON DELETE CASCADE,
  CONSTRAINT `horarios_grupo_seccion_id_foreign` FOREIGN KEY (`grupo_seccion_id`) REFERENCES `grupos_seccion` (`id`) ON DELETE SET NULL,
  CONSTRAINT `horarios_version_planificacion_id_foreign` FOREIGN KEY (`version_planificacion_id`) REFERENCES `versiones_planificacion` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

INSERT INTO `horarios` VALUES
(1,1,1,1,1,1,NULL,1,'2026-11-27 17:15:11','2026-11-27 17:15:11'),
(2,2,2,1,1,2,NULL,1,'2026-11-27 17:15:11','2026-11-27 17:15:11');
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `horarios_profesores` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `horario_id` bigint(20) unsigned NOT NULL,
  `profesor_id` bigint(20) unsigned NOT NULL,
  `tipo_participacion` varchar(50) DEFAULT NULL,
  `es_principal` tinyint(1) NOT NULL DEFAULT 0,
  `observaciones` text DEFAULT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `horario_profesor_unique` (`horario_id`,`profesor_id`),
  KEY `horarios_profesores_profesor_id_foreign` (`profesor_id`),
  CONSTRAINT `horarios_profesores_horario_id_foreign` FOREIGN KEY (`horario_id`) REFERENCES `horarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `horarios_profesores_profesor_id_foreign` FOREIGN KEY (`profesor_id`) REFERENCES `profesores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `importaciones_profesores` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` bigint(20) unsigned DEFAULT NULL,
  `nombre_archivo` varchar(255) NOT NULL,
  `ruta_archivo` varchar(500) DEFAULT NULL,
  `total_registros` int(10) unsigned NOT NULL DEFAULT 0,
  `registros_nuevos` int(10) unsigned NOT NULL DEFAULT 0,
  `registros_actualizados` int(10) unsigned NOT NULL DEFAULT 0,
  `registros_sin_cambios` int(10) unsigned NOT NULL DEFAULT 0,
  `registros_con_error` int(10) unsigned NOT NULL DEFAULT 0,
  `estado` varchar(30) NOT NULL DEFAULT 'Pendiente',
  `observaciones` text DEFAULT NULL,
  `fecha_importacion` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `importaciones_profesores_usuario_id_foreign` (`usuario_id`),
  CONSTRAINT `importaciones_profesores_usuario_id_foreign` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `laboratorios` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sala_id` bigint(20) unsigned NOT NULL,
  `codigo` varchar(30) DEFAULT NULL,
  `nombre` varchar(100) NOT NULL,
  `capacidad` smallint(5) unsigned NOT NULL DEFAULT 0,
  `descripcion` text DEFAULT NULL,
  `disponible` tinyint(1) NOT NULL DEFAULT 1,
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `laboratorios_sala_id_foreign` (`sala_id`),
  CONSTRAINT `laboratorios_sala_id_foreign` FOREIGN KEY (`sala_id`) REFERENCES `salas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

INSERT INTO `laboratorios` VALUES
(1,2,'DEMO-L1','Laboratorio de demostración',24,NULL,1,1,'2026-11-27 17:15:11','2026-11-27 17:15:11');
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `laboratorios_moviles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `universidad_id` bigint(20) unsigned NOT NULL,
  `sede_id` bigint(20) unsigned NOT NULL,
  `codigo` varchar(30) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `capacidad` smallint(5) unsigned NOT NULL DEFAULT 0,
  `descripcion` text DEFAULT NULL,
  `disponible` tinyint(1) NOT NULL DEFAULT 1,
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `laboratorios_moviles_sede_id_codigo_unique` (`sede_id`,`codigo`),
  KEY `laboratorios_moviles_universidad_id_foreign` (`universidad_id`),
  CONSTRAINT `laboratorios_moviles_sede_id_foreign` FOREIGN KEY (`sede_id`) REFERENCES `sedes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `laboratorios_moviles_universidad_id_foreign` FOREIGN KEY (`universidad_id`) REFERENCES `universidades` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=68 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

INSERT INTO `migrations` VALUES
(1,'0001_01_01_000000_create_users_table',1),
(2,'0001_01_01_000001_create_cache_table',1),
(3,'0001_01_01_000002_create_jobs_table',1),
(4,'2026_09_01_110247_add_rut_to_users_table',1),
(5,'2026_09_02_130405_create_dias_table',1),
(6,'2026_09_02_130722_create_bloques_horarios_table',1),
(7,'2026_09_02_130834_create_universidades_table',1),
(8,'2026_09_02_130928_create_facultades_institutos_table',1),
(9,'2026_09_02_131017_create_sedes_table',1),
(10,'2026_09_02_131111_create_carreras_table',1),
(11,'2026_09_02_131239_create_planes_estudio_table',1),
(12,'2026_09_02_131317_create_niveles_carrera_table',1),
(13,'2026_09_02_131358_create_periodos_academicos_table',1),
(14,'2026_09_02_131532_create_asignaturas_table',1),
(15,'2026_09_02_131614_create_secciones_table',1),
(16,'2026_09_02_131657_create_profesores_table',1),
(17,'2026_09_02_131737_create_tipos_sala_table',1),
(18,'2026_09_02_131859_create_salas_table',1),
(19,'2026_09_02_131954_create_laboratorios_table',1),
(20,'2026_09_02_132026_create_laboratorios_moviles_table',1),
(21,'2026_09_02_132131_create_equipamientos_table',1),
(22,'2026_09_02_132313_create_prioridades_recursos_table',1),
(23,'2026_09_02_132536_create_solicitudes_disponibilidad_table',1),
(24,'2026_09_02_132945_create_respuestas_disponibilidad_table',1),
(25,'2026_09_02_133135_create_disponibilidad_profesores_table',1),
(26,'2026_09_02_133539_create_actividades_seccion_table',1),
(27,'2026_09_02_133708_create_requerimientos_actividad_table',1),
(28,'2026_09_02_133829_create_asignaturas_docentes_table',1),
(29,'2026_09_02_133905_create_secciones_profesores_table',1),
(30,'2026_09_02_134015_create_versiones_planificacion_table',1),
(31,'2026_09_02_134124_create_horarios_table',1),
(32,'2026_09_02_134156_create_asignaciones_salas_table',1),
(33,'2026_09_02_134304_create_asignaciones_laboratorios_table',1),
(34,'2026_09_02_134337_create_asignaciones_laboratorios_moviles_table',1),
(35,'2026_09_02_134417_create_candidatos_reemplazo_table',1),
(36,'2026_09_02_134540_create_validaciones_horario_table',1),
(37,'2026_09_02_134622_create_observaciones_validacion_table',1),
(38,'2026_09_02_134650_create_solicitudes_cambio_horario_table',1),
(39,'2026_09_02_134735_create_opciones_cambio_horario_table',1),
(40,'2026_09_02_134903_create_importaciones_profesores_table',1),
(41,'2026_09_02_134938_create_cambios_importacion_profesores_table',1),
(42,'2026_09_02_135005_create_historial_acciones_table',1),
(43,'2026_09_02_135035_create_configuracion_institucion_table',1),
(44,'2026_09_02_135141_create_temas_visuales_table',1),
(45,'2026_09_08_101815_reorganiza_relacion_de_secciones_y_carreras',1),
(46,'2026_09_08_104106_crea_grupos_seccion',1),
(47,'2026_09_08_104907_ajusta_grupos_y_horarios',1),
(48,'2026_09_08_121405_corrige_relacion_grupos_y_horarios',1),
(49,'2026_09_09_092733_create_horarios_profesores_table',1),
(50,'2026_09_09_120743_add_semestre_to_secciones_table',1),
(51,'2026_09_09_130225_create_carreras_sedes_table',1),
(52,'2026_09_21_123659_add_tipo_to_versiones_planificacion_table',1),
(53,'2026_09_22_105828_create_profesores_versiones_table',1),
(54,'2026_09_22_133207_create_permission_tables',1),
(55,'2026_09_25_110803_create_grupo_profesors_table',1),
(56,'2026_10_20_120000_relaciona_grupos_con_actividades',1),
(57,'2026_10_30_120000_vincula_equipamiento_con_espacios_y_requerimientos',1),
(58,'2026_11_04_120000_vincula_prioridades_con_asignaturas',1),
(59,'2026_11_14_120000_completa_respuestas_disponibilidad',1),
(60,'2026_11_14_180000_create_cierres_programacion_table',1),
(61,'2026_11_14_200000_completa_solicitudes_cambio_horario',1),
(62,'2026_11_15_180000_separa_permiso_importacion',1),
(63,'2026_11_15_200000_personaliza_identidad_visual',1),
(64,'2026_11_16_120000_cuentas_y_envios_docentes',1),
(65,'2026_11_16_190000_crea_recordatorios_disponibilidad',1),
(66,'2026_11_16_220000_unifica_perfil_docente',1),
(67,'2026_11_26_210000_create_confirmaciones_grupo_programacion',1);
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_permissions` (
  `permission_id` bigint(20) unsigned NOT NULL,
  `model_type` varchar(255) NOT NULL,
  `model_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_roles` (
  `role_id` bigint(20) unsigned NOT NULL,
  `model_type` varchar(255) NOT NULL,
  `model_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `niveles_carrera` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `carrera_id` bigint(20) unsigned NOT NULL,
  `numero` tinyint(3) unsigned NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `niveles_carrera_carrera_id_foreign` (`carrera_id`),
  CONSTRAINT `niveles_carrera_carrera_id_foreign` FOREIGN KEY (`carrera_id`) REFERENCES `carreras` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `observaciones_validacion` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `validacion_horario_id` bigint(20) unsigned NOT NULL,
  `horario_id` bigint(20) unsigned DEFAULT NULL,
  `tipo` varchar(100) NOT NULL,
  `nivel` varchar(30) NOT NULL DEFAULT 'Advertencia',
  `mensaje` text NOT NULL,
  `resuelta` tinyint(1) NOT NULL DEFAULT 0,
  `fecha_resolucion` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `observaciones_validacion_validacion_horario_id_foreign` (`validacion_horario_id`),
  KEY `observaciones_validacion_horario_id_foreign` (`horario_id`),
  CONSTRAINT `observaciones_validacion_horario_id_foreign` FOREIGN KEY (`horario_id`) REFERENCES `horarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `observaciones_validacion_validacion_horario_id_foreign` FOREIGN KEY (`validacion_horario_id`) REFERENCES `validaciones_horario` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `opciones_cambio_horario` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `solicitud_cambio_horario_id` bigint(20) unsigned NOT NULL,
  `dia_id` bigint(20) unsigned NOT NULL,
  `bloque_horario_id` bigint(20) unsigned NOT NULL,
  `prioridad` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `observaciones` text DEFAULT NULL,
  `estado` varchar(30) NOT NULL DEFAULT 'Propuesta',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `datos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`datos`)),
  PRIMARY KEY (`id`),
  KEY `opciones_cambio_horario_solicitud_cambio_horario_id_foreign` (`solicitud_cambio_horario_id`),
  KEY `opciones_cambio_horario_dia_id_foreign` (`dia_id`),
  KEY `opciones_cambio_horario_bloque_horario_id_foreign` (`bloque_horario_id`),
  CONSTRAINT `opciones_cambio_horario_bloque_horario_id_foreign` FOREIGN KEY (`bloque_horario_id`) REFERENCES `bloques_horarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `opciones_cambio_horario_dia_id_foreign` FOREIGN KEY (`dia_id`) REFERENCES `dias` (`id`) ON DELETE CASCADE,
  CONSTRAINT `opciones_cambio_horario_solicitud_cambio_horario_id_foreign` FOREIGN KEY (`solicitud_cambio_horario_id`) REFERENCES `solicitudes_cambio_horario` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `periodos_academicos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `universidad_id` bigint(20) unsigned NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `anio` smallint(5) unsigned NOT NULL,
  `numero_semestre` tinyint(3) unsigned NOT NULL,
  `fecha_inicio` date DEFAULT NULL,
  `fecha_fin` date DEFAULT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `periodos_academicos_universidad_id_foreign` (`universidad_id`),
  CONSTRAINT `periodos_academicos_universidad_id_foreign` FOREIGN KEY (`universidad_id`) REFERENCES `universidades` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

INSERT INTO `periodos_academicos` VALUES
(1,1,'2026-2 · Demostración',2026,2,NULL,NULL,1,'2026-11-27 17:15:11','2026-11-27 17:15:11');
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `guard_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `planes_estudio` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `carrera_id` bigint(20) unsigned NOT NULL,
  `codigo` varchar(30) DEFAULT NULL,
  `nombre` varchar(150) DEFAULT NULL,
  `version` varchar(30) DEFAULT NULL,
  `anio_inicio` smallint(5) unsigned DEFAULT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `planes_estudio_carrera_id_foreign` (`carrera_id`),
  CONSTRAINT `planes_estudio_carrera_id_foreign` FOREIGN KEY (`carrera_id`) REFERENCES `carreras` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `prioridades_recursos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `nivel` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `asignatura_id` bigint(20) unsigned DEFAULT NULL,
  `tipo_recurso` varchar(20) DEFAULT NULL,
  `tipo_sala_id` bigint(20) unsigned DEFAULT NULL,
  `tipo_equipamiento` varchar(100) DEFAULT NULL,
  `clave_recurso` varchar(191) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `prioridad_asignatura_recurso_unique` (`asignatura_id`,`clave_recurso`),
  KEY `prioridades_recursos_tipo_sala_id_foreign` (`tipo_sala_id`),
  CONSTRAINT `prioridades_recursos_asignatura_id_foreign` FOREIGN KEY (`asignatura_id`) REFERENCES `asignaturas` (`id`),
  CONSTRAINT `prioridades_recursos_tipo_sala_id_foreign` FOREIGN KEY (`tipo_sala_id`) REFERENCES `tipos_sala` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `profesores` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `universidad_id` bigint(20) unsigned NOT NULL,
  `rut` varchar(12) DEFAULT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `correo_institucional` varchar(150) DEFAULT NULL,
  `correo_personal` varchar(150) DEFAULT NULL,
  `tipo_contrato` varchar(50) DEFAULT NULL,
  `tipo_profesor` varchar(50) DEFAULT NULL,
  `unidad_academica` varchar(150) DEFAULT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `profesores_universidad_id_foreign` (`universidad_id`),
  CONSTRAINT `profesores_universidad_id_foreign` FOREIGN KEY (`universidad_id`) REFERENCES `universidades` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

INSERT INTO `profesores` VALUES
(1,1,NULL,'Ana','Docente de demostración',NULL,NULL,NULL,NULL,NULL,1,'2026-11-27 17:15:11','2026-11-27 17:15:11');
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `profesores_versiones` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `version_planificacion_id` bigint(20) unsigned NOT NULL,
  `profesor_id` bigint(20) unsigned NOT NULL,
  `rut` varchar(255) DEFAULT NULL,
  `nombre` varchar(255) DEFAULT NULL,
  `apellido` varchar(255) DEFAULT NULL,
  `correo_institucional` varchar(255) DEFAULT NULL,
  `correo_personal` varchar(255) DEFAULT NULL,
  `tipo_contrato` varchar(255) DEFAULT NULL,
  `tipo_profesor` varchar(255) DEFAULT NULL,
  `unidad_academica` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `profesor_version_unique` (`version_planificacion_id`,`profesor_id`),
  KEY `profesores_versiones_profesor_id_foreign` (`profesor_id`),
  CONSTRAINT `profesores_versiones_profesor_id_foreign` FOREIGN KEY (`profesor_id`) REFERENCES `profesores` (`id`),
  CONSTRAINT `profesores_versiones_version_planificacion_id_foreign` FOREIGN KEY (`version_planificacion_id`) REFERENCES `versiones_planificacion` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `recordatorios_disponibilidad` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `solicitud_disponibilidad_id` bigint(20) unsigned NOT NULL,
  `envio_acceso_docente_id` bigint(20) unsigned DEFAULT NULL,
  `dias_antes` tinyint(3) unsigned NOT NULL,
  `enviar_el` date NOT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'pendiente',
  `enviado_en` timestamp NULL DEFAULT NULL,
  `error` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `recordatorios_solicitud_hito_unique` (`solicitud_disponibilidad_id`,`dias_antes`),
  KEY `recordatorios_envio_foreign` (`envio_acceso_docente_id`),
  CONSTRAINT `recordatorios_envio_foreign` FOREIGN KEY (`envio_acceso_docente_id`) REFERENCES `envios_accesos_docentes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `recordatorios_solicitud_foreign` FOREIGN KEY (`solicitud_disponibilidad_id`) REFERENCES `solicitudes_disponibilidad` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `requerimientos_actividad` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `actividad_seccion_id` bigint(20) unsigned NOT NULL,
  `tipo_sala_id` bigint(20) unsigned DEFAULT NULL,
  `prioridad_recurso_id` bigint(20) unsigned DEFAULT NULL,
  `tipo_requerimiento` varchar(50) NOT NULL,
  `nombre_recurso` varchar(100) DEFAULT NULL,
  `cantidad` smallint(5) unsigned NOT NULL DEFAULT 1,
  `capacidad_minima` smallint(5) unsigned DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `obligatorio` tinyint(1) NOT NULL DEFAULT 0,
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `equipamiento_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `requerimientos_actividad_actividad_seccion_id_foreign` (`actividad_seccion_id`),
  KEY `requerimientos_actividad_tipo_sala_id_foreign` (`tipo_sala_id`),
  KEY `requerimientos_actividad_prioridad_recurso_id_foreign` (`prioridad_recurso_id`),
  KEY `requerimientos_actividad_equipamiento_id_foreign` (`equipamiento_id`),
  CONSTRAINT `requerimientos_actividad_actividad_seccion_id_foreign` FOREIGN KEY (`actividad_seccion_id`) REFERENCES `actividades_seccion` (`id`) ON DELETE CASCADE,
  CONSTRAINT `requerimientos_actividad_equipamiento_id_foreign` FOREIGN KEY (`equipamiento_id`) REFERENCES `equipamientos` (`id`),
  CONSTRAINT `requerimientos_actividad_prioridad_recurso_id_foreign` FOREIGN KEY (`prioridad_recurso_id`) REFERENCES `prioridades_recursos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `requerimientos_actividad_tipo_sala_id_foreign` FOREIGN KEY (`tipo_sala_id`) REFERENCES `tipos_sala` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `respuestas_disponibilidad` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `solicitud_disponibilidad_id` bigint(20) unsigned NOT NULL,
  `profesor_id` bigint(20) unsigned NOT NULL,
  `respuesta` varchar(30) NOT NULL DEFAULT 'Pendiente',
  `observaciones` text DEFAULT NULL,
  `fecha_respuesta` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `bloques` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`bloques`)),
  `revisado_por` bigint(20) unsigned DEFAULT NULL,
  `revisado_en` timestamp NULL DEFAULT NULL,
  `motivo_revision` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `respuestas_disponibilidad_solicitud_disponibilidad_id_foreign` (`solicitud_disponibilidad_id`),
  KEY `respuestas_disponibilidad_profesor_id_foreign` (`profesor_id`),
  KEY `respuestas_disponibilidad_revisado_por_foreign` (`revisado_por`),
  CONSTRAINT `respuestas_disponibilidad_profesor_id_foreign` FOREIGN KEY (`profesor_id`) REFERENCES `profesores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `respuestas_disponibilidad_revisado_por_foreign` FOREIGN KEY (`revisado_por`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `respuestas_disponibilidad_solicitud_disponibilidad_id_foreign` FOREIGN KEY (`solicitud_disponibilidad_id`) REFERENCES `solicitudes_disponibilidad` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `respuestas_solicitud_cambio` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `solicitud_cambio_horario_id` bigint(20) unsigned NOT NULL,
  `profesor_id` bigint(20) unsigned NOT NULL,
  `opcion_id` bigint(20) unsigned DEFAULT NULL,
  `estado` varchar(30) NOT NULL DEFAULT 'Pendiente',
  `observaciones` text DEFAULT NULL,
  `respondido_en` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `respuesta_cambio_docente_unique` (`solicitud_cambio_horario_id`,`profesor_id`),
  KEY `respuestas_solicitud_cambio_profesor_id_foreign` (`profesor_id`),
  KEY `respuestas_solicitud_cambio_opcion_id_foreign` (`opcion_id`),
  CONSTRAINT `respuestas_solicitud_cambio_opcion_id_foreign` FOREIGN KEY (`opcion_id`) REFERENCES `opciones_cambio_horario` (`id`) ON DELETE SET NULL,
  CONSTRAINT `respuestas_solicitud_cambio_profesor_id_foreign` FOREIGN KEY (`profesor_id`) REFERENCES `profesores` (`id`),
  CONSTRAINT `respuestas_solicitud_cambio_solicitud_cambio_horario_id_foreign` FOREIGN KEY (`solicitud_cambio_horario_id`) REFERENCES `solicitudes_cambio_horario` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_has_permissions` (
  `permission_id` bigint(20) unsigned NOT NULL,
  `role_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `role_has_permissions_role_id_foreign` (`role_id`),
  CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `guard_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `salas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `universidad_id` bigint(20) unsigned NOT NULL,
  `sede_id` bigint(20) unsigned NOT NULL,
  `tipo_sala_id` bigint(20) unsigned NOT NULL,
  `codigo` varchar(30) NOT NULL,
  `nombre` varchar(100) DEFAULT NULL,
  `capacidad` smallint(5) unsigned NOT NULL DEFAULT 0,
  `edificio` varchar(100) DEFAULT NULL,
  `piso` varchar(30) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `disponible` tinyint(1) NOT NULL DEFAULT 1,
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `salas_sede_id_codigo_unique` (`sede_id`,`codigo`),
  KEY `salas_universidad_id_foreign` (`universidad_id`),
  KEY `salas_tipo_sala_id_foreign` (`tipo_sala_id`),
  CONSTRAINT `salas_sede_id_foreign` FOREIGN KEY (`sede_id`) REFERENCES `sedes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `salas_tipo_sala_id_foreign` FOREIGN KEY (`tipo_sala_id`) REFERENCES `tipos_sala` (`id`) ON DELETE CASCADE,
  CONSTRAINT `salas_universidad_id_foreign` FOREIGN KEY (`universidad_id`) REFERENCES `universidades` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

INSERT INTO `salas` VALUES
(1,1,1,1,'DEMO-A1','Aula de demostración',24,NULL,NULL,NULL,1,1,'2026-11-27 17:15:11','2026-11-27 17:15:11'),
(2,1,1,2,'DEMO-L1','Laboratorio de demostración',24,NULL,NULL,NULL,1,1,'2026-11-27 17:15:11','2026-11-27 17:15:11');
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `secciones` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `identificador_externo` varchar(255) DEFAULT NULL,
  `asignatura_id` bigint(20) unsigned NOT NULL,
  `periodo_academico_id` bigint(20) unsigned NOT NULL,
  `codigo` varchar(30) DEFAULT NULL,
  `nombre` varchar(100) DEFAULT NULL,
  `semestre` smallint(5) unsigned DEFAULT NULL,
  `cupos` smallint(5) unsigned DEFAULT NULL,
  `inscritos` smallint(5) unsigned NOT NULL DEFAULT 0,
  `modalidad` varchar(50) DEFAULT NULL,
  `categoria` varchar(50) DEFAULT NULL,
  `tipo_dictacion` varchar(50) DEFAULT NULL,
  `estado` varchar(30) NOT NULL DEFAULT 'Pendiente',
  `estado_activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `secciones_asignatura_id_foreign` (`asignatura_id`),
  KEY `secciones_periodo_academico_id_foreign` (`periodo_academico_id`),
  CONSTRAINT `secciones_asignatura_id_foreign` FOREIGN KEY (`asignatura_id`) REFERENCES `asignaturas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `secciones_periodo_academico_id_foreign` FOREIGN KEY (`periodo_academico_id`) REFERENCES `periodos_academicos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

INSERT INTO `secciones` VALUES
(1,NULL,1,1,'1',NULL,2,24,20,NULL,NULL,NULL,'Pendiente',1,'2026-11-27 17:15:11','2026-11-27 17:15:11');
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `secciones_carreras` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `seccion_id` bigint(20) unsigned NOT NULL,
  `carrera_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `seccion_carrera_unique` (`seccion_id`,`carrera_id`),
  KEY `secciones_carreras_carrera_id_foreign` (`carrera_id`),
  CONSTRAINT `secciones_carreras_carrera_id_foreign` FOREIGN KEY (`carrera_id`) REFERENCES `carreras` (`id`) ON DELETE CASCADE,
  CONSTRAINT `secciones_carreras_seccion_id_foreign` FOREIGN KEY (`seccion_id`) REFERENCES `secciones` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

INSERT INTO `secciones_carreras` VALUES
(1,1,1,NULL,NULL);
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `secciones_profesores` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `seccion_id` bigint(20) unsigned NOT NULL,
  `profesor_id` bigint(20) unsigned NOT NULL,
  `tipo_participacion` varchar(50) NOT NULL DEFAULT 'Profesor responsable',
  `es_principal` tinyint(1) NOT NULL DEFAULT 0,
  `observaciones` text DEFAULT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `seccion_profesor_unique` (`seccion_id`,`profesor_id`),
  KEY `secciones_profesores_profesor_id_foreign` (`profesor_id`),
  CONSTRAINT `secciones_profesores_profesor_id_foreign` FOREIGN KEY (`profesor_id`) REFERENCES `profesores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `secciones_profesores_seccion_id_foreign` FOREIGN KEY (`seccion_id`) REFERENCES `secciones` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sedes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `universidad_id` bigint(20) unsigned NOT NULL,
  `codigo` varchar(20) DEFAULT NULL,
  `nombre` varchar(100) NOT NULL,
  `direccion` varchar(200) DEFAULT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sedes_universidad_id_foreign` (`universidad_id`),
  CONSTRAINT `sedes_universidad_id_foreign` FOREIGN KEY (`universidad_id`) REFERENCES `universidades` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

INSERT INTO `sedes` VALUES
(1,1,NULL,'Sede de demostración',NULL,1,'2026-11-27 17:15:11','2026-11-27 17:15:11');
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `solicitudes_cambio_horario` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `horario_id` bigint(20) unsigned NOT NULL,
  `profesor_id` bigint(20) unsigned NOT NULL,
  `usuario_id` bigint(20) unsigned DEFAULT NULL,
  `motivo` varchar(150) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `estado` varchar(30) NOT NULL DEFAULT 'Pendiente',
  `fecha_solicitud` datetime DEFAULT NULL,
  `fecha_respuesta` datetime DEFAULT NULL,
  `respuesta` text DEFAULT NULL,
  `estado_activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `fecha_limite` timestamp NULL DEFAULT NULL,
  `firma_origen` char(64) DEFAULT NULL,
  `origen` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`origen`)),
  `revisado_por` bigint(20) unsigned DEFAULT NULL,
  `revisado_en` timestamp NULL DEFAULT NULL,
  `motivo_revision` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `solicitudes_cambio_horario_horario_id_foreign` (`horario_id`),
  KEY `solicitudes_cambio_horario_profesor_id_foreign` (`profesor_id`),
  KEY `solicitudes_cambio_horario_usuario_id_foreign` (`usuario_id`),
  KEY `solicitudes_cambio_horario_revisado_por_foreign` (`revisado_por`),
  CONSTRAINT `solicitudes_cambio_horario_horario_id_foreign` FOREIGN KEY (`horario_id`) REFERENCES `horarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `solicitudes_cambio_horario_profesor_id_foreign` FOREIGN KEY (`profesor_id`) REFERENCES `profesores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `solicitudes_cambio_horario_revisado_por_foreign` FOREIGN KEY (`revisado_por`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `solicitudes_cambio_horario_usuario_id_foreign` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `solicitudes_disponibilidad` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `profesor_id` bigint(20) unsigned NOT NULL,
  `periodo_academico_id` bigint(20) unsigned NOT NULL,
  `fecha_solicitud` date DEFAULT NULL,
  `fecha_limite` date DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `estado` varchar(30) NOT NULL DEFAULT 'Pendiente',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `solicitudes_disponibilidad_profesor_id_foreign` (`profesor_id`),
  KEY `solicitudes_disponibilidad_periodo_academico_id_foreign` (`periodo_academico_id`),
  CONSTRAINT `solicitudes_disponibilidad_periodo_academico_id_foreign` FOREIGN KEY (`periodo_academico_id`) REFERENCES `periodos_academicos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `solicitudes_disponibilidad_profesor_id_foreign` FOREIGN KEY (`profesor_id`) REFERENCES `profesores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `temas_visuales` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `universidad_id` bigint(20) unsigned NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `color_principal` varchar(20) DEFAULT NULL,
  `color_secundario` varchar(20) DEFAULT NULL,
  `color_fondo` varchar(20) DEFAULT NULL,
  `color_texto` varchar(20) DEFAULT NULL,
  `logo_principal` varchar(255) DEFAULT NULL,
  `logo_secundario` varchar(255) DEFAULT NULL,
  `es_principal` tinyint(1) NOT NULL DEFAULT 0,
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `temas_visuales_universidad_id_foreign` (`universidad_id`),
  CONSTRAINT `temas_visuales_universidad_id_foreign` FOREIGN KEY (`universidad_id`) REFERENCES `universidades` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tipos_sala` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `requiere_equipamiento` tinyint(1) NOT NULL DEFAULT 0,
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

INSERT INTO `tipos_sala` VALUES
(1,'Aula',NULL,0,1,'2026-11-27 17:15:11','2026-11-27 17:15:11'),
(2,'Laboratorio de computación',NULL,0,1,'2026-11-27 17:15:11','2026-11-27 17:15:11');
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `universidades` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) NOT NULL,
  `sigla` varchar(20) NOT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

INSERT INTO `universidades` VALUES
(1,'Universidad Católica de la Santísima Concepción','UCSC',1,NULL,NULL);
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `rut` varchar(12) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `profesor_id` bigint(20) unsigned DEFAULT NULL,
  `debe_cambiar_password` tinyint(1) NOT NULL DEFAULT 0,
  `clave_temporal` text DEFAULT NULL,
  `primer_ingreso_en` timestamp NULL DEFAULT NULL,
  `ultimo_ingreso_en` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_rut_unique` (`rut`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_profesor_id_unique` (`profesor_id`),
  CONSTRAINT `users_profesor_id_foreign` FOREIGN KEY (`profesor_id`) REFERENCES `profesores` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `validaciones_horario` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `version_planificacion_id` bigint(20) unsigned NOT NULL,
  `horario_id` bigint(20) unsigned DEFAULT NULL,
  `tipo_validacion` varchar(100) NOT NULL,
  `resultado` varchar(30) NOT NULL DEFAULT 'Pendiente',
  `detalle` text DEFAULT NULL,
  `nivel` varchar(30) NOT NULL DEFAULT 'Advertencia',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `validaciones_horario_version_planificacion_id_foreign` (`version_planificacion_id`),
  KEY `validaciones_horario_horario_id_foreign` (`horario_id`),
  CONSTRAINT `validaciones_horario_horario_id_foreign` FOREIGN KEY (`horario_id`) REFERENCES `horarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `validaciones_horario_version_planificacion_id_foreign` FOREIGN KEY (`version_planificacion_id`) REFERENCES `versiones_planificacion` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `versiones_planificacion` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `periodo_academico_id` bigint(20) unsigned NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `version` varchar(30) NOT NULL DEFAULT '1.0',
  `tipo` varchar(20) NOT NULL DEFAULT 'anterior',
  `descripcion` text DEFAULT NULL,
  `estado` varchar(30) NOT NULL DEFAULT 'Borrador',
  `usuario_id` bigint(20) unsigned DEFAULT NULL,
  `fecha_publicacion` datetime DEFAULT NULL,
  `es_actual` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `versiones_planificacion_periodo_academico_id_foreign` (`periodo_academico_id`),
  KEY `versiones_planificacion_usuario_id_foreign` (`usuario_id`),
  CONSTRAINT `versiones_planificacion_periodo_academico_id_foreign` FOREIGN KEY (`periodo_academico_id`) REFERENCES `periodos_academicos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `versiones_planificacion_usuario_id_foreign` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

INSERT INTO `versiones_planificacion` VALUES
(1,1,'Semestral de demostración','0.1','semestral',NULL,'Borrador',NULL,NULL,1,'2026-11-27 17:15:11','2026-11-27 17:15:11');
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
