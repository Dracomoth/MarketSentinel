<?php
/**
 * News Service - Financial News Aggregator
 * 
 * Fetches recent company news headlines and market updates using Finnhub API
 * with automatic fallback to Financial Modeling Prep (FMP) news and local file caching.
 */

class NewsService {
    /**
     * Cache directory for news
     */
    protected static function getCacheDir(): string {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'marketsentinel_news';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        return $dir;
    }

    /**
     * Fetch recent company news headlines for a ticker symbol
     * 
     * @param string $symbol
     * @param int $limit
     * @param bool $forceRefresh
     * @return array Array of news articles or headline strings
     */
    public static function getCompanyNews(string $symbol, int $limit = 5, bool $forceRefresh = false): array {
        $symbol = strtoupper(trim($symbol));
        if ($symbol === '') {
            return [];
        }

        $cacheFile = self::getCacheDir() . DIRECTORY_SEPARATOR . md5($symbol . '_' . $limit) . '.json';

        // Check cache (TTL: 1 hour)
        if (!$forceRefresh && file_exists($cacheFile)) {
            $age = time() - filemtime($cacheFile);
            if ($age < 3600) {
                $cached = json_decode(@file_get_contents($cacheFile), true);
                if (is_array($cached)) {
                    return $cached;
                }
            }
        }

        $config = require dirname(__DIR__, 2) . '/config/config.php';
        $finnhubKey = $config['market_data']['finnhub']['api_key'] ?? '';
        $fmpKey = $config['market_data']['fmp']['api_key'] ?? '';

        $news = [];

        // 1. Try Finnhub Company News
        if ($finnhubKey !== '') {
            $from = date('Y-m-d', strtotime('-7 days'));
            $to = date('Y-m-d');
            $url = "https://finnhub.io/api/v1/company-news?symbol={$symbol}&from={$from}&to={$to}&token={$finnhubKey}";
            $raw = self::httpGet($url);
            $data = json_decode($raw, true);

            if (is_array($data) && !empty($data)) {
                $count = 0;
                foreach ($data as $item) {
                    $headline = trim($item['headline'] ?? '');
                    if ($headline === '') {
                        continue;
                    }
                    $news[] = [
                        'headline' => $headline,
                        'summary'  => trim($item['summary'] ?? ''),
                        'source'   => trim($item['source'] ?? 'Finnhub'),
                        'datetime' => isset($item['datetime']) ? date('Y-m-d H:i', (int)$item['datetime']) : date('Y-m-d H:i'),
                        'url'      => trim($item['url'] ?? ''),
                    ];
                    $count++;
                    if ($count >= $limit) {
                        break;
                    }
                }
            }
        }

        // 2. Fallback to FMP Stock News if Finnhub returned empty
        if (empty($news) && $fmpKey !== '') {
            $fmpUrl = "https://financialmodelingprep.com/api/v3/stock_news?tickers={$symbol}&limit={$limit}&apikey={$fmpKey}";
            $rawFmp = self::httpGet($fmpUrl);
            $dataFmp = json_decode($rawFmp, true);

            if (is_array($dataFmp) && !empty($dataFmp)) {
                foreach ($dataFmp as $item) {
                    $title = trim($item['title'] ?? '');
                    if ($title === '') {
                        continue;
                    }
                    $news[] = [
                        'headline' => $title,
                        'summary'  => trim($item['text'] ?? ''),
                        'source'   => trim($item['site'] ?? 'FMP News'),
                        'datetime' => trim($item['publishedDate'] ?? date('Y-m-d H:i')),
                        'url'      => trim($item['url'] ?? ''),
                    ];
                }
            }
        }

        // Cache results if found
        if (!empty($news)) {
            @file_put_contents($cacheFile, json_encode($news));
        }

        return $news;
    }

    /**
     * HTTP GET request helper with timeout
     */
    protected static function httpGet(string $url): string {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'MarketSentinel/1.0');
        $response = curl_exec($ch);
        curl_close($ch);
        return is_string($response) ? $response : '';
    }
}
