<?php
/**
 * Authentication Service
 * 
 * Manages user authentication against the database and validates password hashes.
 */

require_once dirname(__DIR__) . '/repositories/UserRepository.php';

class AuthService {
    /**
     * Authenticate a user by username/email and password.
     * 
     * @param string $login Username or Email
     * @param string $password Plaintext password to verify
     * @return array Result array ['success' => bool, 'user' => ?array, 'error' => ?string]
     */
    public static function attempt(string $login, string $password): array {
        $login = trim($login);
        $password = trim($password);

        if ($login === '' || $password === '') {
            return [
                'success' => false,
                'user'    => null,
                'error'   => 'Please enter both username/email and password.',
            ];
        }

        try {
            $user = UserRepository::findByUsernameOrEmail($login);

            if (!$user) {
                return [
                    'success' => false,
                    'user'    => null,
                    'error'   => 'Invalid username/email or password.',
                ];
            }

            if (empty($user['is_active'])) {
                return [
                    'success' => false,
                    'user'    => null,
                    'error'   => 'This account is inactive. Please contact an administrator.',
                ];
            }

            if (!password_verify($password, $user['password_hash'])) {
                return [
                    'success' => false,
                    'user'    => null,
                    'error'   => 'Invalid username/email or password.',
                ];
            }

            // Update user's last login timestamp
            UserRepository::updateLastLogin((int) $user['id']);

            // Session data (exclude sensitive password hash)
            $sessionUser = [
                'id'           => (int) $user['id'],
                'username'     => $user['username'],
                'first_name'   => $user['first_name'],
                'last_name'    => $user['last_name'],
                'email'        => $user['email'],
                'role_id'      => (int) $user['role_id'],
                'role_name'    => $user['role_name'],
                'is_super'     => (bool) $user['is_super'],
                'avatar_path'  => $user['avatar_path'],
                'logged_in_at' => date('Y-m-d H:i:s'),
            ];

            return [
                'success' => true,
                'user'    => $sessionUser,
                'error'   => null,
            ];
        } catch (Throwable $e) {
            error_log("Authentication failure: " . $e->getMessage());
            return [
                'success' => false,
                'user'    => null,
                'error'   => 'Database connection error. Please verify database settings and migration.',
            ];
        }
    }
}
