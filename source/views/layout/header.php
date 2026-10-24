<?php
/**
 * Application Header Partial
 * 
 * Left: Sandwich toggle, application title, relevant system info.
 * Right: Logged user avatar circle (or initials) with dropdown & logout.
 */

require_once dirname(__DIR__, 2) . '/services/LayoutService.php';

// Prepare layout context if not already set
if (!isset($layoutContext)) {
    $layoutContext = LayoutService::getContext($currentUser ?? $user ?? null);
}

$user = $layoutContext['user'];
$appName = $layoutContext['appName'];
$appEnv = $layoutContext['appEnv'];
$systemVersion = $layoutContext['systemVersion'];
$isDbOnline = $layoutContext['isDbOnline'];
$initials = $layoutContext['initials'];
$avatarPath = $layoutContext['avatarPath'];
$displayName = !empty($user) ? trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) : 'User';
if ($displayName === '') {
    $displayName = $user['username'] ?? 'User';
}
?>
<header class="app-header">
    <div class="header-container">
        <!-- Header Left: Sandwich Icon, App Title, System Info -->
        <div class="header-left">
            <!-- Sandwich Icon / Sidebar Toggle -->
            <button type="button" class="sidebar-toggle-btn" id="btn-sidebar-toggle" 
                    aria-label="Toggle navigation menu" aria-controls="app-sidebar" aria-expanded="false" 
                    title="Toggle navigation sidebar">
                <span class="hamburger-bar"></span>
                <span class="hamburger-bar"></span>
                <span class="hamburger-bar"></span>
            </button>

            <!-- Application Title -->
            <a href="/" class="brand-link" title="<?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?> - Dashboard">
                <span class="brand-logo-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
                        <polyline points="2 17 12 22 22 17"></polyline>
                        <polyline points="2 12 12 17 22 12"></polyline>
                    </svg>
                </span>
                <span class="brand-name"><?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></span>
            </a>
        </div>

        <!-- Header Right: User Avatar Circle (navigates to User Information page) -->
        <div class="header-right">
            <a href="/profile" class="user-avatar-btn" id="btn-user-avatar" 
               aria-label="User Information" 
               title="<?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($user['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>) - View & Edit Profile">
                <?php if (!empty($avatarPath)): ?>
                    <img src="<?= htmlspecialchars($avatarPath, ENT_QUOTES, 'UTF-8') ?>" 
                         alt="<?= htmlspecialchars($user['username'] ?? 'User', ENT_QUOTES, 'UTF-8') ?>" 
                         class="user-avatar-img" 
                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                <?php endif; ?>
                <span class="user-avatar-initials" style="<?= !empty($avatarPath) ? 'display: none;' : '' ?>">
                    <?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?>
                </span>
            </a>
        </div>
    </div>
</header>
