<?php
/**
 * Portfolios Controller - Institutional Wealth Management
 * 
 * Manages Screen 2: My Portfolios (Portfolio List & Detailed Guardian View)
 * - Live aggregated valuations & performance metrics
 * - Live quote refreshing via MarketDataService
 * - Position purchase confirmations ("Mark as Purchased")
 * - AI Council Strategy Recalculation ("Recalculate Strategy")
 * - Portfolio deletion & alert monitoring
 */

require_once dirname(__DIR__) . '/services/Database.php';
require_once dirname(__DIR__) . '/repositories/PortfolioRepository.php';
require_once dirname(__DIR__) . '/services/PermissionService.php';
require_once dirname(__DIR__) . '/services/MarketDataService.php';
require_once dirname(__DIR__) . '/services/GuardianService.php';
require_once dirname(__DIR__) . '/services/CouncilChatService.php';

class PortfoliosController {
    public static function handle(): void {
        // Enforce authentication
        if (empty($_SESSION['user'])) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '/portfolios';
            header('Location: /login', true, 302);
            exit;
        }

        $currentUser = $_SESSION['user'];

        // Enforce screen permission
        if (!PermissionService::canAccess($currentUser, 'portfolios')) {
            http_response_code(403);
            echo "<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body><h1>403 Forbidden</h1><p>You do not have permission to access My Portfolios.</p><p><a href='/'>Return to Dashboard</a></p></body></html>";
            exit;
        }

        $error = $_SESSION['flash_error'] ?? null;
        $success = $_SESSION['flash_success'] ?? null;
        unset($_SESSION['flash_error'], $_SESSION['flash_success']);

        $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $action = $_POST['action'] ?? $_GET['action'] ?? null;

