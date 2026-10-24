<?php
/**
 * System Information Repository
 * 
 * Interacts with the 'system_info' table to query system and database versions.
 */

require_once dirname(__DIR__) . '/services/Database.php';

class SystemInfoRepository {
    /**
     * Retrieve all version entries as an associative array [item => version]
     */
    public static function getAllVersions(): array {
        try {
            $pdo = Database::getConnection();
            if (!$pdo) {
                return [];
            }

            $stmt = $pdo->query("SELECT `item`, `version` FROM `system_info`");
            $results = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            return $results ?: [];
        } catch (Throwable $e) {
            error_log("Failed to fetch system_info: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get specific version by item key ('system', 'database', 'base')
     */
    public static function getVersion(string $item): ?string {
        try {
            $pdo = Database::getConnection();
            if (!$pdo) {
                return null;
            }

            $stmt = $pdo->prepare("SELECT `version` FROM `system_info` WHERE `item` = :item LIMIT 1");
            $stmt->execute(['item' => $item]);
            $version = $stmt->fetchColumn();
            return $version !== false ? (string) $version : null;
        } catch (Throwable $e) {
            error_log("Failed to fetch version for {$item}: " . $e->getMessage());
            return null;
        }
    }
}
