
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
DROP TABLE IF EXISTS `acl_audit_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `acl_audit_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `actor_user_id` int DEFAULT NULL,
  `actor_email` varchar(190) DEFAULT NULL,
  `actor_role` varchar(80) DEFAULT NULL,
  `target_role` varchar(80) NOT NULL,
  `perm_key` varchar(150) NOT NULL,
  `previous_state` varchar(40) NOT NULL,
  `new_state` varchar(40) NOT NULL,
  `reason_text` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_acl_audit_created` (`created_at`),
  KEY `idx_acl_audit_role` (`target_role`),
  KEY `idx_acl_audit_perm` (`perm_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `acl_role_permission_overrides`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `acl_role_permission_overrides` (
  `id` int NOT NULL AUTO_INCREMENT,
  `role` varchar(50) NOT NULL,
  `perm_key` varchar(150) NOT NULL,
  `state` enum('allow','deny') NOT NULL,
  `reason_text` varchar(255) DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_acl_override` (`role`,`perm_key`),
  KEY `idx_acl_override_role` (`role`),
  KEY `idx_acl_override_perm` (`perm_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `audit_activity_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_activity_log` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `entity_type` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` int NOT NULL,
  `event_type` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `action_name` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `actor_user_id` int DEFAULT NULL,
  `actor_email` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `actor_display_name` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `app_key` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `module_key` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `old_state` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `new_state` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reason_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `note_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `diff_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `metadata_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_entity` (`entity_type`,`entity_id`,`created_at`),
  KEY `idx_audit_event_type` (`event_type`,`created_at`),
  KEY `idx_audit_action` (`action_name`,`created_at`),
  KEY `idx_audit_actor` (`actor_user_id`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=58 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `base_field_registry`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `base_field_registry` (
  `id` int NOT NULL AUTO_INCREMENT,
  `module_key` varchar(80) NOT NULL,
  `field_key` varchar(120) NOT NULL,
  `column_name` varchar(120) NOT NULL,
  `label` varchar(160) NOT NULL,
  `data_type` varchar(40) NOT NULL,
  `max_length` int DEFAULT NULL,
  `precision_value` int DEFAULT NULL,
  `scale_value` int DEFAULT NULL,
  `is_required` tinyint(1) NOT NULL DEFAULT '0',
  `default_value` varchar(255) DEFAULT NULL,
  `options_text` text,
  `is_visible` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int NOT NULL DEFAULT '100',
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `last_synced_at` datetime DEFAULT NULL,
  `created_by` varchar(190) DEFAULT NULL,
  `updated_by` varchar(190) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_base_field_module_column` (`module_key`,`column_name`),
  UNIQUE KEY `uniq_base_field_module_field_key` (`module_key`,`field_key`),
  KEY `idx_base_field_module` (`module_key`),
  KEY `idx_base_field_status` (`status`),
  KEY `idx_base_field_visible` (`is_visible`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `base_module_registry`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `base_module_registry` (
  `id` int NOT NULL AUTO_INCREMENT,
  `module_key` varchar(80) NOT NULL,
  `label` varchar(150) NOT NULL,
  `table_name` varchar(120) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `module_key` (`module_key`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `base_schema_audit_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `base_schema_audit_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `action_type` varchar(60) NOT NULL,
  `module_key` varchar(80) NOT NULL,
  `field_id` int DEFAULT NULL,
  `payload_json` json DEFAULT NULL,
  `changed_by` varchar(190) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_base_audit_module` (`module_key`),
  KEY `idx_base_audit_action` (`action_type`),
  KEY `idx_base_audit_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `branding_assets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `branding_assets` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `asset_group` varchar(40) NOT NULL DEFAULT 'logo',
  `usage_key` varchar(64) NOT NULL DEFAULT 'primary',
  `variant_key` varchar(64) NOT NULL DEFAULT 'original',
  `display_name` varchar(190) DEFAULT NULL,
  `file_path` varchar(255) NOT NULL,
  `mime_type` varchar(120) DEFAULT NULL,
  `file_size_bytes` bigint NOT NULL DEFAULT '0',
  `pixel_width` int DEFAULT NULL,
  `pixel_height` int DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `source_kind` varchar(40) NOT NULL DEFAULT 'upload',
  `created_by` varchar(190) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_branding_assets_company` (`company_id`),
  KEY `idx_branding_assets_usage` (`company_id`,`asset_group`,`usage_key`,`is_active`),
  KEY `idx_branding_assets_created` (`created_at`),
  CONSTRAINT `fk_branding_assets_company` FOREIGN KEY (`company_id`) REFERENCES `org_companies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `core_app_hooks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `core_app_hooks` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `app_key` varchar(120) NOT NULL,
  `hook_type` varchar(50) NOT NULL,
  `hook_key` varchar(190) NOT NULL,
  `payload_json` json DEFAULT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_core_app_hooks_app_key` (`app_key`),
  KEY `idx_core_app_hooks_lookup` (`hook_type`,`hook_key`)
) ENGINE=InnoDB AUTO_INCREMENT=4886163 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `core_app_migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `core_app_migrations` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `app_key` varchar(120) NOT NULL,
  `version` varchar(50) NOT NULL,
  `migration_name` varchar(255) NOT NULL,
  `checksum` varchar(128) DEFAULT NULL,
  `status` enum('applied','failed') NOT NULL DEFAULT 'applied',
  `error_text` text,
  `applied_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_core_app_migration` (`app_key`,`migration_name`),
  KEY `idx_core_app_migrations_app_key` (`app_key`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `core_app_modules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `core_app_modules` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `app_key` varchar(120) NOT NULL,
  `module_key` varchar(120) NOT NULL,
  `module_name` varchar(190) NOT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_app_module` (`app_key`,`module_key`),
  KEY `idx_core_app_modules_app_key` (`app_key`)
) ENGINE=InnoDB AUTO_INCREMENT=467193 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `core_app_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `core_app_permissions` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `app_key` varchar(120) NOT NULL,
  `permission_key` varchar(190) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_core_app_permission` (`app_key`,`permission_key`),
  KEY `idx_core_app_permissions_app_key` (`app_key`)
) ENGINE=InnoDB AUTO_INCREMENT=324802 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `core_apps`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `core_apps` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `app_key` varchar(120) NOT NULL,
  `app_name` varchar(190) NOT NULL,
  `version` varchar(50) NOT NULL,
  `app_type` varchar(50) NOT NULL,
  `status` enum('uploaded','installed','enabled','disabled','broken','upgrade_pending','uninstalled') NOT NULL DEFAULT 'uploaded',
  `install_path` varchar(255) NOT NULL,
  `manifest_json` json NOT NULL,
  `checksum` varchar(128) DEFAULT NULL,
  `installed_at` timestamp NULL DEFAULT NULL,
  `enabled_at` timestamp NULL DEFAULT NULL,
  `disabled_at` timestamp NULL DEFAULT NULL,
  `installed_by` varchar(190) DEFAULT NULL,
  `error_text` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `app_key` (`app_key`),
  KEY `idx_core_apps_status` (`status`),
  KEY `idx_core_apps_type` (`app_type`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `core_demo_data_state`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `core_demo_data_state` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `suite_key` varchar(80) NOT NULL,
  `demo_status` varchar(40) NOT NULL DEFAULT 'not_loaded',
  `profile_key` varchar(120) DEFAULT NULL,
  `last_action` varchar(40) DEFAULT NULL,
  `last_seeded_at` datetime DEFAULT NULL,
  `last_reset_at` datetime DEFAULT NULL,
  `seeded_by` varchar(190) DEFAULT NULL,
  `warning_text` text,
  `warnings_json` longtext,
  `blocking_issue_json` longtext,
  `row_counts_json` longtext,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `suite_key` (`suite_key`),
  KEY `idx_demo_suite_status` (`suite_key`,`demo_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `core_environment_portability_runs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `core_environment_portability_runs` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `operation_type` varchar(40) NOT NULL,
  `scope_key` varchar(80) NOT NULL,
  `source_environment` varchar(120) DEFAULT NULL,
  `target_environment` varchar(120) DEFAULT NULL,
  `snapshot_file_name` varchar(255) DEFAULT NULL,
  `snapshot_file_path` varchar(500) DEFAULT NULL,
  `preview_token` varchar(80) DEFAULT NULL,
  `status` varchar(40) NOT NULL DEFAULT 'created',
  `warning_text` text,
  `error_text` text,
  `summary_json` longtext,
  `created_by` varchar(190) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_env_preview_token` (`preview_token`),
  KEY `idx_env_runs_created` (`created_at`),
  KEY `idx_env_runs_operation` (`operation_type`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `core_export_runs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `core_export_runs` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `target_type` varchar(40) NOT NULL,
  `target_key` varchar(120) NOT NULL,
  `suite_key` varchar(80) DEFAULT NULL,
  `export_type` varchar(80) NOT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `status` varchar(40) NOT NULL DEFAULT 'completed',
  `warning_text` text,
  `error_text` text,
  `summary_json` longtext,
  `created_by` varchar(190) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_export_target` (`target_type`,`target_key`),
  KEY `idx_export_suite` (`suite_key`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `core_import_runs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `core_import_runs` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `suite_key` varchar(80) NOT NULL,
  `import_type` varchar(120) NOT NULL,
  `source_file` varchar(255) DEFAULT NULL,
  `status` varchar(40) NOT NULL DEFAULT 'previewed',
  `preview_token` varchar(80) DEFAULT NULL,
  `valid_rows` int NOT NULL DEFAULT '0',
  `invalid_rows` int NOT NULL DEFAULT '0',
  `skipped_rows` int NOT NULL DEFAULT '0',
  `duplicate_rows` int NOT NULL DEFAULT '0',
  `imported_rows` int NOT NULL DEFAULT '0',
  `failed_rows` int NOT NULL DEFAULT '0',
  `warning_text` text,
  `error_text` text,
  `summary_json` longtext,
  `created_by` varchar(190) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_import_preview_token` (`preview_token`),
  KEY `idx_import_suite_created` (`suite_key`,`created_at`),
  KEY `idx_import_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `core_release_runs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `core_release_runs` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `package_name` varchar(255) DEFAULT NULL,
  `package_path` varchar(500) DEFAULT NULL,
  `scope_key` varchar(80) NOT NULL,
  `release_version` varchar(80) NOT NULL,
  `included_suites_json` longtext,
  `included_modules_json` longtext,
  `notes_text` text,
  `preview_token` varchar(80) DEFAULT NULL,
  `status` varchar(40) NOT NULL DEFAULT 'previewed',
  `warning_text` text,
  `error_text` text,
  `summary_json` longtext,
  `created_by` varchar(190) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_release_preview_token` (`preview_token`),
  KEY `idx_release_created` (`created_at`),
  KEY `idx_release_scope` (`scope_key`,`release_version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `core_restore_runs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `core_restore_runs` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `target_type` varchar(40) NOT NULL,
  `target_key` varchar(120) NOT NULL,
  `suite_key` varchar(80) DEFAULT NULL,
  `restore_type` varchar(80) NOT NULL,
  `source_backup` varchar(255) DEFAULT NULL,
  `preview_token` varchar(80) DEFAULT NULL,
  `status` varchar(40) NOT NULL DEFAULT 'previewed',
  `warning_text` text,
  `error_text` text,
  `rollback_attempted` tinyint(1) NOT NULL DEFAULT '0',
  `summary_json` longtext,
  `created_by` varchar(190) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_restore_preview_token` (`preview_token`),
  KEY `idx_restore_suite` (`suite_key`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `core_schema_snapshots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `core_schema_snapshots` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `app_key` varchar(120) NOT NULL,
  `app_version` varchar(50) NOT NULL,
  `snapshot_json` longtext NOT NULL,
  `checksum` varchar(128) DEFAULT NULL,
  `created_by` varchar(190) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_core_schema_snapshots_app_key` (`app_key`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `core_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `core_settings` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(190) NOT NULL,
  `setting_value` longtext,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=107 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `core_setup_runs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `core_setup_runs` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `target_type` varchar(40) NOT NULL,
  `target_key` varchar(190) NOT NULL,
  `action_key` varchar(80) NOT NULL,
  `status` enum('pending','running','installed','configured','verified','failed','rolled_back','partial') NOT NULL DEFAULT 'pending',
  `rollback_performed` tinyint(1) NOT NULL DEFAULT '0',
  `warnings_json` longtext,
  `error_text` text,
  `manual_attention_text` text,
  `resume_action` varchar(80) DEFAULT NULL,
  `retry_action` varchar(80) DEFAULT NULL,
  `repair_action` varchar(80) DEFAULT NULL,
  `run_meta_json` longtext,
  `started_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `finished_at` timestamp NULL DEFAULT NULL,
  `created_by` varchar(190) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_setup_runs_target` (`target_type`,`target_key`),
  KEY `idx_setup_runs_started` (`started_at`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `core_setup_steps`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `core_setup_steps` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `run_id` bigint NOT NULL,
  `step_key` varchar(120) NOT NULL,
  `step_label` varchar(190) NOT NULL,
  `step_order` int NOT NULL DEFAULT '0',
  `status` enum('pending','running','installed','configured','verified','failed','rolled_back','partial') NOT NULL DEFAULT 'pending',
  `rollback_performed` tinyint(1) NOT NULL DEFAULT '0',
  `result_text` text,
  `error_text` text,
  `warnings_json` longtext,
  `manual_attention_text` text,
  `step_meta_json` longtext,
  `started_at` timestamp NULL DEFAULT NULL,
  `finished_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_setup_steps_run` (`run_id`,`step_order`)
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `daily_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `daily_orders` (
  `id` int NOT NULL AUTO_INCREMENT,
  `order_date` date NOT NULL,
  `required_date` date DEFAULT NULL,
  `customer_name` varchar(190) NOT NULL,
  `product_id` int NOT NULL,
  `qty` decimal(14,2) NOT NULL DEFAULT '0.00',
  `dispatch_deadline` datetime DEFAULT NULL,
  `coverage_pct` decimal(8,2) NOT NULL DEFAULT '0.00',
  `coverage_status` varchar(30) NOT NULL DEFAULT 'Low',
  `shortage_qty` decimal(14,2) NOT NULL DEFAULT '0.00',
  `planned_supply_qty` decimal(14,2) NOT NULL DEFAULT '0.00',
  `usable_stock_qty` decimal(14,2) NOT NULL DEFAULT '0.00',
  `qc_pass_qty` decimal(14,2) NOT NULL DEFAULT '0.00',
  `dispatched_qty` decimal(14,2) NOT NULL DEFAULT '0.00',
  `usable_supply_qty` decimal(14,2) NOT NULL DEFAULT '0.00',
  `open_demand_qty` decimal(14,2) NOT NULL DEFAULT '0.00',
  `forecast_pressure_qty` decimal(14,2) NOT NULL DEFAULT '0.00',
  `coverage_last_recalculated_at` datetime DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'Open',
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_daily_orders_product` (`product_id`),
  KEY `idx_daily_orders_dates` (`order_date`,`required_date`),
  KEY `idx_daily_orders_status` (`status`),
  KEY `idx_daily_orders_customer` (`customer_name`),
  KEY `idx_daily_orders_recalc` (`coverage_last_recalculated_at`)
) ENGINE=InnoDB AUTO_INCREMENT=63 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `data_control_approvals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `data_control_approvals` (
  `id` int NOT NULL AUTO_INCREMENT,
  `table_name` varchar(255) NOT NULL,
  `operation` varchar(50) NOT NULL,
  `where_clause` longtext,
  `set_clause` text,
  `affected_rows` int DEFAULT '0',
  `requested_by` int DEFAULT NULL,
  `requested_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `status` varchar(50) DEFAULT 'pending',
  `approved_by` int DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `executed_by` int DEFAULT NULL,
  `executed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_table` (`table_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dispatch_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `dispatch_entries` (
  `id` int NOT NULL AUTO_INCREMENT,
  `dispatch_date` date NOT NULL,
  `daily_order_id` int DEFAULT NULL,
  `production_plan_id` int DEFAULT NULL,
  `production_entry_id` int DEFAULT NULL,
  `qc_entry_id` int DEFAULT NULL,
  `product_id` int NOT NULL,
  `dispatchable_qty` decimal(14,2) NOT NULL DEFAULT '0.00',
  `destination` varchar(190) DEFAULT NULL,
  `dispatch_type` varchar(50) NOT NULL DEFAULT 'Regular',
  `dispatch_status` varchar(30) NOT NULL DEFAULT 'Ready',
  `remarks` text,
  `status_reason` varchar(190) DEFAULT NULL,
  `status_note` text,
  `released_at` datetime DEFAULT NULL,
  `blocked_at` datetime DEFAULT NULL,
  `dispatched_at` datetime DEFAULT NULL,
  `last_transition_at` datetime DEFAULT NULL,
  `status_updated_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `workflow_state` varchar(40) DEFAULT NULL,
  `approval_status` varchar(40) NOT NULL DEFAULT 'Draft',
  `approved_by` int DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `approval_note` text,
  `locked_by` int DEFAULT NULL,
  `locked_at` datetime DEFAULT NULL,
  `reopened_by` int DEFAULT NULL,
  `reopened_at` datetime DEFAULT NULL,
  `reopen_reason` text,
  `override_reason` text,
  `cases_count` int NOT NULL DEFAULT '0',
  `pallets_count` int NOT NULL DEFAULT '0',
  `dispatch_mode` enum('in_house_dispatch','third_party_dispatch','company_origin_dispatch','third_party_direct_dispatch','third_party_to_company_then_destination') NOT NULL DEFAULT 'company_origin_dispatch',
  `delivery_flow` enum('company_to_destination','third_party_to_destination','third_party_to_company_to_destination') NOT NULL DEFAULT 'company_to_destination',
  `source_type` enum('in_house','third_party') NOT NULL DEFAULT 'in_house',
  `prepared_by` varchar(190) DEFAULT NULL,
  `prepared_at` datetime DEFAULT NULL,
  `dispatch_completed_by` varchar(190) DEFAULT NULL,
  `dispatch_completed_at` datetime DEFAULT NULL,
  `completion_status` enum('draft','ready','prepared','completed') NOT NULL DEFAULT 'draft',
  `third_party_reference` varchar(190) DEFAULT NULL,
  `eta_load_at` datetime DEFAULT NULL,
  `driver_name` varchar(190) DEFAULT NULL,
  `truck_no` varchar(80) DEFAULT NULL,
  `carrier_name` varchar(190) DEFAULT NULL,
  `load_reference` varchar(120) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_dispatch_entries_date` (`dispatch_date`),
  KEY `idx_dispatch_entries_status` (`dispatch_status`),
  KEY `idx_dispatch_entries_product` (`product_id`),
  KEY `idx_dispatch_entries_daily_order` (`daily_order_id`),
  KEY `idx_dispatch_entries_qc_entry` (`qc_entry_id`),
  KEY `idx_dispatch_entries_approval_status` (`approval_status`),
  KEY `idx_dispatch_entries_locked_at` (`locked_at`),
  KEY `idx_dispatch_entries_daily_order_status` (`daily_order_id`,`dispatch_status`),
  KEY `idx_dispatch_entries_qc_status` (`qc_entry_id`,`dispatch_status`),
  KEY `idx_dispatch_entries_workflow_state` (`workflow_state`),
  KEY `idx_dispatch_entries_completion_status` (`completion_status`),
  KEY `idx_dispatch_entries_mode` (`dispatch_mode`),
  KEY `idx_dispatch_entries_source_type` (`source_type`)
) ENGINE=InnoDB AUTO_INCREMENT=63 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dispatch_entry_transitions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `dispatch_entry_transitions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `dispatch_entry_id` int NOT NULL,
  `from_status` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `to_status` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `transition_reason` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transition_note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `performed_by` int DEFAULT NULL,
  `performed_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_dispatch_entry_transition_entry` (`dispatch_entry_id`),
  KEY `idx_dispatch_entry_transition_status` (`to_status`),
  KEY `idx_dispatch_entry_transition_performed_at` (`performed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dispatch_preparation_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `dispatch_preparation_logs` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `dispatch_entry_id` int DEFAULT NULL,
  `daily_order_id` int NOT NULL,
  `product_id` int NOT NULL,
  `dispatch_date` date NOT NULL,
  `prepared_qty` decimal(14,2) NOT NULL DEFAULT '0.00',
  `cases_count` int NOT NULL DEFAULT '0',
  `pallets_count` int NOT NULL DEFAULT '0',
  `note` text,
  `prepared_by` varchar(190) DEFAULT NULL,
  `prepared_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_dispatch_prep_order_date` (`daily_order_id`,`dispatch_date`),
  KEY `idx_dispatch_prep_entry` (`dispatch_entry_id`),
  KEY `idx_dispatch_prep_product_date` (`product_id`,`dispatch_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `handoff_tracking`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `handoff_tracking` (
  `id` int NOT NULL AUTO_INCREMENT,
  `entity_type` varchar(40) NOT NULL,
  `entity_id` int NOT NULL,
  `stage` varchar(50) NOT NULL,
  `owner_role` varchar(80) DEFAULT NULL,
  `owner_user_id` int DEFAULT NULL,
  `owner_display` varchar(190) DEFAULT NULL,
  `owner_assigned_at` datetime DEFAULT NULL,
  `owner_authority_role` varchar(40) DEFAULT NULL,
  `owner_profile_key` varchar(120) DEFAULT NULL,
  `ownership_state` varchar(40) DEFAULT NULL,
  `next_stage` varchar(80) DEFAULT NULL,
  `next_owner_role` varchar(80) DEFAULT NULL,
  `next_owner_profile` varchar(120) DEFAULT NULL,
  `entered_stage_at` datetime DEFAULT NULL,
  `ready_since` datetime DEFAULT NULL,
  `waiting_since` datetime DEFAULT NULL,
  `in_progress_since` datetime DEFAULT NULL,
  `blocked_since` datetime DEFAULT NULL,
  `released_since` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `escalation_level` varchar(10) DEFAULT NULL,
  `escalation_state` varchar(80) DEFAULT NULL,
  `escalated_at` datetime DEFAULT NULL,
  `sla_warning_hours` int DEFAULT NULL,
  `sla_breach_hours` int DEFAULT NULL,
  `sla_state` varchar(20) DEFAULT NULL,
  `notes` text,
  `updated_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_handoff_target` (`entity_type`,`entity_id`,`stage`),
  KEY `idx_handoff_stage` (`stage`),
  KEY `idx_handoff_owner_role` (`owner_role`),
  KEY `idx_handoff_escalated_at` (`escalated_at`),
  KEY `idx_handoff_state` (`ownership_state`),
  KEY `idx_handoff_sla_state` (`sla_state`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `identity_security_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `identity_security_events` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `event_type` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int DEFAULT NULL,
  `email_mask` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_hash` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_hash` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `outcome` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `metadata_json` longtext COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_identity_event_type` (`event_type`,`created_at`),
  KEY `idx_identity_user` (`user_id`,`created_at`),
  KEY `idx_identity_email_hash` (`email_hash`,`created_at`),
  KEY `idx_identity_ip_hash` (`ip_hash`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `installed_plugins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `installed_plugins` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `version` varchar(20) NOT NULL,
  `type` enum('engine','business') DEFAULT 'business',
  `status` enum('active','inactive') DEFAULT 'active',
  `requires_json` json DEFAULT NULL,
  `installed_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `machines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `machines` (
  `id` int NOT NULL AUTO_INCREMENT,
  `machine_no` varchar(100) NOT NULL,
  `machine_name` varchar(190) NOT NULL,
  `section` varchar(120) DEFAULT NULL,
  `machine_group` varchar(50) DEFAULT NULL,
  `machine_type` varchar(80) DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'Available',
  `capacity_per_hour` decimal(12,2) DEFAULT NULL,
  `clamping_force_ton` decimal(10,2) DEFAULT NULL,
  `shot_capacity_g` decimal(10,2) DEFAULT NULL,
  `tie_bar_spacing_mm` varchar(80) DEFAULT NULL,
  `platen_size_mm` varchar(80) DEFAULT NULL,
  `min_mold_height_mm` decimal(10,2) DEFAULT NULL,
  `max_mold_height_mm` decimal(10,2) DEFAULT NULL,
  `preferred_materials` varchar(255) DEFAULT NULL,
  `notes` text,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_machine_no` (`machine_no`),
  KEY `idx_machines_status` (`status`),
  KEY `idx_machines_section` (`section`),
  KEY `idx_machines_active` (`is_active`),
  KEY `idx_machines_group` (`machine_group`),
  KEY `idx_machines_type` (`machine_type`)
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `material_capacity`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `material_capacity` (
  `id` int NOT NULL AUTO_INCREMENT,
  `material_id` int DEFAULT NULL,
  `location_code` varchar(120) NOT NULL,
  `slot_label` varchar(120) DEFAULT NULL,
  `max_capacity_qty` decimal(14,2) NOT NULL DEFAULT '0.00',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_material_capacity_slot` (`material_id`,`location_code`),
  KEY `idx_material_capacity_location` (`location_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `material_ledger`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `material_ledger` (
  `id` int NOT NULL AUTO_INCREMENT,
  `material_id` int NOT NULL,
  `movement_type` varchar(40) NOT NULL DEFAULT 'ADJUST',
  `qty_delta` decimal(14,2) NOT NULL DEFAULT '0.00',
  `reserved_delta` decimal(14,2) NOT NULL DEFAULT '0.00',
  `ledger_reference` varchar(120) DEFAULT NULL,
  `reference_type` varchar(80) DEFAULT NULL,
  `reference_id` int DEFAULT NULL,
  `notes` text,
  `updated_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_material_ledger_material` (`material_id`),
  KEY `idx_material_ledger_reference` (`reference_type`,`reference_id`),
  KEY `idx_material_ledger_created` (`created_at`),
  KEY `idx_material_ledger_ledger_reference` (`ledger_reference`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `material_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `material_orders` (
  `id` int NOT NULL AUTO_INCREMENT,
  `material_id` int NOT NULL,
  `order_reference` varchar(120) DEFAULT NULL,
  `supplier_name` varchar(190) DEFAULT NULL,
  `supplier_ref` varchar(120) DEFAULT NULL,
  `planned_qty` decimal(14,2) NOT NULL DEFAULT '0.00',
  `expected_delivery_date` date NOT NULL,
  `received_qty` decimal(14,2) NOT NULL DEFAULT '0.00',
  `status` varchar(40) NOT NULL DEFAULT 'planned',
  `unit_cost` decimal(14,4) NOT NULL DEFAULT '0.0000',
  `ordered_at` datetime DEFAULT NULL,
  `confirmed_at` datetime DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_material_orders_material` (`material_id`),
  KEY `idx_material_orders_status` (`status`),
  KEY `idx_material_orders_expected` (`expected_delivery_date`),
  KEY `idx_material_orders_reference` (`order_reference`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `material_reservations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `material_reservations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `material_id` int NOT NULL,
  `reservation_reference` varchar(120) DEFAULT NULL,
  `demand_source_type` varchar(80) DEFAULT NULL,
  `demand_source_id` int DEFAULT NULL,
  `reserved_qty` decimal(14,2) NOT NULL DEFAULT '0.00',
  `fulfilled_qty` decimal(14,2) NOT NULL DEFAULT '0.00',
  `required_by_date` date DEFAULT NULL,
  `status` varchar(40) NOT NULL DEFAULT 'active',
  `notes` text,
  `created_by` int DEFAULT NULL,
  `released_by` int DEFAULT NULL,
  `released_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_material_reservation_material` (`material_id`),
  KEY `idx_material_reservation_source` (`demand_source_type`,`demand_source_id`),
  KEY `idx_material_reservation_status` (`status`),
  KEY `idx_material_reservation_required` (`required_by_date`),
  KEY `idx_material_reservation_reference` (`reservation_reference`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `materials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `materials` (
  `id` int NOT NULL AUTO_INCREMENT,
  `material_number` varchar(100) DEFAULT NULL,
  `material_code` varchar(100) NOT NULL,
  `material_name` varchar(255) NOT NULL,
  `material_type` varchar(40) NOT NULL DEFAULT '材料',
  `material_subtype` varchar(80) DEFAULT NULL,
  `vendor_id` int DEFAULT NULL,
  `vendor_name` varchar(190) DEFAULT NULL,
  `uom` varchar(40) DEFAULT NULL,
  `pack_size` decimal(14,4) DEFAULT NULL,
  `material_grade` varchar(120) DEFAULT NULL,
  `color` varchar(80) DEFAULT NULL,
  `unit` varchar(40) NOT NULL DEFAULT 'kg',
  `supplier_name` varchar(190) DEFAULT NULL,
  `supplier_ref` varchar(120) DEFAULT NULL,
  `lead_time_days` int NOT NULL DEFAULT '0',
  `minimum_stock_qty` decimal(14,2) NOT NULL DEFAULT '0.00',
  `reorder_point_qty` decimal(14,2) NOT NULL DEFAULT '0.00',
  `maximum_stock_qty` decimal(14,2) NOT NULL DEFAULT '0.00',
  `storage_capacity_qty` decimal(14,2) DEFAULT NULL,
  `standard_cost` decimal(14,4) DEFAULT NULL,
  `currency` varchar(10) NOT NULL DEFAULT 'USD',
  `safety_stock_qty` decimal(14,2) NOT NULL DEFAULT '0.00',
  `max_storage_qty` decimal(14,2) NOT NULL DEFAULT '0.00',
  `storage_location` varchar(120) DEFAULT NULL,
  `standard_unit_cost` decimal(14,4) NOT NULL DEFAULT '0.0000',
  `reorder_policy` varchar(40) DEFAULT NULL,
  `min_order_qty` decimal(14,2) NOT NULL DEFAULT '0.00',
  `order_lot_size` decimal(14,2) NOT NULL DEFAULT '0.00',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_material_code` (`material_code`),
  UNIQUE KEY `uniq_material_number` (`material_number`),
  KEY `idx_materials_type` (`material_type`),
  KEY `idx_materials_active` (`is_active`),
  KEY `idx_materials_storage_location` (`storage_location`),
  KEY `idx_materials_number` (`material_number`)
) ENGINE=InnoDB AUTO_INCREMENT=57 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `menus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `menus` (
  `id` int NOT NULL AUTO_INCREMENT,
  `menu_key` varchar(150) NOT NULL,
  `label` varchar(150) NOT NULL,
  `url` varchar(255) DEFAULT NULL,
  `parent_key` varchar(150) DEFAULT NULL,
  `display_order` int DEFAULT '100',
  `perm_key` varchar(150) DEFAULT NULL,
  `source_app_key` varchar(120) DEFAULT NULL,
  `source_type` varchar(30) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `menu_key` (`menu_key`)
) ENGINE=InnoDB AUTO_INCREMENT=97 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mfg_assembly_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mfg_assembly_entries` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `assembly_plan_id` bigint DEFAULT NULL,
  `product_id` int NOT NULL,
  `source_type` varchar(40) DEFAULT NULL,
  `source_id` bigint DEFAULT NULL,
  `assembly_date` date NOT NULL,
  `planned_qty` decimal(14,2) NOT NULL DEFAULT '0.00',
  `completed_qty` decimal(14,2) NOT NULL DEFAULT '0.00',
  `rejected_qty` decimal(14,2) NOT NULL DEFAULT '0.00',
  `status` enum('draft','in_progress','completed','approved','blocked','cancelled') NOT NULL DEFAULT 'draft',
  `note` text,
  `completed_by` varchar(190) DEFAULT NULL,
  `approved_by` varchar(190) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mfg_assembly_plan` (`assembly_plan_id`),
  KEY `idx_mfg_assembly_product_date` (`product_id`,`assembly_date`),
  KEY `idx_mfg_assembly_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mfg_dispatch_bundle_headers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mfg_dispatch_bundle_headers` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `bundle_code` varchar(80) DEFAULT NULL,
  `pallet_code` varchar(80) NOT NULL,
  `destination` varchar(190) NOT NULL,
  `eta_load_at` datetime DEFAULT NULL,
  `driver_name` varchar(190) DEFAULT NULL,
  `truck_no` varchar(80) DEFAULT NULL,
  `carrier_name` varchar(190) DEFAULT NULL,
  `status` varchar(40) NOT NULL DEFAULT 'draft',
  `notes` text,
  `created_by` varchar(190) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_dispatch_bundle_dest_eta` (`destination`,`eta_load_at`),
  KEY `idx_dispatch_bundle_pallet` (`pallet_code`),
  KEY `idx_dispatch_bundle_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mfg_dispatch_bundle_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mfg_dispatch_bundle_lines` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `bundle_id` bigint NOT NULL,
  `dispatch_entry_id` int DEFAULT NULL,
  `daily_order_id` int DEFAULT NULL,
  `product_id` int NOT NULL,
  `dispatch_date` date NOT NULL,
  `qty` decimal(14,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_dispatch_bundle_line_bundle` (`bundle_id`),
  KEY `idx_dispatch_bundle_line_product_date` (`product_id`,`dispatch_date`),
  KEY `idx_dispatch_bundle_line_entry` (`dispatch_entry_id`),
  KEY `idx_dispatch_bundle_line_order` (`daily_order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mfg_order_processing_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mfg_order_processing_entries` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `demand_date` date NOT NULL,
  `required_qty` decimal(14,2) NOT NULL DEFAULT '0.00',
  `prepared_qty` decimal(14,2) NOT NULL DEFAULT '0.00',
  `case_count` int NOT NULL DEFAULT '0',
  `pallet_count` int NOT NULL DEFAULT '0',
  `labels_ready` tinyint(1) NOT NULL DEFAULT '0',
  `preparation_status` enum('draft','in_progress','prepared','completed','blocked') NOT NULL DEFAULT 'draft',
  `packaging_status` varchar(40) DEFAULT NULL,
  `dependency_state` varchar(60) DEFAULT NULL,
  `source_route_family` varchar(80) DEFAULT NULL,
  `execution_path_label` varchar(190) DEFAULT NULL,
  `source_key` varchar(120) NOT NULL,
  `source_summary_json` json DEFAULT NULL,
  `notes` text,
  `created_by` varchar(190) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_processing_entry` (`product_id`,`demand_date`,`source_key`),
  KEY `idx_processing_date` (`demand_date`),
  KEY `idx_processing_status` (`preparation_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mfg_part_demands`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mfg_part_demands` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `demand_date` date NOT NULL,
  `demand_type` enum('production','qc','assembly') NOT NULL,
  `system_qty` decimal(14,2) NOT NULL DEFAULT '0.00',
  `adjusted_qty` decimal(14,2) DEFAULT NULL,
  `approved_qty` decimal(14,2) DEFAULT NULL,
  `adjustment_note` text,
  `adjusted_by` varchar(190) DEFAULT NULL,
  `approved_by` varchar(190) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `source_summary_json` json DEFAULT NULL,
  `status` enum('calculated','adjusted','approved') NOT NULL DEFAULT 'calculated',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `workflow_state` varchar(40) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_mfg_demand_key` (`product_id`,`demand_date`,`demand_type`),
  KEY `idx_mfg_demands_date` (`demand_date`),
  KEY `idx_mfg_demands_type` (`demand_type`),
  KEY `idx_mfg_demands_status` (`status`),
  KEY `idx_mfg_part_demands_workflow_state` (`workflow_state`)
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mfg_procurement_demands`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mfg_procurement_demands` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `required_qty` decimal(14,2) NOT NULL DEFAULT '0.00',
  `required_date` date NOT NULL,
  `fulfillment_mode` varchar(40) NOT NULL,
  `dispatch_mode` varchar(40) DEFAULT NULL,
  `route_family` varchar(80) DEFAULT NULL,
  `execution_path_label` varchar(190) DEFAULT NULL,
  `source_demand_reference` varchar(120) NOT NULL,
  `source_summary_json` json DEFAULT NULL,
  `status` enum('draft','reviewed','approved','cancelled') NOT NULL DEFAULT 'draft',
  `created_by` varchar(190) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_procurement_demand` (`product_id`,`required_date`,`source_demand_reference`),
  KEY `idx_procurement_date` (`required_date`),
  KEY `idx_procurement_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mfg_production_plan_candidates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mfg_production_plan_candidates` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `demand_date` date NOT NULL,
  `planned_qty` decimal(14,2) NOT NULL DEFAULT '0.00',
  `route_family` varchar(80) DEFAULT NULL,
  `execution_path_label` varchar(190) DEFAULT NULL,
  `source_demand_reference` varchar(120) NOT NULL,
  `source_summary_json` json DEFAULT NULL,
  `status` enum('draft','reviewed','approved','cancelled') NOT NULL DEFAULT 'draft',
  `created_by` varchar(190) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_production_candidate` (`product_id`,`demand_date`,`source_demand_reference`),
  KEY `idx_production_candidate_date` (`demand_date`),
  KEY `idx_production_candidate_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mfg_stage_readiness`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mfg_stage_readiness` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `ref_date` date NOT NULL,
  `stage` enum('production','assembly','qc','packaging','dispatch') NOT NULL,
  `released_qty` decimal(14,2) NOT NULL DEFAULT '0.00',
  `released_by` varchar(190) DEFAULT NULL,
  `released_at` datetime DEFAULT NULL,
  `override_status` enum('released','skipped','blocked') DEFAULT NULL COMMENT 'NULL = auto-computed from actuals; set manually to force skip or block',
  `blocked_reason` text,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_mfg_stage_readiness` (`product_id`,`ref_date`,`stage`),
  KEY `idx_mfg_stage_readiness_date` (`ref_date`),
  KEY `idx_mfg_stage_readiness_stage` (`stage`),
  KEY `idx_mfg_stage_readiness_override` (`override_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mfg_upstream_generation_links`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mfg_upstream_generation_links` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `work_type` enum('production','procurement') NOT NULL,
  `product_id` int NOT NULL,
  `demand_date` date NOT NULL,
  `source_key` varchar(120) NOT NULL,
  `route_family` varchar(80) DEFAULT NULL,
  `execution_path_label` varchar(190) DEFAULT NULL,
  `target_table` varchar(80) NOT NULL,
  `target_id` bigint NOT NULL,
  `demanded_qty` decimal(14,2) NOT NULL DEFAULT '0.00',
  `source_summary_json` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_upstream_link` (`work_type`,`product_id`,`demand_date`,`source_key`),
  KEY `idx_upstream_target` (`target_table`,`target_id`),
  KEY `idx_upstream_date` (`demand_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mfg_work_execution_links`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mfg_work_execution_links` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `work_type` enum('assembly','qc','processing','dispatch') NOT NULL,
  `product_id` int NOT NULL,
  `demand_date` date NOT NULL,
  `source_key` varchar(120) NOT NULL,
  `route_family` varchar(80) DEFAULT NULL,
  `execution_path_label` varchar(190) DEFAULT NULL,
  `target_table` varchar(80) NOT NULL,
  `target_id` bigint NOT NULL,
  `demanded_qty` decimal(14,2) NOT NULL DEFAULT '0.00',
  `source_summary_json` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_work_link` (`work_type`,`product_id`,`demand_date`,`source_key`),
  KEY `idx_work_link_target` (`target_table`,`target_id`),
  KEY `idx_work_link_date` (`demand_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `plugin` varchar(100) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `applied_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_plugin_file` (`plugin`,`filename`)
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `operator_preferences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `operator_preferences` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `pref_key` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pref_value` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_pref` (`user_id`,`pref_key`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `operator_shift_handoff`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `operator_shift_handoff` (
  `id` int NOT NULL AUTO_INCREMENT,
  `author_user_id` int NOT NULL,
  `author_display` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `note_text` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `shift_date` date NOT NULL,
  `shift_label` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_shift_date` (`shift_date`),
  KEY `idx_author` (`author_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `operator_shift_handoff_ack`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `operator_shift_handoff_ack` (
  `id` int NOT NULL AUTO_INCREMENT,
  `handoff_id` int NOT NULL,
  `user_id` int NOT NULL,
  `acked_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_handoff_user` (`handoff_id`,`user_id`),
  KEY `idx_handoff` (`handoff_id`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `operator_tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `operator_tasks` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `assigned_to` int unsigned NOT NULL,
  `assigned_by` int unsigned NOT NULL,
  `priority` enum('low','medium','high','urgent') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium',
  `status` enum('open','in_progress','done','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `due_date` date DEFAULT NULL,
  `due_shift` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_assigned_to` (`assigned_to`),
  KEY `idx_assigned_by` (`assigned_by`),
  KEY `idx_status` (`status`),
  KEY `idx_due_date` (`due_date`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `org_audit_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `org_audit_log` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `entity_type` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` int NOT NULL,
  `action` enum('create','update','delete') COLLATE utf8mb4_unicode_ci NOT NULL,
  `changed_fields` json DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `user_email` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_company_ts` (`company_id`,`created_at`),
  KEY `idx_audit_entity` (`entity_type`,`entity_id`),
  KEY `idx_audit_user` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `org_branches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `org_branches` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `branch_code` varchar(40) NOT NULL,
  `branch_name` varchar(190) NOT NULL,
  `email` varchar(190) DEFAULT NULL,
  `phone` varchar(60) DEFAULT NULL,
  `address_line_1` varchar(190) DEFAULT NULL,
  `address_line_2` varchar(190) DEFAULT NULL,
  `city` varchar(120) DEFAULT NULL,
  `state` varchar(120) DEFAULT NULL,
  `postal_code` varchar(40) DEFAULT NULL,
  `country` varchar(120) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_org_branches_company_code` (`company_id`,`branch_code`),
  KEY `idx_org_branches_company` (`company_id`),
  KEY `idx_org_branches_active` (`is_active`),
  CONSTRAINT `fk_org_branches_company` FOREIGN KEY (`company_id`) REFERENCES `org_companies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `org_companies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `org_companies` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_code` varchar(40) NOT NULL,
  `company_name` varchar(190) NOT NULL,
  `legal_name` varchar(190) DEFAULT NULL,
  `registration_no` varchar(120) DEFAULT NULL,
  `tax_no` varchar(120) DEFAULT NULL,
  `base_currency` varchar(16) NOT NULL DEFAULT 'JPY',
  `timezone` varchar(80) NOT NULL DEFAULT 'Asia/Tokyo',
  `email` varchar(190) DEFAULT NULL,
  `phone` varchar(60) DEFAULT NULL,
  `website` varchar(190) DEFAULT NULL,
  `address_line_1` varchar(190) DEFAULT NULL,
  `address_line_2` varchar(190) DEFAULT NULL,
  `city` varchar(120) DEFAULT NULL,
  `state` varchar(120) DEFAULT NULL,
  `postal_code` varchar(40) DEFAULT NULL,
  `country` varchar(120) DEFAULT NULL,
  `logo_path` varchar(255) DEFAULT NULL,
  `short_brand_name` varchar(120) DEFAULT NULL,
  `report_header_text` varchar(255) DEFAULT NULL,
  `report_footer_text` varchar(255) DEFAULT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT '1',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `logo_svg_theme` varchar(40) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_org_companies_code` (`company_code`),
  KEY `idx_org_companies_primary` (`is_primary`),
  KEY `idx_org_companies_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `org_fiscal_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `org_fiscal_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `fiscal_year_start` char(5) DEFAULT NULL,
  `fiscal_year_end` char(5) DEFAULT NULL,
  `default_tax_mode` varchar(32) NOT NULL DEFAULT 'exclusive',
  `invoice_prefix` varchar(40) DEFAULT NULL,
  `document_prefix_pattern` varchar(120) DEFAULT NULL,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_org_fiscal_company` (`company_id`),
  KEY `idx_org_fiscal_company` (`company_id`),
  CONSTRAINT `fk_org_fiscal_company` FOREIGN KEY (`company_id`) REFERENCES `org_companies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `org_hierarchy`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `org_hierarchy` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `parent_id` int DEFAULT NULL,
  `hierarchy_code` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `hierarchy_name` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `hierarchy_type` enum('department','region','costcenter') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'department',
  `level` tinyint NOT NULL DEFAULT '0',
  `sort_order` smallint NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hierarchy_code` (`company_id`,`hierarchy_code`),
  KEY `idx_hierarchy_company` (`company_id`),
  KEY `idx_hierarchy_parent` (`company_id`,`parent_id`),
  KEY `idx_hierarchy_active` (`company_id`,`is_active`),
  KEY `fk_hierarchy_parent` (`parent_id`),
  CONSTRAINT `fk_hierarchy_company` FOREIGN KEY (`company_id`) REFERENCES `org_companies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_hierarchy_parent` FOREIGN KEY (`parent_id`) REFERENCES `org_hierarchy` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `part_machine_map`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `part_machine_map` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `machine_id` int NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_product_machine_active` (`product_id`,`machine_id`,`is_active`),
  KEY `idx_pmm_product` (`product_id`),
  KEY `idx_pmm_machine` (`machine_id`),
  KEY `idx_pmm_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `part_material_map`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `part_material_map` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `material_id` int NOT NULL,
  `qty_per_part` decimal(14,4) NOT NULL DEFAULT '0.0000',
  `uom` varchar(40) DEFAULT NULL,
  `usage_qty` decimal(14,4) NOT NULL DEFAULT '0.0000',
  `usage_unit` varchar(40) DEFAULT NULL,
  `yield_parts_per_kg` decimal(14,4) DEFAULT NULL,
  `material_code_snapshot` varchar(100) DEFAULT NULL,
  `material_name_snapshot` varchar(255) DEFAULT NULL,
  `material_type_snapshot` varchar(80) DEFAULT NULL,
  `vendor_name_snapshot` varchar(190) DEFAULT NULL,
  `vendor_code_snapshot` varchar(120) DEFAULT NULL,
  `scrap_pct` decimal(8,2) NOT NULL DEFAULT '0.00',
  `is_primary` tinyint(1) NOT NULL DEFAULT '1',
  `effective_from` date DEFAULT NULL,
  `effective_to` date DEFAULT NULL,
  `sequence_no` int NOT NULL DEFAULT '1',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_product_material_pair` (`product_id`,`material_id`),
  UNIQUE KEY `uniq_product_material_window` (`product_id`,`material_id`,`effective_from`,`effective_to`),
  KEY `idx_part_material_product` (`product_id`),
  KEY `idx_part_material_material` (`material_id`)
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `part_molds`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `part_molds` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `mold_code` varchar(120) NOT NULL,
  `mold_name` varchar(190) NOT NULL,
  `cavity_count` int NOT NULL DEFAULT '1',
  `is_family_mold` tinyint(1) NOT NULL DEFAULT '0',
  `tool_weight_kg` decimal(12,2) DEFAULT NULL,
  `mold_width_mm` decimal(12,2) DEFAULT NULL,
  `mold_height_mm` decimal(12,2) DEFAULT NULL,
  `mold_thickness_mm` decimal(12,2) DEFAULT NULL,
  `min_clamp_ton_required` decimal(12,2) DEFAULT NULL,
  `min_shot_g_required` decimal(12,2) DEFAULT NULL,
  `runner_type` varchar(80) DEFAULT NULL,
  `cycle_time_sec` decimal(12,2) DEFAULT NULL,
  `preferred_machine_group` varchar(50) DEFAULT NULL,
  `preferred_machine_type` varchar(80) DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'active',
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_part_mold_code` (`product_id`,`mold_code`),
  KEY `idx_part_molds_product` (`product_id`),
  KEY `idx_part_molds_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `perm_key` varchar(150) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `source_app_key` varchar(120) DEFAULT NULL,
  `source_type` varchar(30) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `perm_key` (`perm_key`)
) ENGINE=InnoDB AUTO_INCREMENT=618642 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pre_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pre_orders` (
  `id` int NOT NULL AUTO_INCREMENT,
  `forecast_type` varchar(60) NOT NULL,
  `planning_priority` varchar(40) NOT NULL DEFAULT 'Normal',
  `product_id` int NOT NULL,
  `planned_qty` decimal(14,2) NOT NULL DEFAULT '0.00',
  `balance_qty` decimal(14,2) NOT NULL DEFAULT '0.00',
  `required_date` date DEFAULT NULL,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pre_orders_product` (`product_id`),
  KEY `idx_pre_orders_required_date` (`required_date`),
  KEY `idx_pre_orders_priority` (`planning_priority`),
  KEY `idx_pre_orders_forecast_type` (`forecast_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `procurement_purchase_order_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `procurement_purchase_order_lines` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `po_id` bigint NOT NULL,
  `product_id` int DEFAULT NULL,
  `line_description` varchar(255) DEFAULT NULL,
  `ordered_qty` decimal(14,2) NOT NULL DEFAULT '0.00',
  `unit_price` decimal(14,2) NOT NULL DEFAULT '0.00',
  `line_status` varchar(30) NOT NULL DEFAULT 'open',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_proc_po_line_po` (`po_id`),
  KEY `idx_proc_po_line_status` (`line_status`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `procurement_purchase_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `procurement_purchase_orders` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `po_ref` varchar(60) NOT NULL,
  `supplier_id` bigint DEFAULT NULL,
  `request_id` bigint DEFAULT NULL,
  `po_status` varchar(30) NOT NULL DEFAULT 'draft',
  `order_date` date DEFAULT NULL,
  `expected_date` date DEFAULT NULL,
  `created_by` varchar(190) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_proc_po_ref` (`po_ref`),
  KEY `idx_proc_po_status` (`po_status`),
  KEY `idx_proc_po_supplier` (`supplier_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `procurement_receipts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `procurement_receipts` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `receipt_ref` varchar(60) NOT NULL,
  `po_id` bigint DEFAULT NULL,
  `po_line_id` bigint DEFAULT NULL,
  `received_qty` decimal(14,2) NOT NULL DEFAULT '0.00',
  `receipt_date` date DEFAULT NULL,
  `receipt_status` varchar(30) NOT NULL DEFAULT 'received',
  `note` text,
  `created_by` varchar(190) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_proc_receipt_ref` (`receipt_ref`),
  KEY `idx_proc_receipt_status` (`receipt_status`),
  KEY `idx_proc_receipt_po` (`po_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `procurement_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `procurement_requests` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `request_ref` varchar(60) NOT NULL,
  `product_id` int DEFAULT NULL,
  `requested_qty` decimal(14,2) NOT NULL DEFAULT '0.00',
  `needed_date` date DEFAULT NULL,
  `source_app` varchar(80) DEFAULT NULL,
  `source_ref_type` varchar(80) DEFAULT NULL,
  `source_ref_id` varchar(80) DEFAULT NULL,
  `request_status` varchar(30) NOT NULL DEFAULT 'draft',
  `note` text,
  `created_by` varchar(190) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_proc_request_ref` (`request_ref`),
  KEY `idx_proc_request_status` (`request_status`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `procurement_suppliers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `procurement_suppliers` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `supplier_code` varchar(40) DEFAULT NULL,
  `supplier_name` varchar(190) NOT NULL,
  `contact_name` varchar(190) DEFAULT NULL,
  `email` varchar(190) DEFAULT NULL,
  `phone` varchar(80) DEFAULT NULL,
  `supplier_status` varchar(30) NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_proc_supplier_status` (`supplier_status`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `production_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `production_entries` (
  `id` int NOT NULL AUTO_INCREMENT,
  `production_date` date NOT NULL,
  `shift` varchar(30) NOT NULL DEFAULT 'Day',
  `machine_id` int NOT NULL,
  `product_id` int NOT NULL,
  `produced_qty` decimal(14,2) NOT NULL DEFAULT '0.00',
  `rejected_qty` decimal(14,2) NOT NULL DEFAULT '0.00',
  `good_qty` decimal(14,2) NOT NULL DEFAULT '0.00',
  `status` varchar(30) NOT NULL DEFAULT 'Draft',
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pe_date` (`production_date`),
  KEY `idx_pe_machine` (`machine_id`),
  KEY `idx_pe_product` (`product_id`),
  KEY `idx_pe_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `production_plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `production_plans` (
  `id` int NOT NULL AUTO_INCREMENT,
  `plan_date` date NOT NULL,
  `machine_id` int NOT NULL,
  `product_id` int NOT NULL,
  `planned_qty` decimal(14,2) NOT NULL DEFAULT '0.00',
  `sequence_no` int NOT NULL DEFAULT '1',
  `runtime` decimal(12,2) DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'Planned',
  `plan_type` varchar(40) NOT NULL DEFAULT 'Manual',
  `reference_doctype` varchar(80) DEFAULT NULL,
  `reference_name` varchar(120) DEFAULT NULL,
  `coverage_pct` decimal(8,2) NOT NULL DEFAULT '0.00',
  `shortage_qty` decimal(14,2) NOT NULL DEFAULT '0.00',
  `auto_created` tinyint(1) NOT NULL DEFAULT '0',
  `notes` text,
  `added_by` varchar(190) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `workflow_state` varchar(40) DEFAULT NULL,
  `approval_status` varchar(40) NOT NULL DEFAULT 'Draft',
  `approved_by` int DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `approval_note` text,
  `locked_by` int DEFAULT NULL,
  `locked_at` datetime DEFAULT NULL,
  `reopened_by` int DEFAULT NULL,
  `reopened_at` datetime DEFAULT NULL,
  `reopen_reason` text,
  `override_reason` text,
  PRIMARY KEY (`id`),
  KEY `idx_pp_machine_date` (`machine_id`,`plan_date`),
  KEY `idx_pp_product` (`product_id`),
  KEY `idx_pp_status` (`status`),
  KEY `idx_pp_sequence` (`sequence_no`),
  KEY `idx_production_plans_approval_status` (`approval_status`),
  KEY `idx_production_plans_locked_at` (`locked_at`),
  KEY `idx_production_plans_workflow_state` (`workflow_state`)
) ENGINE=InnoDB AUTO_INCREMENT=63 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `products` (
  `id` int NOT NULL AUTO_INCREMENT,
  `parts_name` varchar(255) NOT NULL,
  `parts_number` varchar(100) NOT NULL,
  `model` varchar(190) DEFAULT NULL,
  `producer` varchar(190) DEFAULT NULL,
  `lead` varchar(120) DEFAULT NULL,
  `cycle_time` decimal(10,2) DEFAULT NULL,
  `qc_time_per_item` decimal(10,2) DEFAULT NULL,
  `notes` text,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `supply_mode` varchar(40) NOT NULL DEFAULT 'in_house',
  `fulfillment_mode` varchar(40) NOT NULL DEFAULT 'via_ipm',
  `requires_ipm_qc` tinyint(1) NOT NULL DEFAULT '1',
  `default_supplier` varchar(190) DEFAULT NULL,
  `stocked_at_ipm` tinyint(1) NOT NULL DEFAULT '1',
  `default_procurement_lead_days` decimal(10,2) DEFAULT NULL,
  `default_supply_note` text,
  `requires_assembly` tinyint(1) NOT NULL DEFAULT '0',
  `dispatch_as_is` tinyint(1) NOT NULL DEFAULT '0',
  `activity_type` enum('active','passive') NOT NULL DEFAULT 'active',
  `essential_stock_qty` decimal(12,2) DEFAULT NULL,
  `planning_window_days` int DEFAULT NULL,
  `max_buffer_qty` decimal(12,2) DEFAULT NULL,
  `active_machine_id` int DEFAULT NULL,
  `qty_per_case` int DEFAULT NULL,
  `case_spec` varchar(190) DEFAULT NULL,
  `case_type` varchar(120) DEFAULT NULL,
  `default_case_number` varchar(120) DEFAULT NULL,
  `cases_per_pallet` int DEFAULT NULL,
  `requires_processing` tinyint(1) NOT NULL DEFAULT '1',
  `dispatch_mode` enum('internal','direct_supplier','hybrid') DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `production_source` enum('in_house','third_party') NOT NULL DEFAULT 'in_house',
  `requires_qc` tinyint(1) NOT NULL DEFAULT '0',
  `delivery_flow` enum('company_to_destination','third_party_to_destination','third_party_to_company_to_destination') NOT NULL DEFAULT 'company_to_destination',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_parts_number` (`parts_number`),
  KEY `idx_products_active` (`is_active`),
  KEY `idx_products_model` (`model`),
  KEY `idx_products_producer` (`producer`),
  KEY `idx_products_cycle_time` (`cycle_time`),
  KEY `idx_products_supply_mode` (`supply_mode`),
  KEY `idx_products_fulfillment_mode` (`fulfillment_mode`),
  KEY `idx_products_qc_time` (`qc_time_per_item`),
  KEY `idx_products_requires_assembly` (`requires_assembly`),
  KEY `idx_products_essential_stock_qty` (`essential_stock_qty`),
  KEY `idx_products_planning_window_days` (`planning_window_days`),
  KEY `idx_products_max_buffer_qty` (`max_buffer_qty`),
  KEY `idx_products_requires_processing` (`requires_processing`),
  KEY `idx_products_dispatch_mode` (`dispatch_mode`),
  KEY `idx_products_production_source` (`production_source`),
  KEY `idx_products_processing_flags` (`requires_qc`,`requires_assembly`,`dispatch_as_is`),
  KEY `idx_products_activity_type` (`activity_type`),
  KEY `idx_products_active_machine_id` (`active_machine_id`),
  KEY `idx_products_qty_per_case` (`qty_per_case`)
) ENGINE=InnoDB AUTO_INCREMENT=217 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `qc_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qc_entries` (
  `id` int NOT NULL AUTO_INCREMENT,
  `qc_plan_id` int DEFAULT NULL,
  `daily_order_id` int DEFAULT NULL,
  `production_plan_id` int DEFAULT NULL,
  `product_id` int NOT NULL,
  `qc_type` varchar(40) NOT NULL DEFAULT 'Final',
  `checked_qty` decimal(14,2) NOT NULL DEFAULT '0.00',
  `pass_qty` decimal(14,2) NOT NULL DEFAULT '0.00',
  `fail_qty` decimal(14,2) NOT NULL DEFAULT '0.00',
  `status` varchar(30) NOT NULL DEFAULT 'Open',
  `remarks` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `workflow_state` varchar(40) DEFAULT NULL,
  `approval_status` varchar(40) NOT NULL DEFAULT 'Draft',
  `approved_by` int DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `approval_note` text,
  `locked_by` int DEFAULT NULL,
  `locked_at` datetime DEFAULT NULL,
  `reopened_by` int DEFAULT NULL,
  `reopened_at` datetime DEFAULT NULL,
  `reopen_reason` text,
  `override_reason` text,
  PRIMARY KEY (`id`),
  KEY `idx_qc_entries_status` (`status`),
  KEY `idx_qc_entries_product` (`product_id`),
  KEY `idx_qc_entries_qc_plan` (`qc_plan_id`),
  KEY `idx_qc_entries_daily_order` (`daily_order_id`),
  KEY `idx_qc_entries_production_plan` (`production_plan_id`),
  KEY `idx_qc_entries_approval_status` (`approval_status`),
  KEY `idx_qc_entries_locked_at` (`locked_at`),
  KEY `idx_qc_entries_workflow_state` (`workflow_state`)
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `qc_plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qc_plans` (
  `id` int NOT NULL AUTO_INCREMENT,
  `plan_date` date NOT NULL,
  `required_date` date DEFAULT NULL,
  `product_id` int NOT NULL,
  `daily_order_id` int DEFAULT NULL,
  `production_entry_id` int DEFAULT NULL,
  `planned_qty` decimal(14,2) NOT NULL DEFAULT '0.00',
  `estimated_time_minutes` int NOT NULL DEFAULT '0',
  `priority` varchar(30) NOT NULL DEFAULT 'Normal',
  `status` varchar(30) NOT NULL DEFAULT 'Open',
  `notes` text,
  `assigned_to` varchar(190) DEFAULT NULL,
  `added_by` varchar(190) DEFAULT NULL,
  `verified_by` varchar(190) DEFAULT NULL,
  `approved_by` varchar(190) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_qc_plans_dates` (`plan_date`,`required_date`),
  KEY `idx_qc_plans_priority` (`priority`),
  KEY `idx_qc_plans_status` (`status`),
  KEY `idx_qc_plans_product` (`product_id`),
  KEY `idx_qc_plans_daily_order` (`daily_order_id`),
  KEY `idx_qc_plans_production_entry` (`production_entry_id`),
  KEY `idx_qc_plans_added_by` (`added_by`),
  KEY `idx_qc_plans_verified_by` (`verified_by`),
  KEY `idx_qc_plans_approved_by` (`approved_by`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `qr_stock_updates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qr_stock_updates` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `movement_type` enum('IN','OUT','ADJUST') NOT NULL DEFAULT 'ADJUST',
  `qty` decimal(14,2) NOT NULL DEFAULT '0.00',
  `reference_no` varchar(120) DEFAULT NULL,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_qr_stock_updates_product` (`product_id`),
  KEY `idx_qr_stock_updates_type` (`movement_type`),
  KEY `idx_qr_stock_updates_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `role_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_permissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `role` varchar(50) NOT NULL,
  `perm_key` varchar(150) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_role_perm` (`role`,`perm_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sbaio_attendance_daily`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sbaio_attendance_daily` (
  `id` int NOT NULL AUTO_INCREMENT,
  `staff_id` int DEFAULT NULL,
  `attendance_date` date NOT NULL,
  `work_start_at` datetime DEFAULT NULL,
  `work_end_at` datetime DEFAULT NULL,
  `break_start_at` datetime DEFAULT NULL,
  `break_end_at` datetime DEFAULT NULL,
  `day_marker` varchar(40) NOT NULL DEFAULT 'workday',
  `source_label` varchar(80) NOT NULL DEFAULT 'manual',
  `is_manual_correction` tinyint(1) NOT NULL DEFAULT '0',
  `correction_note` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_sbaio_attendance_staff_date` (`staff_id`,`attendance_date`),
  KEY `idx_sbaio_attendance_date` (`attendance_date`),
  KEY `idx_sbaio_attendance_marker` (`day_marker`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sbaio_customers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sbaio_customers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `customer_name` varchar(190) NOT NULL,
  `contact_name` varchar(190) DEFAULT NULL,
  `email` varchar(190) DEFAULT NULL,
  `phone` varchar(60) DEFAULT NULL,
  `status` varchar(40) NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sbaio_expenses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sbaio_expenses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `expense_ref` varchar(80) NOT NULL,
  `category_name` varchar(120) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `expense_status` varchar(40) NOT NULL DEFAULT 'submitted',
  `expense_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sbaio_holiday_calendar`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sbaio_holiday_calendar` (
  `id` int NOT NULL AUTO_INCREMENT,
  `holiday_date` date NOT NULL,
  `holiday_name` varchar(190) NOT NULL,
  `holiday_type` varchar(60) NOT NULL DEFAULT 'public_holiday',
  `holiday_code` varchar(60) DEFAULT NULL,
  `is_closed_day` tinyint(1) NOT NULL DEFAULT '0',
  `notes` text,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_sbaio_holiday_date` (`holiday_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sbaio_leave_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sbaio_leave_requests` (
  `id` int NOT NULL AUTO_INCREMENT,
  `staff_id` int DEFAULT NULL,
  `legacy_name_ref` varchar(220) DEFAULT NULL,
  `leave_code` varchar(60) DEFAULT NULL,
  `leave_type` varchar(60) NOT NULL DEFAULT 'leave',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `partial_day_unit` varchar(20) DEFAULT NULL,
  `leave_status` varchar(40) NOT NULL DEFAULT 'draft',
  `notes` text,
  `approved_by` varchar(190) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sbaio_leave_requests_dates` (`start_date`,`end_date`),
  KEY `idx_sbaio_leave_requests_status` (`leave_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sbaio_notices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sbaio_notices` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(190) NOT NULL,
  `body` text,
  `notice_type` varchar(80) DEFAULT NULL,
  `target_period` varchar(40) DEFAULT NULL,
  `related_staff_id` int DEFAULT NULL,
  `requires_ack` tinyint(1) NOT NULL DEFAULT '0',
  `notice_level` varchar(40) NOT NULL DEFAULT 'info',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sbaio_payroll_fixed_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sbaio_payroll_fixed_profiles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `staff_id` int DEFAULT NULL,
  `legacy_name_ref` varchar(220) DEFAULT NULL,
  `basic_salary_amount` decimal(12,2) DEFAULT NULL,
  `taxable_earnings_amount` decimal(12,2) DEFAULT NULL,
  `social_ins_subject_amount` decimal(12,2) DEFAULT NULL,
  `actual_gross_pay_amount` decimal(12,2) DEFAULT NULL,
  `gross_pay_amount` decimal(12,2) DEFAULT NULL,
  `health_insurance_amount` decimal(12,2) DEFAULT NULL,
  `pension_insurance_amount` decimal(12,2) DEFAULT NULL,
  `employment_insurance_amount` decimal(12,2) DEFAULT NULL,
  `social_insurance_total` decimal(12,2) DEFAULT NULL,
  `taxable_income_amount` decimal(12,2) DEFAULT NULL,
  `income_tax_amount` decimal(12,2) DEFAULT NULL,
  `basic_insurance_amount` decimal(12,2) DEFAULT NULL,
  `specified_insurance_amount` decimal(12,2) DEFAULT NULL,
  `long_term_care_insurance_amount` decimal(12,2) DEFAULT NULL,
  `fixed_tax_reduction_amount` decimal(12,2) DEFAULT NULL,
  `total_deductions_amount` decimal(12,2) DEFAULT NULL,
  `cash_payment_amount` decimal(12,2) DEFAULT NULL,
  `net_pay_amount` decimal(12,2) DEFAULT NULL,
  `dependents_count` int DEFAULT NULL,
  `source_label` varchar(80) NOT NULL DEFAULT 'legacy_fulltime_rates',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_sbaio_payroll_fixed_profiles_staff` (`staff_id`),
  KEY `idx_sbaio_payroll_fixed_profiles_name` (`legacy_name_ref`(120))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sbaio_payroll_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sbaio_payroll_records` (
  `id` int NOT NULL AUTO_INCREMENT,
  `payroll_run_id` int DEFAULT NULL,
  `staff_id` int DEFAULT NULL,
  `legacy_name_ref` varchar(220) DEFAULT NULL,
  `salary_type` varchar(60) DEFAULT NULL,
  `salary_rate` decimal(12,2) DEFAULT NULL,
  `salary_reference_amount` decimal(12,2) DEFAULT NULL,
  `payslip_variant` varchar(40) NOT NULL DEFAULT 'standard',
  `worked_days` decimal(8,2) NOT NULL DEFAULT '0.00',
  `workday_count` int NOT NULL DEFAULT '0',
  `worked_minutes` int NOT NULL DEFAULT '0',
  `worked_hours` decimal(12,2) NOT NULL DEFAULT '0.00',
  `leave_days` decimal(8,2) NOT NULL DEFAULT '0.00',
  `holiday_days` decimal(8,2) NOT NULL DEFAULT '0.00',
  `holiday_work_days` decimal(8,2) NOT NULL DEFAULT '0.00',
  `overtime_minutes` int NOT NULL DEFAULT '0',
  `health_insurance_amount` decimal(12,2) DEFAULT NULL,
  `pension_insurance_amount` decimal(12,2) DEFAULT NULL,
  `employment_insurance_amount` decimal(12,2) DEFAULT NULL,
  `social_insurance_total` decimal(12,2) DEFAULT NULL,
  `taxable_income_amount` decimal(12,2) DEFAULT NULL,
  `income_tax_amount` decimal(12,2) DEFAULT NULL,
  `basic_insurance_amount` decimal(12,2) DEFAULT NULL,
  `specified_insurance_amount` decimal(12,2) DEFAULT NULL,
  `long_term_care_insurance_amount` decimal(12,2) DEFAULT NULL,
  `fixed_tax_reduction_amount` decimal(12,2) DEFAULT NULL,
  `total_deductions_amount` decimal(12,2) DEFAULT NULL,
  `cash_payment_amount` decimal(12,2) DEFAULT NULL,
  `dependents_count` int DEFAULT NULL,
  `base_salary_amount` decimal(12,2) DEFAULT NULL,
  `hourly_pay_amount` decimal(12,2) DEFAULT NULL,
  `gross_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `net_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `payslip_working_days_label` varchar(120) DEFAULT NULL,
  `payslip_working_time_label` varchar(120) DEFAULT NULL,
  `calc_notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sbaio_payroll_records_run` (`payroll_run_id`),
  KEY `idx_sbaio_payroll_records_staff` (`staff_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sbaio_payroll_runs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sbaio_payroll_runs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `period_key` varchar(20) NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `period_year` int DEFAULT NULL,
  `period_month` int DEFAULT NULL,
  `period_month_label` varchar(40) DEFAULT NULL,
  `payslip_variant` varchar(40) NOT NULL DEFAULT 'mixed',
  `run_status` varchar(40) NOT NULL DEFAULT 'draft',
  `approval_status` varchar(40) NOT NULL DEFAULT 'draft',
  `locked_at` datetime DEFAULT NULL,
  `generated_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_sbaio_payroll_runs_period` (`period_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sbaio_salary_monthly_rates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sbaio_salary_monthly_rates` (
  `id` int NOT NULL AUTO_INCREMENT,
  `staff_id` int DEFAULT NULL,
  `legacy_name_ref` varchar(220) DEFAULT NULL,
  `salary_type` varchar(60) DEFAULT NULL,
  `period_year` int NOT NULL,
  `period_month` int NOT NULL,
  `reference_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `source_label` varchar(80) NOT NULL DEFAULT 'legacy_salary_sheet',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_sbaio_salary_monthly_rates_staff_period` (`staff_id`,`period_year`,`period_month`),
  KEY `idx_sbaio_salary_monthly_rates_name_period` (`legacy_name_ref`(120),`period_year`,`period_month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sbaio_sales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sbaio_sales` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sale_ref` varchar(80) NOT NULL,
  `customer_name` varchar(190) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `sale_status` varchar(40) NOT NULL DEFAULT 'open',
  `sale_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sbaio_schedule_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sbaio_schedule_assignments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `staff_id` int DEFAULT NULL,
  `legacy_name_ref` varchar(220) DEFAULT NULL,
  `schedule_date` date NOT NULL,
  `template_id` int DEFAULT NULL,
  `expected_start_time` time DEFAULT NULL,
  `expected_end_time` time DEFAULT NULL,
  `break_start_time` time DEFAULT NULL,
  `break_end_time` time DEFAULT NULL,
  `expected_break_minutes` int NOT NULL DEFAULT '0',
  `day_of_week` varchar(20) DEFAULT NULL,
  `range_start_date` date DEFAULT NULL,
  `range_end_date` date DEFAULT NULL,
  `job_label` varchar(120) DEFAULT NULL,
  `scheduled_minutes` int NOT NULL DEFAULT '0',
  `assignment_status` varchar(40) NOT NULL DEFAULT 'planned',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_sbaio_schedule_assignment_staff_date` (`staff_id`,`schedule_date`),
  KEY `idx_sbaio_schedule_assignments_date` (`schedule_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sbaio_schedule_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sbaio_schedule_templates` (
  `id` int NOT NULL AUTO_INCREMENT,
  `template_name` varchar(120) NOT NULL,
  `shift_code` varchar(40) DEFAULT NULL,
  `day_of_week` varchar(20) DEFAULT NULL,
  `range_start_date` date DEFAULT NULL,
  `range_end_date` date DEFAULT NULL,
  `expected_start_time` time DEFAULT NULL,
  `expected_end_time` time DEFAULT NULL,
  `break_start_time` time DEFAULT NULL,
  `break_end_time` time DEFAULT NULL,
  `expected_break_minutes` int NOT NULL DEFAULT '0',
  `job_label` varchar(120) DEFAULT NULL,
  `scheduled_minutes` int NOT NULL DEFAULT '0',
  `legacy_name_ref` varchar(220) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sbaio_staff`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sbaio_staff` (
  `id` int NOT NULL AUTO_INCREMENT,
  `legacy_sn` varchar(40) DEFAULT NULL,
  `employee_code` varchar(60) DEFAULT NULL,
  `full_name` varchar(190) NOT NULL,
  `name_ref` varchar(220) DEFAULT NULL,
  `email` varchar(190) DEFAULT NULL,
  `role_label` varchar(120) DEFAULT NULL,
  `staff_type` varchar(80) DEFAULT NULL,
  `salary_type` varchar(60) DEFAULT NULL,
  `salary_rate` decimal(12,2) DEFAULT NULL,
  `helper_classification` varchar(80) DEFAULT NULL,
  `nationality` varchar(80) DEFAULT NULL,
  `gender` varchar(40) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `employment_status` varchar(40) NOT NULL DEFAULT 'active',
  `department_name` varchar(120) DEFAULT NULL,
  `branch_name` varchar(120) DEFAULT NULL,
  `hire_date` date DEFAULT NULL,
  `join_date` date DEFAULT NULL,
  `exit_date` date DEFAULT NULL,
  `legacy_job_details` varchar(190) DEFAULT NULL,
  `residence_card_front` varchar(255) DEFAULT NULL,
  `residence_card_back` varchar(255) DEFAULT NULL,
  `status` varchar(40) NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sbaio_tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sbaio_tasks` (
  `id` int NOT NULL AUTO_INCREMENT,
  `task_title` varchar(190) NOT NULL,
  `owner_name` varchar(190) DEFAULT NULL,
  `task_type` varchar(80) DEFAULT NULL,
  `task_bucket` varchar(80) DEFAULT NULL,
  `related_staff_id` int DEFAULT NULL,
  `related_date` date DEFAULT NULL,
  `task_status` varchar(40) NOT NULL DEFAULT 'open',
  `priority` varchar(40) NOT NULL DEFAULT 'normal',
  `due_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sbaio_timecard_periods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sbaio_timecard_periods` (
  `id` int NOT NULL AUTO_INCREMENT,
  `staff_id` int DEFAULT NULL,
  `legacy_name_ref` varchar(220) DEFAULT NULL,
  `period_key` varchar(20) NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `total_days` int NOT NULL DEFAULT '0',
  `total_worked_minutes` int NOT NULL DEFAULT '0',
  `total_worked_hours` decimal(12,2) NOT NULL DEFAULT '0.00',
  `overtime_minutes` int NOT NULL DEFAULT '0',
  `workday_count` int NOT NULL DEFAULT '0',
  `leave_days` decimal(8,2) NOT NULL DEFAULT '0.00',
  `holiday_days` decimal(8,2) NOT NULL DEFAULT '0.00',
  `holiday_work_days` decimal(8,2) NOT NULL DEFAULT '0.00',
  `exception_count` int NOT NULL DEFAULT '0',
  `salary_type` varchar(60) DEFAULT NULL,
  `salary_rate` decimal(12,2) DEFAULT NULL,
  `approval_status` varchar(40) NOT NULL DEFAULT 'draft',
  `locked_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_sbaio_timecard_periods_staff_period` (`staff_id`,`period_key`),
  KEY `idx_sbaio_timecard_periods_period` (`period_start`,`period_end`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sbaio_timecards_daily`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sbaio_timecards_daily` (
  `id` int NOT NULL AUTO_INCREMENT,
  `staff_id` int DEFAULT NULL,
  `legacy_name_ref` varchar(220) DEFAULT NULL,
  `work_date` date NOT NULL,
  `attendance_row_id` int DEFAULT NULL,
  `scheduled_start_at` datetime DEFAULT NULL,
  `scheduled_end_at` datetime DEFAULT NULL,
  `actual_start_at` datetime DEFAULT NULL,
  `actual_end_at` datetime DEFAULT NULL,
  `actual_break_start_at` datetime DEFAULT NULL,
  `actual_break_end_at` datetime DEFAULT NULL,
  `break_minutes` int NOT NULL DEFAULT '0',
  `worked_minutes` int NOT NULL DEFAULT '0',
  `scheduled_minutes` int NOT NULL DEFAULT '0',
  `overtime_minutes` int NOT NULL DEFAULT '0',
  `day_status` varchar(40) NOT NULL DEFAULT 'workday',
  `attendance_marker` varchar(40) DEFAULT NULL,
  `exception_status` varchar(40) NOT NULL DEFAULT 'normal',
  `salary_type` varchar(60) DEFAULT NULL,
  `salary_rate` decimal(12,2) DEFAULT NULL,
  `daily_salary_amount` decimal(12,2) DEFAULT NULL,
  `month_label` varchar(40) DEFAULT NULL,
  `weekday_label` varchar(40) DEFAULT NULL,
  `helper_flag` tinyint(1) NOT NULL DEFAULT '0',
  `leave_type` varchar(60) DEFAULT NULL,
  `holiday_name` varchar(190) DEFAULT NULL,
  `approval_status` varchar(40) NOT NULL DEFAULT 'draft',
  `generated_at` datetime DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_sbaio_timecards_staff_date` (`staff_id`,`work_date`),
  KEY `idx_sbaio_timecards_work_date` (`work_date`),
  KEY `idx_sbaio_timecards_approval` (`approval_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `stock_ledger_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `stock_ledger_entries` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `movement_type` enum('IN','OUT','ADJUST','PRODUCTION_IN','PRODUCTION_OUT','DISPATCH_OUT') NOT NULL DEFAULT 'ADJUST',
  `qty_delta` decimal(14,2) NOT NULL DEFAULT '0.00',
  `balance_after` decimal(14,2) NOT NULL DEFAULT '0.00',
  `reference_no` varchar(120) DEFAULT NULL,
  `source_module` varchar(80) DEFAULT NULL,
  `source_id` int DEFAULT NULL,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_stock_ledger_product` (`product_id`),
  KEY `idx_stock_ledger_movement` (`movement_type`),
  KEY `idx_stock_ledger_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=177 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_account_setup_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_account_setup_tokens` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `email` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_account_setup_token_hash` (`token_hash`),
  KEY `idx_account_setup_user` (`user_id`,`created_at`),
  KEY `idx_account_setup_expires` (`expires_at`),
  CONSTRAINT `fk_account_setup_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_app_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_app_roles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `app_key` varchar(120) NOT NULL,
  `operational_role` varchar(120) NOT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_app` (`user_id`,`app_key`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_dashboard_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_dashboard_assignments` (
  `user_id` int NOT NULL,
  `dashboard_type` varchar(80) NOT NULL,
  `default_app` varchar(120) DEFAULT NULL,
  `default_landing_page` varchar(255) DEFAULT NULL,
  `assigned_apps` text,
  `access_profiles` text,
  `permissions` text,
  `view_access` text,
  `table_access` text,
  `chart_access` text,
  `dashboard_mode` varchar(16) NOT NULL DEFAULT 'auto',
  `default_app_mode` varchar(16) NOT NULL DEFAULT 'auto',
  `landing_mode` varchar(16) NOT NULL DEFAULT 'auto',
  `access_profiles_mode` varchar(16) NOT NULL DEFAULT 'auto',
  `module_visibility_mode` varchar(16) NOT NULL DEFAULT 'auto',
  `account_class` varchar(40) DEFAULT NULL,
  `selected_role_packs` text,
  `cross_functional_access` text,
  `me_dashboard_blocks` text,
  `me_plugin_cards` text,
  `workspace_profile_key` varchar(100) DEFAULT NULL,
  `updated_by` varchar(190) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  KEY `idx_user_dashboard_type` (`dashboard_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_module_visibility`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_module_visibility` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `module_key` varchar(120) NOT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `updated_by` varchar(190) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_user_module` (`user_id`,`module_key`),
  KEY `idx_user_module_user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=348 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_operational_scopes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_operational_scopes` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `machine_id` int DEFAULT NULL,
  `part_id` int DEFAULT NULL,
  `task_type` varchar(80) DEFAULT NULL,
  `department_code` varchar(80) DEFAULT NULL,
  `branch_code` varchar(80) DEFAULT NULL,
  `ownership_role` varchar(80) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `updated_by` varchar(190) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_scope_user_id` (`user_id`),
  KEY `idx_scope_machine_id` (`machine_id`),
  KEY `idx_scope_part_id` (`part_id`),
  KEY `idx_scope_task_type` (`task_type`)
) ENGINE=InnoDB AUTO_INCREMENT=179 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_passkeys`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_passkeys` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `credential_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `public_key` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `device_name` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_used_at` datetime DEFAULT NULL,
  `sign_count` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_user_passkey_credential` (`credential_id`),
  KEY `idx_user_passkeys_user` (`user_id`,`created_at`),
  CONSTRAINT `fk_user_passkeys_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_password_reset_tokens` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `email` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `request_ip_hash` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `request_user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_password_reset_token_hash` (`token_hash`),
  KEY `idx_password_reset_user` (`user_id`,`created_at`),
  KEY `idx_password_reset_expires` (`expires_at`),
  CONSTRAINT `fk_password_reset_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_role_duties`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_role_duties` (
  `user_id` int NOT NULL,
  `duty_codes` text,
  `duty_notes` text,
  `updated_by` varchar(190) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(190) NOT NULL,
  `display_name` varchar(190) DEFAULT NULL,
  `first_name` varchar(120) DEFAULT NULL,
  `last_name` varchar(120) DEFAULT NULL,
  `username` varchar(120) DEFAULT NULL,
  `department` varchar(120) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `auth_session_version` int NOT NULL DEFAULT '1',
  `password_changed_at` datetime DEFAULT NULL,
  `role` varchar(50) NOT NULL DEFAULT 'User',
  `role_tier` varchar(20) DEFAULT NULL,
  `authority_role` varchar(40) DEFAULT NULL,
  `account_status` varchar(20) NOT NULL DEFAULT 'active',
  `verification_status` varchar(40) NOT NULL DEFAULT 'ready',
  `security_status` varchar(40) NOT NULL DEFAULT 'standard',
  `twofa_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `twofa_secret` varchar(255) DEFAULT NULL,
  `is_test_user` tinyint(1) NOT NULL DEFAULT '0',
  `test_2fa_bypass` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `workflow_approval_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `workflow_approval_events` (
  `id` int NOT NULL AUTO_INCREMENT,
  `module_name` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `record_id` int NOT NULL,
  `action_name` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `previous_approval_status` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `new_approval_status` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `previous_locked_state` tinyint(1) NOT NULL DEFAULT '0',
  `new_locked_state` tinyint(1) NOT NULL DEFAULT '0',
  `reason_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `note_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `acted_by` int DEFAULT NULL,
  `acted_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_workflow_events_record` (`module_name`,`record_id`),
  KEY `idx_workflow_events_action` (`action_name`),
  KEY `idx_workflow_events_acted_at` (`acted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `workflow_escalation_runs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `workflow_escalation_runs` (
  `engine_key` varchar(80) NOT NULL,
  `last_run_at` datetime DEFAULT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`engine_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `workflow_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `workflow_notifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `recipient_role` varchar(80) DEFAULT NULL,
  `recipient_user_id` int DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `severity` varchar(20) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'new',
  `event_type` varchar(50) NOT NULL,
  `title` varchar(190) NOT NULL,
  `message` text,
  `entity_type` varchar(40) NOT NULL,
  `entity_id` int NOT NULL,
  `stage` varchar(50) NOT NULL,
  `action_url` varchar(255) DEFAULT NULL,
  `delivery_status` varchar(20) NOT NULL DEFAULT 'sent',
  `sent_at` datetime DEFAULT NULL,
  `dedupe_key` varchar(64) NOT NULL,
  `read_at` datetime DEFAULT NULL,
  `dismissed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_workflow_notification_dedupe` (`dedupe_key`),
  KEY `idx_workflow_notification_target` (`recipient_role`,`recipient_user_id`,`status`),
  KEY `idx_workflow_notification_entity` (`entity_type`,`entity_id`,`stage`),
  KEY `idx_workflow_notification_created` (`created_at`),
  KEY `idx_workflow_notification_user` (`user_id`,`status`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=4516 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `workflow_transition_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `workflow_transition_events` (
  `id` int NOT NULL AUTO_INCREMENT,
  `entity_name` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `record_id` int NOT NULL,
  `action_name` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `from_state` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `to_state` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reason_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `note_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `permission_key` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `app_key` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `module_key` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `approval_required` tinyint(1) NOT NULL DEFAULT '0',
  `notifications_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `acted_by` int DEFAULT NULL,
  `acted_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_workflow_transition_entity_record` (`entity_name`,`record_id`),
  KEY `idx_workflow_transition_action` (`action_name`),
  KEY `idx_workflow_transition_acted_at` (`acted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `workspace_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `workspace_profiles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `profile_key` varchar(120) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text,
  `app_key` varchar(120) DEFAULT NULL,
  `authority_role` varchar(40) NOT NULL DEFAULT 'operator',
  `governance_scope` varchar(40) NOT NULL DEFAULT 'app',
  `landing_route` varchar(255) NOT NULL,
  `nav_sections` json DEFAULT NULL,
  `quick_actions` json DEFAULT NULL,
  `default_app` varchar(120) DEFAULT NULL,
  `assigned_apps` text,
  `access_profiles` text,
  `module_visibility` json DEFAULT NULL,
  `permissions` json DEFAULT NULL,
  `dashboard_blocks` json DEFAULT NULL,
  `widget_discovery` varchar(16) DEFAULT 'auto',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `is_system` tinyint(1) NOT NULL DEFAULT '0',
  `created_by` varchar(190) DEFAULT NULL,
  `updated_by` varchar(190) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `profile_key` (`profile_key`)
) ENGINE=InnoDB AUTO_INCREMENT=13725 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