        // ---------------------------------------------------------------------
        // POST Action Handlers
        // ---------------------------------------------------------------------
        if ($requestMethod === 'POST') {
            // Action 1: Buy Position (with fees)
            if ($action === 'buy_item' || $action === 'mark_purchased') {
                $portfolioId = (int) ($_POST['portfolio_id'] ?? 0);
                $itemId = (int) ($_POST['item_id'] ?? 0);
                $rawPrice = trim($_POST['purchase_price'] ?? $_POST['price'] ?? '');
                $purchasePrice = (float) str_replace([',', ' ', '$', '€'], '', $rawPrice);
                $purchaseShares = (int) ($_POST['purchase_shares'] ?? $_POST['shares'] ?? 0);
                $feeType = trim($_POST['broker_fee_type'] ?? 'fixed');
                $feeValue = (float) str_replace([',', ' ', '$', '€'], '', $_POST['broker_fee_value'] ?? '0');

                if ($itemId <= 0 || $purchasePrice <= 0 || $purchaseShares <= 0) {
                    $_SESSION['flash_error'] = "Invalid buy parameters. Price and shares must be greater than zero.";
                } else {
                    try {
                        $res = PortfolioRepository::buyItem($itemId, $purchasePrice, $purchaseShares, $feeType, $feeValue);
                        $_SESSION['flash_success'] = "Successfully bought {$purchaseShares} shares. Cash buffer adjusted.";
                    } catch (Throwable $e) {
                        $_SESSION['flash_error'] = "Buy order failed: " . $e->getMessage();
                    }
                }

                header('Location: /portfolios?id=' . $portfolioId, true, 302);
                exit;
            }

            // Action 2: Sell Position (with fees and cash buffer return)
            if ($action === 'sell_item') {
                $portfolioId = (int) ($_POST['portfolio_id'] ?? 0);
                $itemId = (int) ($_POST['item_id'] ?? 0);
                $rawPrice = trim($_POST['sell_price'] ?? $_POST['price'] ?? '');
                $sellPrice = (float) str_replace([',', ' ', '$', '€'], '', $rawPrice);
                $sharesSold = (int) ($_POST['shares_sold'] ?? $_POST['shares'] ?? 0);
                $feeType = trim($_POST['broker_fee_type'] ?? 'fixed');
                $feeValue = (float) str_replace([',', ' ', '$', '€'], '', $_POST['broker_fee_value'] ?? '0');

                if ($itemId <= 0 || $sellPrice <= 0 || $sharesSold <= 0) {
                    $_SESSION['flash_error'] = "Invalid sell parameters. Price and shares must be greater than zero.";
                } else {
                    try {
                        $res = PortfolioRepository::sellItem($itemId, $sellPrice, $sharesSold, $feeType, $feeValue);
                        $_SESSION['flash_success'] = "Successfully sold {$sharesSold} shares. Net proceeds credited to cash buffer.";
                    } catch (Throwable $e) {
                        $_SESSION['flash_error'] = "Sell order failed: " . $e->getMessage();
                    }
                }

                header('Location: /portfolios?id=' . $portfolioId, true, 302);
                exit;
            }

            // Action 3: Delete Asset from Portfolio (only when shares == 0)
            if ($action === 'delete_item') {
                $portfolioId = (int) ($_POST['portfolio_id'] ?? 0);
                $itemId = (int) ($_POST['item_id'] ?? 0);

                if ($itemId <= 0) {
                    $_SESSION['flash_error'] = "Invalid asset identifier.";
                } else {
                    try {
                        PortfolioRepository::deleteItem($itemId);
                        $_SESSION['flash_success'] = "Asset removed from portfolio.";
                    } catch (Throwable $e) {
                        $_SESSION['flash_error'] = "Failed to remove asset: " . $e->getMessage();
                    }
                }

                header('Location: /portfolios?id=' . $portfolioId, true, 302);
                exit;
            }

            // Action 4: Edit Asset Parameters (Target Price, Stop Loss, Strategy, etc.)
            if ($action === 'edit_asset') {
                $portfolioId = (int) ($_POST['portfolio_id'] ?? 0);
                $itemId = (int) ($_POST['item_id'] ?? 0);

                if ($itemId <= 0) {
                    $_SESSION['flash_error'] = "Invalid asset identifier.";
                } else {
                    $rawTarget = trim($_POST['target_price'] ?? '');
                    $targetPrice = $rawTarget !== '' ? (float) str_replace([',', ' ', '$', '€'], '', $rawTarget) : null;
                    $rawStop = trim($_POST['stop_loss_price'] ?? '');
                    $stopLossPrice = $rawStop !== '' ? (float) str_replace([',', ' ', '$', '€'], '', $rawStop) : null;
                    $strategy = trim($_POST['asset_strategy'] ?? 'hold');
                    $suggestedShares = (int) ($_POST['suggested_shares'] ?? 0);
                    $timeHorizon = trim($_POST['time_horizon'] ?? 'mid_term');
                    $checkInterval = (int) ($_POST['check_interval_hours'] ?? 24);
                    $whyPicked = trim($_POST['why_picked'] ?? '');
                    $invalidationTrigger = trim($_POST['invalidation_trigger'] ?? '');

                    try {
                        PortfolioRepository::updateAssetDetails($itemId, [
                            'target_price'         => $targetPrice,
                            'stop_loss_price'      => $stopLossPrice,
                            'asset_strategy'       => $strategy,
                            'suggested_shares'     => $suggestedShares,
                            'time_horizon'         => $timeHorizon,
                            'check_interval_hours' => $checkInterval,
                            'why_picked'           => $whyPicked,
                            'invalidation_trigger' => $invalidationTrigger,
                        ]);
                        $_SESSION['flash_success'] = "Asset risk and strategy parameters updated.";
                    } catch (Throwable $e) {
                        $_SESSION['flash_error'] = "Failed to update asset: " . $e->getMessage();
                    }
                }

                header('Location: /portfolios?id=' . $portfolioId, true, 302);
                exit;
            }

            // Action 5: Add Asset to Portfolio
            if ($action === 'add_asset') {
                $portfolioId = (int) ($_POST['portfolio_id'] ?? 0);
                $symbol = strtoupper(trim($_POST['symbol'] ?? ''));

                if ($portfolioId <= 0 || $symbol === '') {
                    $_SESSION['flash_error'] = "Please provide a valid ticker symbol.";
                } else {
                    $rawTarget = trim($_POST['target_price'] ?? '');
                    $targetPrice = $rawTarget !== '' ? (float) str_replace([',', ' ', '$', '€'], '', $rawTarget) : null;
                    $rawStop = trim($_POST['stop_loss_price'] ?? '');
                    $stopLossPrice = $rawStop !== '' ? (float) str_replace([',', ' ', '$', '€'], '', $rawStop) : null;
                    $strategy = trim($_POST['asset_strategy'] ?? 'watch');
                    $suggestedShares = (int) ($_POST['suggested_shares'] ?? 0);
                    $whyPicked = trim($_POST['why_picked'] ?? '');

                    try {
                        PortfolioRepository::addAssetToPortfolio($portfolioId, [
                            'symbol'           => $symbol,
                            'target_price'     => $targetPrice,
                            'stop_loss_price'  => $stopLossPrice,
                            'asset_strategy'   => $strategy,
                            'suggested_shares' => $suggestedShares,
                            'why_picked'       => $whyPicked,
                        ]);
                        $_SESSION['flash_success'] = "Asset {$symbol} added to portfolio.";
                    } catch (Throwable $e) {
                        $_SESSION['flash_error'] = "Failed to add asset: " . $e->getMessage();
                    }
                }

                header('Location: /portfolios?id=' . $portfolioId, true, 302);
                exit;
            }

            // Action 6: Adjust Portfolio Capital / Cash Buffer
            if ($action === 'adjust_capital') {
                $portfolioId = (int) ($_POST['portfolio_id'] ?? 0);
                $rawCapital = trim($_POST['new_capital'] ?? '');
                $newCapital = (float) str_replace([',', ' ', '$', '€'], '', $rawCapital);

                if ($portfolioId <= 0 || $newCapital <= 0) {
                    $_SESSION['flash_error'] = "Please enter a valid capital amount greater than zero.";
                } else {
                    try {
                        PortfolioRepository::updatePortfolioCapital($portfolioId, $newCapital);
                        $_SESSION['flash_success'] = "Portfolio capital adjusted to $" . number_format($newCapital, 2);
                    } catch (Throwable $e) {
                        $_SESSION['flash_error'] = "Failed to adjust capital: " . $e->getMessage();
                    }
                }

                header('Location: /portfolios?id=' . $portfolioId, true, 302);
                exit;
            }

            // Action 7: AI Council Interactive Advisory Message
            if ($action === 'send_council_chat') {
                $portfolioId = (int) ($_POST['portfolio_id'] ?? 0);
                $message = trim($_POST['message'] ?? '');
                $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
                       || (!empty($_POST['ajax']));

                if ($isAjax) {
                    @set_time_limit(120);
                    @ini_set('max_execution_time', '120');

                    // Clean any preceding output buffer/notices to guarantee a clean JSON stream
                    while (ob_get_level() > 0) {
                        ob_end_clean();
                    }
                    ob_start();
                }

                if ($portfolioId <= 0 || $message === '') {
                    if ($isAjax) {
                        ob_end_clean();
                        header('Content-Type: application/json; charset=utf-8');
                        echo json_encode(['success' => false, 'error' => 'Message cannot be empty.']);
                        exit;
                    }
                    $_SESSION['flash_error'] = "Message cannot be empty.";
                } else {
                    try {
                        $result = CouncilChatService::sendMessage($portfolioId, $message);
                        if ($isAjax) {
                            require_once dirname(__DIR__) . '/services/MarkdownService.php';
                            ob_end_clean();
                            header('Content-Type: application/json; charset=utf-8');
                            echo json_encode([
                                'success'           => true,
                                'message_id'        => $result['message_id'] ?? 0,
                                'raw_message'       => $result['message'] ?? '',
                                'rendered_html'     => MarkdownService::render($result['message'] ?? ''),
                                'structured_action' => $result['structured_action'] ?? null,
                                'created_time'      => date('H:i'),
                                'portfolio_id'      => $portfolioId
                            ]);
                            exit;
                        }
                        $_SESSION['flash_success'] = "Council advisory received.";
                    } catch (Throwable $e) {
                        if ($isAjax) {
                            ob_end_clean();
                            header('Content-Type: application/json; charset=utf-8');
                            echo json_encode(['success' => false, 'error' => "Council orchestrator error: " . $e->getMessage()]);
                            exit;
                        }
                        $_SESSION['flash_error'] = "Council orchestrator error: " . $e->getMessage();
                    }
                }

                header('Location: /portfolios?id=' . $portfolioId, true, 302);
                exit;
            }

            // Action 8: Confirm & Execute AI Council Proposal
            if ($action === 'execute_chat_action') {
                $portfolioId = (int) ($_POST['portfolio_id'] ?? 0);
                $messageId = (int) ($_POST['message_id'] ?? 0);

                if ($portfolioId <= 0 || $messageId <= 0) {
                    $_SESSION['flash_error'] = "Invalid action confirmation request.";
                } else {
                    try {
                        $res = CouncilChatService::executeAction($portfolioId, $messageId);
                        $_SESSION['flash_success'] = "Action executed: " . ($res['summary'] ?? 'Proposal applied.');
                    } catch (Throwable $e) {
                        $_SESSION['flash_error'] = "Failed to execute council proposal: " . $e->getMessage();
                    }
                }

                header('Location: /portfolios?id=' . $portfolioId, true, 302);
                exit;
            }

            // Action 8b: Clear Council Chat History
            if ($action === 'clear_council_chat') {
                $portfolioId = (int) ($_POST['portfolio_id'] ?? 0);
                if ($portfolioId > 0) {
                    PortfolioRepository::clearChatHistory($portfolioId);
                    $_SESSION['flash_success'] = "Council chat history cleared.";
                }
                header('Location: /portfolios?id=' . $portfolioId, true, 302);
                exit;
            }

            // Action 9: Recalculate Strategy with AI Council (Arbiter Model)
            if ($action === 'recalculate_strategy') {
                $portfolioId = (int) ($_POST['portfolio_id'] ?? 0);
                $portfolio = $portfolioId > 0 ? PortfolioRepository::findById($portfolioId) : null;

                if (!$portfolio) {
                    $_SESSION['flash_error'] = "Portfolio not found";
                    header('Location: /portfolios', true, 302);
                    exit;
                }

                $items = PortfolioRepository::getItems($portfolioId);

                try {
                    $newStrategy = self::generateRecalculationAdvice($portfolio, $items);
                    PortfolioRepository::updateStrategySummary($portfolioId, $newStrategy);
                    $_SESSION['flash_success'] = "Strategy recalculated successfully";
                } catch (Throwable $e) {
                    $_SESSION['flash_error'] = "Error recalculating strategy: " . $e->getMessage();
                }

                header('Location: /portfolios?id=' . $portfolioId, true, 302);
                exit;
            }

            // Action 10: Delete Portfolio with Strict Safeguards
            if ($action === 'delete_portfolio' || $action === 'delete') {
                $portfolioId = (int) ($_POST['id'] ?? $_POST['portfolio_id'] ?? 0);
                if ($portfolioId > 0) {
                    if (PortfolioRepository::hasActivePositions($portfolioId)) {
                        $_SESSION['flash_error'] = "Cannot delete portfolio with active holdings. Sell all shares first.";
                        header('Location: /portfolios?id=' . $portfolioId, true, 302);
                        exit;
                    }

                    try {
                        $ok = PortfolioRepository::delete($portfolioId);
                        if ($ok) {
                            $_SESSION['flash_success'] = "Portfolio deleted successfully";
                        } else {
                            $_SESSION['flash_error'] = "Failed to delete portfolio #{$portfolioId}.";
                        }
                    } catch (Throwable $e) {
                        $_SESSION['flash_error'] = $e->getMessage();
                    }
                }
                header('Location: /portfolios', true, 302);
                exit;
            }

            // Action 11: Update Alert Status
            if ($action === 'update_alert') {
                $portfolioId = (int) ($_POST['portfolio_id'] ?? 0);
                $status = trim($_POST['alert_status'] ?? 'normal');
                $message = trim($_POST['alert_message'] ?? '');

                if ($portfolioId > 0) {
                    PortfolioRepository::setAlertStatus($portfolioId, $status, $message !== '' ? $message : null);
                    $_SESSION['flash_success'] = "Sentinel alert status updated successfully.";
                }
                header('Location: /portfolios?id=' . $portfolioId, true, 302);
                exit;
            }

            // Action 12: Run Guardian Surveillance On-Demand
            if ($action === 'run_guardian_now') {
                if (!PermissionService::canAccess($currentUser, 'portfolios')) {
                    $_SESSION['flash_error'] = "You do not have permission to trigger market surveillance.";
                    header('Location: /portfolios', true, 302);
                    exit;
                }

                try {
                    $result = GuardianService::runCheck(true);
                    $checkedItems = (int) ($result['checked_items'] ?? $result['checked_count'] ?? 0);
                    $alertsTriggered = (int) ($result['alerts_triggered'] ?? $result['alerts_triggered_count'] ?? 0);
                    $_SESSION['flash_success'] = "Market scan complete: {$checkedItems} portfolio assets checked across active exchanges. {$alertsTriggered} alert(s) flagged.";
                } catch (Throwable $e) {
                    $_SESSION['flash_error'] = "Guardian scan failed: " . $e->getMessage();
                }

                header('Location: /portfolios', true, 302);
                exit;
            }
        }

