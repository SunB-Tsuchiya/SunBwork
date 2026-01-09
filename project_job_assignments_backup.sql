-- MySQL dump 10.13  Distrib 8.0.43, for Linux (aarch64)
--
-- Host: localhost    Database: sunbwork
-- ------------------------------------------------------
-- Server version	8.0.43

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
-- Table structure for table `project_job_assignments`
--

DROP TABLE IF EXISTS `project_job_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `project_job_assignments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `project_job_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `detail` text COLLATE utf8mb4_unicode_ci,
  `difficulty` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal',
  `difficulty_id` bigint unsigned DEFAULT NULL,
  `desired_start_date` date DEFAULT NULL,
  `desired_end_date` date DEFAULT NULL,
  `desired_time` time DEFAULT NULL,
  `estimated_hours` decimal(6,2) DEFAULT NULL,
  `assigned` tinyint(1) NOT NULL DEFAULT '0',
  `completed` tinyint(1) NOT NULL DEFAULT '0',
  `accepted` tinyint(1) NOT NULL DEFAULT '0',
  `read_at` timestamp NULL DEFAULT NULL,
  `scheduled` tinyint(1) NOT NULL DEFAULT '0',
  `size_id` bigint unsigned DEFAULT NULL,
  `amounts` int DEFAULT NULL,
  `amounts_unit` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `work_item_type_id` bigint unsigned DEFAULT NULL,
  `stage_id` bigint unsigned DEFAULT NULL,
  `status_id` bigint unsigned DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT NULL,
  `department_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `starts_at` timestamp NULL DEFAULT NULL,
  `ends_at` timestamp NULL DEFAULT NULL,
  `sender_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `project_job_assignments_project_job_id_index` (`project_job_id`),
  KEY `project_job_assignments_user_id_index` (`user_id`),
  KEY `project_job_assignments_size_id_index` (`size_id`),
  KEY `project_job_assignments_work_item_type_id_index` (`work_item_type_id`),
  KEY `project_job_assignments_stage_id_index` (`stage_id`),
  KEY `project_job_assignments_status_id_index` (`status_id`),
  KEY `project_job_assignments_company_id_index` (`company_id`),
  KEY `project_job_assignments_department_id_index` (`department_id`),
  KEY `project_job_assignments_difficulty_id_index` (`difficulty_id`),
  KEY `project_job_assignments_sender_id_foreign` (`sender_id`),
  CONSTRAINT `project_job_assignments_difficulty_id_foreign` FOREIGN KEY (`difficulty_id`) REFERENCES `difficulties` (`id`) ON DELETE SET NULL,
  CONSTRAINT `project_job_assignments_sender_id_foreign` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_job_assignments`
--

LOCK TABLES `project_job_assignments` WRITE;
/*!40000 ALTER TABLE `project_job_assignments` DISABLE KEYS */;
INSERT INTO `project_job_assignments` VALUES (1,1,30,'初校作成','初校を作成してください。','normal',2,'2025-11-30','2025-12-03','16:00:00',4.00,1,0,1,'2025-11-29 14:45:08',1,4,20,'page',1,1,5,2,1,'2025-11-29 14:43:16','2025-11-29 14:58:25',NULL,NULL,NULL),(3,1,30,'エラーが出ます。','Event','normal',2,'2025-12-15','2025-12-15','19:00:00',7.00,1,1,1,'2025-12-13 09:25:10',1,4,60,'page',2,1,3,2,1,'2025-12-13 09:24:46','2026-01-07 12:00:02',NULL,NULL,NULL),(4,1,30,'校正チェック１','校正のチェックをしてください。','heavy',2,'2026-01-15','2026-01-23','09:00:00',7.50,1,1,1,'2026-01-06 15:03:37',1,5,3000,'page',1,2,3,2,1,'2026-01-06 15:00:25','2026-01-07 11:49:48',NULL,NULL,NULL),(5,1,31,'発送物チェック','発送物のチェックをし、ミスがあれば報告。','heavy',2,'2026-01-12','2026-01-13','16:00:00',8.00,1,0,1,'2026-01-07 12:35:16',1,4,400,'page',6,2,5,2,1,'2026-01-07 12:33:24','2026-01-07 12:36:00',NULL,NULL,NULL),(6,1,29,'組版作成','組版依頼','light',NULL,'2026-01-13','2026-01-13','18:00:00',5.00,1,0,1,NULL,0,4,40,'page',1,1,1,2,1,'2026-01-07 12:33:24','2026-01-07 12:33:33',NULL,NULL,NULL),(7,1,30,'卵集め','卵を集めていきます。','normal',2,'2026-01-07','2026-01-07','18:00:00',3.00,0,0,0,NULL,0,4,30,'page',2,3,4,2,1,'2026-01-07 13:51:15','2026-01-07 13:51:15',NULL,NULL,NULL),(8,1,31,'ジョブジョブ','ジョブジョブジョブ','light',2,'2026-01-07','2026-01-07','18:00:00',3.00,0,0,0,NULL,0,4,70,'page',1,1,1,2,1,'2026-01-07 14:06:18','2026-01-07 14:06:18',NULL,NULL,9),(9,1,31,'ジョブら','ジョブランタン','normal',2,'2026-01-07','2026-01-07','17:00:00',4.00,0,0,0,NULL,0,4,50,'page',1,3,2,2,1,'2026-01-07 14:18:31','2026-01-07 14:18:31',NULL,NULL,NULL);
/*!40000 ALTER TABLE `project_job_assignments` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-01-07  6:26:28
