<?php
/**
 * Users Controller
 * 
 * Handles user management actions (listing, creation, updating, deletion, status toggling)
 * and enforces the security constraint that at least one active SuperAdmin must always exist.
 */

require_once dirname(__DIR__) . '/repositories/UserRepository.php';
require_once dirname(__DIR__) . '/repositories/RoleRepository.php';
require_once dirname(__DIR__) . '/services/PermissionService.php';

class UsersController {
    public static function handle(): void {
        // Enforce authentication
        if (empty($_SESSION['user'])) {
            header('Location: /login', true, 302);
            exit;
        }

        $currentUser = $_SESSION['user'];

        // Enforce screen permission
        if (!PermissionService::canAccess($currentUser, 'users')) {
            http_response_code(403);
            echo "<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body><h1>403 Forbidden</h1><p>You do not have permission to access the Users Management screen.</p><p><a href='/'>Return to Dashboard</a></p></body></html>";
            exit;
        }

        $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $error = $_SESSION['flash_error'] ?? null;
        $success = $_SESSION['flash_success'] ?? null;
        unset($_SESSION['flash_error'], $_SESSION['flash_success']);

        $action = $_GET['action'] ?? $_POST['action'] ?? null;
        $editUser = null;
        $isFormVisible = false;

        // ---------------------------------------------------------------------
        // Handle POST Actions (Create, Update, Delete, Toggle Status)
        // ---------------------------------------------------------------------
        if ($requestMethod === 'POST') {
            try {
                switch ($action) {
                    case 'create':
                        $username = trim($_POST['username'] ?? '');
                        $firstName = trim($_POST['first_name'] ?? '');
                        $lastName = trim($_POST['last_name'] ?? '');
                        $email = trim($_POST['email'] ?? '');
                        $password = $_POST['password'] ?? '';
                        $roleId = (int) ($_POST['role_id'] ?? 0);
                        $isActive = isset($_POST['is_active']) ? 1 : 0;
                        $avatarPath = trim($_POST['avatar_path'] ?? '');

                        if ($username === '' || $firstName === '' || $lastName === '' || $email === '' || $password === '' || $roleId <= 0) {
                            throw new Exception("All required fields (Username, Names, Email, Password, Role) must be provided.");
                        }

                        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                            throw new Exception("Please provide a valid email address.");
                        }

                        if (UserRepository::usernameExists($username)) {
                            throw new Exception("The username '{$username}' is already taken.");
                        }

                        if (UserRepository::emailExists($email)) {
                            throw new Exception("The email '{$email}' is already registered.");
                        }

                        $role = RoleRepository::findById($roleId);
                        if (!$role) {
                            throw new Exception("Selected role is invalid.");
                        }

                        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

                        UserRepository::createUser([
                            'username'      => $username,
                            'first_name'    => $firstName,
                            'last_name'     => $lastName,
                            'email'         => $email,
                            'password_hash' => $passwordHash,
                            'role_id'       => $roleId,
                            'is_active'     => $isActive,
                            'avatar_path'   => $avatarPath !== '' ? $avatarPath : null,
                        ]);

                        $_SESSION['flash_success'] = "User '{$username}' created successfully.";
                        header('Location: /users', true, 302);
                        exit;

                    case 'update':
                        $userId = (int) ($_POST['id'] ?? 0);
                        if ($userId <= 0) {
                            throw new Exception("Invalid user ID specified for update.");
                        }

                        $existingUser = UserRepository::findById($userId);
                        if (!$existingUser) {
                            throw new Exception("User not found.");
                        }

                        $username = trim($_POST['username'] ?? '');
                        $firstName = trim($_POST['first_name'] ?? '');
                        $lastName = trim($_POST['last_name'] ?? '');
                        $email = trim($_POST['email'] ?? '');
                        $password = $_POST['password'] ?? '';
                        $roleId = (int) ($_POST['role_id'] ?? 0);
                        $isActive = isset($_POST['is_active']) ? 1 : 0;
                        $avatarPath = trim($_POST['avatar_path'] ?? '');

                        if ($username === '' || $firstName === '' || $lastName === '' || $email === '' || $roleId <= 0) {
                            throw new Exception("Username, Names, Email, and Role cannot be empty.");
                        }

                        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                            throw new Exception("Please provide a valid email address.");
                        }

                        if (UserRepository::usernameExists($username, $userId)) {
                            throw new Exception("The username '{$username}' is already taken.");
                        }

                        if (UserRepository::emailExists($email, $userId)) {
                            throw new Exception("The email '{$email}' is already registered to another account.");
                        }

                        $targetRole = RoleRepository::findById($roleId);
                        if (!$targetRole) {
                            throw new Exception("Selected role is invalid.");
                        }

                        // -----------------------------------------------------
                        // Critical Rule: Validate that at least one active SuperAdmin remains
                        // -----------------------------------------------------
                        $isCurrentlyActiveSuper = (!empty($existingUser['is_super']) && !empty($existingUser['is_active']));
                        $willRemainActiveSuper = (!empty($targetRole['is_super']) && $isActive === 1);

                        if ($isCurrentlyActiveSuper && !$willRemainActiveSuper) {
                            $activeSuperCount = UserRepository::countActiveSuperAdmins();
                            if ($activeSuperCount <= 1) {
                                throw new Exception("Action forbidden: The system must always have at least one active SuperAdmin. You cannot demote or deactivate the last SuperAdmin.");
                            }
                        }

                        $updateData = [
                            'username'    => $username,
                            'first_name'  => $firstName,
                            'last_name'   => $lastName,
                            'email'       => $email,
                            'role_id'     => $roleId,
                            'is_active'   => $isActive,
                            'avatar_path' => $avatarPath !== '' ? $avatarPath : null,
                        ];

                        if (!empty($password)) {
                            $updateData['password_hash'] = password_hash($password, PASSWORD_BCRYPT);
                        }

                        UserRepository::updateUser($userId, $updateData);

                        // If user updated their own info, refresh session
                        if (!empty($_SESSION['user']) && $_SESSION['user']['id'] === $userId) {
                            $_SESSION['user']['username'] = $username;
                            $_SESSION['user']['first_name'] = $firstName;
                            $_SESSION['user']['last_name'] = $lastName;
                            $_SESSION['user']['email'] = $email;
                            $_SESSION['user']['role_id'] = $roleId;
                            $_SESSION['user']['role_name'] = $targetRole['name'];
                            $_SESSION['user']['is_super'] = (bool) $targetRole['is_super'];
                        }

                        $_SESSION['flash_success'] = "User '{$username}' updated successfully.";
                        header('Location: /users', true, 302);
                        exit;

                    case 'delete':
                        $userId = (int) ($_POST['id'] ?? 0);
                        if ($userId <= 0) {
                            throw new Exception("Invalid user ID specified for deletion.");
                        }

                        $existingUser = UserRepository::findById($userId);
                        if (!$existingUser) {
                            throw new Exception("User not found.");
                        }

                        // -----------------------------------------------------
                        // Critical Rule: Validate that at least one active SuperAdmin remains
                        // -----------------------------------------------------
                        if (!empty($existingUser['is_super']) && !empty($existingUser['is_active'])) {
                            $activeSuperCount = UserRepository::countActiveSuperAdmins();
                            if ($activeSuperCount <= 1) {
                                throw new Exception("Action forbidden: The system must always have at least one active SuperAdmin. You cannot delete the last SuperAdmin user.");
                            }
                        }

                        // Prevent user deleting their own account
                        if (!empty($_SESSION['user']) && $_SESSION['user']['id'] === $userId) {
                            throw new Exception("Action forbidden: You cannot delete your currently logged-in account.");
                        }

                        UserRepository::deleteUser($userId);

                        $_SESSION['flash_success'] = "User '{$existingUser['username']}' deleted successfully.";
                        header('Location: /users', true, 302);
                        exit;

                    case 'toggle_status':
                        $userId = (int) ($_POST['id'] ?? 0);
                        if ($userId <= 0) {
                            throw new Exception("Invalid user ID specified.");
                        }

                        $existingUser = UserRepository::findById($userId);
                        if (!$existingUser) {
                            throw new Exception("User not found.");
                        }

                        // If user is currently active SuperAdmin and will be deactivated
                        if (!empty($existingUser['is_super']) && !empty($existingUser['is_active'])) {
                            $activeSuperCount = UserRepository::countActiveSuperAdmins();
                            if ($activeSuperCount <= 1) {
                                throw new Exception("Action forbidden: The system must always have at least one active SuperAdmin. You cannot deactivate the last SuperAdmin.");
                            }
                        }

                        UserRepository::toggleStatus($userId);

                        $newStatus = !empty($existingUser['is_active']) ? 'deactivated' : 'activated';
                        $_SESSION['flash_success'] = "User '{$existingUser['username']}' {$newStatus} successfully.";
                        header('Location: /users', true, 302);
                        exit;

                    default:
                        throw new Exception("Invalid action requested.");
                }
            } catch (Throwable $e) {
                $error = $e->getMessage();
                $isFormVisible = in_array($action, ['create', 'update']);
                if ($action === 'update' && !empty($_POST['id'])) {
                    $editUser = UserRepository::findById((int) $_POST['id']);
                }
            }
        }

        // ---------------------------------------------------------------------
        // Handle GET Actions (Show Create Form or Load User for Editing)
        // ---------------------------------------------------------------------
        if ($requestMethod === 'GET') {
            if ($action === 'create') {
                $isFormVisible = true;
            } elseif ($action === 'edit' && !empty($_GET['id'])) {
                $editUser = UserRepository::findById((int) $_GET['id']);
                if ($editUser) {
                    $isFormVisible = true;
                } else {
                    $error = "User not found.";
                }
            }
        }

        // Load data for view
        $users = UserRepository::getAllUsers();
        $roles = RoleRepository::getAll();
        $currentUser = $_SESSION['user'];

        require dirname(__DIR__) . '/views/users.php';
    }
}