        // ---------------------------------------------------------------------
        // In-App Opportunistic Check ("Heartbeat" Check)
        // Check MAX(last_checked_at) on candidate portfolio_items; if older than
        // 60 minutes (or never checked), opportunistically run cadence-aware scan
        // ---------------------------------------------------------------------
        self::runOpportunisticHeartbeatCheck();

        // ---------------------------------------------------------------------
        // Detail vs List View Logic
        // ---------------------------------------------------------------------
        $selectedId = isset($_GET['id']) ? (int) $_GET['id'] : null;
        $portfolio = null;
        $items = [];
        $portfolios = [];
        $metrics = [];

        if ($selectedId !== null && $selectedId > 0) {
            // === DETAIL VIEW MODE ===
            $portfolio = PortfolioRepository::findById($selectedId);
            if (!$portfolio) {
                $_SESSION['flash_error'] = "Portfolio not found";
                header('Location: /portfolios', true, 302);
                exit;
            }

            $rawItems = PortfolioRepository::getItems($selectedId);
            $items = [];

            $totalInvested = 0.0;
            $currentHoldingsValue = 0.0;
            $purchasedCount = 0;

            foreach ($rawItems as $it) {
                // Refresh live quote via MarketDataService
                $sym = strtoupper(trim($it['symbol']));
                $quote = MarketDataService::getQuote($sym);

                $livePrice = (float) $it['current_price'];
                $quoteChangePct = 0.0;

                if ($quote && !empty($quote['price'])) {
                    $newPrice = (float) $quote['price'];
                    $quoteChangePct = (float) ($quote['changesPercentage'] ?? 0.0);

                    // Update live price in database if price moved
                    if (abs($newPrice - $livePrice) > 0.0001) {
                        PortfolioRepository::updateItemPrice((int) $it['id'], $newPrice);
                        $livePrice = $newPrice;
                    }
                }

                $it['current_price'] = $livePrice;
                $it['quote_change_pct'] = $quoteChangePct;

                $isPurchased = !empty($it['is_purchased']);
                if ($isPurchased) {
                    $purchasedCount++;
                    $buyPrice = (float) $it['purchase_price'];
                    $shares = (int) $it['purchase_shares'];
                    $costBasis = $buyPrice * $shares;
                    $marketVal = $livePrice * $shares;
                    $pnlDollar = $marketVal - $costBasis;
                    $pnlPercent = $costBasis > 0 ? ($pnlDollar / $costBasis) * 100 : 0.0;

                    $it['cost_basis'] = $costBasis;
                    $it['market_val'] = $marketVal;
                    $it['pnl_dollar'] = $pnlDollar;
                    $it['pnl_percent'] = $pnlPercent;

                    $totalInvested += $costBasis;
                    $currentHoldingsValue += $marketVal;
                } else {
                    $suggShares = (int) $it['suggested_shares'];
                    $it['cost_basis'] = 0.0;
                    $it['market_val'] = round($livePrice * $suggShares, 2);
                    $it['pnl_dollar'] = 0.0;
                    $it['pnl_percent'] = 0.0;
                }

                $items[] = $it;
            }

            $initialCapital = (float) $portfolio['initial_capital'];
            $cashBuffer = $portfolio['cash_balance'] !== null 
                ? (float) $portfolio['cash_balance'] 
                : max(0.0, round($initialCapital - $totalInvested, 2));

            if ($purchasedCount > 0) {
                $currentPortfolioValue = round($currentHoldingsValue + $cashBuffer, 2);
                $netPnlDollar = round($currentHoldingsValue - $totalInvested, 2);
                $netReturnPercent = $totalInvested > 0 ? round(($netPnlDollar / $totalInvested) * 100, 2) : 0.0;
            } else {
                $currentPortfolioValue = $portfolio['cash_balance'] !== null ? round($cashBuffer, 2) : $initialCapital;
                $netPnlDollar = 0.0;
                $netReturnPercent = 0.0;
            }

            // Sync updated valuation to database
            PortfolioRepository::updateValues($selectedId, $currentPortfolioValue);

            $metrics = [
                'initial_capital'      => $initialCapital,
                'total_invested'       => $totalInvested,
                'current_value'        => $currentPortfolioValue,
                'cash_buffer'          => $cashBuffer,
                'net_pnl_dollar'       => $netPnlDollar,
                'net_return_percent'   => $netReturnPercent,
                'purchased_count'      => $purchasedCount,
                'total_items_count'    => count($items),
            ];

            // Chat history & deletion safeguard
            $chatHistory = PortfolioRepository::getChatHistory($selectedId, 50);
            $hasActivePositions = PortfolioRepository::hasActivePositions($selectedId);

        } else {
            // === LIST VIEW MODE ===
            $chatHistory = [];
            $hasActivePositions = false;
            $rawPortfolios = PortfolioRepository::getAll();
            $portfolios = [];

            foreach ($rawPortfolios as $p) {
                $pId = (int) $p['id'];
                $pItems = PortfolioRepository::getItems($pId);

                $initCap = (float) $p['initial_capital'];
                $invested = 0.0;
                $holdingsVal = 0.0;
                $bought = 0;

                foreach ($pItems as $it) {
                    if (!empty($it['is_purchased'])) {
                        $bought++;
                        $c = (float)$it['purchase_price'] * (int)$it['purchase_shares'];
                        $m = (float)$it['current_price'] * (int)$it['purchase_shares'];
                        $invested += $c;
                        $holdingsVal += $m;
                    }
                }

                $cash = max(0.0, round($initCap - $invested, 2));
                if ($bought > 0) {
                    $currVal = round($holdingsVal + $cash, 2);
                    $diff = round($holdingsVal - $invested, 2);
                    $returnPct = $invested > 0 ? round(($diff / $invested) * 100, 2) : 0.0;
                } else {
                    $currVal = (float) $p['current_value'];
                    $diff = round($currVal - $initCap, 2);
                    $returnPct = $initCap > 0 ? round(($diff / $initCap) * 100, 2) : 0.0;
                }

                $p['computed_current_value'] = $currVal;
                $p['computed_return_diff'] = $diff;
                $p['computed_return_pct'] = $returnPct;
                $p['computed_purchased_count'] = $bought;
                $p['computed_total_items'] = count($pItems);

                $portfolios[] = $p;
            }
        }

