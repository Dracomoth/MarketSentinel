<?php
/**
 * Guardian Service - Automated Timezone-Aware Background Monitoring Engine
 * 
 * Implements the continuous surveillance loop:
 * 1. Eligible asset selection filtered by cadence (check_interval_hours)
 * 2. Exchange timezone and market session evaluation (in-session or 90-min post-market window)
 * 3. Live price refreshes via MarketDataService
 * 4. Critical threshold & trigger evaluation (> 8% loss breach, > 25% profit target)
 * 5. Parent portfolio alert escalation & aggregate valuation synchronization
 */

require_once dirname(__DIR__) . '/services/Database.php';
require_once dirname(__DIR__) . '/repositories/PortfolioRepository.php';
require_once dirname(__DIR__) . '/repositories/TrackerRepository.php';
require_once dirname(__DIR__) . '/services/MarketDataService.php';

class GuardianService {
    /**
     * Run guardian surveillance check across candidate assets
     * 
     * @param bool $force Bypass cadence and market hours checks (for testing/manual invocation)
     * @return array Structured execution summary log
     */
    public static function runCheck(bool $force = false): array {
        $pdo = Database::getConnection();
        if (!$pdo) {
            throw new RuntimeException("Database connection unavailable for Guardian check.");
        }

        $startTime = microtime(true);
        $runTimestamp = date('Y-m-d H:i:s');

        // ---------------------------------------------------------------------
        // 1. Eligible Assets Selection
        // Query portfolio_items where item_status IN ('suggested', 'holding')
        // ---------------------------------------------------------------------
        $sql = "SELECT 
                    pi.*,
                    p.name AS portfolio_name,
                    p.alert_status AS portfolio_alert_status,
                    p.alert_message AS portfolio_alert_message,
                    p.initial_capital,
                    CASE 
                        WHEN pi.last_checked_at IS NULL THEN 1
                        WHEN pi.last_checked_at <= NOW() - INTERVAL COALESCE(pi.check_interval_hours, 24) HOUR THEN 1
                        ELSE 0
                    END AS cadence_due
                FROM `portfolio_items` pi
                JOIN `portfolios` p ON pi.portfolio_id = p.id
                WHERE pi.item_status IN ('suggested', 'holding')
                ORDER BY pi.portfolio_id ASC, pi.id ASC";

        $stmt = $pdo->query($sql);
        $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $checkedCount = 0;
        $skippedCadenceCount = 0;
        $skippedMarketClosedCount = 0;
        $alertsTriggeredCount = 0;
        $affectedPortfolioIds = [];
        $details = [];

        // ---------------------------------------------------------------------
        // 2. Iterate candidates & evaluate cadence, market hours & thresholds
        // ---------------------------------------------------------------------
        foreach ($candidates as $item) {
            $itemId = (int) $item['id'];
            $portfolioId = (int) $item['portfolio_id'];
            $symbol = strtoupper(trim($item['symbol']));
            $assetType = strtolower(trim($item['asset_type'] ?? 'stock'));
            $timezone = trim($item['exchange_timezone'] ?? 'America/New_York');
            $intervalHours = max(1, (int) ($item['check_interval_hours'] ?? 24));

            // a) Cadence Check
            $cadenceDue = !empty($item['cadence_due']);
            $hoursSinceLastCheck = null;
            if (!empty($item['last_checked_at'])) {
                $lastCheckedTs = strtotime($item['last_checked_at']);
                $hoursSinceLastCheck = round((time() - $lastCheckedTs) / 3600, 1);
            }

            if (!$force && !$cadenceDue) {
                $skippedCadenceCount++;
                $details[] = [
                    'item_id'          => $itemId,
                    'portfolio_id'     => $portfolioId,
                    'portfolio_name'   => $item['portfolio_name'],
                    'symbol'           => $symbol,
                    'status'           => 'skipped_cadence',
                    'reason'           => "Cadence active: checked " . ($hoursSinceLastCheck !== null ? "{$hoursSinceLastCheck}h ago" : "recently") . " (interval: {$intervalHours}h)",
                    'market_session'   => null,
                    'trigger_hit'      => null,
                ];
                continue;
            }

            // b) Timezone & Market Hours Evaluation
            $sessionInfo = self::evaluateMarketSession($timezone, $assetType);

            if (!$force && empty($sessionInfo['in_window'])) {
                $skippedMarketClosedCount++;
                $details[] = [
                    'item_id'          => $itemId,
                    'portfolio_id'     => $portfolioId,
                    'portfolio_name'   => $item['portfolio_name'],
                    'symbol'           => $symbol,
                    'status'           => 'skipped_market_closed',
                    'reason'           => "Market closed: {$sessionInfo['reason']}",
                    'market_session'   => $sessionInfo,
                    'trigger_hit'      => null,
                ];
                continue;
            }

            // c) Market Data & Threshold Evaluation
            // Fetch live quote using force refresh to get latest market trade
            $quote = MarketDataService::getQuote($symbol, true);

            if (!$quote || empty($quote['price']) || (float) $quote['price'] <= 0.0) {
                $details[] = [
                    'item_id'          => $itemId,
                    'portfolio_id'     => $portfolioId,
                    'portfolio_name'   => $item['portfolio_name'],
                    'symbol'           => $symbol,
                    'status'           => 'failed_quote',
                    'reason'           => "Unable to retrieve live market quote for symbol {$symbol}",
                    'market_session'   => $sessionInfo,
                    'trigger_hit'      => null,
                ];
                continue;
            }

            $oldPrice = (float) $item['current_price'];
            $newPrice = (float) $quote['price'];

            // Update price and last_checked_at in database
            PortfolioRepository::updateItemPrice($itemId, $newPrice);
            $checkedCount++;
            $affectedPortfolioIds[$portfolioId] = true;

            // Determine entry benchmark price
            $isHolding = !empty($item['is_purchased']) || $item['item_status'] === 'holding';
            if ($isHolding) {
                $entryPrice = (float) (!empty($item['purchase_price']) && (float) $item['purchase_price'] > 0 
                    ? $item['purchase_price'] 
                    : (!empty($item['suggested_price']) ? $item['suggested_price'] : $oldPrice));
            } else {
                $entryPrice = (float) (!empty($item['suggested_price']) && (float) $item['suggested_price'] > 0 
                    ? $item['suggested_price'] 
                    : $oldPrice);
            }

            if ($entryPrice <= 0.0) {
                $entryPrice = $newPrice;
            }

            $pctChange = round((($newPrice - $entryPrice) / $entryPrice) * 100, 2);

            // Critical trigger conditions
            $triggerHit = null;
            $escalateLevel = null;
            $alertMsg = null;
            $newItemStatus = $item['item_status'];

            // Condition 1: Significant Drop (> 8% drop below entry)
            if ($pctChange <= -8.0) {
                // Update item status to sell_recommended
                $newItemStatus = 'sell_recommended';
                PortfolioRepository::updateItemStatus($itemId, 'sell_recommended');

                // Evaluate severity: severe if dropped > 15%
                if ($pctChange <= -15.0) {
                    $triggerHit = 'severe_loss_breach';
                    $escalateLevel = 'urgent';
                    $alertMsg = "Alert on {$symbol}: Dropped " . abs($pctChange) . "% below entry. Review for possible exit.";
                } else {
                    $triggerHit = 'loss_breach';
                    $escalateLevel = 'action_needed';
                    $alertMsg = "Alert on {$symbol}: Dropped " . abs($pctChange) . "% below entry. Review for possible exit.";
                }

                // Escalate parent portfolio
                PortfolioRepository::setAlertStatus($portfolioId, $escalateLevel, $alertMsg);
                $alertsTriggeredCount++;
            }
            // Condition 2: Profit Target (> 25% gain above entry)
            elseif ($pctChange >= 25.0) {
                $triggerHit = 'profit_target';
                $escalateLevel = 'action_needed';
                $alertMsg = "Alert on {$symbol}: Gained " . $pctChange . "% above entry. Profit target reached.";

                // Escalate if not already marked as urgent
                if ($item['portfolio_alert_status'] !== 'urgent') {
                    PortfolioRepository::setAlertStatus($portfolioId, $escalateLevel, $alertMsg);
                }
                $alertsTriggeredCount++;
            }

            $details[] = [
                'item_id'          => $itemId,
                'portfolio_id'     => $portfolioId,
                'portfolio_name'   => $item['portfolio_name'],
                'symbol'           => $symbol,
                'status'           => 'checked',
                'is_holding'       => $isHolding,
                'entry_price'      => $entryPrice,
                'old_price'        => $oldPrice,
                'new_price'        => $newPrice,
                'pct_change'       => $pctChange,
                'trigger_hit'      => $triggerHit,
                'escalate_level'   => $escalateLevel,
                'alert_message'    => $alertMsg,
                'item_status_now'  => $newItemStatus,
                'market_session'   => $sessionInfo,
            ];
        }

        // ---------------------------------------------------------------------
        // 3. Aggregate Portfolio Totals
        // Recalculate total current_value for all affected portfolios
        // ---------------------------------------------------------------------
        $portfoliosUpdated = [];
        foreach (array_keys($affectedPortfolioIds) as $pid) {
            $newVal = PortfolioRepository::recalculatePortfolioValue($pid);
            $pObj = PortfolioRepository::findById($pid);
            $portfoliosUpdated[$pid] = [
                'id'            => $pid,
                'name'          => $pObj['name'] ?? "Portfolio #{$pid}",
                'current_value' => $newVal,
                'alert_status'  => $pObj['alert_status'] ?? 'normal',
                'alert_message' => $pObj['alert_message'] ?? null,
            ];
        }

        // ---------------------------------------------------------------------
        // 4. Standalone Tracked Assets Price Refresh
        // ---------------------------------------------------------------------
        $trackedUpdatedCount = 0;
        try {
            $trackedSql = "SELECT * FROM `tracked_assets` ORDER BY `id` ASC";
            $tStmt = $pdo->query($trackedSql);
            $trackedAssets = $tStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($trackedAssets as $ta) {
                $tSym = strtoupper(trim($ta['symbol']));
                $tTz = trim($ta['exchange_timezone'] ?? 'America/New_York');
                $tType = strtolower(trim($ta['asset_type'] ?? 'stock'));

                // Evaluate cadence / market hours if not forced
                if (!$force) {
                    if (!empty($ta['last_checked_at'])) {
                        $hoursSince = (time() - strtotime($ta['last_checked_at'])) / 3600;
                        if ($hoursSince < 1) {
                            continue;
                        }
                    }
                    $tSession = self::evaluateMarketSession($tTz, $tType);
                    if (empty($tSession['in_window'])) {
                        continue;
                    }
                }

                $tQuote = MarketDataService::getQuote($tSym, $force);
                if ($tQuote && !empty($tQuote['price'])) {
                    $tPrice = (float) $tQuote['price'];
                    $tChg = (float) ($tQuote['changesPercentage'] ?? 0.0);
                    TrackerRepository::updatePrice($tSym, $tPrice, $tChg);
                    $trackedUpdatedCount++;
                }
            }
        } catch (Throwable $e) {
            error_log("GuardianService tracked_assets refresh error: " . $e->getMessage());
        }

        $duration = round(microtime(true) - $startTime, 3);

        return [
            'checked_items'               => $checkedCount,
            'portfolios_updated'          => count($portfoliosUpdated),
            'alerts_triggered'            => $alertsTriggeredCount,
            'details'                     => $details,
            // Auxiliary keys for backward compatibility and CLI runner
            'checked_count'               => $checkedCount,
            'portfolios_updated_list'     => $portfoliosUpdated,
            'alerts_triggered_count'      => $alertsTriggeredCount,
            'tracked_assets_updated'      => $trackedUpdatedCount,
            'total_candidates'            => count($candidates),
            'skipped_cadence_count'       => $skippedCadenceCount,
            'skipped_market_closed_count' => $skippedMarketClosedCount,
            'duration_seconds'            => $duration,
            'timestamp'                   => $runTimestamp,
            'is_forced'                   => $force,
        ];
    }

