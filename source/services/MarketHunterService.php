<?php
/**
 * Market Hunter Service - Core Discovery Engine
 * 
 * Orchestrates multi-step portfolio scouting:
 * 1. LLM Parameter Extraction (Analyst Model)
 * 2. Live Market Screening (FMP API with robust fallback)
 * 3. Multi-Agent Council Evaluation & Plain-English Allocation (Arbiter Model)
 */

class MarketHunterService {
    /**
     * Scout market candidates and construct a bespoke portfolio
     * 
     * @param string $userPrompt Natural language investment prompt
     * @param float $budget Target investment budget
     * @return array Structured portfolio proposal
     */
    public static function scout(string $userPrompt, float $budget = 0.00): array {
        $userPrompt = trim($userPrompt);
        if ($userPrompt === '') {
            throw new InvalidArgumentException("User prompt cannot be empty.");
        }

        $config = require dirname(__DIR__, 2) . '/config/config.php';
        $aiKey = $config['ai']['api_key'] ?? '';
        $aiBase = rtrim($config['ai']['base_url'] ?? 'https://openrouter.ai/api/v1', '/');
        $analystModel = $config['ai']['models']['analyst'] ?? 'deepseek/deepseek-chat';
        $arbiterModel = $config['ai']['models']['arbiter'] ?? 'anthropic/claude-sonnet-4.5';
        $fmpKey = $config['market_data']['fmp']['api_key'] ?? '';

        // ---------------------------------------------------------------------
        // Step 1: Strategy Parameter Extraction via LLM (Analyst Model)
        // ---------------------------------------------------------------------
        $params = self::extractParameters($userPrompt, $aiBase, $aiKey, $analystModel);

        // If budget not explicitly passed, check if extracted from prompt or default
        if ($budget <= 0.00) {
            $budget = !empty($params['budget']) ? (float) $params['budget'] : 5000.00;
        }

        // ---------------------------------------------------------------------
        // Step 2: Live Market Screening (FMP with intelligent fallback)
        // ---------------------------------------------------------------------
        $candidates = self::screenMarket($params, $fmpKey);

        if (count($candidates) < 3) {
            // Apply sensible fallback expansion to guarantee real results
            $candidates = self::getFallbackCandidates($params, $fmpKey);
        }

        // ---------------------------------------------------------------------
        // Step 3: Council Evaluation & Plain-English Allocation (Arbiter Model)
        // ---------------------------------------------------------------------
        $proposal = self::evaluateCandidates(
            $userPrompt,
            $budget,
            $params,
            $candidates,
            $aiBase,
            $aiKey,
            $arbiterModel,
            $analystModel
        );

        return $proposal;
    }

    /**
     * Step 1: Extract screening parameters from natural language prompt
     */
    protected static function extractParameters(string $userPrompt, string $aiBase, string $aiKey, string $model): array {
        $systemPrompt = "You are a quantitative finance screener assistant for MarketSentinel.
Analyze the user's investment prompt and extract structured screening parameters as a strict JSON object.
Generate all narrative output in clear, professional English without financial jargon.
JSON schema:
{
  \"price_min\": float|null,
  \"price_max\": float|null,
  \"market_cap_max\": float|null,
  \"sector\": string|null,
  \"time_horizon\": \"short_term\"|\"mid_term\"|\"long_term\",
  \"target_asset_count\": int,
  \"budget\": float|null
}
Valid sectors are: Technology, Healthcare, Financial Services, Consumer Cyclical, Industrials, Communication Services, Consumer Defensive, Energy, Real Estate, Basic Materials, Utilities. If no single sector applies, return null.
If penny stocks or small caps are requested without exact price, set price_max to 5.0.
Default target_asset_count is 4 (range 3-6).
Default time_horizon is mid_term unless prompt indicates short_term (e.g. days/weeks/1-3 months, swing) or long_term (years).
If a monetary amount is in the prompt (e.g. '2.000 €' or '$5000'), extract it as float into 'budget'.
Return ONLY a valid JSON object. No markdown fences.";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt]
        ];