        require dirname(__DIR__) . '/views/portfolios.php';
    }

    /**
     * Call AI Council (Arbiter Model) to recalculate portfolio allocation & strategy
     */
    protected static function generateRecalculationAdvice(array $portfolio, array $items): string {
        $config = require dirname(__DIR__, 2) . '/config/config.php';
        $aiKey = $config['ai']['api_key'] ?? '';
        $aiBase = rtrim($config['ai']['base_url'] ?? 'https://openrouter.ai/api/v1', '/');
        $arbiterModel = $config['ai']['models']['arbiter'] ?? 'anthropic/claude-sonnet-4.5';
        $fallbackModel = $config['ai']['models']['analyst'] ?? 'deepseek/deepseek-chat';

        $holdingsSummary = [];
        $totalCost = 0.0;
        foreach ($items as $it) {
            $isBought = !empty($it['is_purchased']);
            $cost = $isBought ? ((float)$it['purchase_price'] * (int)$it['purchase_shares']) : 0.0;
            $totalCost += $cost;

            $holdingsSummary[] = [
                'symbol'         => $it['symbol'],
                'name'           => $it['asset_name'],
                'status'         => $isBought ? 'holding' : 'suggested_unbought',
                'purchase_price' => $isBought ? (float)$it['purchase_price'] : null,
                'current_price'  => (float)$it['current_price'],
                'shares'         => $isBought ? (int)$it['purchase_shares'] : (int)$it['suggested_shares'],
                'why_picked'     => $it['why_picked'],
            ];
        }

        $initCap = (float)$portfolio['initial_capital'];
        $cashRemaining = max(0.0, round($initCap - $totalCost, 2));

        $systemPrompt = "You are the Chief Investment Officer & Arbiter of the MarketSentinel Multi-Agent Council.
Your objective is to re-evaluate and recalculate the portfolio strategy based on real purchase execution, current market prices, and remaining cash buffer.
Provide the updated rebalancing advice and thesis in clear, plain English.
The report must include:
1. Diagnosis of the current portfolio status and executed holdings vs suggested positions.
2. Concrete action recommendation for each position (hold, monitor, take profits, or discard if unbought).
3. Deployment roadmap for remaining capital or cash buffer.
Be specific, citing tickers and actual market prices.
DO NOT return JSON. Return only the markdown text of the report in plain English.";

        $userMsg = "Portfolio: {$portfolio['name']}\nOriginal Mandate: {$portfolio['user_prompt']}\nInitial Capital: \${$initCap}\nRemaining Cash: \${$cashRemaining}\nCurrent Positions & Market Quotes:\n" . json_encode($holdingsSummary, JSON_PRETTY_PRINT);

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userMsg]
        ];

        $payload = [
            'model'       => $arbiterModel,
            'messages'    => $messages,
            'max_tokens'  => 1200,
            'temperature' => 0.2,
        ];

        $ch = curl_init("{$aiBase}/chat/completions");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $aiKey,
            'Content-Type: application/json',
            'HTTP-Referer: http://localhost:8000',
            'X-Title: MarketSentinel'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 50);

        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true);
        $content = $data['choices'][0]['message']['content'] ?? '';

        // If arbiter failed or returned error, try fallback analyst model
        if ($code !== 200 || $content === '') {
            $payload['model'] = $fallbackModel;
            $ch = curl_init("{$aiBase}/chat/completions");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $aiKey,
                'Content-Type: application/json',
                'HTTP-Referer: http://localhost:8000',
                'X-Title: MarketSentinel'
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 50);
            $response = curl_exec($ch);
            curl_close($ch);
            $data = json_decode($response, true);
            $content = $data['choices'][0]['message']['content'] ?? '';
        }

        if ($content === '') {
            throw new RuntimeException("The Investment Council could not generate recalculation recommendations.");
        }

        $dateHeader = "=== COUNCIL RE-EVALUATION (" . date('m/d/Y H:i') . ") ===\n\n";
        return $dateHeader . trim($content) . "\n\n--- Previous Strategy ---\n" . $portfolio['strategy_summary'];
    }

    /**
     * In-App Opportunistic Surveillance ("Heartbeat" Check)
     * Executes cadence & market-hour aware scan if no check occurred in the past 60 minutes.
     */
    protected static function runOpportunisticHeartbeatCheck(): void {
        try {
            $pdo = Database::getConnection();
            if (!$pdo) {
                return;
            }

            // Check if there are any candidate portfolio items
            $stmt = $pdo->query("SELECT MAX(last_checked_at) AS latest_check FROM `portfolio_items` WHERE `item_status` IN ('suggested', 'holding')");
            $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
            $latestCheck = $row['latest_check'] ?? null;

            $shouldRun = false;
            if (empty($latestCheck)) {
                $shouldRun = true;
            } else {
                $secondsSince = time() - strtotime($latestCheck);
                if ($secondsSince >= 3600) { // More than 60 minutes ago
                    $shouldRun = true;
                }
            }

            // Apply a 5-minute cooldown throttle file so rapid page loads don't re-check if market is closed
            $throttleFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'marketsentinel_heartbeat_throttle.txt';
            $lastRun = file_exists($throttleFile) ? (int) @file_get_contents($throttleFile) : 0;
            if ($shouldRun && (time() - $lastRun) >= 300) {
                @file_put_contents($throttleFile, (string) time());
                GuardianService::runCheck(false);
            }
        } catch (Throwable $e) {
            error_log("Opportunistic Guardian heartbeat check error: " . $e->getMessage());
        }
    }
}
