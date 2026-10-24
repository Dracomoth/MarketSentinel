<?php
/**
 * Permission Service
 * 
 * Enforces role-based screen access control, device context detection,
 * and SuperAdmin privilege bypass.
 */

require_once dirname(__DIR__) . '/repositories/RolePermissionRepository.php';
require_once dirname(__DIR__) . '/repositories/ScreenRepository.php';

class PermissionService {
    /**
     * Determine if current client is mobile or tablet device
     * 
     * @return bool
     */
    public static function isOtherDevice(): bool {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        return (bool) preg_match('/(android|iphone|ipad|ipod|mobile|blackberry|iemobile|kindle|silk|opera mini)/i', $userAgent);
    }

    /**
     * Check if a user has read or write access to a given screen
     * 
     * @param array $user User session array
     * @param string $screenKey Screen identifier (e.g. 'users', 'roles')
     * @return bool
     */
    public static function canAccess(array $user, string $screenKey): bool {
        // SuperAdmins always have full unrestricted access to everything
        if (!empty($user['is_super'])) {
            return true;
        }

        $roleId = (int) ($user['role_id'] ?? 0);
        if ($roleId <= 0) {
            return false;
        }

        $perm = RolePermissionRepository::getPermissionByRoleAndKey($roleId, $screenKey);
        if (!$perm) {
            return false;
        }

        $isOther = self::isOtherDevice();
        $access = $isOther ? ($perm['access_other'] ?? 'none') : ($perm['access_pc'] ?? 'none');

        return in_array($access, ['read', 'write'], true);
    }

    /**
     * Check if a user has write access to a given screen
     * 
     * @param array $user User session array
     * @param string $screenKey Screen identifier
     * @return bool
     */
    public static function canWrite(array $user, string $screenKey): bool {
        if (!empty($user['is_super'])) {
            return true;
        }

        $roleId = (int) ($user['role_id'] ?? 0);
        if ($roleId <= 0) {
            return false;
        }

        $perm = RolePermissionRepository::getPermissionByRoleAndKey($roleId, $screenKey);
        if (!$perm) {
            return false;
        }

        $isOther = self::isOtherDevice();
        $access = $isOther ? ($perm['access_other'] ?? 'none') : ($perm['access_pc'] ?? 'none');

        return $access === 'write';
    }
}
