/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `access_group_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `access_group_users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `access_group_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `access_group_users_access_group_id_user_id_unique` (`access_group_id`,`user_id`),
  KEY `access_group_users_user_id_foreign` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `access_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `access_groups` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) DEFAULT '1',
  `slug` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `activities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `activities` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `activities_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `activity_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `activity_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `log_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `event` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject_id` bigint unsigned DEFAULT NULL,
  `causer_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `causer_id` bigint unsigned DEFAULT NULL,
  `properties` json DEFAULT NULL,
  `batch_uuid` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `subject` (`subject_type`,`subject_id`),
  KEY `causer` (`causer_type`,`causer_id`),
  KEY `activity_log_log_name_index` (`log_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `approved_training_areas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `approved_training_areas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `description` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `is_active` int DEFAULT NULL,
  `sort_order` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `article_program`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `article_program` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `article_id` bigint unsigned NOT NULL,
  `program_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `article_program_article_id_foreign` (`article_id`),
  KEY `article_program_program_id_foreign` (`program_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `article_tag`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `article_tag` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `article_id` bigint unsigned NOT NULL,
  `tag_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `article_tag_article_id_foreign` (`article_id`),
  KEY `article_tag_tag_id_foreign` (`tag_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `assessment_cadres`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `assessment_cadres` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `order` int DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `assessment_cadres_code_unique` (`code`),
  KEY `assessment_cadres_is_active_order_index` (`is_active`,`order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `assessment_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `assessment_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `category_type` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `instructions` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `weight_percentage` decimal(5,2) DEFAULT '25.00',
  `default_weight_percentage` decimal(5,2) DEFAULT NULL,
  `pass_threshold` decimal(5,2) DEFAULT '70.00',
  `default_pass_threshold` decimal(5,2) DEFAULT NULL,
  `assessment_method` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `order_sequence` int DEFAULT '1',
  `is_required` tinyint(1) DEFAULT '1',
  `is_active` tinyint(1) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `assessment_categories_training_id_order_sequence_index` (`order_sequence`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `assessment_commodity_responses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `assessment_commodity_responses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `assessment_id` bigint unsigned NOT NULL,
  `commodity_id` bigint unsigned NOT NULL,
  `assessment_department_id` bigint unsigned NOT NULL,
  `available` tinyint(1) NOT NULL DEFAULT '0',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `score` decimal(5,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `assessment_commodity_dept_unique` (`assessment_id`,`commodity_id`,`assessment_department_id`),
  KEY `assessment_commodity_responses_assessment_id_index` (`assessment_id`),
  KEY `assessment_commodity_responses_commodity_id_index` (`commodity_id`),
  KEY `assessment_commodity_responses_assessment_department_id_index` (`assessment_department_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `assessment_department_scores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `assessment_department_scores` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `assessment_id` bigint unsigned NOT NULL,
  `assessment_department_id` bigint unsigned NOT NULL,
  `commodity_category_id` bigint unsigned DEFAULT NULL,
  `available_count` int NOT NULL DEFAULT '0',
  `total_applicable` int NOT NULL DEFAULT '0',
  `percentage` decimal(5,2) NOT NULL DEFAULT '0.00',
  `grade` enum('green','yellow','red') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `assessment_department_scores_assessment_id_index` (`assessment_id`),
  KEY `assessment_department_scores_assessment_department_id_index` (`assessment_department_id`),
  KEY `assessment_department_scores_commodity_category_id_index` (`commodity_category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `assessment_departments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `assessment_departments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `color` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#3B82F6',
  `icon` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `order` int NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `assessment_departments_slug_unique` (`slug`),
  KEY `assessment_departments_is_active_order_index` (`is_active`,`order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `assessment_email_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `assessment_email_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `assessment_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `emails` json NOT NULL,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'queued',
  `error` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `assessment_email_jobs_assessment_id_foreign` (`assessment_id`),
  KEY `assessment_email_jobs_user_id_created_at_index` (`user_id`,`created_at`),
  KEY `assessment_email_jobs_status_index` (`status`),
  CONSTRAINT `assessment_email_jobs_assessment_id_foreign` FOREIGN KEY (`assessment_id`) REFERENCES `assessments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `assessment_email_jobs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `assessment_question_responses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `assessment_question_responses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `assessment_id` bigint unsigned NOT NULL,
  `assessment_question_id` bigint unsigned NOT NULL,
  `response_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `explanation` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `metadata` json DEFAULT NULL,
  `score` decimal(5,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `assessment_question_unique` (`assessment_id`,`assessment_question_id`),
  KEY `assessment_question_responses_assessment_id_index` (`assessment_id`),
  KEY `assessment_question_responses_assessment_question_id_index` (`assessment_question_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `assessment_questions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `assessment_questions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `assessment_section_id` bigint unsigned NOT NULL,
  `question_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `question_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `help_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `question_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` json DEFAULT NULL,
  `is_required` tinyint(1) NOT NULL DEFAULT '0',
  `validation_rules` json DEFAULT NULL,
  `display_conditions` json DEFAULT NULL,
  `requires_explanation_on` json DEFAULT NULL,
  `explanation_label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Comments/Recommendations',
  `skip_logic` json DEFAULT NULL,
  `scoring_map` json DEFAULT NULL,
  `is_scored` tinyint(1) NOT NULL DEFAULT '1',
  `order` int NOT NULL DEFAULT '0',
  `group` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `assessment_questions_question_code_unique` (`question_code`),
  KEY `assessment_questions_assessment_section_id_order_index` (`assessment_section_id`,`order`),
  KEY `assessment_questions_question_code_index` (`question_code`),
  KEY `assessment_questions_is_active_index` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `assessment_section_scores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `assessment_section_scores` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `assessment_id` bigint unsigned NOT NULL,
  `assessment_section_id` bigint unsigned NOT NULL,
  `total_score` decimal(8,2) NOT NULL DEFAULT '0.00',
  `max_score` decimal(8,2) NOT NULL DEFAULT '0.00',
  `percentage` decimal(5,2) NOT NULL DEFAULT '0.00',
  `grade` enum('green','yellow','red') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total_questions` int NOT NULL DEFAULT '0',
  `answered_questions` int NOT NULL DEFAULT '0',
  `skipped_questions` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `assessment_section_unique` (`assessment_id`,`assessment_section_id`),
  KEY `assessment_section_scores_assessment_section_id_foreign` (`assessment_section_id`),
  KEY `assessment_section_scores_assessment_id_index` (`assessment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `assessment_sections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `assessment_sections` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `section_type` enum('dynamic_questions','structured_data','commodity_matrix') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'dynamic_questions',
  `is_scored` tinyint(1) NOT NULL DEFAULT '1',
  `order` int NOT NULL DEFAULT '0',
  `icon` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `color` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `assessment_sections_code_unique` (`code`),
  KEY `assessment_sections_code_index` (`code`),
  KEY `assessment_sections_is_active_order_index` (`is_active`,`order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `assessment_team`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `assessment_team` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `assessment_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `role` enum('team_lead','member') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'member',
  `added_by` bigint unsigned DEFAULT NULL,
  `added_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `assessment_team_assessment_id_user_id_unique` (`assessment_id`,`user_id`),
  KEY `assessment_team_added_by_foreign` (`added_by`),
  KEY `assessment_team_assessment_id_role_index` (`assessment_id`,`role`),
  KEY `assessment_team_user_id_role_index` (`user_id`,`role`),
  CONSTRAINT `assessment_team_added_by_foreign` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `assessment_team_assessment_id_foreign` FOREIGN KEY (`assessment_id`) REFERENCES `assessments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `assessment_team_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `assessment_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `assessment_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` int NOT NULL,
  `is_active` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `assessments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `assessments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `facility_id` bigint unsigned NOT NULL,
  `has_nbu` tinyint(1) DEFAULT NULL,
  `nbu_nicu_beds` int NOT NULL DEFAULT '0',
  `nbu_general_cots` int NOT NULL DEFAULT '0',
  `nbu_kmc_beds` int NOT NULL DEFAULT '0',
  `nbu_comments` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `has_paediatric` tinyint(1) DEFAULT NULL,
  `paediatric_general_beds` int NOT NULL DEFAULT '0',
  `paediatric_picu_beds` int NOT NULL DEFAULT '0',
  `paediatric_comments` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `assessment_type` enum('baseline','midline','endline') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'baseline',
  `assessment_date` date NOT NULL,
  `assessor_id` bigint unsigned DEFAULT NULL,
  `assessor_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `assessor_contact` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('draft','in_progress','completed','reviewed','approved') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `overall_score` decimal(8,2) DEFAULT NULL,
  `overall_percentage` decimal(5,2) DEFAULT NULL,
  `overall_grade` enum('green','yellow','red') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `section_progress` json DEFAULT NULL,
  `excluded_cadre_ids` json DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `completed_by` bigint unsigned DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `trained_before_mentorship` bigint unsigned DEFAULT NULL,
  `trained_marked_by` bigint unsigned DEFAULT NULL,
  `trained_marked_at` datetime DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `is_locked` tinyint(1) NOT NULL DEFAULT '0',
  `locked_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `locked_by` bigint unsigned DEFAULT NULL,
  `feedback_given` bigint unsigned DEFAULT NULL,
  `feedback_given_by` bigint unsigned DEFAULT NULL,
  `feedback_given_at` timestamp NULL DEFAULT NULL,
  `feedback_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `assessments_completed_by_foreign` (`completed_by`),
  KEY `assessments_created_by_foreign` (`created_by`),
  KEY `assessments_updated_by_foreign` (`updated_by`),
  KEY `assessments_assessment_date_index` (`assessment_date`),
  KEY `assessments_status_index` (`status`),
  KEY `assessments_assessment_type_index` (`assessment_type`),
  KEY `assessments_facility_id_assessment_date_index` (`facility_id`,`assessment_date`),
  KEY `assessments_assessor_id_index` (`assessor_id`),
  KEY `assessments_locked_by_foreign` (`locked_by`),
  CONSTRAINT `assessments_locked_by_foreign` FOREIGN KEY (`locked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cadres`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cadres` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `categories_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `class_attendances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `class_attendances` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `class_id` bigint unsigned NOT NULL,
  `session_id` bigint unsigned DEFAULT NULL COMMENT 'Nullable: null = enrollment-level attendance, non-null = session-specific',
  `user_id` bigint unsigned DEFAULT NULL,
  `marked_by` bigint unsigned DEFAULT NULL,
  `class_module_id` bigint unsigned DEFAULT NULL,
  `marked_at` timestamp NULL DEFAULT NULL,
  `source` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'manual' COMMENT 'auto = via invite link enrollment, manual = mentor/co-mentor',
  `created_at` timestamp NULL DEFAULT NULL,
  `invitation_sent_at` datetime DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `class_attendances_user_id_foreign` (`user_id`),
  KEY `class_attendances_marked_by_foreign` (`marked_by`),
  KEY `idx_class_user_attendance` (`class_id`,`user_id`),
  KEY `idx_session_user_attendance` (`session_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `class_module_activity_participants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `class_module_activity_participants` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `class_module_id` bigint unsigned NOT NULL,
  `class_participant_id` bigint unsigned NOT NULL,
  `activity_id` bigint unsigned NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `completed_at` timestamp NULL DEFAULT NULL,
  `completed_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_activity_participant` (`class_module_id`,`class_participant_id`,`activity_id`),
  KEY `class_module_activity_participants_class_participant_id_foreign` (`class_participant_id`),
  KEY `class_module_activity_participants_activity_id_foreign` (`activity_id`),
  KEY `class_module_activity_participants_completed_by_foreign` (`completed_by`),
  CONSTRAINT `class_module_activity_participants_activity_id_foreign` FOREIGN KEY (`activity_id`) REFERENCES `activities` (`id`) ON DELETE CASCADE,
  CONSTRAINT `class_module_activity_participants_class_module_id_foreign` FOREIGN KEY (`class_module_id`) REFERENCES `class_modules` (`id`) ON DELETE CASCADE,
  CONSTRAINT `class_module_activity_participants_class_participant_id_foreign` FOREIGN KEY (`class_participant_id`) REFERENCES `class_participants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `class_module_activity_participants_completed_by_foreign` FOREIGN KEY (`completed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `class_modules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `class_modules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `mentorship_class_id` bigint unsigned NOT NULL,
  `program_module_id` bigint unsigned NOT NULL,
  `status` enum('not_started','in_progress','completed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'not_started',
  `started_at` timestamp NULL DEFAULT NULL,
  `requires_assessment` tinyint(1) NOT NULL DEFAULT '0',
  `min_attendance_percentage` decimal(5,2) NOT NULL DEFAULT '75.00',
  `completed_at` timestamp NULL DEFAULT NULL,
  `order_sequence` int NOT NULL DEFAULT '0',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `attendance_token` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attendance_link_active` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `class_modules_mentorship_class_id_program_module_id_unique` (`mentorship_class_id`,`program_module_id`),
  UNIQUE KEY `attendance_token` (`attendance_token`),
  KEY `class_modules_program_module_id_foreign` (`program_module_id`),
  KEY `class_modules_mentorship_class_id_order_sequence_index` (`mentorship_class_id`,`order_sequence`),
  KEY `class_modules_attendance_token_index` (`attendance_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `class_participants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `class_participants` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `mentorship_class_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `status` enum('enrolled','active','completed','dropped') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'enrolled',
  `enrolled_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `mentor_approved_at` timestamp NULL DEFAULT NULL,
  `mentor_approved_by` bigint unsigned DEFAULT NULL,
  `head_drmh_approved_at` timestamp NULL DEFAULT NULL,
  `head_drmh_approved_by` bigint unsigned DEFAULT NULL,
  `dropped_at` timestamp NULL DEFAULT NULL,
  `drop_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `invitation_sent_at` datetime DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `class_participants_mentorship_class_id_user_id_unique` (`mentorship_class_id`,`user_id`),
  KEY `class_participants_user_id_foreign` (`user_id`),
  KEY `class_participants_status_index` (`status`),
  KEY `class_participants_mentor_approved_by_foreign` (`mentor_approved_by`),
  KEY `class_participants_head_drmh_approved_by_foreign` (`head_drmh_approved_by`),
  CONSTRAINT `class_participants_head_drmh_approved_by_foreign` FOREIGN KEY (`head_drmh_approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `class_participants_mentor_approved_by_foreign` FOREIGN KEY (`mentor_approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `class_session_attendance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `class_session_attendance` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `class_session_id` bigint unsigned NOT NULL,
  `class_participant_id` bigint unsigned NOT NULL,
  `status` enum('present','absent','excused','late') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'present',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `marked_at` timestamp NULL DEFAULT NULL,
  `marked_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `class_session_attendance_session_participant_unique` (`class_session_id`,`class_participant_id`),
  KEY `class_session_attendance_class_participant_id_index` (`class_participant_id`),
  KEY `class_session_attendance_status_index` (`status`),
  KEY `class_session_attendance_marked_by_foreign` (`marked_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `class_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `class_sessions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `class_module_id` bigint unsigned NOT NULL,
  `module_session_id` bigint unsigned DEFAULT NULL,
  `session_number` int NOT NULL DEFAULT '1',
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `scheduled_date` date DEFAULT NULL,
  `scheduled_time` time DEFAULT NULL,
  `actual_date` date DEFAULT NULL,
  `actual_time` time DEFAULT NULL,
  `duration_minutes` int DEFAULT NULL,
  `facilitator_id` bigint unsigned DEFAULT NULL,
  `location` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('scheduled','in_progress','completed','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'scheduled',
  `attendance_taken` tinyint(1) NOT NULL DEFAULT '0',
  `attendance_link_token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `class_sessions_attendance_link_token_unique` (`attendance_link_token`),
  KEY `class_sessions_module_session_id_foreign` (`module_session_id`),
  KEY `class_sessions_facilitator_id_foreign` (`facilitator_id`),
  KEY `class_sessions_class_module_id_scheduled_date_index` (`class_module_id`,`scheduled_date`),
  KEY `class_sessions_attendance_link_token_index` (`attendance_link_token`),
  KEY `class_sessions_status_index` (`status`),
  KEY `class_sessions_session_number_index` (`session_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `commodities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `commodities` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `commodity_category_id` bigint unsigned NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `order` int NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `commodities_commodity_category_id_order_index` (`commodity_category_id`,`order`),
  KEY `commodities_is_active_index` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `commodity_applicability`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `commodity_applicability` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `commodity_id` bigint unsigned NOT NULL,
  `assessment_department_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `commodity_department_unique` (`commodity_id`,`assessment_department_id`),
  KEY `commodity_applicability_commodity_id_index` (`commodity_id`),
  KEY `commodity_applicability_assessment_department_id_index` (`assessment_department_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `commodity_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `commodity_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `order` int NOT NULL DEFAULT '0',
  `icon` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `commodity_categories_slug_unique` (`slug`),
  KEY `commodity_categories_order_index` (`order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `counties`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `counties` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `uid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `division_id` bigint unsigned DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `counties_name_unique` (`name`),
  KEY `counties_division_id_foreign` (`division_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `county_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `county_user` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `county_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `county_user_user_id_foreign` (`user_id`),
  KEY `county_user_county_id_foreign` (`county_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `departments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `departments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `divisions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `divisions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `divisions_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `facilities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `facilities` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `uid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dhis2_org_unit_uid` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `subcounty_id` bigint unsigned DEFAULT NULL,
  `ward` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `facility_level_id` bigint unsigned DEFAULT NULL,
  `facility_type_id` bigint unsigned DEFAULT NULL,
  `facility_ownership_id` bigint unsigned DEFAULT NULL,
  `physical_address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `postal_address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `telephone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `incharge_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `incharge_designation` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `incharge_contact` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `is_hub` tinyint(1) DEFAULT '0',
  `hub_id` bigint unsigned DEFAULT NULL,
  `mfl_code` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `lat` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `long` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_central_store` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '0',
  `storage_capacity` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `operating_hours` json DEFAULT NULL,
  `is_active` bigint DEFAULT '1',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `storage_conditions` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `distribution_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `facilities_subcounty_id_foreign` (`subcounty_id`),
  KEY `facilities_facility_type_id_foreign` (`facility_type_id`),
  KEY `facilities_hub_id_foreign` (`hub_id`),
  KEY `facilities_mfl_code_index` (`mfl_code`),
  KEY `facilities_facility_level_id_index` (`facility_level_id`),
  KEY `facilities_facility_ownership_id_index` (`facility_ownership_id`),
  KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `facility_assessments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `facility_assessments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `facility_id` bigint unsigned NOT NULL,
  `assessor_id` bigint unsigned NOT NULL,
  `assessment_date` date NOT NULL,
  `infrastructure_score` decimal(5,2) DEFAULT NULL,
  `equipment_score` decimal(5,2) DEFAULT NULL,
  `staff_capacity_score` decimal(5,2) DEFAULT NULL,
  `training_environment_score` decimal(5,2) DEFAULT NULL,
  `overall_score` decimal(5,2) DEFAULT NULL,
  `status` enum('pending','approved','rejected','expired') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `recommendations` json DEFAULT NULL,
  `next_assessment_due` date DEFAULT NULL,
  `assessment_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `facility_assessments_assessor_id_foreign` (`assessor_id`),
  KEY `facility_assessments_facility_id_assessment_date_index` (`facility_id`,`assessment_date`),
  KEY `facility_assessments_status_next_assessment_due_index` (`status`,`next_assessment_due`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `facility_indicator_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `facility_indicator_assignments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `facility_id` bigint unsigned NOT NULL,
  `enabled_report_types` json DEFAULT NULL,
  `is_locked` tinyint(1) NOT NULL DEFAULT '0',
  `locked_at` timestamp NULL DEFAULT NULL,
  `locked_by` bigint unsigned DEFAULT NULL,
  `last_updated_at` timestamp NULL DEFAULT NULL,
  `last_updated_by` bigint unsigned DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `facility_indicator_assignments_facility_id_unique` (`facility_id`),
  KEY `facility_indicator_assignments_locked_by_foreign` (`locked_by`),
  KEY `facility_indicator_assignments_last_updated_by_foreign` (`last_updated_by`),
  CONSTRAINT `facility_indicator_assignments_facility_id_foreign` FOREIGN KEY (`facility_id`) REFERENCES `facilities` (`id`) ON DELETE CASCADE,
  CONSTRAINT `facility_indicator_assignments_last_updated_by_foreign` FOREIGN KEY (`last_updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `facility_indicator_assignments_locked_by_foreign` FOREIGN KEY (`locked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `facility_inventory_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `facility_inventory_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `facility_id` bigint unsigned NOT NULL,
  `inventory_item_id` bigint unsigned NOT NULL,
  `minimum_level` int DEFAULT '0',
  `maximum_level` int DEFAULT '0',
  `current_stock` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_facility_inventory` (`facility_id`,`inventory_item_id`),
  KEY `idx_stock_levels` (`current_stock`,`minimum_level`),
  KEY `inventory_item_id` (`inventory_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `facility_levels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `facility_levels` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `level_number` int NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `facility_levels_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `facility_ownerships`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `facility_ownerships` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `facility_ownerships_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `facility_report_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `facility_report_templates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `facility_id` bigint unsigned NOT NULL,
  `report_template_id` bigint unsigned NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `facility_report_templates_facility_id_report_template_id_unique` (`facility_id`,`report_template_id`),
  KEY `facility_report_templates_report_template_id_foreign` (`report_template_id`),
  CONSTRAINT `facility_report_templates_facility_id_foreign` FOREIGN KEY (`facility_id`) REFERENCES `facilities` (`id`) ON DELETE CASCADE,
  CONSTRAINT `facility_report_templates_report_template_id_foreign` FOREIGN KEY (`report_template_id`) REFERENCES `report_templates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `facility_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `facility_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `facility_types_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `facility_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `facility_user` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `facility_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `facility_user_user_id_foreign` (`user_id`),
  KEY `facility_user_facility_id_foreign` (`facility_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `global_assessment_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `global_assessment_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `weight_percentage` decimal(5,2) NOT NULL DEFAULT '25.00',
  `assessment_method` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Practical Demonstration',
  `order_sequence` int NOT NULL DEFAULT '1',
  `is_required` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `global_assessment_categories_name_unique` (`name`),
  KEY `global_assessment_categories_order_sequence_index` (`order_sequence`),
  KEY `global_assessment_categories_is_required_index` (`is_required`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `grades`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `grades` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `grades_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `human_resource_responses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `human_resource_responses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `assessment_id` bigint unsigned NOT NULL,
  `cadre_id` bigint unsigned NOT NULL,
  `total_in_facility` int NOT NULL DEFAULT '0',
  `etat_plus` int NOT NULL DEFAULT '0',
  `comprehensive_newborn_care` int NOT NULL DEFAULT '0',
  `imnci` int NOT NULL DEFAULT '0',
  `type_1_diabetes` int NOT NULL DEFAULT '0',
  `essential_newborn_care` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `human_resource_responses_assessment_id_cadre_id_unique` (`assessment_id`,`cadre_id`),
  KEY `human_resource_responses_assessment_id_index` (`assessment_id`),
  KEY `human_resource_responses_cadre_id_foreign` (`cadre_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `indicator_frequencies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `indicator_frequencies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `dhis2_period_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` smallint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `indicator_frequencies_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `indicator_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `indicator_groups` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `report_type_id` bigint unsigned NOT NULL,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `dhis2_section_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` smallint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `indicator_groups_report_type_id_sort_order_index` (`report_type_id`,`sort_order`),
  CONSTRAINT `indicator_groups_report_type_id_foreign` FOREIGN KEY (`report_type_id`) REFERENCES `indicator_report_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `indicator_report_periods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `indicator_report_periods` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `facility_id` bigint unsigned NOT NULL,
  `report_type_id` bigint unsigned NOT NULL,
  `frequency_id` bigint unsigned NOT NULL,
  `period_year` smallint unsigned NOT NULL,
  `period_month` tinyint unsigned DEFAULT NULL,
  `period_quarter` tinyint unsigned DEFAULT NULL,
  `dhis2_period` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('draft','submitted','validated','rejected','pushed_to_dhis2') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `submitted_by` bigint unsigned DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `validated_by` bigint unsigned DEFAULT NULL,
  `validated_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `dhis2_push_status` enum('pending','success','failed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dhis2_push_at` timestamp NULL DEFAULT NULL,
  `dhis2_import_summary` json DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_indicator_period` (`facility_id`,`report_type_id`,`frequency_id`,`period_year`,`period_month`,`period_quarter`),
  KEY `indicator_report_periods_report_type_id_foreign` (`report_type_id`),
  KEY `indicator_report_periods_frequency_id_foreign` (`frequency_id`),
  KEY `indicator_report_periods_submitted_by_foreign` (`submitted_by`),
  KEY `indicator_report_periods_validated_by_foreign` (`validated_by`),
  KEY `indicator_report_periods_facility_id_status_index` (`facility_id`,`status`),
  KEY `indicator_report_periods_status_index` (`status`),
  CONSTRAINT `indicator_report_periods_facility_id_foreign` FOREIGN KEY (`facility_id`) REFERENCES `facilities` (`id`) ON DELETE CASCADE,
  CONSTRAINT `indicator_report_periods_frequency_id_foreign` FOREIGN KEY (`frequency_id`) REFERENCES `indicator_frequencies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `indicator_report_periods_report_type_id_foreign` FOREIGN KEY (`report_type_id`) REFERENCES `indicator_report_types` (`id`) ON DELETE CASCADE,
  CONSTRAINT `indicator_report_periods_submitted_by_foreign` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `indicator_report_periods_validated_by_foreign` FOREIGN KEY (`validated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `indicator_report_type_frequencies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `indicator_report_type_frequencies` (
  `report_type_id` bigint unsigned NOT NULL,
  `frequency_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`report_type_id`,`frequency_id`),
  KEY `indicator_report_type_frequencies_frequency_id_foreign` (`frequency_id`),
  CONSTRAINT `indicator_report_type_frequencies_frequency_id_foreign` FOREIGN KEY (`frequency_id`) REFERENCES `indicator_frequencies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `indicator_report_type_frequencies_report_type_id_foreign` FOREIGN KEY (`report_type_id`) REFERENCES `indicator_report_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `indicator_report_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `indicator_report_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `color` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'blue',
  `icon` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dhis2_dataset_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dhis2_org_unit_level` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'facility',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` smallint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `indicator_report_types_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `indicator_values`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `indicator_values` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `period_id` bigint unsigned NOT NULL,
  `indicator_id` bigint unsigned NOT NULL,
  `numerator_value` int unsigned DEFAULT NULL,
  `denominator_value` int unsigned DEFAULT NULL,
  `computed_percentage` decimal(8,4) DEFAULT NULL,
  `count_value` int unsigned DEFAULT NULL,
  `yes_no_value` tinyint(1) DEFAULT NULL,
  `comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `indicator_values_period_id_indicator_id_unique` (`period_id`,`indicator_id`),
  KEY `indicator_values_created_by_foreign` (`created_by`),
  KEY `indicator_values_updated_by_foreign` (`updated_by`),
  KEY `indicator_values_indicator_id_index` (`indicator_id`),
  CONSTRAINT `indicator_values_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `indicator_values_indicator_id_foreign` FOREIGN KEY (`indicator_id`) REFERENCES `indicators` (`id`) ON DELETE CASCADE,
  CONSTRAINT `indicator_values_period_id_foreign` FOREIGN KEY (`period_id`) REFERENCES `indicator_report_periods` (`id`) ON DELETE CASCADE,
  CONSTRAINT `indicator_values_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `indicators`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `indicators` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `group_id` bigint unsigned NOT NULL,
  `parent_indicator_id` bigint unsigned DEFAULT NULL,
  `code` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `short_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `indicator_type` enum('proportion','count','rate','yes_no') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'proportion',
  `category` enum('process','output','outcome','satisfaction') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'output',
  `has_numerator` tinyint(1) NOT NULL DEFAULT '1',
  `has_denominator` tinyint(1) NOT NULL DEFAULT '1',
  `numerator_label` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `denominator_label` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `source_document` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_document_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dhis2_numerator_uid` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dhis2_denominator_uid` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dhis2_indicator_uid` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dhis2_data_element_uid` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `calculation_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `min_value` int unsigned DEFAULT NULL,
  `max_value` int unsigned DEFAULT NULL,
  `target_value` int unsigned DEFAULT NULL,
  `display_hint` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `definition` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` smallint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `indicators_group_id_sort_order_index` (`group_id`,`sort_order`),
  KEY `indicators_indicator_type_index` (`indicator_type`),
  KEY `indicators_parent_indicator_id_index` (`parent_indicator_id`),
  CONSTRAINT `indicators_group_id_foreign` FOREIGN KEY (`group_id`) REFERENCES `indicator_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `indicators_parent_indicator_id_foreign` FOREIGN KEY (`parent_indicator_id`) REFERENCES `indicators` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `inventory_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventory_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `parent_id` bigint unsigned DEFAULT NULL,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `color` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `icon` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `inventory_categories_code_unique` (`code`),
  KEY `inventory_categories_parent_id_foreign` (`parent_id`),
  KEY `inventory_categories_is_active_parent_id_index` (`is_active`,`parent_id`),
  KEY `inventory_categories_sort_order_index` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `inventory_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventory_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `sku` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `barcode` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category_id` bigint unsigned NOT NULL,
  `supplier_id` bigint unsigned NOT NULL,
  `unit_of_measure` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `unit_price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `status` enum('active','inactive','discontinued','recalled','quarantined','restricted') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `condition` enum('new','good','fair','poor','damaged','expired','lost','stolen','decommissioned','disposed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'new',
  `minimum_stock_level` int NOT NULL DEFAULT '0',
  `maximum_stock_level` int DEFAULT NULL,
  `reorder_point` int NOT NULL DEFAULT '0',
  `manufacturer` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `model_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `warranty_period` int DEFAULT NULL,
  `specifications` json DEFAULT NULL,
  `storage_requirements` json DEFAULT NULL,
  `disposal_method` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `requires_approval` tinyint(1) NOT NULL DEFAULT '0',
  `is_trackable` tinyint(1) NOT NULL DEFAULT '0',
  `expiry_tracking` tinyint(1) NOT NULL DEFAULT '0',
  `batch_tracking` tinyint(1) NOT NULL DEFAULT '0',
  `serial_tracking` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `inventory_items_sku_unique` (`sku`),
  UNIQUE KEY `inventory_items_barcode_unique` (`barcode`),
  KEY `inventory_items_category_id_foreign` (`category_id`),
  KEY `inventory_items_is_active_category_id_index` (`is_active`,`category_id`),
  KEY `inventory_items_sku_barcode_index` (`sku`,`barcode`),
  KEY `inventory_items_status_condition_index` (`status`,`condition`),
  KEY `inventory_items_reorder_point_index` (`reorder_point`),
  KEY `inventory_items_supplier_id_index` (`supplier_id`),
  KEY `inventory_items_manufacturer_index` (`manufacturer`),
  KEY `inventory_items_name_index` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `inventory_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventory_transactions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `inventory_item_id` bigint unsigned NOT NULL,
  `facility_id` bigint unsigned NOT NULL,
  `transaction_type` enum('stock_in','stock_out','adjustment','transfer_in','transfer_out','request_out','request_in','disposal','expired','damaged') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` int NOT NULL,
  `previous_stock` int NOT NULL,
  `new_stock` int NOT NULL,
  `reference_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference_id` bigint unsigned DEFAULT NULL,
  `batch_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `unit_price` decimal(10,2) DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `inventory_transactions_facility_id_foreign` (`facility_id`),
  KEY `inventory_transactions_inventory_item_id_facility_id_index` (`inventory_item_id`,`facility_id`),
  KEY `inventory_transactions_transaction_type_created_at_index` (`transaction_type`,`created_at`),
  KEY `inventory_transactions_reference_type_reference_id_index` (`reference_type`,`reference_id`),
  KEY `inventory_transactions_created_by_index` (`created_by`),
  KEY `inventory_transactions_created_at_index` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `item_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `item_batches` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `inventory_item_id` bigint unsigned NOT NULL,
  `batch_no` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `initial_quantity` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `item_status_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `item_status_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `inventory_item_id` bigint unsigned NOT NULL,
  `old_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `new_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `old_condition` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `new_condition` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `changed_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `item_status_logs_inventory_item_id_created_at_index` (`inventory_item_id`,`created_at`),
  KEY `item_status_logs_changed_by_index` (`changed_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `knowledge_base_articles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `knowledge_base_articles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `category_id` bigint unsigned DEFAULT NULL,
  `author_id` bigint unsigned DEFAULT NULL,
  `is_published` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `knowledge_base_articles_author_id_foreign` (`author_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `knowledge_base_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `knowledge_base_attachments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `article_id` bigint unsigned NOT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `external_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `display_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `knowledge_base_attachments_article_id_foreign` (`article_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `knowledge_base_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `knowledge_base_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `icon` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `knowledge_base_categories_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `knowledge_base_tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `knowledge_base_tags` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `color` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `knowledge_base_tags_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `locations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `locations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mentee_assessment_results`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mentee_assessment_results` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `participant_id` bigint unsigned DEFAULT NULL,
  `assessment_category_id` bigint unsigned DEFAULT NULL,
  `result` enum('pass','fail') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `score` decimal(5,2) DEFAULT NULL,
  `grade_id` bigint unsigned DEFAULT NULL,
  `assessed_by` bigint unsigned DEFAULT NULL,
  `assessment_date` timestamp NULL DEFAULT NULL,
  `category_weight` decimal(5,2) DEFAULT NULL,
  `feedback` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `attempts` int DEFAULT '1',
  `mentor_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `time_taken_minutes` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mar_participant_cat_unique` (`participant_id`,`assessment_category_id`),
  KEY `mentee_assessment_results_grade_id_foreign` (`grade_id`),
  KEY `ass_cat_score` (`assessment_category_id`,`score`),
  KEY `mentee_assessment_results_assessed_by_assessment_date_index` (`assessed_by`,`assessment_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mentee_module_progress`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mentee_module_progress` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `class_participant_id` bigint unsigned NOT NULL,
  `class_module_id` bigint unsigned NOT NULL,
  `status` enum('not_started','in_progress','completed','exempted') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'not_started',
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `exempted_at` timestamp NULL DEFAULT NULL,
  `completed_in_previous_class` tinyint(1) NOT NULL DEFAULT '0',
  `attendance_percentage` decimal(5,2) DEFAULT NULL,
  `assessment_score` decimal(5,2) DEFAULT NULL,
  `assessment_status` enum('pending','passed','failed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `pre_test_attempt_id` bigint unsigned DEFAULT NULL,
  `post_test_attempt_id` bigint unsigned DEFAULT NULL,
  `hands_on_video_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hands_on_video_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `video_review_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `video_reviewed_at` timestamp NULL DEFAULT NULL,
  `video_reviewed_by` bigint unsigned DEFAULT NULL,
  `video_review_notes` text COLLATE utf8mb4_unicode_ci,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `mentor_recommendation` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `recommendation_written_at` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_participant_module` (`class_participant_id`,`class_module_id`),
  KEY `mentee_module_progress_class_module_id_foreign` (`class_module_id`),
  KEY `mentee_module_progress_class_participant_id_status_index` (`class_participant_id`,`status`),
  KEY `mentee_module_progress_pre_test_attempt_id_foreign` (`pre_test_attempt_id`),
  KEY `mentee_module_progress_post_test_attempt_id_foreign` (`post_test_attempt_id`),
  KEY `mentee_module_progress_video_reviewed_by_foreign` (`video_reviewed_by`),
  CONSTRAINT `mentee_module_progress_post_test_attempt_id_foreign` FOREIGN KEY (`post_test_attempt_id`) REFERENCES `quiz_attempts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `mentee_module_progress_pre_test_attempt_id_foreign` FOREIGN KEY (`pre_test_attempt_id`) REFERENCES `quiz_attempts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `mentee_module_progress_video_reviewed_by_foreign` FOREIGN KEY (`video_reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mentee_placement_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mentee_placement_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `change_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `old_department_id` bigint unsigned DEFAULT NULL,
  `new_department_id` bigint unsigned DEFAULT NULL,
  `old_cadre_id` bigint unsigned DEFAULT NULL,
  `new_cadre_id` bigint unsigned DEFAULT NULL,
  `effective_date` date NOT NULL,
  `reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `mentee_placement_logs_user_id_foreign` (`user_id`),
  KEY `mentee_placement_logs_old_department_id_foreign` (`old_department_id`),
  KEY `mentee_placement_logs_new_department_id_foreign` (`new_department_id`),
  KEY `mentee_placement_logs_old_cadre_id_foreign` (`old_cadre_id`),
  KEY `mentee_placement_logs_new_cadre_id_foreign` (`new_cadre_id`),
  KEY `mentee_placement_logs_change_type_index` (`change_type`),
  KEY `mentee_placement_logs_effective_date_index` (`effective_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mentee_status_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mentee_status_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `previous_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `new_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `effective_date` date NOT NULL,
  `reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `changed_by` bigint unsigned DEFAULT NULL,
  `facility_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `mentee_status_logs_changed_by_foreign` (`changed_by`),
  KEY `mentee_status_logs_facility_id_foreign` (`facility_id`),
  KEY `mentee_status_logs_user_id_effective_date_index` (`user_id`,`effective_date`),
  KEY `mentee_status_logs_new_status_effective_date_index` (`new_status`,`effective_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mentee_statuses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mentee_statuses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mentee_statuses_name_unique` (`name`),
  KEY `mentee_statuses_is_active_index` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mentorship_classes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mentorship_classes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `training_id` bigint unsigned NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('draft','active','completed','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `enrollment_token` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `enrollment_link_active` tinyint(1) NOT NULL DEFAULT '0',
  `created_by` bigint unsigned NOT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `enrollment_token` (`enrollment_token`),
  KEY `mentorship_classes_created_by_foreign` (`created_by`),
  KEY `mentorship_classes_training_id_status_index` (`training_id`,`status`),
  KEY `mentorship_classes_enrollment_token_index` (`enrollment_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mentorship_co_mentors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mentorship_co_mentors` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `training_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `invited_by` bigint unsigned DEFAULT NULL,
  `invited_at` timestamp NULL DEFAULT NULL,
  `accepted_at` timestamp NULL DEFAULT NULL,
  `status` enum('pending','accepted','declined','removed','revoked') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `permissions` json DEFAULT NULL,
  `invitation_token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mentorship_co_mentors_training_id_user_id_unique` (`training_id`,`user_id`),
  UNIQUE KEY `mentorship_co_mentors_invitation_token_unique` (`invitation_token`),
  KEY `mentorship_co_mentors_user_id_foreign` (`user_id`),
  KEY `mentorship_co_mentors_invited_by_foreign` (`invited_by`),
  KEY `mentorship_co_mentors_training_id_status_index` (`training_id`,`status`),
  KEY `co_mentors_invitation_token_index` (`invitation_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mentorship_module_usages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mentorship_module_usages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `mentorship_id` bigint unsigned NOT NULL COMMENT 'References trainings.id (the mentorship)',
  `module_id` bigint unsigned NOT NULL,
  `first_class_id` bigint unsigned NOT NULL COMMENT 'The class where this module was first assigned',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_module_per_mentorship` (`mentorship_id`,`module_id`),
  KEY `mentorship_module_usages_module_id_foreign` (`module_id`),
  KEY `mentorship_module_usages_first_class_id_foreign` (`first_class_id`),
  KEY `idx_mentorship_usages` (`mentorship_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `methodologies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `methodologies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_active` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `methodologies_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `model_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_permissions` (
  `permission_id` bigint unsigned NOT NULL,
  `model_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `model_has_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_roles` (
  `role_id` bigint unsigned NOT NULL,
  `model_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `module_assessment_results`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `module_assessment_results` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `module_assessment_id` bigint unsigned NOT NULL,
  `class_participant_id` bigint unsigned NOT NULL,
  `mentee_progress_id` bigint unsigned DEFAULT NULL,
  `score` decimal(5,2) NOT NULL,
  `status` enum('passed','failed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'failed',
  `feedback` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `assessed_by` bigint unsigned DEFAULT NULL,
  `assessed_at` timestamp NOT NULL,
  `answers_data` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `module_assessment_results_mentee_progress_id_foreign` (`mentee_progress_id`),
  KEY `module_assessment_results_assessed_by_foreign` (`assessed_by`),
  KEY `module_assessment_results_class_participant_id_status_index` (`class_participant_id`,`status`),
  KEY `module_assessment_results_module_assessment_id_status_index` (`module_assessment_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `module_assessments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `module_assessments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `class_module_id` bigint unsigned NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `assessment_type` enum('manual','checklist','score','mcq') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'score',
  `pass_threshold` decimal(5,2) NOT NULL DEFAULT '70.00',
  `max_score` decimal(8,2) NOT NULL DEFAULT '100.00',
  `weight_percentage` decimal(5,2) NOT NULL DEFAULT '100.00',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `questions_data` json DEFAULT NULL,
  `order_sequence` int NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `module_assessments_class_module_id_is_active_index` (`class_module_id`,`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `module_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `module_sessions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `program_module_id` bigint unsigned NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `time_minutes` int NOT NULL DEFAULT '0',
  `methodology_id` bigint unsigned DEFAULT NULL,
  `order_sequence` int NOT NULL DEFAULT '0',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `module_sessions_methodology_id_foreign` (`methodology_id`),
  KEY `module_sessions_program_module_id_order_sequence_index` (`program_module_id`,`order_sequence`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `modules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `modules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `program_id` bigint unsigned NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `modules_program_id_foreign` (`program_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_id` bigint unsigned NOT NULL,
  `data` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`,`notifiable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `objectives`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `objectives` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `training_id` bigint unsigned DEFAULT NULL,
  `objective_text` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `objective_order` int DEFAULT NULL,
  `assessment_method` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pass_criteria` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `objectives_training_session_id_foreign` (`training_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `participant_objective_results`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `participant_objective_results` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `objective_id` bigint unsigned NOT NULL,
  `participant_id` bigint unsigned NOT NULL,
  `grade_id` bigint unsigned NOT NULL,
  `score` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assessed_by` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assessment_date` date DEFAULT NULL,
  `feedback` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `comments` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `participant_objective_results_objective_id_foreign` (`objective_id`),
  KEY `participant_objective_results_training_participant_id_foreign` (`participant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `participant_status_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `participant_status_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `training_participant_id` bigint unsigned DEFAULT NULL,
  `mentorship_participant_id` bigint unsigned DEFAULT NULL,
  `month_number` tinyint unsigned NOT NULL,
  `status_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `old_value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `new_value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `recorded_by` bigint unsigned DEFAULT NULL,
  `recorded_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `participant_status_logs_recorded_by_foreign` (`recorded_by`),
  KEY `participant_status_logs_training_participant_id_index` (`training_participant_id`),
  KEY `participant_status_logs_mentorship_participant_id_index` (`mentorship_participant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `partners`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `partners` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('ngo','private','international','faith_based','academic','development','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'other',
  `contact_person` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `website` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `registration_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `partners_name_is_active_index` (`name`,`is_active`),
  KEY `partners_type_index` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint unsigned NOT NULL,
  `name` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  KEY `personal_access_tokens_expires_at_index` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `program_module_activities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `program_module_activities` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `program_module_id` bigint unsigned NOT NULL,
  `activity_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `program_module_activities_program_module_id_activity_id_unique` (`program_module_id`,`activity_id`),
  KEY `program_module_activities_program_module_id_index` (`program_module_id`),
  KEY `program_module_activities_activity_id_index` (`activity_id`),
  CONSTRAINT `program_module_activities_activity_id_foreign` FOREIGN KEY (`activity_id`) REFERENCES `activities` (`id`) ON DELETE CASCADE,
  CONSTRAINT `program_module_activities_program_module_id_foreign` FOREIGN KEY (`program_module_id`) REFERENCES `program_modules` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `program_module_contents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `program_module_contents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `program_module_id` bigint unsigned NOT NULL,
  `type` enum('introduction','video','case_scenario') COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` longtext COLLATE utf8mb4_unicode_ci,
  `video_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `video_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `order_sequence` int NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `program_module_contents_program_module_id_index` (`program_module_id`),
  KEY `program_module_contents_type_index` (`type`),
  KEY `program_module_contents_order_sequence_index` (`order_sequence`),
  CONSTRAINT `program_module_contents_program_module_id_foreign` FOREIGN KEY (`program_module_id`) REFERENCES `program_modules` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `program_module_quizzes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `program_module_quizzes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `program_module_id` bigint unsigned NOT NULL,
  `type` enum('pre_test','post_test','both') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'both',
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `pass_mark_percentage` decimal(5,2) NOT NULL DEFAULT '85.00',
  `order_sequence` int NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `program_module_quizzes_program_module_id_type_unique` (`program_module_id`,`type`),
  KEY `program_module_quizzes_program_module_id_index` (`program_module_id`),
  KEY `program_module_quizzes_type_index` (`type`),
  CONSTRAINT `program_module_quizzes_program_module_id_foreign` FOREIGN KEY (`program_module_id`) REFERENCES `program_modules` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `program_modules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `program_modules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `program_id` bigint unsigned NOT NULL,
  `parent_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `order_sequence` int NOT NULL DEFAULT '0',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `total_time_minutes` int NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `program_modules_program_id_order_sequence_index` (`program_id`,`order_sequence`),
  KEY `program_modules_parent_id_index` (`parent_id`),
  CONSTRAINT `program_modules_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `program_modules` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `programs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `programs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `visible_to_roles` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `programs_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `quiz_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quiz_attempts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `program_module_quiz_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `attempt_type` enum('pre_test','post_test') COLLATE utf8mb4_unicode_ci NOT NULL,
  `score` decimal(5,2) DEFAULT NULL,
  `total_questions` int NOT NULL DEFAULT '0',
  `correct_answers` int NOT NULL DEFAULT '0',
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `quiz_attempts_program_module_quiz_id_index` (`program_module_quiz_id`),
  KEY `quiz_attempts_user_id_index` (`user_id`),
  KEY `quiz_attempts_program_module_quiz_id_user_id_attempt_type_index` (`program_module_quiz_id`,`user_id`,`attempt_type`),
  CONSTRAINT `quiz_attempts_program_module_quiz_id_foreign` FOREIGN KEY (`program_module_quiz_id`) REFERENCES `program_module_quizzes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quiz_attempts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `quiz_options`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quiz_options` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `quiz_question_id` bigint unsigned NOT NULL,
  `option_text` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_correct` tinyint(1) NOT NULL DEFAULT '0',
  `order_sequence` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `quiz_options_quiz_question_id_index` (`quiz_question_id`),
  KEY `quiz_options_order_sequence_index` (`order_sequence`),
  CONSTRAINT `quiz_options_quiz_question_id_foreign` FOREIGN KEY (`quiz_question_id`) REFERENCES `quiz_questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `quiz_questions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quiz_questions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `program_module_quiz_id` bigint unsigned NOT NULL,
  `question_text` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `explanation` longtext COLLATE utf8mb4_unicode_ci,
  `order_sequence` int NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `quiz_questions_program_module_quiz_id_index` (`program_module_quiz_id`),
  KEY `quiz_questions_order_sequence_index` (`order_sequence`),
  CONSTRAINT `quiz_questions_program_module_quiz_id_foreign` FOREIGN KEY (`program_module_quiz_id`) REFERENCES `program_module_quizzes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `quiz_responses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quiz_responses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `quiz_attempt_id` bigint unsigned NOT NULL,
  `quiz_question_id` bigint unsigned NOT NULL,
  `quiz_option_id` bigint unsigned NOT NULL,
  `is_correct` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `quiz_responses_quiz_attempt_id_quiz_question_id_unique` (`quiz_attempt_id`,`quiz_question_id`),
  KEY `quiz_responses_quiz_question_id_foreign` (`quiz_question_id`),
  KEY `quiz_responses_quiz_option_id_foreign` (`quiz_option_id`),
  KEY `quiz_responses_quiz_attempt_id_index` (`quiz_attempt_id`),
  CONSTRAINT `quiz_responses_quiz_attempt_id_foreign` FOREIGN KEY (`quiz_attempt_id`) REFERENCES `quiz_attempts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quiz_responses_quiz_option_id_foreign` FOREIGN KEY (`quiz_option_id`) REFERENCES `quiz_options` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quiz_responses_quiz_question_id_foreign` FOREIGN KEY (`quiz_question_id`) REFERENCES `quiz_questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `report_template_indicators`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `report_template_indicators` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `report_template_id` bigint unsigned NOT NULL,
  `indicator_id` bigint unsigned NOT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `is_required` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `report_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `report_templates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `report_type` enum('newborn','pediatric','general') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `frequency` enum('monthly','quarterly','annually') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'monthly',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `dhis2_mapping` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `report_templates_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `resource_access_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `resource_access_groups` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `resource_id` bigint unsigned NOT NULL,
  `access_group_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `resource_access_groups_resource_id_access_group_id_unique` (`resource_id`,`access_group_id`),
  KEY `resource_access_groups_access_group_id_foreign` (`access_group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `resource_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `resource_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `parent_id` bigint unsigned DEFAULT NULL,
  `image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `resource_categories_slug_unique` (`slug`),
  KEY `resource_categories_parent_id_foreign` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `resource_comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `resource_comments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `resource_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `parent_id` bigint unsigned DEFAULT NULL,
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `author_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `author_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_approved` tinyint(1) NOT NULL DEFAULT '0',
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `resource_comments_user_id_foreign` (`user_id`),
  KEY `resource_comments_parent_id_foreign` (`parent_id`),
  KEY `resource_comments_resource_id_is_approved_index` (`resource_id`,`is_approved`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `resource_counties`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `resource_counties` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `resource_id` bigint unsigned NOT NULL,
  `county_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `resource_counties_resource_id_county_id_unique` (`resource_id`,`county_id`),
  KEY `resource_counties_county_id_foreign` (`county_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `resource_departments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `resource_departments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `resource_id` bigint unsigned NOT NULL,
  `department_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `resource_departments_resource_id_department_id_unique` (`resource_id`,`department_id`),
  KEY `resource_departments_department_id_foreign` (`department_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `resource_downloads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `resource_downloads` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `resource_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `resource_downloads_user_id_foreign` (`user_id`),
  KEY `resource_downloads_resource_id_created_at_index` (`resource_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `resource_facilities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `resource_facilities` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `resource_id` bigint unsigned NOT NULL,
  `facility_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `resource_facilities_resource_id_facility_id_unique` (`resource_id`,`facility_id`),
  KEY `resource_facilities_facility_id_foreign` (`facility_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `resource_files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `resource_files` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `resource_id` bigint unsigned DEFAULT NULL,
  `original_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_size` bigint DEFAULT NULL,
  `file_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_primary` tinyint(1) DEFAULT '0',
  `sort_order` int DEFAULT '0',
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `resource_files_resource_id_is_primary_index` (`resource_id`,`is_primary`),
  KEY `resource_files_sort_order_index` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `resource_interactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `resource_interactions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `resource_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `type` enum('like','dislike','bookmark','share') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `resource_interactions_resource_id_user_id_type_unique` (`resource_id`,`user_id`,`type`),
  KEY `resource_interactions_user_id_foreign` (`user_id`),
  KEY `resource_interactions_resource_id_type_index` (`resource_id`,`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `resource_program_modules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `resource_program_modules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `resource_id` bigint unsigned NOT NULL,
  `program_module_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `resource_program_modules_resource_id_program_module_id_unique` (`resource_id`,`program_module_id`),
  KEY `resource_program_modules_program_module_id_foreign` (`program_module_id`),
  CONSTRAINT `resource_program_modules_program_module_id_foreign` FOREIGN KEY (`program_module_id`) REFERENCES `program_modules` (`id`) ON DELETE CASCADE,
  CONSTRAINT `resource_program_modules_resource_id_foreign` FOREIGN KEY (`resource_id`) REFERENCES `resources` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `resource_tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `resource_tags` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `resource_id` bigint unsigned NOT NULL,
  `tag_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `resource_tags_resource_id_tag_id_unique` (`resource_id`,`tag_id`),
  KEY `resource_tags_tag_id_foreign` (`tag_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `resource_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `resource_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `icon` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `color` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#3B82F6',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `resource_types_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `resource_views`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `resource_views` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `resource_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `referrer` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `resource_views_user_id_foreign` (`user_id`),
  KEY `resource_views_resource_id_created_at_index` (`resource_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `resources`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `resources` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `excerpt` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `content` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `meta_description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `featured_image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `resource_type_id` bigint unsigned NOT NULL,
  `category_id` bigint unsigned DEFAULT NULL,
  `author_id` bigint unsigned NOT NULL,
  `status` enum('draft','published','archived') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `visibility` enum('public','authenticated','restricted') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'public',
  `is_featured` tinyint(1) NOT NULL DEFAULT '0',
  `is_downloadable` tinyint(1) NOT NULL DEFAULT '0',
  `download_count` bigint unsigned NOT NULL DEFAULT '0',
  `view_count` bigint unsigned NOT NULL DEFAULT '0',
  `like_count` bigint unsigned NOT NULL DEFAULT '0',
  `dislike_count` bigint unsigned NOT NULL DEFAULT '0',
  `published_at` timestamp NULL DEFAULT NULL,
  `file_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_size` bigint unsigned DEFAULT NULL,
  `file_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `external_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `duration` int DEFAULT NULL,
  `difficulty_level` enum('beginner','intermediate','advanced') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `prerequisites` json DEFAULT NULL,
  `learning_outcomes` json DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `resources_slug_unique` (`slug`),
  KEY `resources_status_published_at_index` (`status`,`published_at`),
  KEY `resources_visibility_index` (`visibility`),
  KEY `resources_resource_type_id_index` (`resource_type_id`),
  KEY `resources_category_id_index` (`category_id`),
  KEY `resources_author_id_index` (`author_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `role_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_has_permissions` (
  `permission_id` bigint unsigned NOT NULL,
  `role_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `role_has_permissions_role_id_foreign` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `scope_role_access`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `scope_role_access` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `scope_id` bigint unsigned NOT NULL,
  `role_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `scope_role_access_scope_id_role_name_unique` (`scope_id`,`role_name`),
  CONSTRAINT `scope_role_access_scope_id_foreign` FOREIGN KEY (`scope_id`) REFERENCES `scopes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `scopes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `scopes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `icon` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 0xF09F938B,
  `color` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#6366F1',
  `gradient` json NOT NULL,
  `tabs` json NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `scopes_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `serial_numbers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `serial_numbers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `inventory_item_id` bigint unsigned NOT NULL,
  `serial_number` varchar(255) NOT NULL,
  `tag_number` varchar(255) DEFAULT NULL COMMENT 'Asset tag number for organization',
  `manufacturer_serial` varchar(255) DEFAULT NULL COMMENT 'Original manufacturer serial number',
  `model_number` varchar(255) DEFAULT NULL,
  `batch_lot_number` varchar(255) DEFAULT NULL,
  `status` enum('available','assigned','in_transit','damaged','lost','retired','maintenance','quarantine') DEFAULT 'available',
  `condition` enum('new','excellent','good','fair','poor','damaged','non_functional') DEFAULT 'new',
  `current_location_id` bigint unsigned DEFAULT NULL,
  `current_location_type` enum('main_store','facility','in_transit','field') DEFAULT 'main_store',
  `assigned_to_user_id` bigint unsigned DEFAULT NULL,
  `assigned_to_department_id` bigint unsigned DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL COMMENT 'Current GPS latitude',
  `longitude` decimal(11,8) DEFAULT NULL COMMENT 'Current GPS longitude',
  `last_tracked_at` timestamp NULL DEFAULT NULL,
  `tracking_accuracy` decimal(8,2) DEFAULT NULL COMMENT 'GPS accuracy in meters',
  `acquisition_date` date DEFAULT NULL,
  `acquisition_cost` decimal(12,2) DEFAULT NULL,
  `purchase_order_number` varchar(255) DEFAULT NULL,
  `supplier_id` bigint unsigned DEFAULT NULL,
  `warranty_start_date` date DEFAULT NULL,
  `warranty_expiry_date` date DEFAULT NULL,
  `warranty_provider` varchar(255) DEFAULT NULL,
  `warranty_terms` text,
  `extended_warranty` tinyint(1) DEFAULT '0',
  `last_service_date` date DEFAULT NULL,
  `next_service_date` date DEFAULT NULL,
  `service_interval_days` int DEFAULT NULL,
  `total_service_cost` decimal(12,2) DEFAULT '0.00',
  `service_count` int DEFAULT '0',
  `insurance_policy_number` varchar(255) DEFAULT NULL,
  `insured_value` decimal(12,2) DEFAULT NULL,
  `compliance_certifications` json DEFAULT NULL COMMENT 'List of compliance certificates',
  `last_inspection_date` date DEFAULT NULL,
  `next_inspection_date` date DEFAULT NULL,
  `specifications` json DEFAULT NULL COMMENT 'Technical specifications and features',
  `firmware_version` varchar(255) DEFAULT NULL,
  `software_version` varchar(255) DEFAULT NULL,
  `last_update_date` date DEFAULT NULL,
  `operating_conditions` json DEFAULT NULL COMMENT 'Temperature, humidity, etc. requirements',
  `usage_hours` decimal(10,2) DEFAULT NULL COMMENT 'Total usage hours',
  `usage_cycles` int DEFAULT NULL COMMENT 'Total usage cycles',
  `last_calibration_date` date DEFAULT NULL,
  `next_calibration_date` date DEFAULT NULL,
  `disposal_date` date DEFAULT NULL,
  `disposal_method` varchar(255) DEFAULT NULL,
  `disposal_notes` text,
  `disposal_value` decimal(12,2) DEFAULT NULL COMMENT 'Salvage or disposal value',
  `notes` text,
  `special_instructions` text,
  `custom_fields` json DEFAULT NULL COMMENT 'Additional custom data fields',
  `attachments` json DEFAULT NULL COMMENT 'File attachments like photos, documents',
  `created_by` bigint unsigned DEFAULT NULL,
  `last_updated_by` bigint unsigned DEFAULT NULL,
  `status_changed_at` timestamp NULL DEFAULT NULL,
  `location_changed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `serial_number` (`serial_number`),
  UNIQUE KEY `tag_number` (`tag_number`),
  KEY `assigned_to_department_id` (`assigned_to_department_id`),
  KEY `created_by` (`created_by`),
  KEY `last_updated_by` (`last_updated_by`),
  KEY `idx_item_status` (`inventory_item_id`,`status`),
  KEY `idx_status_condition` (`status`,`condition`),
  KEY `idx_location_type` (`current_location_id`,`current_location_type`),
  KEY `idx_user_status` (`assigned_to_user_id`,`status`),
  KEY `idx_warranty` (`warranty_expiry_date`,`status`),
  KEY `idx_service` (`next_service_date`,`status`),
  KEY `idx_inspection` (`next_inspection_date`,`status`),
  KEY `idx_acquisition` (`acquisition_date`,`status`),
  KEY `idx_geo` (`latitude`,`longitude`),
  KEY `idx_tracked` (`last_tracked_at`,`status`),
  KEY `idx_supplier_acquisition` (`supplier_id`,`acquisition_date`),
  KEY `idx_serial_number` (`serial_number`),
  KEY `idx_tag_number` (`tag_number`),
  KEY `idx_manufacturer_serial` (`manufacturer_serial`),
  KEY `idx_item_status_condition` (`inventory_item_id`,`status`,`condition`),
  KEY `idx_location_combo` (`current_location_type`,`current_location_id`,`status`),
  KEY `idx_user_dept_combo` (`assigned_to_user_id`,`assigned_to_department_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `session_attendance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `session_attendance` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `training_session_id` bigint unsigned NOT NULL,
  `training_participant_id` bigint unsigned NOT NULL,
  `present` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `session_attendance_training_session_id_foreign` (`training_session_id`),
  KEY `session_attendance_training_participant_id_foreign` (`training_participant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `session_inventories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `session_inventories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `training_session_id` bigint unsigned NOT NULL,
  `inventory_item_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `session_inventory_training_session_id_foreign` (`training_session_id`),
  KEY `session_inventory_inventory_item_id_foreign` (`inventory_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `session_materials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `session_materials` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `module_session_id` bigint unsigned NOT NULL,
  `material_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` int NOT NULL DEFAULT '1',
  `is_required` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `session_materials_module_session_id_index` (`module_session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `stock_levels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `stock_levels` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `facility_id` bigint unsigned NOT NULL,
  `inventory_item_id` bigint unsigned NOT NULL,
  `current_stock` int NOT NULL DEFAULT '0',
  `reserved_stock` int NOT NULL DEFAULT '0',
  `available_stock` int NOT NULL DEFAULT '0',
  `location` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `batch_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `condition` enum('new','good','fair','poor','damaged','expired','lost','stolen','decommissioned','disposed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'new',
  `serial_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `last_updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_facility_item_batch` (`facility_id`,`inventory_item_id`,`batch_number`),
  KEY `stock_levels_last_updated_by_foreign` (`last_updated_by`),
  KEY `stock_levels_facility_id_current_stock_index` (`facility_id`,`current_stock`),
  KEY `stock_levels_inventory_item_id_current_stock_index` (`inventory_item_id`,`current_stock`),
  KEY `stock_levels_expiry_date_index` (`expiry_date`),
  KEY `stock_levels_available_stock_index` (`available_stock`),
  KEY `stock_levels_condition_index` (`condition`),
  KEY `stock_levels_serial_number_index` (`serial_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `stock_request_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `stock_request_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `stock_request_id` bigint unsigned DEFAULT NULL,
  `inventory_item_id` bigint unsigned DEFAULT NULL,
  `quantity_requested` int DEFAULT NULL,
  `quantity_approved` int DEFAULT '0',
  `quantity_dispatched` int DEFAULT '0',
  `quantity_received` int DEFAULT '0',
  `balance_quantity` int DEFAULT '0',
  `unit_price` decimal(10,2) DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `stock_request_items_stock_request_id_inventory_item_id_index` (`stock_request_id`,`inventory_item_id`),
  KEY `stock_request_items_inventory_item_id_index` (`inventory_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `stock_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `stock_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `request_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `requesting_facility_id` bigint unsigned NOT NULL,
  `central_store_id` bigint unsigned NOT NULL,
  `requested_by` bigint unsigned NOT NULL,
  `approved_by` bigint unsigned DEFAULT NULL,
  `dispatched_by` bigint unsigned DEFAULT NULL,
  `received_by` bigint unsigned DEFAULT NULL,
  `status` enum('pending','approved','partially_approved','rejected','dispatched','partially_dispatched','received','partially_received','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `priority` enum('low','medium','high','urgent') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium',
  `request_date` date NOT NULL,
  `approved_date` date DEFAULT NULL,
  `dispatch_date` date DEFAULT NULL,
  `received_date` date DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `rejection_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `requires_approval` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `approval_level` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `total_requested_value` int DEFAULT NULL,
  `total_approved_value` int DEFAULT NULL,
  `total_dispatched_value` int DEFAULT NULL,
  `total_received_value` int DEFAULT NULL,
  `estimated_arrival` date DEFAULT NULL,
  `total_items` int NOT NULL DEFAULT '0',
  `total_value` decimal(12,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `stock_requests_request_number_unique` (`request_number`),
  KEY `stock_requests_approved_by_foreign` (`approved_by`),
  KEY `stock_requests_dispatched_by_foreign` (`dispatched_by`),
  KEY `stock_requests_received_by_foreign` (`received_by`),
  KEY `stock_requests_status_priority_index` (`status`,`priority`),
  KEY `stock_requests_requesting_facility_id_status_index` (`requesting_facility_id`,`status`),
  KEY `stock_requests_central_store_id_status_index` (`central_store_id`,`status`),
  KEY `stock_requests_request_date_status_index` (`request_date`,`status`),
  KEY `stock_requests_requested_by_index` (`requested_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `stock_transfer_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `stock_transfer_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `stock_transfer_id` bigint unsigned NOT NULL,
  `inventory_item_id` bigint unsigned NOT NULL,
  `quantity` int NOT NULL,
  `quantity_dispatched` int NOT NULL DEFAULT '0',
  `quantity_received` int NOT NULL DEFAULT '0',
  `unit_price` decimal(10,2) NOT NULL,
  `batch_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `stock_transfer_items_stock_transfer_id_inventory_item_id_index` (`stock_transfer_id`,`inventory_item_id`),
  KEY `stock_transfer_items_inventory_item_id_index` (`inventory_item_id`),
  KEY `stock_transfer_items_batch_number_index` (`batch_number`),
  KEY `stock_transfer_items_expiry_date_index` (`expiry_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `stock_transfers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `stock_transfers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `transfer_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `from_facility_id` bigint unsigned NOT NULL,
  `to_facility_id` bigint unsigned NOT NULL,
  `initiated_by` bigint unsigned NOT NULL,
  `approved_by` bigint unsigned DEFAULT NULL,
  `dispatched_by` bigint unsigned DEFAULT NULL,
  `received_by` bigint unsigned DEFAULT NULL,
  `status` enum('pending','approved','rejected','in_transit','delivered','cancelled','partially_received') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `priority` enum('low','medium','high','urgent') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium',
  `transfer_date` date NOT NULL,
  `approved_date` date DEFAULT NULL,
  `dispatch_date` date DEFAULT NULL,
  `received_date` date DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `rejection_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `requires_approval` tinyint(1) NOT NULL DEFAULT '1',
  `approval_level` enum('facility','regional','national') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'facility',
  `total_items` int NOT NULL DEFAULT '0',
  `total_value` decimal(12,2) NOT NULL DEFAULT '0.00',
  `tracking_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transport_method` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estimated_arrival` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `stock_transfers_transfer_number_unique` (`transfer_number`),
  KEY `stock_transfers_from_facility_id_foreign` (`from_facility_id`),
  KEY `stock_transfers_to_facility_id_foreign` (`to_facility_id`),
  KEY `stock_transfers_initiated_by_foreign` (`initiated_by`),
  KEY `stock_transfers_approved_by_foreign` (`approved_by`),
  KEY `stock_transfers_dispatched_by_foreign` (`dispatched_by`),
  KEY `stock_transfers_received_by_foreign` (`received_by`),
  KEY `stock_transfers_status_from_facility_id_index` (`status`,`from_facility_id`),
  KEY `stock_transfers_status_to_facility_id_index` (`status`,`to_facility_id`),
  KEY `stock_transfers_transfer_date_status_index` (`transfer_date`,`status`),
  KEY `stock_transfers_tracking_number_index` (`tracking_number`),
  KEY `stock_transfers_priority_status_index` (`priority`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `subcounties`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `subcounties` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `uid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `county_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `county_id` (`county_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `subcounty_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `subcounty_user` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `subcounty_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `subcounty_user_user_id_foreign` (`user_id`),
  KEY `subcounty_user_subcounty_id_foreign` (`subcounty_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `suppliers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `suppliers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `supplier_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `supplier_type` enum('manufacturer','distributor','wholesaler','retailer','government','ngo') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'distributor',
  `status` enum('active','inactive','suspended','blacklisted') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `contact_person` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `website` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `city` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `postal_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'KE',
  `tax_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `registration_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_terms` enum('cash_on_delivery','net_7','net_15','net_30','net_60','net_90') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'net_30',
  `credit_limit` decimal(12,2) NOT NULL DEFAULT '0.00',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_preferred` tinyint(1) NOT NULL DEFAULT '0',
  `requires_po` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `suppliers_supplier_code_unique` (`supplier_code`),
  KEY `suppliers_status_supplier_type_index` (`status`,`supplier_type`),
  KEY `suppliers_name_index` (`name`),
  KEY `suppliers_is_preferred_index` (`is_preferred`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tags` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `color` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#6B7280',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tags_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `topics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `topics` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `module_id` bigint unsigned NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `topics_module_id_foreign` (`module_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `training_assessment_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `training_assessment_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `training_id` bigint unsigned NOT NULL,
  `assessment_category_id` bigint unsigned NOT NULL,
  `pass_threshold` decimal(5,2) NOT NULL DEFAULT '70.00',
  `weight_percentage` decimal(5,2) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `is_required` tinyint(1) NOT NULL,
  `order_sequence` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `training_id_assm` (`training_id`,`assessment_category_id`),
  KEY `training_assessment_categories_training_id_index` (`training_id`),
  KEY `assessment_category_id` (`assessment_category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `training_counties`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `training_counties` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `training_id` bigint unsigned NOT NULL,
  `county_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `training_counties_training_id_county_id_unique` (`training_id`,`county_id`),
  KEY `training_counties_county_id_foreign` (`county_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `training_data`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `training_data` (
  `name` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `county` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `mfl_code` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `facility` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `level_of_care` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `department` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `cadre` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `phone` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `training_level` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `month` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `year` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `date` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `tot` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `trained_by` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `location` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `outcome` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `training_name` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `training_data_master`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `training_data_master` (
  `name` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `county` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `mfl_code` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `facility_id` bigint unsigned DEFAULT '0',
  `facility` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `facility_type_id` bigint unsigned DEFAULT '0',
  `level_of_care` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `department_id` bigint unsigned DEFAULT '0',
  `department` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `cadre_id` bigint unsigned DEFAULT '0',
  `cadre` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `phone` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `training_level` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `month` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `year` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `date` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `training_date` varchar(10) DEFAULT NULL,
  `is_tot` int NOT NULL DEFAULT '0',
  `trainer_id` bigint unsigned DEFAULT '0',
  `trained_by` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `location` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `grade_id` bigint unsigned DEFAULT '0',
  `outcome` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `training_name` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `identifier` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `training_departments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `training_departments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `training_id` bigint unsigned DEFAULT NULL,
  `department_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `training_id` (`training_id`),
  KEY `department_id` (`department_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `training_hospitals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `training_hospitals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `training_id` bigint unsigned NOT NULL,
  `facility_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `training_hospitals_training_id_facility_id_unique` (`training_id`,`facility_id`),
  KEY `training_hospitals_facility_id_foreign` (`facility_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `training_hotels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `training_hotels` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `training_id` bigint unsigned NOT NULL,
  `hotel_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `hotel_address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `hotel_contact` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `training_hotels_training_id_foreign` (`training_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `training_locations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `training_locations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `training_id` bigint unsigned NOT NULL,
  `location_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `training_locations_training_id_location_id_unique` (`training_id`,`location_id`),
  KEY `training_locations_training_id_index` (`training_id`),
  KEY `training_locations_location_id_index` (`location_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `training_materials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `training_materials` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `training_id` bigint unsigned NOT NULL,
  `inventory_item_id` bigint unsigned NOT NULL,
  `quantity_planned` int NOT NULL DEFAULT '0',
  `quantity_used` int NOT NULL DEFAULT '0',
  `returned_quantity` int NOT NULL DEFAULT '0',
  `unit_cost` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total_cost` decimal(10,2) NOT NULL DEFAULT '0.00',
  `usage_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `training_materials_inventory_item_id_foreign` (`inventory_item_id`),
  KEY `training_materials_training_id_inventory_item_id_index` (`training_id`,`inventory_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `training_methodologies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `training_methodologies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `training_id` bigint unsigned NOT NULL,
  `methodology_id` bigint unsigned NOT NULL,
  `is_active` bigint unsigned NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `training_methodologies_training_id_methodology_id_unique` (`training_id`,`methodology_id`),
  KEY `training_methodologies_methodology_id_foreign` (`methodology_id`),
  KEY `training_methodologies_training_id_methodology_id_index` (`training_id`,`methodology_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `training_modules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `training_modules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `training_id` bigint unsigned NOT NULL,
  `module_id` bigint unsigned NOT NULL,
  `order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `training_modules_training_id_module_id_unique` (`training_id`,`module_id`),
  KEY `training_modules_module_id_foreign` (`module_id`),
  KEY `training_modules_training_id_module_id_index` (`training_id`,`module_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `training_participants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `training_participants` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `training_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `attendance_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `completion_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `completion_date` date DEFAULT NULL,
  `registration_date` date DEFAULT NULL,
  `certificate_issued` tinyint(1) NOT NULL DEFAULT '0',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `department_id` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `cadre_id` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_tot` tinyint(1) DEFAULT '0',
  `outcome_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `training_participants_training_id_attendance_status_index` (`training_id`,`attendance_status`),
  KEY `training_participants_training_id_completion_status_index` (`training_id`,`completion_status`),
  KEY `training_participants_user_id_training_id_index` (`user_id`,`training_id`),
  KEY `outcome_id` (`outcome_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `training_participants_v2`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `training_participants_v2` (
  `id` bigint unsigned NOT NULL DEFAULT '0',
  `training_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cadre_id` bigint unsigned DEFAULT NULL,
  `department_id` bigint unsigned DEFAULT NULL,
  `mobile` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attendance_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `completion_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `completion_date` date DEFAULT NULL,
  `registration_date` date DEFAULT NULL,
  `certificate_issued` tinyint(1) NOT NULL DEFAULT '0',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_tot` tinyint(1) DEFAULT '0',
  `outcome_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `training_partners`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `training_partners` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `training_id` bigint unsigned NOT NULL,
  `partner_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `training_partners_training_id_partner_id_unique` (`training_id`,`partner_id`),
  KEY `training_partners_partner_id_foreign` (`partner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `training_programs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `training_programs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `training_id` bigint unsigned NOT NULL,
  `program_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `training_programs_training_id_program_id_unique` (`training_id`,`program_id`),
  KEY `training_programs_training_id_program_id_index` (`training_id`,`program_id`),
  KEY `program_id` (`program_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `training_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `training_sessions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `training_id` bigint unsigned NOT NULL,
  `module_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `session_time` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `methodology_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `training_sessions_training_id_foreign` (`training_id`),
  KEY `training_sessions_topic_id_foreign` (`module_id`),
  KEY `training_sessions_methodology_id_foreign` (`methodology_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `training_target_facilities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `training_target_facilities` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `training_id` bigint unsigned NOT NULL,
  `facility_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `training_target_facilities_training_id_facility_id_unique` (`training_id`,`facility_id`),
  KEY `training_target_facilities_facility_id_foreign` (`facility_id`),
  KEY `training_target_facilities_training_id_facility_id_index` (`training_id`,`facility_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `trainings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `trainings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `facility_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `program_id` bigint unsigned DEFAULT NULL,
  `location` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `organizer_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '',
  `registration_deadline` date DEFAULT NULL,
  `prerequisites` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '',
  `lead_type` enum('national','county','partner','facility') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'national',
  `lead_division_id` bigint unsigned DEFAULT NULL,
  `lead_county_id` bigint unsigned DEFAULT NULL,
  `county_id` bigint unsigned DEFAULT NULL,
  `lead_partner_id` bigint unsigned DEFAULT NULL,
  `deleted_by` bigint unsigned DEFAULT NULL,
  `approach` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '',
  `status` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'active',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `identifier` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `mentor_id` int DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `max_participants` int DEFAULT NULL,
  `target_audience` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `learning_outcomes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `training_approaches` json DEFAULT NULL,
  `completion_criteria` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `materials_needed` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `notes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `is_pilot` tinyint(1) NOT NULL DEFAULT '0',
  `assess_participants` tinyint DEFAULT '0',
  `provide_materials` tinyint DEFAULT '0',
  `approved_training_area_id` bigint DEFAULT NULL,
  `location_type` enum('hospital','hotel','online') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `online_link` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `deletion_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  PRIMARY KEY (`id`),
  KEY `trainings_lead_division_id_foreign` (`lead_division_id`),
  KEY `trainings_lead_county_id_foreign` (`lead_county_id`),
  KEY `trainings_lead_partner_id_foreign` (`lead_partner_id`),
  KEY `trainings_lead_type_lead_division_id_index` (`lead_type`,`lead_division_id`),
  KEY `trainings_lead_type_lead_county_id_index` (`lead_type`,`lead_county_id`),
  KEY `trainings_lead_type_lead_partner_id_index` (`lead_type`,`lead_partner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `trainings_v2`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `trainings_v2` (
  `id` bigint unsigned NOT NULL DEFAULT '0',
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `facility_id` bigint unsigned DEFAULT NULL,
  `program_id` bigint unsigned DEFAULT NULL,
  `organizer_id` bigint unsigned DEFAULT NULL,
  `location` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `registration_deadline` date DEFAULT NULL,
  `prerequisites` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `approach` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `notes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `identifier` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `mentor_id` bigint unsigned DEFAULT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `max_participants` bigint unsigned DEFAULT NULL,
  `target_audience` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `learning_outcomes` json DEFAULT NULL,
  `training_approaches` json DEFAULT NULL,
  `completion_criteria` json DEFAULT NULL,
  `materials_needed` json DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `transfer_tracking_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `transfer_tracking_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `stock_transfer_id` bigint unsigned NOT NULL,
  `event_type` enum('created','approved','rejected','dispatched','in_transit','location_update','delivered','received') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `transfer_tracking_events_created_by_foreign` (`created_by`),
  KEY `transfer_tracking_events_stock_transfer_id_event_type_index` (`stock_transfer_id`,`event_type`),
  KEY `transfer_tracking_events_latitude_longitude_index` (`latitude`,`longitude`),
  KEY `transfer_tracking_events_created_at_index` (`created_at`),
  KEY `transfer_tracking_events_event_type_index` (`event_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `facility_id` bigint unsigned DEFAULT NULL,
  `department_id` bigint unsigned DEFAULT NULL,
  `cadre_id` bigint unsigned DEFAULT NULL,
  `role` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'mentee',
  `first_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `middle_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `can_create_mentorships` tinyint(1) NOT NULL DEFAULT '0',
  `supervisor_id` bigint unsigned DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remember_token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `users_facility_id_foreign` (`facility_id`),
  KEY `users_department_id_foreign` (`department_id`),
  KEY `users_cadre_id_foreign` (`cadre_id`),
  KEY `users_email_unique` (`email`),
  KEY `users_id_number_unique` (`id_number`),
  KEY `name` (`name`),
  KEY `id_number` (`id_number`),
  KEY `users_supervisor_id_foreign` (`supervisor_id`),
  CONSTRAINT `users_supervisor_id_foreign` FOREIGN KEY (`supervisor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (2,'0001_01_01_000001_create_cache_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (3,'0001_01_01_000002_create_jobs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (4,'2025_07_04_204654_create_facility_types_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (5,'2025_07_04_204750_create_divisions_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (6,'2025_07_04_204822_create_counties_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (7,'2025_07_04_204922_create_subcounties_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (8,'2025_07_04_205005_create_facilities_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (9,'2025_07_04_205042_create_departments_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (10,'2025_07_04_205112_create_cadres_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (11,'2025_07_04_205113_create_users_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (13,'2025_07_04_211114_create_permission_tables',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (14,'2025_07_05_135831_create_grades_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (15,'2025_07_05_142642_create_county_user_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (16,'2025_07_05_142825_create_subcounty_user_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (17,'2025_07_05_142854_create_facility_user_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (19,'2025_07_05_183440_create_programs_table',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (20,'2025_07_05_183441_create_modules_table',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (21,'2025_07_05_183442_create_trainings_table',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (22,'2025_07_05_191533_create_topics_table',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (24,'2025_07_05_193225_create_methodologies_table',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (25,'2025_07_05_215848_create_categories_table',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (27,'2025_07_05_220129_create_locations_table',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (34,'2025_07_07_101016_create_knowledge_base_articles_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (35,'2025_07_07_101018_create_knowledge_base_categories_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (36,'2025_07_07_101020_create_knowledge_base_tags_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (37,'2025_07_07_101022_create_knowledge_base_attachments_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (38,'2025_07_07_101023_create_article_program_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (39,'2025_07_07_101025_create_article_tag_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (40,'2025_07_07_171933_create_training_participants_table',12);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (41,'2025_07_07_171935_create_training_departments_table',12);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (43,'2025_07_07_171937_create_training_sessions_table',13);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (44,'2025_07_07_171939_create_session_attendances_table',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (45,'2025_07_07_171940_create_objectives_table',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (46,'2025_07_07_171942_create_participant_objective_results_table',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (47,'2025_07_07_171944_create_session_inventories_table',15);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (60,'2025_07_26_034726_create_training_updates',19);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (75,'2025_07_30_203559_create_transfer_tracking_events_table',20);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (78,'2025_07_05_220018_create_suppliers_table',21);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (79,'2025_07_28_220205_create_inventory_categories_table',21);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (80,'2025_07_28_230447_create_inventory_items_table',21);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (81,'2025_07_30_203541_create_stock_levels_table',21);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (82,'2025_07_30_203544_create_stock_requests_table',21);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (83,'2025_07_30_203547_create_stock_request_items_table',21);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (84,'2025_07_30_203550_create_stock_transfers_table',21);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (85,'2025_07_30_203553_create_stock_transfer_items_table',21);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (86,'2025_07_30_203558_create_inventory_transactions_table',21);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (87,'2025_07_31_104931_create_item_status_logs_table',21);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (88,'2025_08_01_192949_create_notifications_table',22);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (89,'2025_08_02_061826_create_resource_types_table',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (90,'2025_08_02_061827_create_resource_categories_table',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (91,'2025_08_02_061828_create_resources_table',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (92,'2025_08_02_061830_create_tags_table',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (93,'2025_08_02_061832_create_resource_comments_table',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (94,'2025_08_02_061834_create_resource_interactions_table',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (95,'2025_08_02_061835_create_resource_views_table',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (96,'2025_08_02_061837_create_resource_downloads_table',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (97,'2025_08_02_061839_create_access_groups_table',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (98,'2025_08_02_061857_create_resource_tags_table',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (99,'2025_08_02_061859_create_access_group_users_table',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (100,'2025_08_02_061901_create_resource_access_groups_table',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (101,'2025_08_02_061903_create_resource_facilities_table',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (102,'2025_08_02_061905_create_resource_counties_table',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (103,'2025_08_02_061939_create_resource_departments_table',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (105,'2025_08_03_183151_create_training_materials_table',24);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (106,'2025_08_03_183152_create_assessment_categories_table',24);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (107,'2025_08_03_183153_create_mentee_assessment_results_table',25);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (108,'2025_08_03_183157_create_mentee_status_logs_table',25);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (111,'2025_08_04_103911_create_global_assessment_categories_table',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (112,'2025_08_04_103912_create_training_assessment_categories_table',27);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (113,'2025_08_04_103913_update_training_assessment_results_table',27);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (114,'2025_08_20_081656_create_partners_table',28);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (115,'2025_08_20_081818_add_lead_fields_to_trainings_table',28);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (116,'2025_08_21_131130_create_locations_pivot_table',29);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (117,'2025_08_24_092043_create_mentee_statuses_table',30);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (118,'2025_08_24_105622_create_mentee_placement_logs_table',31);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (122,'2025_09_02_165704_create_activity_log_table',32);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (123,'2025_09_02_165705_add_event_column_to_activity_log_table',32);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (124,'2025_09_02_165706_add_batch_uuid_column_to_activity_log_table',32);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (125,'2025_09_02_175734_create_resource_files_table',32);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (126,'2025_09_10_051427_create_participant_status_logs_table',33);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (127,'2025_11_29_104556_create_facility_levels_table',34);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (128,'2025_11_29_104623_create_facility_ownerships_table',34);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (129,'2025_11_29_115039_update_facilities_table_add_assessment_columns',35);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (130,'2025_11_29_120453_create_assessment_types_table',36);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (131,'2025_11_29_120454_create_assessment_sections_table',36);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (132,'2025_11_29_120455_create_assessment_questions_table',36);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (133,'2025_11_29_120456_create_assessments_table',36);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (134,'2025_11_29_120458_create_assessment_responses_table',36);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (148,'2025_12_01_064530_create_assessments_table',37);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (149,'2025_12_01_064531_create_assessment_sections_table',37);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (150,'2025_12_01_064532_create_assessment_questions_table',37);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (151,'2025_12_01_064533_create_assessment_question_responses_table',37);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (152,'2025_12_01_064534_create_assessment_section_scores_table',37);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (153,'2025_12_01_064535_create_assessment_departments_table',37);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (154,'2025_12_01_064535_create_cadres_table',37);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (155,'2025_12_01_064536_create_human_resource_responses_table',37);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (156,'2025_12_01_064537_create_commodity_categories_table',37);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (157,'2025_12_01_064539_create_commodities_table',37);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (158,'2025_12_01_064540_create_commodity_applicability_table',37);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (159,'2025_12_01_064541_create_assessment_commodity_responses_table',37);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (160,'2025_12_01_064542_create_assessment_department_scores_table',37);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (161,'2025_12_01_160049_update_table_assessment_add_has_nbu_paediatric',38);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (162,'2025_12_01_163641_update_table_assessment_remove_old_nicu_beds',39);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (163,'2025_12_10_094741_add_hotel_and_modify_training_table',40);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (164,'2025_12_10_185911_add_mentorshhip_system_table',41);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (165,'2025_12_10_200656_add_mentee_progress_and_assessments_table',42);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (168,'2026_02_15_000003_add_revoked_and_invitation_token_to_co_mentors',43);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (169,'2026_02_15_000001_create_mentorship_module_usages_table',44);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (170,'2026_02_15_000002_create_class_attendances_table',45);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (171,'2026_02_20_164638_update_mentee_attendance_with_recommendation',46);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (194,'2026_02_27_084919_create_indicator_report_types_table',47);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (195,'2026_02_27_084920_create_indicator_frequencies_table',47);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (196,'2026_02_27_084920_create_indicator_groups_table',47);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (197,'2026_02_27_084921_create_facility_indicator_assignments_table',47);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (198,'2026_02_27_084921_create_indicators_table',48);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (199,'2026_02_27_084922_create_indicator_report_periods_table',49);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (200,'2026_02_27_084922_create_indicator_values_table',49);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (201,'2026_03_03_082118_assessment_team_management',50);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (202,'2026_03_03_151854_create_personal_access_tokens_table',51);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (203,'2026_03_29_120000_clear_mentorship_data_for_production_launch',52);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (204,'2026_03_30_120000_create_assessment_email_jobs_table',53);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (205,'2026_04_12_120000_clear_dummy_mentorship_data_except_production_67',54);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (206,'2026_04_14_000003_create_module_sessions_table',55);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (207,'2026_04_14_145824_add_missing_columns_to_trainings_table',56);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (208,'2026_04_14_000001_add_class_module_id_to_class_attendances',57);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (209,'2026_04_14_000001_add_soft_deletes_to_users_table',57);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (210,'2026_04_14_000002_add_soft_deletes_to_programs_table',57);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (211,'2026_04_14_144753_create_personal_access_tokens_table',57);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (212,'2026_04_23_064508_create_scopes_table',57);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (213,'2026_04_23_064511_create_scope_role_access_table',57);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (214,'2026_05_11_194111_add_feedback_tracking_to_assessments_table',58);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (215,'2026_05_12_000001_add_deletion_tracking_to_trainings_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (216,'2026_05_12_074028_add_trained_before_mentorship_to_assessments',60);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (217,'2026_05_13_113342_add_excluded_cadre_ids_to_assessments',61);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (218,'2026_05_13_150232_add_is_pilot_to_trainings',62);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (219,'2026_05_18_000001_create_resource_program_modules_table',63);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (220,'2026_06_21_111749_add_parent_id_to_program_modules_table',64);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (221,'2026_06_21_111749_create_activities_table',64);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (222,'2026_06_21_111749_create_program_module_activities_table',64);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (223,'2026_06_21_115119_create_program_module_contents_table',65);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (225,'2026_06_21_121022_create_program_module_quizzes_table',66);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (226,'2026_06_21_121023_create_quiz_questions_table',66);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (227,'2026_06_21_121024_create_quiz_options_table',67);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (228,'2026_06_21_121025_create_quiz_attempts_table',67);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (229,'2026_06_21_121026_create_quiz_responses_table',67);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (230,'2026_06_21_125159_add_start_and_end_dates_to_program_modules_table',68);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (231,'2026_06_21_120000_create_class_module_activity_participants_table',69);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (232,'2026_06_21_190000_add_start_end_date_to_class_modules_table',70);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (233,'2026_06_21_200000_add_quiz_attempts_and_video_to_mentee_module_progress',71);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (234,'2026_06_22_100000_add_completion_to_class_module_activity_participants',72);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (235,'2026_06_22_100001_add_video_review_to_mentee_module_progress',72);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (236,'2026_06_22_100002_add_certificate_approval_to_class_participants',72);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (237,'2026_06_22_110000_add_can_create_mentorships_to_users',73);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (238,'2026_06_22_085009_add_supervisor_id_to_users_table',74);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (239,'2026_06_23_193706_add_activation_to_programs_table',75);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (240,'2026_06_23_230920_normalise_role_names',76);
