<?php
/**
 * Tracker Repository - Standalone Asset Radar Repository
 * 
 * Handles all database operations for the `tracked_assets` table:
 * - Adding and finding tracked assets (symbols, companies, market types)
 * - Live price and change percentage updates
 * - On-demand AI Council Audit JSON persistence
 * - Asset deletion from radar
 */

require_once dirname(__DIR__) . '/services/Database.php';

class TrackerRepository {
    /**
     * Add or update an asset in the tracked_assets radar
     * 
     * @param array $data
     * @return int Inserted or existing asset ID
     */
    public static function add(array $data): int {
        $pdo = Database::getConnection();
        if (!$pdo) {
            throw new RuntimeException("Database connection unavailable.");
        }

        $symbol = strtoupper(trim($data['symbol'] ?? ''));
        if ($symbol === '') {
            throw new InvalidArgumentException("Asset symbol cannot be empty.");
        }

        $now = date('Y-m-d H:i:s');
        $sql = "INSERT INTO `tracked_assets` (
                    `symbol`,
                    `asset_name`,
                    `asset_type`,
                    `exchange`,
                    `exchange_timezone`,
                    `current_price`,
                    `change_percent`,
                    `notes`,
                    `audit_data`,
                    `last_checked_at`,
                    `created`,
                    `updated`
                ) VALUES (
                    :symbol,
                    :asset_name,
                    :asset_type,
                    :exchange,
                    :exchange_timezone,
                    :current_price,
                    :change_percent,
                    :notes,
                    :audit_data,
                    :last_checked_at,
                    :created,
                    :updated
                ) ON DUPLICATE KEY UPDATE
                    `asset_name`        = VALUES(`asset_name`),
                    `asset_type`        = VALUES(`asset_type`),
                    `exchange`          = VALUES(`exchange`),
                    `exchange_timezone` = VALUES(`exchange_timezone`),
                    `current_price`     = VALUES(`current_price`),
                    `change_percent`    = VALUES(`change_percent`),
                    `notes`             = COALESCE(VALUES(`notes`), `notes`),
                    `audit_data`        = COALESCE(VALUES(`audit_data`), `audit_data`),
                    `last_checked_at`   = VALUES(`last_checked_at`),
                    `updated`           = VALUES(`updated`)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'symbol'            => $symbol,
            'asset_name'        => trim($data['asset_name'] ?? $symbol),
            'asset_type'        => trim($data['asset_type'] ?? 'stock'),
            'exchange'          => trim($data['exchange'] ?? 'US'),
            'exchange_timezone' => trim($data['exchange_timezone'] ?? 'America/New_York'),
            'current_price'     => (float) ($data['current_price'] ?? 0.0000),
            'change_percent'    => isset($data['change_percent']) ? (float) $data['change_percent'] : null,
            'notes'             => !empty($data['notes']) ? trim($data['notes']) : null,
            'audit_data'        => !empty($data['audit_data']) ? trim($data['audit_data']) : null,
            'last_checked_at'   => $data['last_checked_at'] ?? $now,
            'created'           => $data['created'] ?? $now,
            'updated'           => $data['updated'] ?? $now,
        ]);

        $lastId = (int) $pdo->lastInsertId();
        if ($lastId > 0) {
            return $lastId;
        }

        $existing = self::findBySymbol($symbol);
        return $existing ? (int) $existing['id'] : 0;
    }

    /**
     * Retrieve all tracked assets ordered by updated DESC
     * 
     * @return array
     */
    public static function getAll(): array {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return [];
        }

        try {
            $stmt = $pdo->query("SELECT * FROM `tracked_assets` ORDER BY `updated` DESC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            error_log("Failed to fetch all tracked assets: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Find a tracked asset by symbol (case-insensitive)
     * 
     * @param string $symbol
     * @return array|null
     */
    public static function findBySymbol(string $symbol): ?array {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return null;
        }

        try {
            $stmt = $pdo->prepare("SELECT * FROM `tracked_assets` WHERE UPPER(`symbol`) = UPPER(:sym) LIMIT 1");
            $stmt->execute(['sym' => trim($symbol)]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Throwable $e) {
            error_log("Failed to find tracked asset by symbol {$symbol}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Find a tracked asset by primary key ID
     * 
     * @param int $id
     * @return array|null
     */
    public static function findById(int $id): ?array {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return null;
        }

        try {
            $stmt = $pdo->prepare("SELECT * FROM `tracked_assets` WHERE `id` = :id LIMIT 1");
            $stmt->execute(['id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Throwable $e) {
            error_log("Failed to find tracked asset by ID {$id}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Update current price, change percentage, and touch updated
     * 
     * @param string $symbol
     * @param float $price
     * @param float|null $changePercent
     * @return bool
     */
    public static function updatePrice(string $symbol, float $price, ?float $changePercent): bool {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return false;
        }

        try {
            $stmt = $pdo->prepare("UPDATE `tracked_assets` 
                                   SET `current_price` = :price, 
                                       `change_percent` = :chg, 
                                       `last_checked_at` = NOW(), 
                                       `updated` = NOW() 
                                   WHERE UPPER(`symbol`) = UPPER(:sym)");
            return $stmt->execute([
                'price' => $price,
                'chg'   => $changePercent,
                'sym'   => trim($symbol)
            ]);
        } catch (Throwable $e) {
            error_log("Failed to update price for tracked asset {$symbol}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Save an on-demand council audit JSON for the asset
     * 
     * @param int $id
     * @param string $auditJson
     * @return bool
     */
    public static function updateAudit(int $id, string $auditJson): bool {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return false;
        }

        try {
            $stmt = $pdo->prepare("UPDATE `tracked_assets` 
                                   SET `audit_data` = :audit, 
                                       `updated` = NOW() 
                                   WHERE `id` = :id");
            return $stmt->execute([
                'audit' => $auditJson,
                'id'    => $id,
            ]);
        } catch (Throwable $e) {
            error_log("Failed to update audit for tracked asset ID {$id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Remove an asset from tracker by ID
     * 
     * @param int $id
     * @return bool
     */
    public static function remove(int $id): bool {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return false;
        }

        try {
            $stmt = $pdo->prepare("DELETE FROM `tracked_assets` WHERE `id` = :id");
            return $stmt->execute(['id' => $id]);
        } catch (Throwable $e) {
            error_log("Failed to remove tracked asset {$id}: " . $e->getMessage());
            return false;
        }
    }
}
