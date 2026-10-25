#!/usr/bin/env php
<?php
/**
 * MarketSentinel - Timezone-Aware Guardian Runner
 * 
 * Command-line background surveillance worker that continuously evaluates
 * portfolio holdings and suggestions against live market quotes, exchange
 * trading hours, and critical threshold breaches.
 * 
 * Usage:
 *   php scripts/run_guardian.php           # Standard cadence & market session aware run
 *   php scripts/run_guardian.php --force   # Force quotes update bypassing cadence & market hours
 *   php scripts/run_guardian.php --help    # Show help and usage instructions
 */

// Define execution root and require dependencies
$baseDir = dirname(__DIR__);
require_once $baseDir . '/config/config.php';
require_once $baseDir . '/source/services/Database.php';
require_once $baseDir . '/source/repositories/PortfolioRepository.php';
require_once $baseDir . '/source/services/MarketDataService.php';
require_once $baseDir . '/source/services/GuardianService.php';

// Parse command line arguments
$args = $argv ?? [];
$isForced = in_array('--force', $args, true) || in_array('-f', $args, true);
$showHelp = in_array('--help', $args, true) || in_array('-h', $args, true);

if ($showHelp) {
    echo <<<HELP
MarketSentinel Guardian Runner
================================================================================
Automated background surveillance runner for investment portfolios.

Usage:
  php scripts/run_guardian.php [options]

Options:
  -f, --force    Bypass cadence (check_interval_hours) and market operating
                 hours evaluation. Forces live price refresh on all candidate
                 assets for manual testing and audits.
  -h, --help     Display this help documentation.

Cadence & Market Session Rules:
  - Assets are checked only if last_checked_at is NULL or older than interval.
  - Exchanges must be in session or closed within the 90-minute post-market window.
  - Crypto assets trade 24/7 continuously.

HELP;
    exit(0);
}

// ANSI Color helper functions for clean terminal output
function cliColor(string $text, string $color): string {
    // If output is redirected or on non-ansi terminal, keep raw text
    if (DIRECTORY_SEPARATOR === '\\' && !getenv('ANSICON') && !getenv('WT_SESSION') && !getenv('ConEmuANSI') && getenv('TERM') === '') {
        // Windows CMD without ANSI support
        return $text;
    }
    $colors = [
        'green'   => "\033[32m",
        'red'     => "\033[31m",
        'yellow'  => "\033[33m",
        'blue'    => "\033[34m",
        'cyan'    => "\033[36m",
        'bold'    => "\033[1m",
        'dim'     => "\033[2m",
        'reset'   => "\033[0m",
        'bg_red'  => "\033[41;37m",
        'bg_gold' => "\033[43;30m",
    ];
    return ($colors[$color] ?? '') . $text . ($colors['reset'] ?? '');
}

echo "\n";
echo cliColor("================================================================================", 'cyan') . "\n";
echo cliColor("  MARKET SENTINEL // TIMEZONE-AWARE GUARDIAN RUNNER", 'bold') . "\n";
echo cliColor("  Automated Continuous Surveillance & Risk Engine", 'dim') . "\n";
echo cliColor("================================================================================", 'cyan') . "\n";
echo "Run Mode:     " . ($isForced ? cliColor("FORCE (Bypassing Cadence & Market Hours)", 'yellow') : cliColor("STANDARD (Timezone & Cadence Aware)", 'blue')) . "\n";
echo "Timestamp:    " . date('Y-m-d H:i:s T') . "\n";
echo "Database:     " . (defined('DB_NAME') ? DB_NAME : 'configured') . " @" . (defined('DB_HOST') ? DB_HOST : 'localhost') . "\n";
echo cliColor("--------------------------------------------------------------------------------", 'dim') . "\n\n";

