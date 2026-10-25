-- =============================================================================
-- Migration: 002_portfolio_risk_and_chat.sql
-- Description: Phase 2 evolution adding risk management metrics (target price,
--              stop loss), asset strategy signals, broker fee tracking, and
--              persistent AI Council chat history with structured actions.
-- Dialect: MySQL 5.7+ / MySQL 8.0+ / MariaDB 10.3+
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- 1. Extend `portfolios` Table with Explicit Cash Balance
-- -----------------------------------------------------------------------------
ALTER TABLE `portfolios` 
    ADD COLUMN `cash_balance` DECIMAL(12,2) NULL DEFAULT NULL AFTER `initial_capital`;

-- -----------------------------------------------------------------------------
-- 2. Extend `portfolio_items` Table with Risk Metrics & Broker Fees
-- -----------------------------------------------------------------------------
ALTER TABLE `portfolio_items`
    ADD COLUMN `target_price` DECIMAL(12,4) NULL DEFAULT NULL AFTER `suggested_price`,
    ADD COLUMN `stop_loss_price` DECIMAL(12,4) NULL DEFAULT NULL AFTER `target_price`,
    ADD COLUMN `asset_strategy` ENUM('buy', 'hold', 'sell', 'rebalance', 'watch') NOT NULL DEFAULT 'hold' AFTER `item_status`,
    ADD COLUMN `broker_fee_type` ENUM('fixed', 'percent') NOT NULL DEFAULT 'fixed' AFTER `asset_strategy`,
    ADD COLUMN `broker_fee_value` DECIMAL(10,4) NOT NULL DEFAULT 0.0000 AFTER `broker_fee_type`,
    ADD COLUMN `total_fees_paid` DECIMAL(12,4) NOT NULL DEFAULT 0.0000 AFTER `broker_fee_value`;

-- -----------------------------------------------------------------------------
-- 3. Create `portfolio_chat_messages` Table (Council Chat History)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `portfolio_chat_messages` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `portfolio_id` INT UNSIGNED NOT NULL,
    `role` ENUM('user', 'assistant', 'system') NOT NULL,
    `message` TEXT NOT NULL,
    `structured_action` JSON NULL,
    `created` DATETIME NOT NULL,
    INDEX `idx_chat_portfolio` (`portfolio_id`),
    INDEX `idx_chat_created` (`created`),
    CONSTRAINT `fk_portfolio_chat_portfolio` FOREIGN KEY (`portfolio_id`) REFERENCES `portfolios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
