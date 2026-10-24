<?php
/**
 * Database Service Connection Manager
 * 
 * Provides a shared PDO database connection configured from config/config.php.
 */

class Database {
    private static ?PDO $connection = null;

    /**
     * Get or initialize the PDO database connection.
     * Returns null if connection fails.
     */
    public static function getConnection(): ?PDO {
        if (self::$connection !== null) {
            return self::$connection;
        }

        $configFile = dirname(__DIR__, 2) . '/config/config.php';
        if (!file_exists($configFile)) {
            return null;
        }

        $config = require $configFile;
        $db = $config['database'] ?? null;

        if (!$db || empty($db['database'])) {
            return null;
        }

        $dsn = sprintf(
            '%s:host=%s;port=%d;dbname=%s;charset=%s',
            $db['driver'] ?? 'mysql',
            $db['host'] ?? '127.0.0.1',
            $db['port'] ?? 3306,
            $db['database'],
            $db['charset'] ?? 'utf8mb4'
        );

        $options = $db['options'] ?? [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        // Default 2s timeout to prevent requests hanging if DB server is offline
        if (!isset($options[PDO::ATTR_TIMEOUT])) {
            $options[PDO::ATTR_TIMEOUT] = 2;
        }

        try {
            self::$connection = new PDO(
                $dsn,
                $db['username'] ?? '',
                $db['password'] ?? '',
                $options
            );
            return self::$connection;
        } catch (Throwable $e) {
            // Silently log and allow application to handle database offline state gracefully
            error_log("Database connection error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Check if the database connection is currently alive and responsive.
     * 
     * @return bool
     */
    public static function isOnline(): bool {
        try {
            $pdo = self::getConnection();
            if (!$pdo) {
                return false;
            }
            return $pdo->query("SELECT 1") !== false;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Reset the active connection (useful for reconnects or testing).
     */
    public static function disconnect(): void {
        self::$connection = null;
    }
}
