<?php
/**
 * Market Data Service - Institutional Market Quote & Metadata Provider
 * 
 * Interacts with Financial Modeling Prep (FMP) API with local disk TTL caching,
 * rate-limit protection, and fallback quoting.
 */

class MarketDataService {
    /**
     * Cache directory for quotes
     */
    protected static function getCacheDir(): string {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'marketsentinel_quotes';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        return $dir;
    }

    /**
     * Retrieve live quote for a given ticker symbol
     * 
     * @param string $symbol
     * @param bool $forceRefresh
     * @return array|null
     */
    public static function getQuote(string $symbol, bool $forceRefresh = false): ?array {
        $symbol = strtoupper(trim($symbol));
        if ($symbol === '') {
            return null;
        }

        $config = require dirname(__DIR__, 2) . '/config/config.php';
        $fmpKey = $config['market_data']['fmp']['api_key'] ?? '';
        $cacheTtl = (int) ($config['market_data']['cache_ttl'] ?? 900); // 15 mins default

        $cacheFile = self::getCacheDir() . DIRECTORY_SEPARATOR . md5($symbol) . '.json';

        // 1. Check local file cache if not forcing refresh
        if (!$forceRefresh && file_exists($cacheFile)) {
            $cacheAge = time() - filemtime($cacheFile);
            if ($cacheAge < $cacheTtl) {
                $cached = json_decode(@file_get_contents($cacheFile), true);
                if (is_array($cached) && !empty($cached['price'])) {
                    return $cached;
                }
            }
        }

        // 2. Fetch live from FMP stable quote endpoint
        if ($fmpKey !== '') {
            $url = "https://financialmodelingprep.com/stable/quote?symbol={$symbol}&apikey={$fmpKey}";
            $raw = self::httpGet($url);
            $data = json_decode($raw, true);

            if (is_array($data) && !empty($data[0]['price'])) {
                $q = $data[0];
                $quote = [
                    'symbol'            => $symbol,
                    'name'              => trim($q['name'] ?? $symbol),
                    'price'             => (float) $q['price'],
                    'change'            => (float) ($q['change'] ?? 0.0),
                    'changesPercentage' => (float) ($q['changePercentage'] ?? $q['changesPercentage'] ?? 0.0),
                    'volume'            => (int) ($q['volume'] ?? 0),
                    'dayLow'            => isset($q['dayLow']) ? (float) $q['dayLow'] : null,
                    'dayHigh'           => isset($q['dayHigh']) ? (float) $q['dayHigh'] : null,
                    'marketCap'         => isset($q['marketCap']) ? (float) $q['marketCap'] : null,
                    'exchange'          => trim($q['exchange'] ?? 'US'),
                    'last_checked_at'   => date('Y-m-d H:i:s'),
                ];

                @file_put_contents($cacheFile, json_encode($quote));
                return $quote;
            }

            // Fallback: try quote-short endpoint
            $shortUrl = "https://financialmodelingprep.com/stable/quote-short?symbol={$symbol}&apikey={$fmpKey}";
            $rawShort = self::httpGet($shortUrl);
            $shortData = json_decode($rawShort, true);

            if (is_array($shortData) && !empty($shortData[0]['price'])) {
                $sq = $shortData[0];
                $quote = [
                    'symbol'            => $symbol,
                    'name'              => $symbol,
                    'price'             => (float) $sq['price'],
                    'change'            => (float) ($sq['change'] ?? 0.0),
                    'changesPercentage' => 0.0,
                    'volume'            => (int) ($sq['volume'] ?? 0),
                    'dayLow'            => null,
                    'dayHigh'           => null,
                    'marketCap'         => null,
                    'exchange'          => 'US',
                    'last_checked_at'   => date('Y-m-d H:i:s'),
                ];

                @file_put_contents($cacheFile, json_encode($quote));
                return $quote;
            }
        }

        // 3. Fallback: Finnhub quote endpoint
        $finnhubKey = $config['market_data']['finnhub']['api_key'] ?? '';
        if ($finnhubKey !== '') {
            $finnhubUrl = "https://finnhub.io/api/v1/quote?symbol={$symbol}&token={$finnhubKey}";
            $rawFinnhub = self::httpGet($finnhubUrl);
            $fhData = json_decode($rawFinnhub, true);

            if (is_array($fhData) && !empty($fhData['c']) && (float) $fhData['c'] > 0) {
                $quote = [
                    'symbol'            => $symbol,
                    'name'              => $symbol,
                    'price'             => (float) $fhData['c'],
                    'change'            => (float) ($fhData['d'] ?? 0.0),
                    'changesPercentage' => (float) ($fhData['dp'] ?? 0.0),
                    'volume'            => 0,
                    'dayLow'            => isset($fhData['l']) ? (float) $fhData['l'] : null,
                    'dayHigh'           => isset($fhData['h']) ? (float) $fhData['h'] : null,
                    'marketCap'         => null,
                    'exchange'          => 'US',
                    'last_checked_at'   => date('Y-m-d H:i:s'),
                ];

                @file_put_contents($cacheFile, json_encode($quote));
                return $quote;
            }
        }

        // 4. If cache file existed even if expired, return it as stale fallback
        if (file_exists($cacheFile)) {
            $cached = json_decode(@file_get_contents($cacheFile), true);
            if (is_array($cached) && !empty($cached['price'])) {
                return $cached;
            }
        }

        return null;
    }

    /**
     * Retrieve quotes for multiple symbols
     * 
     * @param array $symbols
     * @param bool $forceRefresh
     * @return array Array keyed by symbol
     */
    public static function getQuotes(array $symbols, bool $forceRefresh = false): array {
        $quotes = [];
        foreach ($symbols as $sym) {
            $q = self::getQuote($sym, $forceRefresh);
            if ($q) {
                $quotes[$q['symbol']] = $q;
            }
        }
        return $quotes;
    }

    /**
     * HTTP GET request helper using cURL
     */
    protected static function httpGet(string $url): string {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $res = curl_exec($ch);
        curl_close($ch);
        return is_string($res) ? $res : '';
    }

    /**
     * Invalidate cached quote for a symbol
     */
    public static function clearCache(?string $symbol = null): void {
        if ($symbol !== null) {
            $file = self::getCacheDir() . DIRECTORY_SEPARATOR . md5(strtoupper(trim($symbol))) . '.json';
            if (file_exists($file)) {
                @unlink($file);
            }
        } else {
            $files = glob(self::getCacheDir() . DIRECTORY_SEPARATOR . '*.json');
            foreach ($files as $f) {
                @unlink($f);
            }
        }
    }
}
