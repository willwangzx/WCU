CREATE DATABASE IF NOT EXISTS `wcu_applications`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `wcu_applications`;

CREATE TABLE IF NOT EXISTS `applications` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `first_name` VARCHAR(100) NOT NULL,
  `last_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(50) NOT NULL,
  `birth_month` VARCHAR(20) NOT NULL,
  `birth_day` TINYINT UNSIGNED NOT NULL,
  `birth_year` SMALLINT UNSIGNED NOT NULL,
  `gender` VARCHAR(50) NOT NULL,
  `citizenship` VARCHAR(100) NOT NULL,
  `entry_term` VARCHAR(20) NOT NULL,
  `program` VARCHAR(100) NOT NULL,
  `school_name` VARCHAR(255) NOT NULL,
  `personal_statement` TEXT NOT NULL,
  `portfolio_url` VARCHAR(500) NOT NULL,
  `additional_notes` TEXT DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` TEXT DEFAULT NULL,
  `origin_url` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_email` (`email`),
  KEY `idx_entry_term` (`entry_term`),
  KEY `idx_program` (`program`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
