<?php
/**
 * Application Footer Partial
 * 
 * Displays relevant system info:
 * - Database status (online / offline)
 * - System version
 * - Database version
 * - Current date
 */

require_once dirname(__DIR__, 2) . '/services/LayoutService.php';

if (!isset($layoutContext)) {
    $layoutContext = LayoutService::getContext($currentUser ?? $user ?? null);
}

$appName = $layoutContext['appName'];
$systemVersion = $layoutContext['systemVersion'];
$databaseVersion = $layoutContext['databaseVersion'];
$isDbOnline = $layoutContext['isDbOnline'];
$currentDate = $layoutContext['currentDate'];
?>
<footer class="app-footer">
    <div class="footer-container">
        <!-- Footer Left: Branding / Copyright -->
        <div class="footer-left">
            <span class="footer-brand">&copy; <?= date('Y') ?> <?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></span>
            <span class="footer-separator">&bull;</span>
            <span class="footer-subtitle">Core Framework</span>
        </div>

        <!-- Footer Right: Relevant System Info -->
        <div class="footer-right">
            <!-- Database Status -->
            <div class="footer-item footer-item-db <?= $isDbOnline ? 'is-online' : 'is-offline' ?>" 
                 title="Database Status: <?= $isDbOnline ? 'Online (Connected)' : 'Offline (Disconnected)' ?>">
                <span class="footer-status-dot"></span>
                <span class="footer-label">Database:</span>
                <span class="footer-value"><?= $isDbOnline ? 'Online' : 'Offline' ?></span>
            </div>

            <span class="footer-divider"></span>

            <!-- System Version -->
            <div class="footer-item" title="Application System Version">
                <span class="footer-label">System:</span>
                <span class="footer-value">v<?= htmlspecialchars($systemVersion, ENT_QUOTES, 'UTF-8') ?></span>
            </div>

            <span class="footer-divider"></span>

            <!-- Database Version -->
            <div class="footer-item" title="Database Schema Version">
                <span class="footer-label">DB:</span>
                <span class="footer-value">v<?= htmlspecialchars($databaseVersion, ENT_QUOTES, 'UTF-8') ?></span>
            </div>

            <span class="footer-divider"></span>

            <!-- Current Date -->
            <div class="footer-item footer-item-date" title="Current Server Date">
                <span class="footer-label">Date:</span>
                <span class="footer-value"><?= htmlspecialchars($currentDate, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        </div>
    </div>
</footer>

<!-- Core Application Client Scripts -->
<script src="/assets/js/app.js"></script>
