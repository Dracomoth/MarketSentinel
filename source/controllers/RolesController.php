<?php
/**
 * Roles Controller
 * 
 * Manages roles and screen permissions matrix.
 * Enforces rules:
 * - SuperAdmin role cannot be deleted ever.
 * - SuperAdmin permissions always have write access for everything and cannot be edited.
 */

require_once dirname(__DIR__) . '/repositories/RoleRepository.php';
require_once dirname(__DIR__) . '/repositories/ScreenRepository.php';
require_once dirname(__DIR__) . '/repositories/RolePermissionRepository.php';
require_once dirname(__DIR__) . '/services/PermissionService.php';

class RolesController {
    public static function handle(): void {
        // Enforce authentication
        if (empty($_SESSION['user'])) {
            header('Location: /login', true, 302);
            exit;
        }

        $currentUser = $_SESSION['user'];

        // Enforce screen permission
        if (!PermissionService::canAccess($currentUser, 'roles')) {
            http_response_code(403);
            echo "<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body><h1>403 Forbidden</h1><p>You do not have permission to access the Roles Management screen.</p><p><a href='/'>Return to Dashboard</a></p></body></html>";
            exit;
        }

        $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $error = $_SESSION['flash_error'] ?? null;
        $success = $_SESSION['flash_success'] ?? null;
        unset($_SESSION['flash_error'], $_SESSION['flash_success']);

        $action = $_POST['action'] ?? $_GET['action'] ?? null;

        // ---------------------------------------------------------------------
        // Handle POST Actions
        // ---------------------------------------------------------------------
        if ($requestMethod === 'POST') {
            try {
                switch ($action) {
                    case 'create_role':
                        $name = trim($_POST['name'] ?? '');
                        $description = trim($_POST['description'] ?? '');

                        if ($name === '') {
                            throw new Exception("Role name cannot be empty.");
                        }

                        if (RoleRepository::nameExists($name)) {
                            throw new Exception("A role named '{$name}' already exists.");
                        }

                        $newRoleId = RoleRepository::createRole($name, $description !== '' ? $description : null);

                        // Initialize default permissions (none) for all existing screens
                        $allScreens = ScreenRepository::getAll();
                        $initialPerms = [];
                        foreach ($allScreens as $screen) {
                            $initialPerms[$screen['id']] = [
                                'access_pc'    => 'none',
                                'access_other' => 'none',
                            ];
                        }
                        RolePermissionRepository::savePermissions($newRoleId, $initialPerms);

                        $_SESSION['flash_success'] = "Role '{$name}' created successfully.";
                        header("Location: /roles?role_id={$newRoleId}", true, 302);
                        exit;

                    case 'delete_role':
                        $roleId = (int) ($_POST['role_id'] ?? 0);
                        if ($roleId <= 0) {
                            throw new Exception("Invalid role specified.");
                        }

                        $role = RoleRepository::findById($roleId);
                        if (!$role) {
                            throw new Exception("Role not found.");
                        }

                        // Rule: SuperAdmin role can NEVER be deleted
                        if (!empty($role['is_super']) || strcasecmp($role['name'], 'SuperAdmin') === 0) {
                            throw new Exception("Action forbidden: The SuperAdmin role can never be deleted.");
                        }

                        // Prevent deleting role if users are assigned to it
                        $assignedUsers = RoleRepository::countUsersWithRole($roleId);
                        if ($assignedUsers > 0) {
                            throw new Exception("Cannot delete role '{$role['name']}': {$assignedUsers} user(s) are currently assigned to it. Please reassign them first.");
                        }

                        RoleRepository::deleteRole($roleId);

                        $_SESSION['flash_success'] = "Role '{$role['name']}' deleted successfully.";
                        header("Location: /roles", true, 302);
                        exit;

                    case 'save_permissions':
                        $roleId = (int) ($_POST['role_id'] ?? 0);
                        if ($roleId <= 0) {
                            throw new Exception("Invalid role specified.");
                        }

                        $role = RoleRepository::findById($roleId);
                        if (!$role) {
                            throw new Exception("Role not found.");
                        }

                        // Rule: SuperAdmin always has write access to everything and cannot be edited
                        if (!empty($role['is_super'])) {
                            throw new Exception("Action forbidden: SuperAdmin permissions are permanent (Write access to all screens) and cannot be edited.");
                        }

                        $rawPerms = $_POST['perms'] ?? [];
                        $allScreens = ScreenRepository::getAll();
                        $screenPermissions = [];

                        foreach ($allScreens as $screen) {
                            $sid = $screen['id'];
                            $pcVal = $rawPerms[$sid]['access_pc'] ?? 'none';
                            $otherVal = $rawPerms[$sid]['access_other'] ?? 'none';

                            $screenPermissions[$sid] = [
                                'access_pc'    => in_array($pcVal, ['write', 'read', 'none'], true) ? $pcVal : 'none',
                                'access_other' => in_array($otherVal, ['write', 'read', 'none'], true) ? $otherVal : 'none',
                            ];
                        }

                        RolePermissionRepository::savePermissions($roleId, $screenPermissions);

                        $_SESSION['flash_success'] = "Permissions for role '{$role['name']}' saved successfully.";
                        header("Location: /roles?role_id={$roleId}", true, 302);
                        exit;

                    default:
                        throw new Exception("Invalid action requested.");
                }
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }

        // ---------------------------------------------------------------------
        // Prepare Data for View
        // ---------------------------------------------------------------------
        $roles = RoleRepository::getAllWithUserCount();
        $screens = ScreenRepository::getAll();

        // Selected Role
        $selectedRoleId = (int) ($_GET['role_id'] ?? 0);
        $selectedRole = null;

        if ($selectedRoleId > 0) {
            foreach ($roles as $r) {
                if ((int) $r['id'] === $selectedRoleId) {
                    $selectedRole = $r;
                    break;
                }
            }
        }

        // Default to first role if none or invalid selected
        if (!$selectedRole && !empty($roles)) {
            $selectedRole = $roles[0];
            $selectedRoleId = (int) $selectedRole['id'];
        }

        // Permissions for selected role
        $rolePermissions = [];
        if ($selectedRole) {
            $rolePermissions = RolePermissionRepository::getPermissionsByRoleId($selectedRoleId);
        }

        $showCreateModal = ($action === 'new_role' || ($action === 'create_role' && !empty($error)));

        require dirname(__DIR__) . '/views/roles.php';
    }
}