        $raw = self::callOpenRouter($aiBase, $aiKey, $model, $messages, 400, 0.1);
        $clean = self::extractJsonString($raw);
        $data = json_decode($clean, true);

        return [
            'price_min'          => isset($data['price_min']) ? (float)$data['price_min'] : null,
            'price_max'          => isset($data['price_max']) ? (float)$data['price_max'] : null,
            'market_cap_max'     => isset($data['market_cap_max']) ? (float)$data['market_cap_max'] : null,
            'sector'             => !empty($data['sector']) ? trim($data['sector']) : null,
            'time_horizon'       => in_array($data['time_horizon'] ?? '', ['short_term', 'mid_term', 'long_term'], true) ? $data['time_horizon'] : 'mid_term',
            'target_asset_count' => max(3, min(6, (int)($data['target_asset_count'] ?? 4))),
            'budget'             => isset($data['budget']) && (float)$data['budget'] > 0 ? (float)$data['budget'] : null,
        ];
    }

    /**
     * Step 2: Screen candidates from FMP API
     */
    protected static function screenMarket(array $params, string $fmpKey): array {
        if ($fmpKey === '') {
            return [];
        }

        // Try standard FMP stock screener endpoint
        $queryParams = [
            'isActivelyTrading' => 'true',
            'limit'             => '25',
            'volumeMoreThan'    => '100000',
            'apikey'            => $fmpKey,
        ];

        if ($params['price_min'] !== null) {
            $queryParams['priceMoreThan'] = $params['price_min'];
        }
        if ($params['price_max'] !== null) {
            $queryParams['priceLowerThan'] = $params['price_max'];
        }
        if (!empty($params['sector'])) {
            $queryParams['sector'] = $params['sector'];
        }
        if ($params['market_cap_max'] !== null) {
            $queryParams['marketCapLowerThan'] = $params['market_cap_max'];
        }

        $endpoints = [
            'https://financialmodelingprep.com/api/v3/stock-screener',
            'https://financialmodelingprep.com/stable/company-screener',
        ];

        foreach ($endpoints as $baseUrl) {
            $url = $baseUrl . '?' . http_build_query($queryParams);
            $raw = self::fetchUrl($url);
            $data = json_decode($raw, true);

            if (is_array($data) && count($data) >= 3 && !isset($data['Error Message']) && !isset($data['error'])) {
                $candidates = [];
                foreach ($data as $item) {
                    if (!empty($item['symbol']) && isset($item['price']) && (float)$item['price'] > 0) {
                        $candidates[] = [
                            'symbol'            => strtoupper(trim($item['symbol'])),
                            'name'              => trim($item['companyName'] ?? $item['name'] ?? $item['symbol']),
                            'price'             => (float)$item['price'],
                            'exchange'          => trim($item['exchangeShortName'] ?? $item['exchange'] ?? 'NASDAQ'),
                            'sector'            => trim($item['sector'] ?? ($params['sector'] ?? 'General')),
                            'changesPercentage' => isset($item['changesPercentage']) ? (float)$item['changesPercentage'] : 0.0,
                        ];
                    }
                }
                if (count($candidates) >= 3) {
                    return array_slice($candidates, 0, 20);
                }
            }
        }

        return [];
    }

    /**
     * Fallback market screening using FMP live active market feeds & targeted quotes
     */
    protected static function getFallbackCandidates(array $params, string $fmpKey): array {
        $candidates = [];
        $seen = [];

        // 1. Query live market movers (most-actives, biggest-gainers, biggest-losers)
        $feeds = ['most-actives', 'biggest-gainers', 'biggest-losers'];
        foreach ($feeds as $feed) {
            $url = "https://financialmodelingprep.com/stable/{$feed}?apikey={$fmpKey}";
            $raw = self::fetchUrl($url);
            $feedData = json_decode($raw, true);

            if (is_array($feedData)) {
                foreach ($feedData as $item) {
                    $sym = strtoupper(trim($item['symbol'] ?? ''));
                    $price = (float)($item['price'] ?? 0);

                    if ($sym === '' || isset($seen[$sym]) || $price <= 0) {
                        continue;
                    }

                    // Apply price filters
                    if ($params['price_min'] !== null && $price < $params['price_min']) {
                        continue;
                    }
                    if ($params['price_max'] !== null && $price > $params['price_max']) {
                        continue;
                    }

                    $seen[$sym] = true;
                    $candidates[] = [
                        'symbol'            => $sym,
                        'name'              => trim($item['name'] ?? $sym),
                        'price'             => $price,
                        'exchange'          => trim($item['exchange'] ?? 'NASDAQ'),
                        'sector'            => $params['sector'] ?? 'Active Market Mover',
                        'changesPercentage' => isset($item['changesPercentage']) ? (float)$item['changesPercentage'] : 0.0,
                    ];
                }
            }
        }

        // 2. If still fewer than 5 candidates, pull from sector & style universe with live quotes
        if (count($candidates) < 5) {
            $universe = self::getCuratedTickers($params['sector'] ?? '', $params['price_max']);
            foreach ($universe as $stock) {
                if (isset($seen[$stock['symbol']])) {
                    continue;
                }

                // Check live quote if possible
                $quoteUrl = "https://financialmodelingprep.com/stable/quote?symbol={$stock['symbol']}&apikey={$fmpKey}";
                $qRaw = self::fetchUrl($quoteUrl);
                $qData = json_decode($qRaw, true);

                $livePrice = $stock['price'];
                $liveName = $stock['name'];
                $liveChg = 0.0;

                if (is_array($qData) && !empty($qData[0]['price'])) {
                    $livePrice = (float) $qData[0]['price'];
                    $liveName = $qData[0]['name'] ?? $liveName;
                    $liveChg = (float) ($qData[0]['changePercentage'] ?? 0.0);
                }

                // Apply price bounds
                if ($params['price_min'] !== null && $livePrice < $params['price_min']) {
                    continue;
                }
                if ($params['price_max'] !== null && $livePrice > $params['price_max']) {
                    continue;
                }

                $seen[$stock['symbol']] = true;
                $candidates[] = [
                    'symbol'            => $stock['symbol'],
                    'name'              => $liveName,
                    'price'             => $livePrice,
                    'exchange'          => $stock['exchange'],
                    'sector'            => $stock['sector'],
                    'changesPercentage' => $liveChg,
                ];

                if (count($candidates) >= 15) {
                    break;
                }
            }
        }

        return array_slice($candidates, 0, 15);
    }

    /**
     * Curated universe of liquid tickers across sectors
     */
    protected static function getCuratedTickers(?string $sector, ?float $priceMax): array {
        if ($priceMax !== null && $priceMax <= 5.0) {
            return [
                ['symbol' => 'GPUS', 'name' => 'Hyperscale Data, Inc.', 'price' => 0.17, 'exchange' => 'AMEX', 'sector' => 'Technology'],
                ['symbol' => 'LUCD', 'name' => 'Lucid Diagnostics Inc.', 'price' => 0.41, 'exchange' => 'NASDAQ', 'sector' => 'Healthcare'],
                ['symbol' => 'XXII', 'name' => '22nd Century Group, Inc.', 'price' => 0.20, 'exchange' => 'NASDAQ', 'sector' => 'Consumer Defensive'],
                ['symbol' => 'SHFS', 'name' => 'SHF Holdings, Inc.', 'price' => 0.13, 'exchange' => 'NASDAQ', 'sector' => 'Financial Services'],
                ['symbol' => 'NVD',  'name' => 'GraniteShares 2x Short NVDA', 'price' => 4.19, 'exchange' => 'NASDAQ', 'sector' => 'Technology'],
                ['symbol' => 'SOUN', 'name' => 'SoundHound AI, Inc.', 'price' => 4.85, 'exchange' => 'NASDAQ', 'sector' => 'Technology'],
                ['symbol' => 'BNGO', 'name' => 'Bionano Genomics, Inc.', 'price' => 0.35, 'exchange' => 'NASDAQ', 'sector' => 'Healthcare'],
                ['symbol' => 'MNTS', 'name' => 'Momentus Inc.', 'price' => 0.88, 'exchange' => 'NASDAQ', 'sector' => 'Industrials'],
            ];
        }

        return [
            ['symbol' => 'NVDA', 'name' => 'NVIDIA Corporation', 'price' => 140.50, 'exchange' => 'NASDAQ', 'sector' => 'Technology'],
            ['symbol' => 'MSFT', 'name' => 'Microsoft Corporation', 'price' => 425.80, 'exchange' => 'NASDAQ', 'sector' => 'Technology'],
            ['symbol' => 'AAPL', 'name' => 'Apple Inc.', 'price' => 245.20, 'exchange' => 'NASDAQ', 'sector' => 'Technology'],
            ['symbol' => 'AMZN', 'name' => 'Amazon.com, Inc.', 'price' => 188.40, 'exchange' => 'NASDAQ', 'sector' => 'Consumer Cyclical'],
            ['symbol' => 'GOOGL', 'name' => 'Alphabet Inc.', 'price' => 175.60, 'exchange' => 'NASDAQ', 'sector' => 'Communication Services'],
            ['symbol' => 'LLY',  'name' => 'Eli Lilly and Company', 'price' => 840.10, 'exchange' => 'NYSE', 'sector' => 'Healthcare'],
            ['symbol' => 'XOM',  'name' => 'Exxon Mobil Corporation', 'price' => 118.20, 'exchange' => 'NYSE', 'sector' => 'Energy'],
            ['symbol' => 'JPM',  'name' => 'JPMorgan Chase & Co.', 'price' => 220.50, 'exchange' => 'NYSE', 'sector' => 'Financial Services'],
            ['symbol' => 'NEE',  'name' => 'NextEra Energy, Inc.', 'price' => 78.40, 'exchange' => 'NYSE', 'sector' => 'Utilities'],
            ['symbol' => 'CAT',  'name' => 'Caterpillar Inc.', 'price' => 380.20, 'exchange' => 'NYSE', 'sector' => 'Industrials'],
            ['symbol' => 'GLD',  'name' => 'SPDR Gold Shares', 'price' => 252.00, 'exchange' => 'NYSE Arca', 'sector' => 'Basic Materials'],
            ['symbol' => 'SPY',  'name' => 'SPDR S&P 500 ETF Trust', 'price' => 585.10, 'exchange' => 'NYSE Arca', 'sector' => 'Financial Services'],
        ];
    }

    /**
     * Step 3: Evaluate candidates with OpenRouter Arbiter Model
     */
    protected static function evaluateCandidates(
        string $userPrompt,
        float $budget,
        array $params,
        array $candidates,
        string $aiBase,
        string $aiKey,
        string $arbiterModel,
        string $fallbackModel
    ): array {
        $targetCount = $params['target_asset_count'] ?? 4;
        $horizon = $params['time_horizon'] ?? 'mid_term';

        $systemPrompt = "You are the Chief Investment Officer & Arbiter of the MarketSentinel Multi-Agent Council.
Construct a high-conviction, bespoke investment portfolio from the provided screened market candidates.
MANDATORY LANGUAGE REQUIREMENT: Generate all narrative output in clear, professional English without financial jargon.
All human-facing keys ('portfolio_name', 'strategy_summary', 'why_picked', 'bull_thesis', 'bear_thesis', 'invalidation_trigger') MUST be strictly written in natural, concise, professional English.

STRICT JSON OUTPUT SCHEMA:
{
  \"portfolio_name\": \"string (creative, relevant, e.g. 'High-Growth Tech Leaders 2026')\",
  \"strategy_summary\": \"string (3-4 sentences in natural, plain English explaining the strategy, logic, and realistic expectations)\",
  \"time_horizon\": \"short_term\"|\"mid_term\"|\"long_term\",
  \"allocated_total\": float,
  \"cash_buffer\": float,
  \"items\": [
    {
      \"symbol\": \"TICKER\",
      \"asset_name\": \"Company Name\",
      \"asset_type\": \"stock\",
      \"exchange\": \"NASDAQ\"|\"NYSE\"|\"AMEX\",
      \"exchange_timezone\": \"America/New_York\",
      \"current_price\": float,
      \"suggested_shares\": int,
      \"suggested_price\": float,
      \"why_picked\": \"string (plain English rationale explaining why this company fits the thesis)\",
      \"bull_thesis\": \"string (plain English upside catalyst)\",
      \"bear_thesis\": \"string (plain English real downside risk)\",
      \"invalidation_trigger\": \"string (plain English condition to cut losses)\",
      \"time_horizon\": \"short_term\"|\"mid_term\"|\"long_term\",
      \"check_interval_hours\": int
    }
  ]
}

