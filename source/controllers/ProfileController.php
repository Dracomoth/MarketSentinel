<?php
/**
 * Profile Controller
 * 
 * Handles viewing and editing the logged user's own profile,
 * changing password, and uploading / removing avatar images.
 */

require_once dirname(__DIR__) . '/repositories/UserRepository.php';
require_once dirname(__DIR__) . '/services/PermissionService.php';

class ProfileController {
    public static function handle(): void {
        // Enforce authentication
        if (empty($_SESSION['user'])) {
            $_SESSION['redirect_after_login'] = '/profile';
            header('Location: /login', true, 302);
            exit;
        }

        $sessionUser = $_SESSION['user'];
        $userId = (int) ($sessionUser['id'] ?? 0);

        // Fetch fresh user data from database
        $currentUser = UserRepository::findById($userId);
        if (!$currentUser) {
            session_destroy();
            header('Location: /login', true, 302);
            exit;
        }

        $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $error = $_SESSION['flash_error'] ?? null;
        $success = $_SESSION['flash_success'] ?? null;
        unset($_SESSION['flash_error'], $_SESSION['flash_success']);

        if ($requestMethod === 'POST') {
            try {
                $username = trim($_POST['username'] ?? '');
                $firstName = trim($_POST['first_name'] ?? '');
                $lastName = trim($_POST['last_name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $newPassword = $_POST['new_password'] ?? '';
                $confirmPassword = $_POST['confirm_password'] ?? '';
                $removeAvatar = !empty($_POST['remove_avatar']) && $_POST['remove_avatar'] === '1';

                // Basic validation
                if ($username === '') {
                    throw new Exception("Username cannot be empty.");
                }
                if (!preg_match('/^[a-zA-Z0-9_.-]{3,30}$/', $username)) {
                    throw new Exception("Username must be between 3 and 30 characters (letters, numbers, underscores, dots, and hyphens only).");
                }
                if (UserRepository::usernameExists($username, $userId)) {
                    throw new Exception("The username '{$username}' is already in use by another account.");
                }
                if ($firstName === '') {
                    throw new Exception("First name cannot be empty.");
                }
                if ($lastName === '') {
                    throw new Exception("Last name cannot be empty.");
                }
                if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception("Please enter a valid email address.");
                }

                // Check email uniqueness excluding current user
                if (UserRepository::emailExists($email, $userId)) {
                    throw new Exception("The email address '{$email}' is already in use by another account.");
                }

                // Password change validation
                $passwordHash = null;
                if ($newPassword !== '' || $confirmPassword !== '') {
                    if ($newPassword !== $confirmPassword) {
                        throw new Exception("New password and confirm password do not match.");
                    }
                    if (strlen($newPassword) < 6) {
                        throw new Exception("New password must be at least 6 characters long.");
                    }
                    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                }

                // Avatar handling
                $avatarPath = $currentUser['avatar_path'];
                $uploadDir = dirname(__DIR__, 2) . '/images/avatars';

                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                if ($removeAvatar) {
                    // Delete existing avatar files on disk matching this user ID
                    $existingFiles = glob($uploadDir . '/' . $userId . '_avatar.*');
                    if ($existingFiles) {
                        foreach ($existingFiles as $f) {
                            if (file_exists($f)) {
                                @unlink($f);
                            }
                        }
                    }
                    $avatarPath = null;
                } elseif (isset($_FILES['avatar_file']) && $_FILES['avatar_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $file = $_FILES['avatar_file'];

                    if ($file['error'] !== UPLOAD_ERR_OK) {
                        throw new Exception("Avatar upload failed with error code: " . $file['error']);
                    }

                    // Max size: 5MB
                    if ($file['size'] > 5 * 1024 * 1024) {
                        throw new Exception("Avatar image size must be less than 5MB.");
                    }

                    // Check mime type and extension
                    $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                    $origExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

                    // Verify image with getimagesize
                    $imgInfo = @getimagesize($file['tmp_name']);
                    if ($imgInfo === false) {
                        throw new Exception("The uploaded file is not a valid image.");
                    }

                    if (!in_array($origExt, $allowedExts, true)) {
                        $origExt = 'png';
                    }

                    // Normalize jpeg to jpg
                    if ($origExt === 'jpeg') {
                        $origExt = 'jpg';
                    }

                    $filename = "{$userId}_avatar.{$origExt}";
                    $destPath = $uploadDir . '/' . $filename;

                    // Remove prior avatar files for this user with different extensions
                    $priorFiles = glob($uploadDir . '/' . $userId . '_avatar.*');
                    if ($priorFiles) {
                        foreach ($priorFiles as $pf) {
                            if (file_exists($pf)) {
                                @unlink($pf);
                            }
                        }
                    }

                    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                        throw new Exception("Failed to save avatar image to server.");
                    }

                    $avatarPath = '/images/avatars/' . $filename;
                }

                // Prepare update payload
                $updateData = [
                    'username'    => $username,
                    'first_name'  => $firstName,
                    'last_name'   => $lastName,
                    'email'       => $email,
                    'avatar_path' => $avatarPath,
                ];

                if ($passwordHash !== null) {
                    $updateData['password_hash'] = $passwordHash;
                }

                // Execute DB update
                UserRepository::updateProfile($userId, $updateData);

                // Update session data
                $_SESSION['user']['username']    = $username;
                $_SESSION['user']['first_name']  = $firstName;
                $_SESSION['user']['last_name']   = $lastName;
                $_SESSION['user']['email']       = $email;
                $_SESSION['user']['avatar_path'] = $avatarPath;

                $_SESSION['flash_success'] = "Your profile has been updated successfully.";
                header('Location: /profile', true, 303);
                exit;

            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }

        require dirname(__DIR__) . '/views/profile.php';
    }
}
