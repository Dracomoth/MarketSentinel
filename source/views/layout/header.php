<?php
/**
 * Application Header Partial - Institutional Wealth Management
 * 
 * Left: Sandwich toggle, Institutional sentinel brand, Portal status
 * Right: Live market status chip, Theme toggle, Executive user avatar
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
        <!-- Header Left: Sandwich Icon, App Title, Institutional Portal Tag -->
        <div class="header-left">
            <!-- Sandwich Icon / Sidebar Toggle -->
            <button type="button" class="sidebar-toggle-btn" id="btn-sidebar-toggle" 
                    aria-label="Toggle navigation menu" aria-controls="app-sidebar" aria-expanded="false" 
                    title="Toggle navigation sidebar">
                <span class="hamburger-bar"></span>
                <span class="hamburger-bar"></span>
                <span class="hamburger-bar"></span>
            </button>

            <!-- Application Title & Institutional Emblem -->
            <a href="/" class="brand-link" title="<?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?> - Institutional Wealth Advisory">
                <span class="brand-logo-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
                        <polyline points="2 17 12 22 22 17"></polyline>
                        <polyline points="2 12 12 17 22 12"></polyline>
                    </svg>
                </span>
                <span class="brand-name"><?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></span>
                <span class="brand-portal-tag">Institutional Wealth</span>
            </a>
        </div>

        <!-- Header Right: Market Status, Theme Switcher & User Avatar -->
        <div class="header-right">
            <div class="header-status-group">
                <!-- Real-time Market Status Indicator -->
                <div class="market-status-chip is-live" title="Global Market Surveillance: Real-time Data Feeds Active">
                    <span class="pulse-dot"></span>
                    <span class="market-status-text">US / EU Markets Live</span>
                </div>

                <!-- Theme Switcher (Dark Executive / Light Finance) -->
                <button type="button" class="theme-toggle-btn" id="btn-theme-toggle" 
                        title="Toggle Light / Dark Finance Mode" aria-label="Toggle theme mode">
                    <svg class="icon-moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"></path>
                    </svg>
                </button>
            </div>

            <!-- Executive User Avatar Circle -->
            <a href="/profile" class="user-avatar-btn" id="btn-user-avatar" 
               aria-label="User Information" 
               title="<?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($user['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>) - Institutional Portfolio Manager">
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
