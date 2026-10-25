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

    /**
     * Insert or update a key-value entry in system_info
     */
    public static function setItem(string $item, string $version): bool {
        try {
            $pdo = Database::getConnection();
            if (!$pdo) {
                return false;
            }

            $stmt = $pdo->prepare("
                INSERT INTO `system_info` (`item`, `version`, `created`, `updated`)
                VALUES (:item, :version, NOW(), NOW())
                ON DUPLICATE KEY UPDATE `version` = VALUES(`version`), `updated` = NOW()
            ");
            return $stmt->execute([
                'item'    => $item,
                'version' => $version
            ]);
        } catch (Throwable $e) {
            error_log("Failed to set system_info item {$item}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieve last Guardian surveillance runner execution timestamp and format metrics
     * 
     * @return array [timestamp => string|null, relative_time => string, state_class => string, tooltip => string]
     */
    public static function getLastGuardianRun(): array {
        $raw = self::getVersion('last_guardian_run');
        if (!$raw || trim($raw) === '') {
            return [
                'timestamp'     => null,
                'relative_time' => 'Never',
                'state_class'   => 'is-inactive',
                'tooltip'       => 'Never executed',
            ];
        }

        $timestampStr = trim($raw);
        $runTs = strtotime($timestampStr);
        if (!$runTs) {
            return [
                'timestamp'     => $timestampStr,
                'relative_time' => 'Unknown',
                'state_class'   => 'is-inactive',
                'tooltip'       => "Unrecognized timestamp: {$timestampStr}",
            ];
        }

        $now = time();
        $diff = max(0, $now - $runTs);

        // Format relative human-readable time
        if ($diff < 15) {
            $relativeTime = 'Just now';
        } elseif ($diff < 60) {
            $relativeTime = "{$diff}s ago";
        } elseif ($diff < 3600) {
            $mins = max(1, (int) round($diff / 60));
            $relativeTime = "{$mins}m ago";
        } elseif ($diff < 86400) {
            $hours = round($diff / 3600, 1);
            $formattedHours = (floor($hours) == $hours) ? (int)$hours : $hours;
            $relativeTime = "{$formattedHours}h ago";
        } else {
            $days = round($diff / 86400, 1);
            $formattedDays = (floor($days) == $days) ? (int)$days : $days;
            $relativeTime = "{$formattedDays}d ago";
        }

        // Determine health state class:
        // < 4h: Recent / Healthy (green)
        // 4h - 24h: Stale (amber)
        // > 24h: Overdue (red)
        if ($diff < 4 * 3600) {
            $stateClass = 'is-recent';
        } elseif ($diff < 24 * 3600) {
            $stateClass = 'is-stale';
        } else {
            $stateClass = 'is-overdue';
        }

        $tooltip = "Last execution: {$timestampStr} UTC ({$relativeTime})";

        return [
            'timestamp'     => $timestampStr,
            'relative_time' => $relativeTime,
            'state_class'   => $stateClass,
            'tooltip'       => $tooltip,
        ];
    }
}