    /**
     * Evaluate market session status for a given timezone and asset type
     * Determines whether the market is open or within the 90-minute post-market close window.
     * 
     * @param string $timezone
     * @param string $assetType
     * @return array
     */
    public static function evaluateMarketSession(string $timezone = 'America/New_York', string $assetType = 'stock'): array {
        // Crypto trades 24/7 continuously
        if (strtolower($assetType) === 'crypto') {
            return [
                'in_session'    => true,
                'in_window'     => true,
                'session_state' => 'open',
                'local_time'    => date('H:i'),
                'timezone'      => $timezone,
                'reason'        => '24/7 Continuous Crypto Trading',
            ];
        }

        try {
            $tz = new DateTimeZone(!empty($timezone) ? $timezone : 'America/New_York');
        } catch (Throwable $e) {
            $tz = new DateTimeZone('America/New_York');
        }

        $now = new DateTime('now', $tz);
        $dayOfWeek = (int) $now->format('N'); // 1 = Monday, 7 = Sunday
        $time = $now->format('H:i');
        $tzAbbr = $now->format('T');

        // Weekends: Closed
        if ($dayOfWeek >= 6) {
            return [
                'in_session'    => false,
                'in_window'     => false,
                'session_state' => 'closed',
                'local_time'    => $time,
                'timezone'      => $tz->getName(),
                'reason'        => "Weekend ({$tzAbbr}) - Exchange closed",
            ];
        }

        // Determine session bounds by exchange geography
        $tzName = $tz->getName();
        if (strpos($tzName, 'Europe/') !== false) {
            $openTime = '09:00';
            $closeTime = '17:30';
            $postCloseTime = '19:00'; // 90-minute post-close evaluation window
        } elseif (strpos($tzName, 'Asia/Tokyo') !== false) {
            $openTime = '09:00';
            $closeTime = '15:30';
            $postCloseTime = '17:00'; // 90-minute post-close evaluation window
        } elseif (strpos($tzName, 'Asia/Hong_Kong') !== false || strpos($tzName, 'Asia/Shanghai') !== false) {
            $openTime = '09:30';
            $closeTime = '16:00';
            $postCloseTime = '17:30';
        } else {
            // Default US (NYSE, NASDAQ, AMEX)
            $openTime = '09:30';
            $closeTime = '16:00';
            $postCloseTime = '17:30'; // 90-minute post-close evaluation window
        }

        // 1. Regular market hours
        if ($time >= $openTime && $time < $closeTime) {
            return [
                'in_session'    => true,
                'in_window'     => true,
                'session_state' => 'open',
                'local_time'    => $time,
                'timezone'      => $tz->getName(),
                'reason'        => "Regular trading session ({$openTime} - {$closeTime} {$tzAbbr})",
            ];
        }

        // 2. Post-close evaluation window (90 minutes)
        if ($time >= $closeTime && $time < $postCloseTime) {
            return [
                'in_session'    => false,
                'in_window'     => true,
                'session_state' => 'post_close',
                'local_time'    => $time,
                'timezone'      => $tz->getName(),
                'reason'        => "Post-market close window ({$closeTime} - {$postCloseTime} {$tzAbbr})",
            ];
        }

        // 3. Fully closed outside trading window
        return [
            'in_session'    => false,
            'in_window'     => false,
            'session_state' => 'closed',
            'local_time'    => $time,
            'timezone'      => $tz->getName(),
            'reason'        => "Market closed ({$time} {$tzAbbr}, outside {$openTime} - {$postCloseTime})",
        ];
    }
}
