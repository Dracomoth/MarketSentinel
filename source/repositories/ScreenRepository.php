<?php
/**
 * Screen Repository
 * 
 * Handles database operations for the 'screens' table.
 */

require_once dirname(__DIR__) . '/services/Database.php';

class ScreenRepository {
    /**
     * Retrieve all screens ordered by sort_order
     * 
     * @return array
     */
    public static function getAll(): array {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return [];
        }

        try {
            $stmt = $pdo->query("SELECT `id`, `screen_key`, `name`, `category`, `sort_order`, `grouped` FROM `screens` ORDER BY `sort_order` ASC, `id` ASC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            error_log("Failed to fetch screens: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Find a screen by its screen_key identifier
     * 
     * @param string $screenKey
     * @return array|null
     */
    public static function findByKey(string $screenKey): ?array {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return null;
        }

        try {
            $stmt = $pdo->prepare("SELECT `id`, `screen_key`, `name`, `category`, `sort_order`, `grouped` FROM `screens` WHERE `screen_key` = :key LIMIT 1");
            $stmt->execute(['key' => $screenKey]);
            $screen = $stmt->fetch(PDO::FETCH_ASSOC);
            return $screen ?: null;
        } catch (Throwable $e) {
            error_log("Failed to find screen by key {$screenKey}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Retrieve screens formatted for sidebar navigation according to permissions.
     * Separates screens into standalone items and grouped collections.
     * For grouped screens, groups are sorted and items under each group are sorted by sort_order.
     * 
     * @param array $user
     * @return array ['standalone' => array, 'groups' => array]
     */
    public static function getNavScreensForUser(array $user): array {
        $screens = self::getAll();
        $standalone = [];
        $groups = [];

        require_once dirname(__DIR__) . '/services/PermissionService.php';

        foreach ($screens as $screen) {
            if (!PermissionService::canAccess($user, $screen['screen_key'])) {
                continue;
            }

            if (!empty($screen['grouped'])) {
                $cat = !empty($screen['category']) ? trim($screen['category']) : 'General';
                if (!isset($groups[$cat])) {
                    $groups[$cat] = [
                        'name'     => $cat,
                        'min_sort' => (int) ($screen['sort_order'] ?? 0),
                        'items'    => [],
                    ];
                } else {
                    $groups[$cat]['min_sort'] = min($groups[$cat]['min_sort'], (int) ($screen['sort_order'] ?? 0));
                }
                $groups[$cat]['items'][] = $screen;
            } else {
                $standalone[] = $screen;
            }
        }

        // Sort items within each group by sort_order
        foreach ($groups as &$group) {
            usort($group['items'], fn($a, $b) => ((int) $a['sort_order'] <=> (int) $b['sort_order']) ?: ((int) $a['id'] <=> (int) $b['id']));
        }
        unset($group);

        // Sort groups by minimum sort_order
        uasort($groups, fn($a, $b) => ($a['min_sort'] <=> $b['min_sort']) ?: strcmp($a['name'], $b['name']));

        // Sort standalone screens by sort_order
        usort($standalone, fn($a, $b) => ((int) $a['sort_order'] <=> (int) $b['sort_order']) ?: ((int) $a['id'] <=> (int) $b['id']));

        return [
            'standalone' => $standalone,
            'groups'     => $groups,
        ];
    }
}