try {
    $result = GuardianService::runCheck($isForced);
} catch (Throwable $e) {
    echo cliColor("CRITICAL ERROR: " . $e->getMessage(), 'bg_red') . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

// Render Asset Evaluation Stream
echo cliColor("AUDITED CANDIDATES & EVALUATION STREAM:", 'bold') . "\n";
echo cliColor("--------------------------------------------------------------------------------", 'dim') . "\n";

if (empty($result['details'])) {
    echo "  " . cliColor("No eligible portfolio items found (item_status IN 'holding', 'suggested').", 'dim') . "\n";
} else {
    foreach ($result['details'] as $idx => $d) {
        $num = str_pad((string)($idx + 1), 2, '0', STR_PAD_LEFT);
        $sym = str_pad($d['symbol'], 6);
        $portName = $d['portfolio_name'] ?? "Portfolio #{$d['portfolio_id']}";
        
        switch ($d['status']) {
            case 'checked':
                $pct = (float) $d['pct_change'];
                $pctFormatted = ($pct >= 0 ? "+{$pct}%" : "{$pct}%");
                $pctColored = $pct < -8.0 
                    ? cliColor($pctFormatted, 'red') 
                    : ($pct >= 25.0 ? cliColor($pctFormatted, 'green') : cliColor($pctFormatted, 'cyan'));

                $sessionBadge = '';
                if (!empty($d['market_session'])) {
                    $state = strtoupper($d['market_session']['session_state'] ?? 'UNKNOWN');
                    $localT = $d['market_session']['local_time'] ?? '';
                    $sessionBadge = "[{$state} {$localT}]";
                }

                echo "  {$num}. " . cliColor($sym, 'bold') . " | {$portName} | {$sessionBadge}\n";
                echo "      Price: $" . number_format($d['old_price'], 4) . " -> " . cliColor("$" . number_format($d['new_price'], 4), 'bold') . " (Return vs entry: {$pctColored})\n";
                
                if (!empty($d['trigger_hit'])) {
                    $badge = $d['escalate_level'] === 'urgent' 
                        ? cliColor("[URGENT TRIGGER HIT]", 'bg_red') 
                        : cliColor("[ACTION NEEDED TRIGGER HIT]", 'bg_gold');
                    echo "      {$badge} " . cliColor($d['alert_message'] ?? '', 'yellow') . "\n";
                    echo "      " . cliColor(">> Item status shifted to: {$d['item_status_now']}", 'dim') . "\n";
                } else {
                    echo "      " . cliColor("Status: Normal (Within acceptable thresholds)", 'dim') . "\n";
                }
                break;

            case 'skipped_cadence':
                echo "  {$num}. " . cliColor($sym, 'dim') . " | {$portName}\n";
                echo "      " . cliColor("[SKIPPED - CADENCE]", 'dim') . " {$d['reason']}\n";
                break;

            case 'skipped_market_closed':
                $sessionBadge = '';
                if (!empty($d['market_session'])) {
                    $state = strtoupper($d['market_session']['session_state'] ?? 'CLOSED');
                    $sessionBadge = "[{$state}]";
                }
                echo "  {$num}. " . cliColor($sym, 'dim') . " | {$portName} | {$sessionBadge}\n";
                echo "      " . cliColor("[SKIPPED - MARKET CLOSED]", 'dim') . " {$d['reason']}\n";
                break;

            case 'failed_quote':
                echo "  {$num}. " . cliColor($sym, 'yellow') . " | {$portName}\n";
                echo "      " . cliColor("[WARNING - QUOTE FAILED]", 'red') . " {$d['reason']}\n";
                break;
        }
        echo "\n";
    }
}

// Render Aggregated Portfolios
$updatedPortfolios = !empty($result['portfolios_updated_list']) ? $result['portfolios_updated_list'] : (is_array($result['portfolios_updated'] ?? null) ? $result['portfolios_updated'] : []);
if (!empty($updatedPortfolios)) {
    echo cliColor("AFFECTED PORTFOLIOS VALUATION RE-SYNCHRONIZATION:", 'bold') . "\n";
    echo cliColor("--------------------------------------------------------------------------------", 'dim') . "\n";
    foreach ($updatedPortfolios as $pid => $p) {
        $alertColor = 'green';
        if ($p['alert_status'] === 'urgent') {
            $alertColor = 'red';
        } elseif ($p['alert_status'] === 'action_needed') {
            $alertColor = 'yellow';
        }
        $alertBadge = cliColor(strtoupper($p['alert_status']), $alertColor);
        
        echo "  * Portfolio #{$pid} [" . cliColor($p['name'], 'bold') . "]\n";
        echo "    Current Value:  $" . number_format($p['current_value'], 2) . "\n";
        echo "    Beacon Status:  {$alertBadge}\n";
        if (!empty($p['alert_message'])) {
            echo "    Active Alert:   " . cliColor($p['alert_message'], 'yellow') . "\n";
        }
        echo "\n";
    }
}

// Summary Metrics Box
echo cliColor("================================================================================", 'cyan') . "\n";
echo cliColor("  SURVEILLANCE CYCLE SUMMARY", 'bold') . "\n";
echo cliColor("================================================================================", 'cyan') . "\n";
echo "  Total Candidate Assets:   " . $result['total_candidates'] . "\n";
echo "  Audited & Price Updated:  " . cliColor((string)$result['checked_count'], 'green') . "\n";
echo "  Skipped (Cadence Window): " . $result['skipped_cadence_count'] . "\n";
echo "  Skipped (Market Closed):  " . $result['skipped_market_closed_count'] . "\n";
echo "  Risk Alerts Triggered:    " . ($result['alerts_triggered_count'] > 0 ? cliColor((string)$result['alerts_triggered_count'], 'red') : "0") . "\n";
echo "  Portfolios Synchronized:  " . (is_numeric($result['portfolios_updated'] ?? null) ? $result['portfolios_updated'] : count($updatedPortfolios)) . "\n";
if (isset($result['tracked_assets_updated']) && $result['tracked_assets_updated'] > 0) {
    echo "  Tracked Assets Refreshed: " . $result['tracked_assets_updated'] . "\n";
}
echo "  Cycle Run Duration:       " . $result['duration_seconds'] . "s\n";
echo cliColor("================================================================================", 'cyan') . "\n\n";

exit(0);
