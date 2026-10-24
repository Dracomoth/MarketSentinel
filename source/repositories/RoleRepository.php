<?php
/**
 * Role Repository
 * 
 * Handles database operations for the 'roles' table.
 */

require_once dirname(__DIR__) . '/services/Database.php';

class RoleRepository {
    /**
     * Retrieve all roles ordered by name
     * 
     * @return array
     */
    public static function getAll(): array {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return [];
        }

        try {
            $stmt = $pdo->query("SELECT `id`, `name`, `description`, `is_super` FROM `roles` ORDER BY `name` ASC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            error_log("Failed to fetch roles: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Retrieve all roles with a count of active and total users assigned to each
     * 
     * @return array
     */
    public static function getAllWithUserCount(): array {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return [];
        }

        try {
            $sql = "SELECT 
                        r.`id`, 
                        r.`name`, 
                        r.`description`, 
                        r.`is_super`,
                        COUNT(u.`id`) AS `user_count`
                    FROM `roles` r
                    LEFT JOIN `users` u ON r.`id` = u.`role_id`
                    GROUP BY r.`id`, r.`name`, r.`description`, r.`is_super`
                    ORDER BY r.`is_super` DESC, r.`name` ASC";

            $stmt = $pdo->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            error_log("Failed to fetch roles with user counts: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Find a role by its primary key ID
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
            $stmt = $pdo->prepare("SELECT `id`, `name`, `description`, `is_super` FROM `roles` WHERE `id` = :id LIMIT 1");
            $stmt->execute(['id' => $id]);
            $role = $stmt->fetch(PDO::FETCH_ASSOC);
            return $role ?: null;
        } catch (Throwable $e) {
            error_log("Failed to fetch role {$id}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Check if a role name already exists
     * 
     * @param string $name
     * @param int|null $excludeId
     * @return bool
     */
    public static function nameExists(string $name, ?int $excludeId = null): bool {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return false;
        }

        $sql = "SELECT COUNT(*) FROM `roles` WHERE `name` = :name";
        $params = ['name' => $name];

        if ($excludeId !== null) {
            $sql .= " AND `id` != :exclude_id";
            $params['exclude_id'] = $excludeId;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Count users assigned to a specific role
     * 
     * @param int $roleId
     * @return int
     */
    public static function countUsersWithRole(int $roleId): int {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return 0;
        }

        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM `users` WHERE `role_id` = :role_id");
            $stmt->execute(['role_id' => $roleId]);
            return (int) $stmt->fetchColumn();
        } catch (Throwable $e) {
            error_log("Failed to count users for role {$roleId}: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Create a new non-superadmin role
     * 
     * @param string $name
     * @param string|null $description
     * @return int Inserted ID
     * @throws Exception
     */
    public static function createRole(string $name, ?string $description = null): int {
        $pdo = Database::getConnection();
        if (!$pdo) {
            throw new Exception("Database connection unavailable.");
        }

        $stmt = $pdo->prepare("INSERT INTO `roles` (`name`, `description`, `is_super`) VALUES (:name, :description, 0)");
        $stmt->execute([
            'name'        => $name,
            'description' => $description,
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * Delete a role by ID
     * 
     * @param int $id
     * @return bool
     * @throws Exception
     */
    public static function deleteRole(int $id): bool {
        $pdo = Database::getConnection();
        if (!$pdo) {
            throw new Exception("Database connection unavailable.");
        }

        $stmt = $pdo->prepare("DELETE FROM `roles` WHERE `id` = :id");
        return $stmt->execute(['id' => $id]);
    }
}
