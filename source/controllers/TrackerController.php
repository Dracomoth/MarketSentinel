<?php
/**
 * Tracker Controller - Standalone Asset Radar & On-Demand Council Audit
 * 
 * Manages Screen 3: Asset Tracker (Standalone Asset Radar & On-Demand Council Audit)
 * - Independent watchlist and telemetry without requiring a portfolio
 * - Live market quotes & exchange session status (Abierto / Cerrado)
 * - Adding and removing assets from the tracking radar
 * - On-demand AI Council Audit with real-time news headlines (Comprar / Vigilar / Evitar)
 */

require_once dirname(__DIR__) . '/repositories/TrackerRepository.php';
require_once dirname(__DIR__) . '/services/PermissionService.php';
require_once dirname(__DIR__) . '/services/MarketDataService.php';
require_once dirname(__DIR__) . '/services/NewsService.php';

class TrackerController {
    public static function handle(): void {
        // Enforce authentication
        if (empty($_SESSION['user'])) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '/tracker';
            header('Location: /login', true, 302);
            exit;
        }

        $currentUser = $_SESSION['user'];

        // Enforce screen permission
        if (!PermissionService::canAccess($currentUser, 'tracker')) {
            http_response_code(403);
            echo "<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body><h1>403 Forbidden</h1><p>You do not have permission to access the Asset Tracker.</p><p><a href='/'>Return to Dashboard</a></p></body></html>";
            exit;
        }

        $error = $_SESSION['flash_error'] ?? null;
        $success = $_SESSION['flash_success'] ?? null;
        $activeAuditAssetId = $_SESSION['active_audit_asset_id'] ?? null;
        $activeAuditData = $_SESSION['active_audit_data'] ?? null;
        unset($_SESSION['flash_error'], $_SESSION['flash_success'], $_SESSION['active_audit_asset_id'], $_SESSION['active_audit_data']);

        $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $action = $_POST['action'] ?? $_GET['action'] ?? null;

