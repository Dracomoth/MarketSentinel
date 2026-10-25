-- =============================================================================
-- Migration: 001_portfolio_system.sql
-- Description: Phase 1 reset establishing core portfolio system, tracked assets,
--              and clean navigation permissions.
-- Dialect: MySQL 5.7+ / MySQL 8.0+ / MariaDB 10.3+
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- 1. Drop Leftover Prototype Tables
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `paper_trades`;
DROP TABLE IF EXISTS `analysis_reports`;
DROP TABLE IF EXISTS `watchlist`;

-- -----------------------------------------------------------------------------
-- 2. Portfolios Table
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `portfolios` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `user_prompt` TEXT NOT NULL,
    `strategy_summary` TEXT NOT NULL,
    `initial_capital` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `current_value` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `alert_status` ENUM('normal', 'action_needed', 'urgent') NOT NULL DEFAULT 'normal',
    `alert_message` VARCHAR(255) NULL,
    `time_horizon` ENUM('short_term', 'mid_term', 'long_term') NOT NULL DEFAULT 'mid_term',
    `created` DATETIME NOT NULL,
    `updated` DATETIME NOT NULL,
    INDEX `idx_portfolios_alert` (`alert_status`),
    INDEX `idx_portfolios_created` (`created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 3. Portfolio Items Table
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `portfolio_items` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `portfolio_id` INT UNSIGNED NOT NULL,
    `symbol` VARCHAR(20) NOT NULL,
    `asset_name` VARCHAR(100) NOT NULL,
    `asset_type` VARCHAR(20) NOT NULL DEFAULT 'stock',
    `exchange` VARCHAR(30) NOT NULL DEFAULT 'US',
    `exchange_timezone` VARCHAR(50) NOT NULL DEFAULT 'America/New_York',
    `current_price` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
    `suggested_shares` INT UNSIGNED NOT NULL DEFAULT 1,
    `suggested_price` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
    `why_picked` TEXT NOT NULL,
    `bull_thesis` TEXT NULL,
    `bear_thesis` TEXT NULL,
    `invalidation_trigger` TEXT NULL,
    `time_horizon` ENUM('short_term', 'mid_term', 'long_term') NOT NULL DEFAULT 'mid_term',
    `check_interval_hours` INT UNSIGNED NOT NULL DEFAULT 24,
    `last_checked_at` DATETIME NULL,
    `is_purchased` TINYINT(1) NOT NULL DEFAULT 0,
    `purchase_price` DECIMAL(12,4) NULL,
    `purchase_shares` INT UNSIGNED NULL,
    `purchased_at` DATETIME NULL,
    `item_status` ENUM('suggested', 'holding', 'sell_recommended', 'sold') NOT NULL DEFAULT 'suggested' ,
    `created` DATETIME NOT NULL,
    `updated` DATETIME NOT NULL,
    INDEX `idx_items_portfolio` (`portfolio_id`),
    INDEX `idx_items_symbol` (`symbol`),
    INDEX `idx_items_status` (`item_status`),
    CONSTRAINT `fk_portfolio_items_portfolio` FOREIGN KEY (`portfolio_id`) REFERENCES `portfolios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 4. Tracked Assets Table (Standalone Watchlist / Market Tracker)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tracked_assets` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `symbol` VARCHAR(20) NOT NULL UNIQUE,
    `asset_name` VARCHAR(100) NOT NULL,
    `asset_type` VARCHAR(20) NOT NULL DEFAULT 'stock',
    `exchange` VARCHAR(30) NOT NULL DEFAULT 'US',
    `exchange_timezone` VARCHAR(50) NOT NULL DEFAULT 'America/New_York',
    `current_price` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
    `change_percent` DECIMAL(6,2) NULL,
    `notes` VARCHAR(255) NULL,
    `audit_data` TEXT NULL,
    `last_checked_at` DATETIME NULL,
    `created` DATETIME NOT NULL,
    `updated` DATETIME NOT NULL,
    INDEX `idx_tracked_symbol` (`symbol`),
    INDEX `idx_tracked_type` (`asset_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 5. Update Navigation Screens & Clean Leftover Prototype Keys
-- -----------------------------------------------------------------------------
-- Remove obsolete prototype screen keys
DELETE FROM `role_permissions` 
WHERE `screen_id` IN (SELECT `id` FROM `screens` WHERE `screen_key` IN ('terminal', 'watchlist', 'paper-trades'));

DELETE FROM `screens` 
WHERE `screen_key` IN ('terminal', 'watchlist', 'paper-trades');

-- Insert 3-screen workflow screens under category 'Investments'
INSERT INTO `screens` (`screen_key`, `name`, `category`, `sort_order`, `grouped`) VALUES
    ('portfolio-builder', 'Build Portfolio', 'Investments', 30, 1),
    ('portfolios', 'My Portfolios', 'Investments', 40, 1),
    ('tracker', 'Asset Tracker', 'Investments', 50, 1)
ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `category` = VALUES(`category`),
    `sort_order` = VALUES(`sort_order`),
    `grouped` = VALUES(`grouped`),
    `modified` = CURRENT_TIMESTAMP;

-- Grant 'write' access for SuperAdmin (role_id = 1) across the new screens
INSERT INTO `role_permissions` (`role_id`, `screen_id`, `access_pc`, `access_other`)
SELECT 1, s.id, 'write', 'write'
FROM `screens` s
WHERE s.screen_key IN ('portfolio-builder', 'portfolios', 'tracker')
ON DUPLICATE KEY UPDATE
    `access_pc` = VALUES(`access_pc`),
    `access_other` = VALUES(`access_other`),
    `updated` = CURRENT_TIMESTAMP;

-- -----------------------------------------------------------------------------
-- 6. Update System Schema Version
-- -----------------------------------------------------------------------------
INSERT INTO `system_info` (`item`, `version`) VALUES ('database', '1.0.0')
ON DUPLICATE KEY UPDATE
    `version` = '1.0.0',
    `updated` = CURRENT_TIMESTAMP;

INSERT INTO `system_info` (`item`, `version`) VALUES ('system', '1.0.0')
ON DUPLICATE KEY UPDATE
    `version` = '1.0.0',
    `updated` = CURRENT_TIMESTAMP;

SET FOREIGN_KEY_CHECKS = 1;
