<?php
/**
 * Layout Service
 * 
 * Central provider for header, sidebar, and footer layout data including
 * user avatar/initials, system info, database status, and screen navigation.
 */

require_once __DIR__ . '/Database.php';
require_once dirname(__DIR__) . '/repositories/SystemInfoRepository.php';
require_once dirname(__DIR__) . '/repositories/ScreenRepository.php';
require_once __DIR__ . '/PermissionService.php';

class LayoutService {
    /**
     * Compute 1 or 2 letter initials for a user safely without requiring mbstring.
     * 
     * @param array|null $user
     * @return string
     */
    public static function getUserInitials(?array $user): string {
        if (!$user) {
            return '?';
        }

        $first = trim($user['first_name'] ?? '');
        $last  = trim($user['last_name'] ?? '');

        $substr = function(string $str, int $start, int $len = 1): string {
            return function_exists('mb_substr') ? mb_substr($str, $start, $len) : substr($str, $start, $len);
        };
        $upper = function(string $str): string {
            return function_exists('mb_strtoupper') ? mb_strtoupper($str) : strtoupper($str);
        };

        if ($first !== '' && $last !== '') {
            return $upper($substr($first, 0, 1) . $substr($last, 0, 1));
        }

        if ($first !== '') {
            return $upper($substr($first, 0, 2));
        }

        $username = trim($user['username'] ?? '');
        if ($username !== '') {
            return $upper($substr($username, 0, 2));
        }

        return 'U';
    }

    /**
     * Retrieve all layout context data.
     * 
     * @param array|null $user Current logged user
     * @return array
     */
    public static function getContext(?array $user = null): array {
        if ($user === null) {
            $user = $_SESSION['user'] ?? null;
        }

        // Keep session synced with latest user record if available
        if (!empty($user['id'])) {
            require_once dirname(__DIR__) . '/repositories/UserRepository.php';
            $freshUser = UserRepository::findById((int) $user['id']);
            if ($freshUser) {
                $freshUser['logged_in_at'] = $user['logged_in_at'] ?? date('Y-m-d H:i:s');
                $user = $freshUser;
                $_SESSION['user'] = $freshUser;
            }
        }

        // App configuration
        $configFile = dirname(__DIR__, 2) . '/config/config.php';
        $config = file_exists($configFile) ? (require $configFile) : [];
        $appName = $config['app']['name'] ?? 'BasePHP';
        $appEnv = $config['app']['environment'] ?? 'development';

        // System versions
        $versions = SystemInfoRepository::getAllVersions();
        $systemVersion = $versions['system'] ?? '0.0.0';
        $databaseVersion = $versions['database'] ?? '0.0.0';
        $baseVersion = $versions['base'] ?? '1.0.0';

        // Database status
        $isDbOnline = Database::isOnline();

        // Current request path for active link detection
        $requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $currentPath = rtrim($requestUri, '/');
        if ($currentPath === '') {
            $currentPath = '/';
        }

        // User Avatar & Initials
        $initials = self::getUserInitials($user);
        $avatarPath = null;
        if (!empty($user['avatar_path'])) {
            $rawPath = trim($user['avatar_path']);
            $fsPath = dirname(__DIR__, 2) . '/' . ltrim($rawPath, '/');
            if (file_exists($fsPath)) {
                $avatarPath = $rawPath . '?t=' . filemtime($fsPath);
            } else {
                $avatarPath = $rawPath;
            }
        }

        // Navigation screens
        $navScreens = $user ? ScreenRepository::getNavScreensForUser($user) : ['standalone' => [], 'groups' => []];

        return [
            'user'            => $user,
            'appName'         => $appName,
            'appEnv'          => $appEnv,
            'systemVersion'   => $systemVersion,
            'databaseVersion' => $databaseVersion,
            'baseVersion'     => $baseVersion,
            'isDbOnline'      => $isDbOnline,
            'currentPath'     => $currentPath,
            'initials'        => $initials,
            'avatarPath'      => $avatarPath,
            'navScreens'      => $navScreens,
            'currentDate'     => date('D, M j, Y'),
        ];
    }
}