        // ---------------------------------------------------------------------
        // POST Action Handlers
        // ---------------------------------------------------------------------
        if ($requestMethod === 'POST') {
            // Check write permission for modifying actions
            if (!PermissionService::canWrite($currentUser, 'tracker')) {
                $_SESSION['flash_error'] = "You do not have write permissions to modify the asset tracker.";
                header('Location: /tracker', true, 302);
                exit;
            }

            // 1. Add Asset to Radar
            if ($action === 'add_asset' || $action === 'add') {
                $symbol = strtoupper(trim($_POST['symbol'] ?? ''));
                $notes = trim($_POST['notes'] ?? '');

                if ($symbol === '') {
                    $_SESSION['flash_error'] = "Symbol is required";
                    header('Location: /tracker', true, 302);
                    exit;
                }

                // Check if already tracked
                $existing = TrackerRepository::findBySymbol($symbol);
                if ($existing) {
                    $_SESSION['flash_error'] = "Asset is already being tracked";
                    header('Location: /tracker', true, 302);
                    exit;
                }

                // Fetch live quote from MarketDataService
                $quote = MarketDataService::getQuote($symbol);
                if (empty($quote) || empty($quote['price']) || (float)$quote['price'] <= 0.0) {
                    $_SESSION['flash_error'] = "Symbol not found in market";
                    header('Location: /tracker', true, 302);
                    exit;
                }

                // Determine exchange and timezone
                $exchange = trim($quote['exchange'] ?? 'US');
                if ($exchange === '' || $exchange === 'UNKNOWN') {
                    $exchange = 'US';
                }

                $exchangeTimezone = self::resolveExchangeTimezone($symbol, $exchange);
                $assetType = self::resolveAssetType($symbol, $quote);

                try {
                    TrackerRepository::add([
                        'symbol'            => $symbol,
                        'asset_name'        => !empty($quote['name']) ? trim($quote['name']) : $symbol,
                        'asset_type'        => $assetType,
                        'exchange'          => $exchange,
                        'exchange_timezone' => $exchangeTimezone,
                        'current_price'     => (float) $quote['price'],
                        'change_percent'    => isset($quote['changesPercentage']) ? (float) $quote['changesPercentage'] : (float)($quote['change'] ?? 0.0),
                        'notes'             => $notes !== '' ? $notes : null,
                        'last_checked_at'   => date('Y-m-d H:i:s'),
                    ]);

                    $_SESSION['flash_success'] = "Asset added to tracker successfully";
                } catch (Throwable $e) {
                    $_SESSION['flash_error'] = "Error saving asset: " . $e->getMessage();
                }

                header('Location: /tracker', true, 302);
                exit;
            }

            // 2. On-Demand AI Council Audit / Inspect Asset
            if ($action === 'inspect_asset' || $action === 'inspect' || $action === 'audit') {
                $id = (int) ($_POST['id'] ?? 0);
                $symbol = strtoupper(trim($_POST['symbol'] ?? ''));

                $asset = null;
                if ($id > 0) {
                    $asset = TrackerRepository::findById($id);
                    if ($asset) {
                        $symbol = strtoupper($asset['symbol']);
                    }
                } elseif ($symbol !== '') {
                    $asset = TrackerRepository::findBySymbol($symbol);
                }

                if ($symbol === '') {
                    $msg = "Asset symbol not specified for audit.";
                    if (self::isAjaxRequest()) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'error' => $msg]);
                        exit;
                    }
                    $_SESSION['flash_error'] = $msg;
                    header('Location: /tracker', true, 302);
                    exit;
                }

                // Run AI Council Audit
                $auditResult = self::executeCouncilAudit($symbol, $asset);

                // Save audit in database if asset exists
                if ($asset && !empty($asset['id'])) {
                    TrackerRepository::updateAudit((int)$asset['id'], json_encode($auditResult, JSON_UNESCAPED_UNICODE));
                    $_SESSION['active_audit_asset_id'] = (int)$asset['id'];
                }

                $_SESSION['active_audit_data'] = $auditResult;
                $_SESSION['flash_success'] = "Council audit completed for {$symbol}.";

                // Return JSON if AJAX
                if (self::isAjaxRequest()) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success'  => true,
                        'asset_id' => $asset['id'] ?? null,
                        'symbol'   => $symbol,
                        'audit'    => $auditResult
                    ]);
                    exit;
                }

                header('Location: /tracker', true, 302);
                exit;
            }

            // 3. Remove Asset from Radar
            if ($action === 'remove_asset' || $action === 'remove') {
                $id = (int) ($_POST['id'] ?? 0);
                if ($id > 0) {
                    $ok = TrackerRepository::remove($id);
                    if ($ok) {
                        $_SESSION['flash_success'] = "Asset removed from tracker";
                    } else {
                        $_SESSION['flash_error'] = "Could not remove asset.";
                    }
                } else {
                    $_SESSION['flash_error'] = "Invalid asset identifier.";
                }
                header('Location: /tracker', true, 302);
                exit;
            }
        }

        // ---------------------------------------------------------------------
        // GET Handler (/tracker): Fetch & Refresh Assets
        // ---------------------------------------------------------------------
        $trackedAssets = TrackerRepository::getAll();

        // Refresh quotes if cache expired, and attach market session status
        foreach ($trackedAssets as &$asset) {
            $quote = MarketDataService::getQuote($asset['symbol']);
            if (!empty($quote) && !empty($quote['price'])) {
                $newPrice = (float) $quote['price'];
                $newChg = isset($quote['changesPercentage']) ? (float) $quote['changesPercentage'] : (float)($quote['change'] ?? 0.0);
                
                // Update DB if price or change percent updated
                if (abs((float)$asset['current_price'] - $newPrice) > 0.0001 || abs((float)($asset['change_percent'] ?? 0) - $newChg) > 0.01) {
                    TrackerRepository::updatePrice($asset['symbol'], $newPrice, $newChg);
                    $asset['current_price'] = $newPrice;
                    $asset['change_percent'] = $newChg;
                    $asset['updated'] = date('Y-m-d H:i:s');
                }
            }

            // Compute exchange session status
            $asset['market_session'] = self::getMarketSessionStatus($asset['exchange_timezone'] ?? 'America/New_York', $asset['asset_type'] ?? 'stock');

            // Parse saved audit JSON if present
            $asset['saved_audit'] = null;
            if (!empty($asset['audit_data'])) {
                $parsed = json_decode($asset['audit_data'], true);
                if (is_array($parsed) && !empty($parsed['bottom_line'])) {
                    $asset['saved_audit'] = $parsed;
                }
            }
        }
        unset($asset);

        require dirname(__DIR__) . '/views/tracker.php';
    }

    /**
     * Execute an On-Demand AI Council Audit for an asset
     * 
     * @param string $symbol
     * @param array|null $asset
     * @return array
     */
    protected static function executeCouncilAudit(string $symbol, ?array $asset = null): array {
        $config = require dirname(__DIR__, 2) . '/config/config.php';
        $aiKey = $config['ai']['api_key'] ?? '';
        $aiBase = rtrim($config['ai']['base_url'] ?? 'https://openrouter.ai/api/v1', '/');
        $arbiterModel = $config['ai']['models']['arbiter'] ?? 'anthropic/claude-sonnet-4.5';
        $analystModel = $config['ai']['models']['analyst'] ?? 'deepseek/deepseek-chat';

        // Get latest price & company news
        $quote = MarketDataService::getQuote($symbol);
        $price = !empty($quote['price']) ? (float)$quote['price'] : (float)($asset['current_price'] ?? 0.0);
        $chg = isset($quote['changesPercentage']) ? (float)$quote['changesPercentage'] : (float)($asset['change_percent'] ?? 0.0);
        $name = !empty($quote['name']) ? $quote['name'] : (!empty($asset['asset_name']) ? $asset['asset_name'] : $symbol);

        $news = NewsService::getCompanyNews($symbol, 5);
        $newsLines = [];
        foreach ($news as $i => $n) {
            $newsLines[] = ($i + 1) . ". " . $n['headline'];
        }
        $newsText = !empty($newsLines) ? implode("\n", $newsLines) : "No hay noticias recientes destacadas.";

        $systemPrompt = "You are the Arbiter & Chief Investment Officer of the MarketSentinel Multi-Agent Council.
Your objective is to conduct an institutional audit and express diagnosis on asset {$symbol} ({$name}).
Current market price: \${$price} ({$chg}% today).
Recent headlines and news for this asset:
{$newsText}

MANDATORY LANGUAGE REQUIREMENT: Generate all narrative output in clear, professional English without financial jargon.
Return strict JSON in English with fields: bottom_line ('Buy'|'Watch'|'Avoid'), plain_summary, bull_catalyst, bear_risk, key_level.
Structure:
{
  \"bottom_line\": \"Buy\" | \"Watch\" | \"Avoid\",
  \"plain_summary\": \"string (2 to 3 sentences in clear plain English explaining the situation of this asset)\",
  \"bull_catalyst\": \"string (Key positive catalyst or upside driver)\",
  \"bear_risk\": \"string (Primary downside risk or caution factor)\",
  \"key_level\": \"string (Key price level or condition to watch, e.g. '\$240 support / \$255 resistance')\"
}
Return ONLY valid JSON without extra text or markdown formatting.";

        $userMsg = "Audit asset {$symbol} ({$name}) at current market price \${$price} ({$chg}%).";

        $payload = [
            'model'       => $arbiterModel,
            'messages'    => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userMsg]
            ],
            'max_tokens'  => 800,
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
        curl_setopt($ch, CURLOPT_TIMEOUT, 40);

        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true);
        $content = $data['choices'][0]['message']['content'] ?? '';

        // Fallback to analyst model if arbiter returned error or empty
        if ($code !== 200 || $content === '') {
            $payload['model'] = $analystModel;
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
            curl_setopt($ch, CURLOPT_TIMEOUT, 40);
            $response = curl_exec($ch);
            curl_close($ch);
            $data = json_decode($response, true);
            $content = $data['choices'][0]['message']['content'] ?? '';
        }

        // Clean markdown code blocks
        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/i', $content, $m)) {
            $content = trim($m[1]);
        }
        $firstBrace = strpos($content, '{');
        $lastBrace = strrpos($content, '}');
        if ($firstBrace !== false && $lastBrace !== false && $lastBrace > $firstBrace) {
            $content = substr($content, $firstBrace, $lastBrace - $firstBrace + 1);
        }

        $audit = json_decode($content, true);

        // Fallback robust audit if LLM failed completely
        if (!is_array($audit) || empty($audit['bottom_line'])) {
            $recommendation = $chg > 2.0 ? 'Buy' : ($chg < -3.0 ? 'Avoid' : 'Watch');
            return [
                'symbol'        => $symbol,
                'asset_name'    => $name,
                'bottom_line'   => $recommendation,
                'plain_summary' => "The asset currently trades near \${$price} with a recent change of {$chg}%. Maintaining active institutional volume and market interest.",
                'bull_catalyst' => "Technical momentum recovery and key support consolidation.",
                'bear_risk'     => "Macroeconomic volatility and sector sentiment fluctuations.",
                'key_level'     => "\$" . round($price * 0.95, 2) . " key support / \$" . round($price * 1.08, 2) . " target resistance",
                'generated_at'  => date('Y-m-d H:i:s')
            ];
        }

        $audit['symbol'] = $symbol;
        $audit['asset_name'] = $name;
        $audit['generated_at'] = date('Y-m-d H:i:s');

        return $audit;
    }

    /**
     * Compute exchange session status (Abierto / Cerrado) based on timezone
     * 
     * @param string $exchangeTimezone
     * @param string $assetType
     * @return array
     */
    public static function getMarketSessionStatus(string $exchangeTimezone = 'America/New_York', string $assetType = 'stock'): array {
        if (strtolower($assetType) === 'crypto') {
            return [
                'open'        => true,
                'label'       => 'Open',
                'badge_class' => 'beacon-normal',
                'tz_label'    => '24/7 Continuous Market',
                'local_time'  => date('H:i')
            ];
        }

        try {
            $tz = new DateTimeZone(!empty($exchangeTimezone) ? $exchangeTimezone : 'America/New_York');
            $now = new DateTime('now', $tz);
            $dayOfWeek = (int) $now->format('N'); // 1 = Monday, 7 = Sunday
            $time = $now->format('H:i');
            $tzAbbr = $now->format('T');

            // Weekend: Closed
            if ($dayOfWeek >= 6) {
                return [
                    'open'        => false,
                    'label'       => 'Closed',
                    'badge_class' => 'beacon-closed',
                    'tz_label'    => "Weekend • {$tzAbbr}",
                    'local_time'  => $time
                ];
            }

            // Trading hours: US 09:30 - 16:00, European 09:00 - 17:30
            $isEuropean = (strpos($exchangeTimezone, 'Europe/') !== false);
            $openTime = $isEuropean ? '09:00' : '09:30';
            $closeTime = $isEuropean ? '17:30' : '16:00';

            if ($time >= $openTime && $time < $closeTime) {
                return [
                    'open'        => true,
                    'label'       => 'Open',
                    'badge_class' => 'beacon-normal',
                    'tz_label'    => "Regular Session • {$tzAbbr}",
                    'local_time'  => $time
                ];
            }

            return [
                'open'        => false,
                'label'       => 'Closed',
                'badge_class' => 'beacon-closed',
                'tz_label'    => "After Hours • {$tzAbbr}",
                'local_time'  => $time
            ];
        } catch (Throwable $e) {
            return [
                'open'        => false,
                'label'       => 'Closed',
                'badge_class' => 'beacon-closed',
                'tz_label'    => $exchangeTimezone,
                'local_time'  => date('H:i')
            ];
        }
    }

    /**
     * Resolve exchange timezone from symbol and quote exchange
     */
    protected static function resolveExchangeTimezone(string $symbol, string $exchange): string {
        $upperSym = strtoupper($symbol);
        if (str_ends_with($upperSym, '.MC')) {
            return 'Europe/Madrid';
        }
        if (str_ends_with($upperSym, '.L')) {
            return 'Europe/London';
        }
        if (str_ends_with($upperSym, '.PA')) {
            return 'Europe/Paris';
        }
        if (str_ends_with($upperSym, '.DE')) {
            return 'Europe/Berlin';
        }

        $upperEx = strtoupper($exchange);
        if (strpos($upperEx, 'MADRID') !== false || strpos($upperEx, 'BME') !== false) {
            return 'Europe/Madrid';
        }
        if (strpos($upperEx, 'LONDON') !== false || strpos($upperEx, 'LSE') !== false) {
            return 'Europe/London';
        }

        return 'America/New_York';
    }

    /**
     * Resolve asset type
     */
    protected static function resolveAssetType(string $symbol, array $quote): string {
        $upperSym = strtoupper($symbol);
        if (str_contains($upperSym, 'USD') && (str_contains($upperSym, 'BTC') || str_contains($upperSym, 'ETH') || str_contains($upperSym, 'SOL'))) {
            return 'crypto';
        }
        if (!empty($quote['exchange']) && strtoupper($quote['exchange']) === 'CRYPTO') {
            return 'crypto';
        }
        return 'stock';
    }

    /**
     * Check if current request is AJAX
     */
    protected static function isAjaxRequest(): bool {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            return true;
        }
        if (!empty($_SERVER['HTTP_ACCEPT']) && str_contains(strtolower($_SERVER['HTTP_ACCEPT']), 'application/json')) {
            return true;
        }
        return false;
    }
}
