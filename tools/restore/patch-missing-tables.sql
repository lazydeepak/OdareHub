-- Patch: create tables that may be missing from a partial restore.
-- Safe to run multiple times (CREATE TABLE IF NOT EXISTS).
-- Run this in phpMyAdmin → SQL tab against oqtkzvov_suserp.

CREATE TABLE IF NOT EXISTS `user_app_roles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `app_key` varchar(120) NOT NULL,
  `operational_role` varchar(120) NOT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_app` (`user_id`,`app_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
