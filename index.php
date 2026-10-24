<?php
/**
 * Application Front Controller & Authentication Guard
 * 
 * Central entry point for both Apache (.htaccess) on Hostinger
 * and the local development server (router.php).
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = rtrim($requestUri, '/');
if ($path === '') {
    $path = '/';
}

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$isLoggedIn = isset($_SESSION['user']) && !empty($_SESSION['user']);
$publicRoutes = ['/login', '/logout'];

// -----------------------------------------------------------------------------
// Authentication Guard: Redirect non-logged users to the login screen
// -----------------------------------------------------------------------------
if (!$isLoggedIn && !in_array($path, $publicRoutes, true)) {
    // Remember the originally requested URL to return to after login
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header('Location: /login', true, 302);
    exit;
}

// If already logged in and visiting /login, redirect to home
if ($isLoggedIn && $path === '/login') {
    header('Location: /', true, 302);
    exit;
}

// -----------------------------------------------------------------------------
// Application Route Dispatcher
// -----------------------------------------------------------------------------
switch ($path) {
    case '/login':
        $error = null;
        if ($requestMethod === 'POST') {
            require_once __DIR__ . '/source/services/AuthService.php';
            $loginInput = trim($_POST['username'] ?? '');
            $passwordInput = trim($_POST['password'] ?? '');

            $authResult = AuthService::attempt($loginInput, $passwordInput);

            if ($authResult['success']) {
                $_SESSION['user'] = $authResult['user'];

                $redirectTo = $_SESSION['redirect_after_login'] ?? '/';
                unset($_SESSION['redirect_after_login']);
                header('Location: ' . $redirectTo, true, 302);
                exit;
            } else {
                $error = $authResult['error'];
            }
        }

        require_once __DIR__ . '/source/repositories/SystemInfoRepository.php';
        $systemVersions = SystemInfoRepository::getAllVersions();
        $systemVersion = $systemVersions['system'] ?? 'N/A';
        $databaseVersion = $systemVersions['database'] ?? 'N/A';

        require __DIR__ . '/source/views/login.php';
        break;

    case '/logout':
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
        header('Location: /login', true, 302);
        exit;

    case '/users':
        require_once __DIR__ . '/source/controllers/UsersController.php';
        UsersController::handle();
        break;

    case '/roles':
        require_once __DIR__ . '/source/controllers/RolesController.php';
        RolesController::handle();
        break;

    case '/profile':
        require_once __DIR__ . '/source/controllers/ProfileController.php';
        ProfileController::handle();
        break;

    case '/':
        require_once __DIR__ . '/source/services/PermissionService.php';
        $user = $_SESSION['user'] ?? null;
        $canAccessUsers = $user ? PermissionService::canAccess($user, 'users') : false;
        $canAccessRoles = $user ? PermissionService::canAccess($user, 'roles') : false;
        require __DIR__ . '/source/views/home.php';
        break;

    default:
        http_response_code(404);
        echo "<!DOCTYPE html><html><head><title>404 Not Found</title></head><body><h1>404 - Page Not Found</h1><p><a href='/'>Return to Home</a></p></body></html>";
        break;
}
