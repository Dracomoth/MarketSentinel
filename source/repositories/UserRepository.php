<?php
/**
 * User Repository
 * 
 * Handles database operations for the 'users' and 'roles' tables.
 */

require_once dirname(__DIR__) . '/services/Database.php';

class UserRepository {
    /**
     * Retrieve all users joined with role information
     * 
     * @return array
     */
    public static function getAllUsers(): array {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return [];
        }

        try {
            $sql = "SELECT 
                        u.id,
                        u.role_id,
                        u.username,
                        u.first_name,
                        u.last_name,
                        u.email,
                        u.is_active,
                        u.avatar_path,
                        u.last_login,
                        u.created,
                        u.updated,
                        r.name AS role_name,
                        r.is_super
                    FROM `users` u
                    INNER JOIN `roles` r ON u.role_id = r.id
                    ORDER BY u.id ASC";

            $stmt = $pdo->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            error_log("Failed to fetch all users: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Find a single user by primary key ID with role information
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
            $sql = "SELECT 
                        u.id,
                        u.role_id,
                        u.username,
                        u.first_name,
                        u.last_name,
                        u.email,
                        u.password_hash,
                        u.is_active,
                        u.avatar_path,
                        u.last_login,
                        u.created,
                        u.updated,
                        r.name AS role_name,
                        r.is_super
                    FROM `users` u
                    INNER JOIN `roles` r ON u.role_id = r.id
                    WHERE u.id = :id
                    LIMIT 1";

            $stmt = $pdo->prepare($sql);
            $stmt->execute(['id' => $id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            return $user ?: null;
        } catch (Throwable $e) {
            error_log("Failed to find user by ID {$id}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Find an active user by username or email, including role information
     * 
     * @param string $login Username or Email
     * @return array|null
     * @throws Exception If database connection is unavailable
     */
    public static function findByUsernameOrEmail(string $login): ?array {
        $pdo = Database::getConnection();
        if (!$pdo) {
            throw new Exception("Database connection unavailable.");
        }

        $sql = "SELECT 
                    u.id,
                    u.role_id,
                    u.username,
                    u.first_name,
                    u.last_name,
                    u.email,
                    u.password_hash,
                    u.is_active,
                    u.avatar_path,
                    u.last_login,
                    r.name AS role_name,
                    r.is_super
                FROM `users` u
                INNER JOIN `roles` r ON u.role_id = r.id
                WHERE (u.username = :login_user OR u.email = :login_email)
                LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'login_user'  => $login,
            'login_email' => $login,
        ]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ?: null;
    }

    /**
     * Count the total number of currently active SuperAdmin users
     * 
     * @return int
     */
    public static function countActiveSuperAdmins(): int {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return 0;
        }

        try {
            $sql = "SELECT COUNT(*) 
                    FROM `users` u 
                    INNER JOIN `roles` r ON u.role_id = r.id 
                    WHERE r.is_super = 1 AND u.is_active = 1";
            $stmt = $pdo->query($sql);
            return (int) $stmt->fetchColumn();
        } catch (Throwable $e) {
            error_log("Failed to count active superadmins: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Check if a username is already taken
     * 
     * @param string $username
     * @param int|null $excludeId User ID to ignore (for edits)
     * @return bool
     */
    public static function usernameExists(string $username, ?int $excludeId = null): bool {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return false;
        }

        $sql = "SELECT COUNT(*) FROM `users` WHERE `username` = :username";
        $params = ['username' => $username];

        if ($excludeId !== null) {
            $sql .= " AND `id` != :exclude_id";
            $params['exclude_id'] = $excludeId;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Check if an email is already taken
     * 
     * @param string $email
     * @param int|null $excludeId User ID to ignore (for edits)
     * @return bool
     */
    public static function emailExists(string $email, ?int $excludeId = null): bool {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return false;
        }

        $sql = "SELECT COUNT(*) FROM `users` WHERE `email` = :email";
        $params = ['email' => $email];

        if ($excludeId !== null) {
            $sql .= " AND `id` != :exclude_id";
            $params['exclude_id'] = $excludeId;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Create a new user
     * 
     * @param array $data
     * @return int Inserted ID
     * @throws Exception
     */
    public static function createUser(array $data): int {
        $pdo = Database::getConnection();
        if (!$pdo) {
            throw new Exception("Database connection unavailable.");
        }

        $sql = "INSERT INTO `users` (
                    `role_id`,
                    `username`,
                    `first_name`,
                    `last_name`,
                    `email`,
                    `password_hash`,
                    `is_active`,
                    `avatar_path`
                ) VALUES (
                    :role_id,
                    :username,
                    :first_name,
                    :last_name,
                    :email,
                    :password_hash,
                    :is_active,
                    :avatar_path
                )";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'role_id'       => (int) $data['role_id'],
            'username'      => $data['username'],
            'first_name'    => $data['first_name'],
            'last_name'     => $data['last_name'],
            'email'         => $data['email'],
            'password_hash' => $data['password_hash'],
            'is_active'     => !empty($data['is_active']) ? 1 : 0,
            'avatar_path'   => $data['avatar_path'] ?? null,
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * Update an existing user
     * 
     * @param int $id
     * @param array $data
     * @return bool
     * @throws Exception
     */
    public static function updateUser(int $id, array $data): bool {
        $pdo = Database::getConnection();
        if (!$pdo) {
            throw new Exception("Database connection unavailable.");
        }

        $fields = [
            '`role_id` = :role_id',
            '`username` = :username',
            '`first_name` = :first_name',
            '`last_name` = :last_name',
            '`email` = :email',
            '`is_active` = :is_active',
            '`avatar_path` = :avatar_path',
        ];

        $params = [
            'id'          => $id,
            'role_id'     => (int) $data['role_id'],
            'username'    => $data['username'],
            'first_name'  => $data['first_name'],
            'last_name'   => $data['last_name'],
            'email'       => $data['email'],
            'is_active'   => !empty($data['is_active']) ? 1 : 0,
            'avatar_path' => $data['avatar_path'] ?? null,
        ];

        if (!empty($data['password_hash'])) {
            $fields[] = '`password_hash` = :password_hash';
            $params['password_hash'] = $data['password_hash'];
        }

        $sql = "UPDATE `users` SET " . implode(', ', $fields) . " WHERE `id` = :id";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Update user profile information (first_name, last_name, email, optional password and avatar)
     * 
     * @param int $id
     * @param array $data
     * @return bool
     * @throws Exception
     */
    public static function updateProfile(int $id, array $data): bool {
        $pdo = Database::getConnection();
        if (!$pdo) {
            throw new Exception("Database connection unavailable.");
        }

        $fields = [
            '`first_name` = :first_name',
            '`last_name` = :last_name',
            '`email` = :email',
        ];

        $params = [
            'id'         => $id,
            'first_name' => $data['first_name'],
            'last_name'  => $data['last_name'],
            'email'      => $data['email'],
        ];

        if (!empty($data['username'])) {
            $fields[] = '`username` = :username';
            $params['username'] = $data['username'];
        }

        if (array_key_exists('avatar_path', $data)) {
            $fields[] = '`avatar_path` = :avatar_path';
            $params['avatar_path'] = $data['avatar_path'];
        }

        if (!empty($data['password_hash'])) {
            $fields[] = '`password_hash` = :password_hash';
            $params['password_hash'] = $data['password_hash'];
        }

        $sql = "UPDATE `users` SET " . implode(', ', $fields) . " WHERE `id` = :id";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Delete a user by ID
     * 
     * @param int $id
     * @return bool
     * @throws Exception
     */
    public static function deleteUser(int $id): bool {
        $pdo = Database::getConnection();
        if (!$pdo) {
            throw new Exception("Database connection unavailable.");
        }

        $stmt = $pdo->prepare("DELETE FROM `users` WHERE `id` = :id");
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Toggle a user's active status
     * 
     * @param int $id
     * @return bool
     * @throws Exception
     */
    public static function toggleStatus(int $id): bool {
        $pdo = Database::getConnection();
        if (!$pdo) {
            throw new Exception("Database connection unavailable.");
        }

        $stmt = $pdo->prepare("UPDATE `users` SET `is_active` = 1 - `is_active` WHERE `id` = :id");
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Update the last_login timestamp for a user
     * 
     * @param int $userId
     */
    public static function updateLastLogin(int $userId): void {
        try {
            $pdo = Database::getConnection();
            if ($pdo) {
                $stmt = $pdo->prepare("UPDATE `users` SET `last_login` = NOW() WHERE `id` = :id");
                $stmt->execute(['id' => $userId]);
            }
        } catch (Throwable $e) {
            error_log("Failed to update last_login: " . $e->getMessage());
        }
    }
}
