<?php
/**
 * Council Chat Service - AI Council Orchestrator for MarketSentinel
 * 
 * Provides interactive institutional advisory for portfolios by orchestrating
 * the 5-Agent Council (Macro, Fundamental, Technical, Bull/Bear, Risk Arbiter).
 * Contextualizes portfolio positions, live quotes, cash balances, and risk parameters.
 * Formulates structured actionable proposals (JSON) requiring explicit user confirmation.
 */

require_once dirname(__DIR__) . '/repositories/PortfolioRepository.php';
require_once dirname(__DIR__) . '/services/MarketDataService.php';

class CouncilChatService {
    /**
     * Send user message to the AI Council Orchestrator and record the response
     * 
     * @param int $portfolioId
     * @param string $userMessage
     * @return array ['success' => bool, 'message' => string, 'structured_action' => ?array]
     */
    public static function sendMessage(int $portfolioId, string $userMessage): array {
        $portfolio = PortfolioRepository::findById($portfolioId);
        if (!$portfolio) {
            throw new InvalidArgumentException("Portfolio not found.");
        }

        $userMessage = trim($userMessage);
        if ($userMessage === '') {
            throw new InvalidArgumentException("Message cannot be empty.");
        }

        // 1. Record user message in database
        PortfolioRepository::saveChatMessage($portfolioId, 'user', $userMessage);

        // 2. Load context
        $items = PortfolioRepository::getItems($portfolioId);
        $chatHistory = PortfolioRepository::getChatHistory($portfolioId, 12);

        // Calculate cash & valuations
        $totalInvested = 0.0;
        $holdingsVal = 0.0;
        foreach ($items as $it) {
            if (!empty($it['is_purchased']) && (int)($it['purchase_shares'] ?? 0) > 0) {
                $totalInvested += (float)$it['purchase_price'] * (int)$it['purchase_shares'];
                $holdingsVal += (float)$it['current_price'] * (int)$it['purchase_shares'];
            }
        }
        $cashBalance = $portfolio['cash_balance'] !== null 
            ? (float)$portfolio['cash_balance'] 
            : max(0.0, round((float)$portfolio['initial_capital'] - $totalInvested, 2));

        // 3. Build system prompt injecting full institutional portfolio state
        $systemPrompt = self::buildSystemPrompt($portfolio, $items, $cashBalance, $totalInvested, $holdingsVal);

        // 4. Assemble OpenAI-compatible messages array
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt]
        ];

        // Include recent history (excluding the very message we just added to avoid duplicate if fetched)
        $historyCount = count($chatHistory);
        $recent = array_slice($chatHistory, max(0, $historyCount - 10));
        foreach ($recent as $msg) {
            $role = in_array($msg['role'], ['user', 'assistant', 'system'], true) ? $msg['role'] : 'user';
            $content = $msg['message'];
            if (!empty($msg['structured_action']) && is_array($msg['structured_action'])) {
                $content .= "\n[Action Proposed: " . ($msg['structured_action']['summary'] ?? '') . "]";
            }
            $messages[] = ['role' => $role, 'content' => $content];
        }

        // 5. Call AI through OpenRouter (Try fast analyst first to avoid 402 credit limits on sonnet)
        $config = require dirname(__DIR__, 2) . '/config/config.php';
        $aiKey = $config['ai']['api_key'] ?? '';
        $aiBase = rtrim($config['ai']['base_url'] ?? 'https://openrouter.ai/api/v1', '/');
        $analystModel = $config['ai']['models']['analyst'] ?? 'deepseek/deepseek-chat';
        $arbiterModel = $config['ai']['models']['arbiter'] ?? 'anthropic/claude-sonnet-4.5';

        $rawResponse = '';
        $attemptErrors = [];
        $modelsToTry = [$analystModel, $arbiterModel];

        foreach ($modelsToTry as $model) {
            try {
                $rawResponse = self::callOpenRouter($aiBase, $aiKey, $model, $messages, 650, 0.2);
                if ($rawResponse !== '') {
                    break;
                }
            } catch (Throwable $e) {
                $attemptErrors[] = "{$model}: " . $e->getMessage();
            }
        }

        if ($rawResponse === '') {
            throw new RuntimeException("All Council AI models failed: " . implode(" | ", $attemptErrors));
        }

        // 6. Extract structured action proposal if present
        $parsed = self::extractStructuredAction($rawResponse);
        $cleanMessage = $parsed['clean_message'];
        $action = $parsed['action'];

        // 7. Save assistant message with action
        $messageId = PortfolioRepository::saveChatMessage($portfolioId, 'assistant', $cleanMessage, $action);

        return [
            'success'           => true,
            'message_id'        => $messageId,
            'message'           => $cleanMessage,
            'structured_action' => $action,
        ];
    }

    /**
     * Execute an action proposed by the Council upon user confirmation
     * 
     * @param int $portfolioId
     * @param int $messageId
     * @return array
     */
    public static function executeAction(int $portfolioId, int $messageId): array {
        $msg = PortfolioRepository::getChatMessageById($messageId);
        if (!$msg || (int)$msg['portfolio_id'] !== $portfolioId) {
            throw new InvalidArgumentException("Council proposal message not found.");
        }

        $action = $msg['structured_action'];
        if (empty($action) || !is_array($action)) {
            throw new RuntimeException("No actionable proposal found in this message.");
        }

        if (!empty($action['executed'])) {
            throw new RuntimeException("This proposal has already been executed.");
        }

        $actionType = $action['action_type'] ?? '';
        $payload = $action['payload'] ?? [];
        $summary = $action['summary'] ?? $actionType;

        switch ($actionType) {
            case 'update_strategy':
                if (!empty($payload['item_id'])) {
                    $strategy = in_array($payload['asset_strategy'] ?? '', ['buy', 'hold', 'sell', 'rebalance', 'watch'], true)
                        ? $payload['asset_strategy']
                        : 'hold';
                    PortfolioRepository::updateAssetDetails((int)$payload['item_id'], ['asset_strategy' => $strategy]);
                }
                if (!empty($payload['portfolio_strategy_summary'])) {
                    PortfolioRepository::updateStrategySummary($portfolioId, trim($payload['portfolio_strategy_summary']));
                }
                break;

            case 'set_risk_levels':
                $itemId = (int)($payload['item_id'] ?? 0);
                if ($itemId <= 0) {
                    throw new InvalidArgumentException("Missing item_id for setting risk levels.");
                }
                $updateData = [];
                if (isset($payload['target_price'])) {
                    $updateData['target_price'] = $payload['target_price'] !== null ? (float)$payload['target_price'] : null;
                }
                if (isset($payload['stop_loss_price'])) {
                    $updateData['stop_loss_price'] = $payload['stop_loss_price'] !== null ? (float)$payload['stop_loss_price'] : null;
                }
                if (isset($payload['asset_strategy'])) {
                    $updateData['asset_strategy'] = $payload['asset_strategy'];
                }
                PortfolioRepository::updateAssetDetails($itemId, $updateData);
                break;

            case 'add_asset':
                $symbol = strtoupper(trim($payload['symbol'] ?? ''));
                if ($symbol === '') {
                    throw new InvalidArgumentException("Cannot add asset without a ticker symbol.");
                }
                PortfolioRepository::addAssetToPortfolio($portfolioId, $payload);
                break;

            case 'adjust_capital':
                $newCapital = (float)($payload['new_capital'] ?? 0);
                if ($newCapital <= 0) {
                    throw new InvalidArgumentException("Capital must be greater than zero.");
                }
                PortfolioRepository::updatePortfolioCapital($portfolioId, $newCapital);
                break;

            case 'draft_trade':
                $tradeType = strtolower(trim($payload['trade_type'] ?? 'buy'));
                $itemId = (int)($payload['item_id'] ?? 0);
                $price = (float)($payload['price'] ?? 0);
                $shares = (int)($payload['shares'] ?? 0);
                $feeType = trim($payload['fee_type'] ?? 'fixed');
                $feeValue = (float)($payload['fee_value'] ?? 0);

                if ($itemId <= 0 || $price <= 0 || $shares <= 0) {
                    throw new InvalidArgumentException("Invalid trade draft parameters.");
                }

                if ($tradeType === 'buy') {
                    PortfolioRepository::buyItem($itemId, $price, $shares, $feeType, $feeValue);
                } elseif ($tradeType === 'sell') {
                    PortfolioRepository::sellItem($itemId, $price, $shares, $feeType, $feeValue);
                } else {
                    throw new InvalidArgumentException("Unsupported trade type: {$tradeType}");
                }
                break;

            default:
                throw new InvalidArgumentException("Unrecognized council action type: {$actionType}");
        }

        // Mark action as executed in database
        $action['executed'] = true;
        $action['executed_at'] = date('Y-m-d H:i:s');
        PortfolioRepository::updateChatMessageAction($messageId, $action);

        // Record a confirmation system message in the chat stream
        PortfolioRepository::saveChatMessage($portfolioId, 'system', "✓ Confirmed & Executed: " . $summary);

        // Re-synchronize portfolio valuation
        PortfolioRepository::recalculatePortfolioValue($portfolioId);

        return [
            'success' => true,
            'action'  => $action,
            'summary' => $summary,
        ];
    }

    /**
     * Build rich institutional system prompt injecting the portfolio's entire operational context
     */
    protected static function buildSystemPrompt(array $portfolio, array $items, float $cashBalance, float $totalInvested, float $holdingsVal): string {
        $holdingsList = [];
        foreach ($items as $it) {
            $isHolding = !empty($it['is_purchased']) && (int)($it['purchase_shares'] ?? 0) > 0;
            $statusStr = $isHolding ? 'HOLDING' : 'SUGGESTED / UNBOUGHT';
            $shares = $isHolding ? (int)$it['purchase_shares'] : (int)$it['suggested_shares'];
            $buyPrice = $isHolding && $it['purchase_price'] !== null ? '$' . number_format((float)$it['purchase_price'], 2) : 'N/A';
            $currPrice = '$' . number_format((float)$it['current_price'], 2);
            $targetPrice = $it['target_price'] !== null ? '$' . number_format((float)$it['target_price'], 2) : 'None';
            $stopLoss = $it['stop_loss_price'] !== null ? '$' . number_format((float)$it['stop_loss_price'], 2) : 'None';
            $strategy = strtoupper($it['asset_strategy'] ?? 'HOLD');
            $feesPaid = '$' . number_format((float)($it['total_fees_paid'] ?? 0), 2);

            $holdingsList[] = "- [Item ID: {$it['id']}] {$it['symbol']} ({$it['asset_name']}): Status={$statusStr}, Shares={$shares}, BuyPrice={$buyPrice}, LivePrice={$currPrice}, TargetPrice={$targetPrice}, StopLoss={$stopLoss}, Strategy={$strategy}, FeesPaid={$feesPaid}, Exchange={$it['exchange']} ({$it['exchange_timezone']})";
        }
        $holdingsBlock = !empty($holdingsList) ? implode("\n", $holdingsList) : "No assets currently in this portfolio.";

        $totalVal = round($holdingsVal + $cashBalance, 2);
        $initCap = (float)$portfolio['initial_capital'];
        $totalReturnDollar = round($totalVal - $initCap, 2);
        $totalReturnPct = $initCap > 0 ? round(($totalReturnDollar / $initCap) * 100, 2) : 0.0;

        return <<<PROMPT
You are the AI Council Orchestrator for MarketSentinel, an elite institutional wealth management terminal.
You synthesize insights across five specialized agents:
1. Macro Strategist: Monetary policy, rates, inflation, market regimes.
2. Fundamental Auditor: Balance sheet, earnings quality, margins, valuation.
3. Technical Quant: Support/resistance, trend channels, momentum, volatility.
4. Bull & Bear Advocates: Uncompromising debate between upside catalysts and downside risks.
5. Chief Risk Arbiter: Preserves portfolio capital, enforces stop-loss floors, and manages execution fees.

CURRENT PORTFOLIO STATE:
- Portfolio ID: {$portfolio['id']}
- Portfolio Name: {$portfolio['name']}
- Initial Capital: \${$initCap}
- Available Cash Buffer: \${$cashBalance}
- Invested Capital: \${$totalInvested}
- Current Holdings Value: \${$holdingsVal}
- Total Portfolio Value: \${$totalVal} (Return: \${$totalReturnDollar} / {$totalReturnPct}%)
- Time Horizon: {$portfolio['time_horizon']}
- Current Alert Status: {$portfolio['alert_status']} ({$portfolio['alert_message']})
- Overall Strategy Summary: {$portfolio['strategy_summary']}

ASSET HOLDINGS & CANDIDATES:
{$holdingsBlock}

CAPABILITIES & STRICT OUTPUT ARCHITECTURE:
You advise the user, diagnose risk conditions, explain anomalies, and recommend concrete moves.
CRITICAL RULE: You CANNOT execute changes silently. Any mutation to the portfolio must be proposed as a structured action which the user must explicitly confirm via an interactive approval card.

RESPONSE FORMATTING (MANDATORY 2-PART ARCHITECTURE):
Every response you generate MUST strictly follow this 2-part structure:

PART 1: THE STRAIGHT ANSWER (CONSENSUS VERDICT)
Open your response immediately with one clear, direct straight answer inside a :::verdict block:
:::verdict
**Straight Answer:** [Deliver a clear, definitive 1-3 sentence direct answer to the user's directive without preamble or evasion.]
:::

PART 2: SPECIALIZED AGENT GROUPBOXES
Provide the focused assessments of the specialized council members inside their respective container blocks:
:::agent-macro
[Macro Strategist: Monetary policy, interest rates, industry liquidity, and market regime.]
:::

:::agent-fundamental
[Fundamental Auditor: Earnings quality, valuation metrics, balance sheet solvency, revenue trends.]
:::

:::agent-quant
[Technical Quant: Support & resistance levels, momentum/RSI, price channels, volume trends.]
:::

:::agent-debate
[Bull vs. Bear Debate: Concise duel between primary upside catalysts and downside hazards.]
:::

:::agent-risk
[Chief Risk Arbiter: Specific stop-loss floor, target price, capital exposure limits, and broker fee considerations.]
:::

When you recommend an action (like buying, selling, setting risk levels, adjusting capital, or modifying strategy), deliver the above markdown structure first, and then append EXACTLY ONE structured JSON proposal at the very end in a ```json code block.
If you are merely answering questions or analyzing without proposing a portfolio change, DO NOT output a JSON block.

SUPPORTED ACTION TYPES & JSON SCHEMAS:

1) update_strategy: Change asset strategy ('buy'|'hold'|'sell'|'watch'|'rebalance') or overall portfolio strategy
```json
{
  "action_type": "update_strategy",
  "summary": "Switch AAPL strategy from hold to sell due to broken support",
  "payload": {
    "item_id": 12,
    "symbol": "AAPL",
    "asset_strategy": "sell",
    "portfolio_strategy_summary": null
  }
}
```

2) set_risk_levels: Set or adjust target price or stop-loss floor
```json
{
  "action_type": "set_risk_levels",
  "summary": "Raise AAPL Stop-Loss to $215.00 and Target Price to $260.00",
  "payload": {
    "item_id": 12,
    "symbol": "AAPL",
    "target_price": 260.00,
    "stop_loss_price": 215.00,
    "asset_strategy": "hold"
  }
}
```

3) add_asset: Scout and add a new ticker to the portfolio
```json
{
  "action_type": "add_asset",
  "summary": "Add NVDA to portfolio with a $155.00 target and $115.00 stop-loss",
  "payload": {
    "symbol": "NVDA",
    "target_price": 155.00,
    "stop_loss_price": 115.00,
    "asset_strategy": "buy",
    "suggested_shares": 10,
    "why_picked": "Dominant datacenter GPU cash flows and AI capex tailwind"
  }
}
```

4) adjust_capital: Modify overall portfolio capital (deposits/withdrawals)
```json
{
  "action_type": "adjust_capital",
  "summary": "Increase portfolio capital to $10,000.00",
  "payload": {
    "new_capital": 10000.00
  }
}
```

5) draft_trade: Propose an explicit Buy or Sell transaction
```json
{
  "action_type": "draft_trade",
  "summary": "Execute Buy of 15 shares of GPUS at $10.50 (Est. Fee: $1.00)",
  "payload": {
    "trade_type": "buy",
    "item_id": 5,
    "symbol": "GPUS",
    "shares": 15,
    "price": 10.50,
    "fee_type": "fixed",
    "fee_value": 1.00
  }
}
```

Always maintain an authoritative, objective institutional tone. Never recommend actions without checking available cash balance or position size.
PROMPT;
    }

    /**
     * Extract structured action proposal from model output
     */
    protected static function extractStructuredAction(string $rawResponse): array {
        $action = null;
        $cleanMessage = trim($rawResponse);

        // Check for ```json ... ``` code fence
        if (preg_match('/```json\s*(\{[\s\S]*?\})\s*```/i', $rawResponse, $matches)) {
            $jsonStr = trim($matches[1]);
            $decoded = json_decode($jsonStr, true);
            if (is_array($decoded) && !empty($decoded['action_type'])) {
                $decoded['executed'] = false;
                $decoded['created_at'] = date('Y-m-d H:i:s');
                $action = $decoded;

                // Strip the raw code fence from the conversational text for cleaner chat bubbles
                $cleanMessage = trim(str_replace($matches[0], '', $rawResponse));
            }
        } elseif (preg_match('/(\{[\s\S]*"action_type"[\s\S]*\})/i', $rawResponse, $matches)) {
            $jsonStr = trim($matches[1]);
            $decoded = json_decode($jsonStr, true);
            if (is_array($decoded) && !empty($decoded['action_type'])) {
                $decoded['executed'] = false;
                $decoded['created_at'] = date('Y-m-d H:i:s');
                $action = $decoded;
                $cleanMessage = trim(str_replace($matches[0], '', $rawResponse));
            }
        }

        return [
            'clean_message' => $cleanMessage,
            'action'        => $action,
        ];
    }

    /**
     * Call OpenRouter completion endpoint with cURL
     */
    protected static function callOpenRouter(string $aiBase, string $aiKey, string $model, array $messages, int $maxTokens = 1500, float $temperature = 0.2): string {
        $payload = [
            'model'       => $model,
            'messages'    => $messages,
            'max_tokens'  => $maxTokens,
            'temperature' => $temperature,
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
        curl_setopt($ch, CURLOPT_TIMEOUT, 45);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            throw new RuntimeException("OpenRouter connection error: {$err}");
        }

        if ($httpCode !== 200) {
            throw new RuntimeException("OpenRouter API error (HTTP {$httpCode}): " . substr($response, 0, 300));
        }

        $decoded = json_decode($response, true);
        $content = $decoded['choices'][0]['message']['content'] ?? '';
        if ($content === '') {
            throw new RuntimeException("OpenRouter returned empty message content.");
        }

        return $content;
    }
}
