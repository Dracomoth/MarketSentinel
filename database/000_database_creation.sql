-- =============================================================================
-- Migration: 000_database_creation.sql
-- Description: Initial database schema setup including system info and security structure.
-- Dialect: MySQL 5.7+ / MySQL 8.0+ / MariaDB 10.3+
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- 1. System Information Table
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `system_info` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `item` VARCHAR(50) NOT NULL UNIQUE,
    `version` VARCHAR(50) NOT NULL,
    `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 2. Screens Table
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `screens` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `screen_key` VARCHAR(50) NOT NULL UNIQUE,
    `name` VARCHAR(100) NOT NULL,
    `category` VARCHAR(50) NOT NULL DEFAULT 'General',
    `sort_order` INT NOT NULL DEFAULT 0,
    `grouped` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Flag: 1 = grouped screen, 0 = standalone',
    `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `modified` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 3. Roles Table
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `roles` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL UNIQUE,
    `description` VARCHAR(255) NULL,
    `is_super` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = SuperAdmin role with unrestricted permissions that cannot be edited',
    `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 4. Users Table
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `role_id` INT UNSIGNED NOT NULL,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `first_name` VARCHAR(50) NOT NULL,
    `last_name` VARCHAR(50) NOT NULL,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `avatar_path` VARCHAR(255) NULL,
    `last_login` DATETIME NULL,
    `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 5. Role Permissions Table
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `role_permissions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `role_id` INT UNSIGNED NOT NULL,
    `screen_id` INT UNSIGNED NOT NULL,
    `access_pc` ENUM('read', 'write', 'none') NOT NULL DEFAULT 'none',
    `access_other` ENUM('read', 'write', 'none') NOT NULL DEFAULT 'none' COMMENT 'Permission for other devices (mobile, tablet, etc.)',
    `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_role_screen` (`role_id`, `screen_id`),
    CONSTRAINT `fk_role_permissions_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_role_permissions_screen` FOREIGN KEY (`screen_id`) REFERENCES `screens` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- Initial Data Seeding
-- =============================================================================

-- -----------------------------------------------------------------------------
-- System Information Versions
-- -----------------------------------------------------------------------------
INSERT INTO `system_info` (`item`, `version`) VALUES
    ('system', '0.0.0'),
    ('base', '1.0.0'),
    ('database', '0.5.0')
ON DUPLICATE KEY UPDATE
    `version` = VALUES(`version`),
    `updated` = CURRENT_TIMESTAMP;

-- -----------------------------------------------------------------------------
-- Seed Initial Screens (Users and Roles Management)
-- -----------------------------------------------------------------------------
INSERT INTO `screens` (`screen_key`, `name`, `category`, `sort_order`, `grouped`) VALUES
    ('users', 'Users', 'Security', 10, 1),
    ('roles', 'Roles', 'Security', 20, 1)
ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `category` = VALUES(`category`),
    `sort_order` = VALUES(`sort_order`),
    `grouped` = VALUES(`grouped`),
    `modified` = CURRENT_TIMESTAMP;

-- -----------------------------------------------------------------------------
-- Seed SuperAdmin Role
-- -----------------------------------------------------------------------------
INSERT INTO `roles` (`name`, `description`, `is_super`) VALUES
    ('SuperAdmin', 'System Super Administrator with unrestricted access', 1)
ON DUPLICATE KEY UPDATE
    `description` = VALUES(`description`),
    `is_super` = VALUES(`is_super`),
    `updated` = CURRENT_TIMESTAMP;

-- -----------------------------------------------------------------------------
-- Seed Permissions for SuperAdmin Role (Write access across all screens)
-- -----------------------------------------------------------------------------
INSERT INTO `role_permissions` (`role_id`, `screen_id`, `access_pc`, `access_other`)
SELECT r.id, s.id, 'write', 'write'
FROM `roles` r
CROSS JOIN `screens` s
WHERE r.name = 'SuperAdmin'
ON DUPLICATE KEY UPDATE
    `access_pc` = VALUES(`access_pc`),
    `access_other` = VALUES(`access_other`),
    `updated` = CURRENT_TIMESTAMP;

-- -----------------------------------------------------------------------------
-- Seed Super Admin User (Default credentials: username=superadmin, password=SuperAdmin123!)
-- -----------------------------------------------------------------------------
INSERT INTO `users` (
    `role_id`,
    `username`,
    `first_name`,
    `last_name`,
    `email`,
    `password_hash`,
    `is_active`
)
SELECT
    r.id,
    'superadmin',
    'Super',
    'Admin',
    'admin@basephp.local',
    '$2y$10$8/7xZ4MZRLYIxLCtOZUhR.MnUlh14YwnjSb9CTOlAhOzAlr0wecKW',
    1
FROM `roles` r
WHERE r.name = 'SuperAdmin'
ON DUPLICATE KEY UPDATE
    `role_id` = VALUES(`role_id`),
    `first_name` = VALUES(`first_name`),
    `last_name` = VALUES(`last_name`),
    `email` = VALUES(`email`),
    `is_active` = VALUES(`is_active`),
    `updated` = CURRENT_TIMESTAMP;
