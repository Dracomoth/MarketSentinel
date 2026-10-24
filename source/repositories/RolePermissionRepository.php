<?php
/**
 * Role Permission Repository
 * 
 * Handles database operations for the 'role_permissions' table.
 */

require_once dirname(__DIR__) . '/services/Database.php';

class RolePermissionRepository {
    /**
     * Get all permissions for a specific role keyed by screen_id
     * 
     * @param int $roleId
     * @return array [screen_id => ['access_pc' => '...', 'access_other' => '...']]
     */
    public static function getPermissionsByRoleId(int $roleId): array {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return [];
        }

        try {
            $sql = "SELECT `screen_id`, `access_pc`, `access_other` 
                    FROM `role_permissions` 
                    WHERE `role_id` = :role_id";

            $stmt = $pdo->prepare($sql);
            $stmt->execute(['role_id' => $roleId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $permissions = [];
            foreach ($rows as $row) {
                $permissions[(int) $row['screen_id']] = [
                    'access_pc'    => $row['access_pc'],
                    'access_other' => $row['access_other'],
                ];
            }
            return $permissions;
        } catch (Throwable $e) {
            error_log("Failed to fetch permissions for role {$roleId}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Save/upsert permissions for a role across screens
     * 
     * @param int $roleId
     * @param array $screenPermissions Array of [screen_id => ['access_pc' => '...', 'access_other' => '...']]
     * @return bool
     * @throws Exception
     */
    public static function savePermissions(int $roleId, array $screenPermissions): bool {
        $pdo = Database::getConnection();
        if (!$pdo) {
            throw new Exception("Database connection unavailable.");
        }

        $validValues = ['read', 'write', 'none'];

        $sql = "INSERT INTO `role_permissions` (`role_id`, `screen_id`, `access_pc`, `access_other`) 
                VALUES (:role_id, :screen_id, :access_pc, :access_other)
                ON DUPLICATE KEY UPDATE 
                    `access_pc` = VALUES(`access_pc`),
                    `access_other` = VALUES(`access_other`),
                    `updated` = CURRENT_TIMESTAMP";

        $stmt = $pdo->prepare($sql);

        $pdo->beginTransaction();
        try {
            foreach ($screenPermissions as $screenId => $perms) {
                $accessPc = in_array($perms['access_pc'] ?? '', $validValues, true) ? $perms['access_pc'] : 'none';
                $accessOther = in_array($perms['access_other'] ?? '', $validValues, true) ? $perms['access_other'] : 'none';

                $stmt->execute([
                    'role_id'      => $roleId,
                    'screen_id'    => (int) $screenId,
                    'access_pc'    => $accessPc,
                    'access_other' => $accessOther,
                ]);
            }
            $pdo->commit();
            return true;
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log("Failed to save permissions: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Check permission for a specific role and screen
     * 
     * @param int $roleId
     * @param string $screenKey
     * @return array|null ['access_pc' => '...', 'access_other' => '...']
     */
    public static function getPermissionByRoleAndKey(int $roleId, string $screenKey): ?array {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return null;
        }

        try {
            $sql = "SELECT rp.`access_pc`, rp.`access_other` 
                    FROM `role_permissions` rp
                    INNER JOIN `screens` s ON rp.`screen_id` = s.`id`
                    WHERE rp.`role_id` = :role_id AND s.`screen_key` = :screen_key
                    LIMIT 1";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'role_id'    => $roleId,
                'screen_key' => $screenKey,
            ]);

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (Throwable $e) {
            error_log("Failed to check permission: " . $e->getMessage());
            return null;
        }
    }
}
