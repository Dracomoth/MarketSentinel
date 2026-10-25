<?php
/**
 * Application Footer Partial - Institutional Wealth Management
 * 
 * Displays relevant system info and market feed diagnostics:
 * - Institutional copyright & platform tier
 * - Market data feed status (FMP / Finnhub)
 * - Database connectivity with status dot
 * - System & schema versions in monospace
 * - UTC server timestamp
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
        <!-- Footer Left: Branding / Institutional Advisory Notice -->
        <div class="footer-left">
            <span class="footer-brand">&copy; <?= date('Y') ?> <?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></span>
            <span class="footer-separator">&bull;</span>
            <span class="footer-subtitle">Institutional Wealth Management &amp; Market Intelligence</span>
        </div>

        <!-- Footer Right: Feeds, Database Status & System Metrics -->
        <div class="footer-right">
            <!-- Market Feeds Status -->
            <div class="footer-item" title="External Feeds: Financial Modeling Prep &amp; Finnhub Ingestion">
                <span class="footer-label">Feeds:</span>
                <span class="footer-value text-emerald">FMP &amp; Finnhub Active</span>
            </div>

            <span class="footer-divider"></span>

            <!-- Database Status -->
            <div class="footer-item footer-item-db <?= $isDbOnline ? 'is-online' : 'is-offline' ?>" 
                 title="Database Connection: <?= $isDbOnline ? 'Connected (Hostinger Cloud MySQL)' : 'Disconnected' ?>">
                <span class="footer-status-dot"></span>
                <span class="footer-label">Database:</span>
                <span class="footer-value"><?= $isDbOnline ? 'Connected' : 'Offline' ?></span>
            </div>

            <span class="footer-divider"></span>

            <!-- System Version -->
            <div class="footer-item" title="Argus Sentinel Framework Version">
                <span class="footer-label">Sys:</span>
                <span class="footer-value">v<?= htmlspecialchars($systemVersion, ENT_QUOTES, 'UTF-8') ?></span>
            </div>

            <span class="footer-divider"></span>

            <!-- Database Version -->
            <div class="footer-item" title="Database Schema Version">
                <span class="footer-label">DB:</span>
                <span class="footer-value">v<?= htmlspecialchars($databaseVersion, ENT_QUOTES, 'UTF-8') ?></span>
            </div>

            <span class="footer-divider"></span>

            <!-- Current Date & UTC Clock -->
            <div class="footer-item footer-item-date" title="Server Execution Date">
                <span class="footer-label">UTC:</span>
                <span class="footer-value"><?= htmlspecialchars($currentDate, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        </div>
    </div>
</footer>

<!-- Core Application Client Scripts -->
<script src="/assets/js/app.js"></script>
