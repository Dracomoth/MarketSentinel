<?php
/**
 * Application Sidebar Partial - Institutional Wealth Management
 * 
 * Displays screens from the 'screens' table.
 * Grouped screens are organized under institutional categories:
 * - Overview (Portfolio Command Center)
 * - Investments & Trading (Terminal, Watchlist, Paper Trades)
 * - Governance & Security (Users, Roles & Permissions)
 */

require_once dirname(__DIR__, 2) . '/services/LayoutService.php';

if (!isset($layoutContext)) {
    $layoutContext = LayoutService::getContext($currentUser ?? $user ?? null);
}

$appName = $layoutContext['appName'];
$currentPath = $layoutContext['currentPath'];
$navScreens = $layoutContext['navScreens'];
$user = $layoutContext['user'];
$initials = $layoutContext['initials'];
$displayName = !empty($user) ? trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) : 'User';
if ($displayName === '') {
    $displayName = $user['username'] ?? 'User';
}
?>

<!-- Backdrop Overlay for off-canvas drawer -->
<div class="sidebar-backdrop" id="sidebar-backdrop" aria-hidden="true"></div>

<!-- Off-Canvas Navigation Sidebar -->
<aside class="app-sidebar" id="app-sidebar" aria-label="Main Application Navigation">
    <!-- Sidebar Header -->
    <div class="sidebar-header">
        <a href="/" class="sidebar-brand">
            <span class="sidebar-brand-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
                    <polyline points="2 17 12 22 22 17"></polyline>
                    <polyline points="2 12 12 17 22 12"></polyline>
                </svg>
            </span>
            <span class="sidebar-brand-title"><?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></span>
        </a>
        <button type="button" class="sidebar-close-btn" id="btn-sidebar-close" aria-label="Close navigation sidebar" title="Close sidebar">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
        </button>
    </div>

    <!-- Navigation Scroll Container -->
    <div class="sidebar-body">
        <nav class="sidebar-nav">
            <!-- Section: Overview / Portfolio Command Center -->
            <div class="nav-section">
                <span class="nav-section-title">Overview</span>
                <ul class="nav-list">
                    <li class="nav-item">
                        <a href="/" class="nav-link <?= ($currentPath === '/' || $currentPath === '') ? 'active' : '' ?>">
                            <span class="nav-icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="3" width="7" height="7"></rect>
                                    <rect x="14" y="3" width="7" height="7"></rect>
                                    <rect x="14" y="14" width="7" height="7"></rect>
                                    <rect x="3" y="14" width="7" height="7"></rect>
                                </svg>
                            </span>
                            <span class="nav-label">Portfolio Dashboard</span>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Standalone Screens (grouped = 0) -->
            <?php if (!empty($navScreens['standalone'])): ?>
                <div class="nav-section">
                    <span class="nav-section-title">Advisory Tools</span>
                    <ul class="nav-list">
                        <?php foreach ($navScreens['standalone'] as $screen): ?>
                            <?php 
                            $screenUrl = '/' . htmlspecialchars($screen['screen_key'], ENT_QUOTES, 'UTF-8');
                            $isActive = (strpos($currentPath, $screenUrl) === 0);
                            ?>
                            <li class="nav-item">
                                <a href="<?= $screenUrl ?>" class="nav-link <?= $isActive ? 'active' : '' ?>">
                                    <span class="nav-icon">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                                        </svg>
                                    </span>
                                    <span class="nav-label"><?= htmlspecialchars($screen['name'], ENT_QUOTES, 'UTF-8') ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Grouped Screens (grouped = 1, separated by category: Trading & Investments, Security) -->
            <?php if (!empty($navScreens['groups'])): ?>
                <div class="nav-section">
                    <span class="nav-section-title">Wealth Management</span>
                    <div class="nav-groups-container">
                        <?php foreach ($navScreens['groups'] as $groupName => $groupData): ?>
                            <?php
                            $hasActiveChild = false;
                            foreach ($groupData['items'] as $item) {
                                if (strpos($currentPath, '/' . $item['screen_key']) === 0) {
                                    $hasActiveChild = true;
                                    break;
                                }
                            }

                            // Refined institutional group title
                            $displayGroupName = $groupName;
                            if (strcasecmp($groupName, 'Trading') === 0 || strcasecmp($groupName, 'Investments') === 0) {
                                $displayGroupName = 'Investments';
                            } elseif (strcasecmp($groupName, 'Security') === 0) {
                                $displayGroupName = 'Security & Governance';
                            }
                            ?>
                            <div class="nav-group <?= $hasActiveChild ? 'is-open' : '' ?>" data-group="<?= htmlspecialchars($groupName, ENT_QUOTES, 'UTF-8') ?>">
                                <!-- Group Header displaying group name -->
                                <button type="button" class="nav-group-header" 
                                        aria-expanded="<?= $hasActiveChild ? 'true' : 'false' ?>" 
                                        title="Toggle <?= htmlspecialchars($displayGroupName, ENT_QUOTES, 'UTF-8') ?> modules">
                                    <span class="group-header-left">
                                        <span class="group-icon">
                                            <?php if (stripos($groupName, 'security') !== false): ?>
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                                                </svg>
                                            <?php elseif (stripos($groupName, 'trading') !== false || stripos($groupName, 'invest') !== false): ?>
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline>
                                                    <polyline points="17 6 23 6 23 12"></polyline>
                                                </svg>
                                            <?php elseif (stripos($groupName, 'report') !== false || stripos($groupName, 'analytic') !== false): ?>
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <line x1="18" y1="20" x2="18" y2="10"></line>
                                                    <line x1="12" y1="20" x2="12" y2="4"></line>
                                                    <line x1="6" y1="20" x2="6" y2="14"></line>
                                                </svg>
                                            <?php else: ?>
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                                                    <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                                                </svg>
                                            <?php endif; ?>
                                        </span>
                                        <span class="group-title"><?= htmlspecialchars($displayGroupName, ENT_QUOTES, 'UTF-8') ?></span>
                                    </span>
                                    <span class="group-chevron">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <polyline points="6 9 12 15 18 9"></polyline>
                                        </svg>
                                    </span>
                                </button>

                                <!-- Items under the group (sorted by sort_order) -->
                                <ul class="nav-group-items">
                                    <?php foreach ($groupData['items'] as $item): ?>
                                        <?php 
                                        $itemUrl = '/' . htmlspecialchars($item['screen_key'], ENT_QUOTES, 'UTF-8');
                                        $isActiveItem = (strpos($currentPath, $itemUrl) === 0);
                                        ?>
                                        <li class="nav-subitem">
                                            <a href="<?= $itemUrl ?>" class="nav-sublink <?= $isActiveItem ? 'active' : '' ?>">
                                                <div style="display: inline-flex; align-items: center;">
                                                    <span class="sublink-dot"></span>
                                                    <span class="sublink-label"><?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?></span>
                                                </div>
                                                <span class="sublink-order-badge" title="Sort order: <?= (int) $item['sort_order'] ?>">
                                                    #<?= (int) $item['sort_order'] ?>
                                                </span>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </nav>
    </div>

    <!-- Sidebar Footer with user info & institutional tier -->
    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="sidebar-user-avatar">
                <?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?>
            </div>
            <div class="sidebar-user-text">
                <span class="sidebar-user-name"><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?></span>
                <span class="sidebar-user-role"><?= htmlspecialchars($user['role_name'] ?? 'Portfolio Manager', ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        </div>
        <a href="/logout" class="sidebar-logout-btn" title="Sign Out of Session" aria-label="Sign out">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                <polyline points="16 17 21 12 16 7"></polyline>
                <line x1="21" y1="12" x2="9" y2="12"></line>
            </svg>
        </a>
    </div>
</aside>