Allocation & Math Rules:
1. Select between 3 and {$targetCount} optimal assets from the screened candidate list.
2. For each asset, use the exact current_price from the candidates.
3. Calculate suggested_shares (integers >= 1) such that:
   (suggested_shares * current_price) across all items <= Total Budget.
4. Set cash_buffer to the remaining unallocated budget (between 0% and 15% of budget).
5. check_interval_hours: 8 for volatile/penny stocks, 24 for standard mid-term stocks, 72 for stable blue chips.
6. Return ONLY valid JSON. No markdown backticks, no introduction, no commentary.";

        $candidateJson = json_encode(array_values($candidates), JSON_PRETTY_PRINT);
        $userMsg = "User Prompt: {$userPrompt}\nTarget Budget: \${$budget} USD\nTime Horizon: {$horizon}\nScreened Candidates:\n{$candidateJson}";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userMsg]
        ];

        // Try arbiter model first, fallback to analyst model if rate-limited or error
        $raw = '';
        try {
            $raw = self::callOpenRouter($aiBase, $aiKey, $arbiterModel, $messages, 1200, 0.2);
        } catch (Throwable $e) {
            error_log("Arbiter model call failed ({$arbiterModel}): " . $e->getMessage() . ". Retrying with fallback {$fallbackModel}");
            $raw = self::callOpenRouter($aiBase, $aiKey, $fallbackModel, $messages, 1200, 0.2);
        }

        $clean = self::extractJsonString($raw);
        $proposal = json_decode($clean, true);

        if (!is_array($proposal) || empty($proposal['items'])) {
            // If parsing failed, retry once with fallback model
            $raw = self::callOpenRouter($aiBase, $aiKey, $fallbackModel, $messages, 1800, 0.1);
            $clean = self::extractJsonString($raw);
            $proposal = json_decode($clean, true);
        }

        if (!is_array($proposal) || empty($proposal['items'])) {
            throw new RuntimeException("AI Council failed to generate a valid portfolio structure.");
        }

        // Sanitize & re-verify arithmetic
        return self::sanitizeProposal($proposal, $budget, $candidates, $horizon);
    }

    /**
     * Sanitize, format, and mathematically verify the portfolio proposal
     */
    protected static function sanitizeProposal(array $proposal, float $budget, array $candidates, string $defaultHorizon): array {
        $portfolioName = trim($proposal['portfolio_name'] ?? 'Custom Portfolio');
        $strategySummary = trim($proposal['strategy_summary'] ?? 'Strategy formulated according to the provided investment mandate.');
        $timeHorizon = in_array($proposal['time_horizon'] ?? '', ['short_term', 'mid_term', 'long_term'], true) 
            ? $proposal['time_horizon'] 
            : $defaultHorizon;

        // Build candidate price lookup
        $priceMap = [];
        foreach ($candidates as $c) {
            $priceMap[strtoupper($c['symbol'])] = $c;
        }

        $cleanItems = [];
        $allocatedTotal = 0.0;

        foreach ($proposal['items'] as $it) {
            $sym = strtoupper(trim($it['symbol'] ?? ''));
            if ($sym === '') {
                continue;
            }

            // Ground price in candidate quote or fallback
            $cand = $priceMap[$sym] ?? null;
            $currentPrice = (float)($cand['price'] ?? $it['current_price'] ?? 10.0);
            if ($currentPrice <= 0) {
                $currentPrice = 1.0;
            }

            $suggestedShares = max(1, (int)($it['suggested_shares'] ?? 1));
            $suggestedPrice = (float)($it['suggested_price'] ?? $currentPrice);

            $itemCost = $suggestedShares * $currentPrice;
            $allocatedTotal += $itemCost;

            $cleanItems[] = [
                'symbol'               => $sym,
                'asset_name'           => trim($it['asset_name'] ?? $cand['name'] ?? $sym),
                'asset_type'           => trim($it['asset_type'] ?? 'stock'),
                'exchange'             => trim($it['exchange'] ?? $cand['exchange'] ?? 'NASDAQ'),
                'exchange_timezone'    => trim($it['exchange_timezone'] ?? 'America/New_York'),
                'current_price'        => $currentPrice,
                'suggested_shares'     => $suggestedShares,
                'suggested_price'      => $suggestedPrice,
                'why_picked'           => trim($it['why_picked'] ?? 'Position selected to match the investment thesis.'),
                'bull_thesis'          => !empty($it['bull_thesis']) ? trim($it['bull_thesis']) : 'Positive business catalysts and market upside potential.',
                'bear_thesis'          => !empty($it['bear_thesis']) ? trim($it['bear_thesis']) : 'Broader market volatility and sector headwinds.',
                'invalidation_trigger' => !empty($it['invalidation_trigger']) ? trim($it['invalidation_trigger']) : 'Loss of key technical support or deteriorating fundamentals.',
                'time_horizon'         => in_array($it['time_horizon'] ?? '', ['short_term', 'mid_term', 'long_term'], true) ? $it['time_horizon'] : $timeHorizon,
                'check_interval_hours' => max(4, min(168, (int)($it['check_interval_hours'] ?? 24))),
                'allocation_value'     => round($itemCost, 2),
            ];
        }

        // If allocatedTotal exceeds budget, scale down suggested shares proportionally
        if ($allocatedTotal > $budget && $budget > 0) {
            $scale = ($budget * 0.95) / $allocatedTotal;
            $allocatedTotal = 0.0;
            foreach ($cleanItems as &$it) {
                $it['suggested_shares'] = max(1, (int) floor($it['suggested_shares'] * $scale));
                $cost = round($it['suggested_shares'] * $it['current_price'], 2);
                $it['allocation_value'] = $cost;
                $allocatedTotal += $cost;
            }
            unset($it);
        }

        $cashBuffer = max(0.0, round($budget - $allocatedTotal, 2));

        return [
            'portfolio_name'   => $portfolioName,
            'strategy_summary' => $strategySummary,
            'time_horizon'     => $timeHorizon,
            'budget'           => round($budget, 2),
            'allocated_total'  => round($allocatedTotal, 2),
            'cash_buffer'      => $cashBuffer,
            'items'            => $cleanItems,
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
        curl_setopt($ch, CURLOPT_TIMEOUT, 55);

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

    /**
     * Perform HTTP GET using cURL
     */
    protected static function fetchUrl(string $url): string {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $res = curl_exec($ch);
        curl_close($ch);
        return is_string($res) ? $res : '';
    }

    /**
     * Clean and isolate JSON substring from markdown backticks or extra text
     */
    protected static function extractJsonString(string $raw): string {
        $raw = trim($raw);

        // Strip ```json ... ``` or ``` ... ```
        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/i', $raw, $matches)) {
            $raw = trim($matches[1]);
        }

        // Find outermost { ... }
        $firstBrace = strpos($raw, '{');
        $lastBrace = strrpos($raw, '}');

        if ($firstBrace !== false && $lastBrace !== false && $lastBrace > $firstBrace) {
            return substr($raw, $firstBrace, $lastBrace - $firstBrace + 1);
        }

        return $raw;
    }
}
