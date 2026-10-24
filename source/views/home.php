<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - BasePHP</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <?php require __DIR__ . '/layout/header.php'; ?>
    <?php require __DIR__ . '/layout/sidebar.php'; ?>

    <main class="container page-content">
        <div class="card">
            <h1>Protected Dashboard</h1>
            <p class="text-muted">You have successfully authenticated. This area is protected by the authentication guard.</p>

            <!-- Screens Navigation Section -->
            <?php if (!empty($canAccessUsers) || !empty($canAccessRoles)): ?>
                <div class="section">
                    <h2>Accessible Screens</h2>
                    <p class="text-muted" style="margin-bottom: 1rem;">Modules available based on your assigned role permissions:</p>
                    <div class="info-grid">
                        <?php if (!empty($canAccessUsers)): ?>
                            <a href="/users" class="info-item" style="text-decoration: none; color: inherit; transition: border-color 0.15s ease;">
                                <span class="info-label" style="color: var(--color-primary);">Screen &bull; Security</span>
                                <span class="info-value" style="font-size: 1.1rem; font-weight: 600;">Users Management &rarr;</span>
                                <span class="text-muted text-sm">Create, edit, deactivate, or delete system users.</span>
                            </a>
                        <?php endif; ?>

                        <?php if (!empty($canAccessRoles)): ?>
                            <a href="/roles" class="info-item" style="text-decoration: none; color: inherit; transition: border-color 0.15s ease;">
                                <span class="info-label" style="color: var(--color-primary);">Screen &bull; Security</span>
                                <span class="info-value" style="font-size: 1.1rem; font-weight: 600;">Roles & Permissions &rarr;</span>
                                <span class="text-muted text-sm">Manage roles and configure PC/other device screen permissions.</span>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="section">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
                    <h2 style="margin-bottom: 0;">User Information</h2>
                    <a href="/profile" class="btn btn-outline btn-sm">Edit Profile &amp; Avatar &rarr;</a>
                </div>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Username</span>
                        <span class="info-value"><?= htmlspecialchars($user['username'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Full Name</span>
                        <span class="info-value"><?= htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')), ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Email</span>
                        <span class="info-value"><?= htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Role</span>
                        <span class="info-value">
                            <span class="badge"><?= htmlspecialchars($user['role_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                            <?php if (!empty($user['is_super'])): ?>
                                <span class="badge badge-primary">SuperAdmin</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Session Login Time</span>
                        <span class="info-value"><?= htmlspecialchars($user['logged_in_at'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php require __DIR__ . '/layout/footer.php'; ?>
</body>
</html>
