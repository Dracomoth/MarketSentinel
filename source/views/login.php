<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - BasePHP</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="auth-page">
    <div class="auth-wrapper">
        <div class="auth-card">
            <header class="auth-header">
                <h1>Sign In</h1>
                <p>Enter your credentials to access the application.</p>
            </header>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="/login">
                <div class="form-group">
                    <label for="username">Username or Email</label>
                    <input type="text" id="username" name="username" required autocomplete="username" placeholder="superadmin">
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required autocomplete="current-password" placeholder="••••••••">
                </div>

                <button type="submit" class="btn btn-primary">Sign In</button>
            </form>
        </div>

        <footer class="auth-footer">
            <span>System: <?= htmlspecialchars($systemVersion ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></span>
            <span class="dot">&bull;</span>
            <span>Database: <?= htmlspecialchars($databaseVersion ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></span>
        </footer>
    </div>
</body>
</html>
