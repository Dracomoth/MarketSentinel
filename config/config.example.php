<?php
/**
 * Database & Application Configuration Template
 * 
 * Copy this file to 'config.php' in this directory (or project root)
 * and fill in your actual credentials.
 * 
 * NOTE: 'config.php' is ignored by Git to prevent exposing credentials.
 */

return [
    // -------------------------------------------------------------------------
    // Database Configuration (MySQL / MariaDB)
    // Supports both local (e.g., localhost, Docker) and remote database servers.
    // -------------------------------------------------------------------------
    'database' => [
        // Database driver (mysql)
        'driver'    => 'mysql',

        // Hostname or IP address:
        // - Local: '127.0.0.1' or 'localhost'
        // - Remote / Cloud: 'db.example.com' or remote IP address
        'host'      => '127.0.0.1',

        // Port (default MySQL port is 3306)
        'port'      => 3306,

        // Database schema / catalog name
        'database'  => 'basephp_db',

        // Database username
        'username'  => 'db_user',

        // Database password
        'password'  => 'your_secure_password_here',

        // Character encoding and collation
        'charset'   => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',

        // PDO connection options
        'options'   => [
            // Throw exceptions on SQL errors
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            // Return associative arrays by default
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Use native prepared statements
            PDO::ATTR_EMULATE_PREPARES   => false,
            // Connection timeout in seconds
            PDO::ATTR_TIMEOUT            => 5,

            // Optional: For secure remote connections requiring SSL/TLS, uncomment below:
            // PDO::MYSQL_ATTR_SSL_CA    => '/path/to/ca-cert.pem',
            // PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
        ],
    ],

    // -------------------------------------------------------------------------
    // Application Settings
    // -------------------------------------------------------------------------
    'app' => [
        'name'        => 'MarketSentinel',
        'environment' => 'development', // 'development' or 'production'
        'debug'       => true,
        'url'         => 'http://localhost:8000',
    ],

    // -------------------------------------------------------------------------
    // External Market & News Data Providers
    // -------------------------------------------------------------------------
    'market_data' => [
        // Financial Modeling Prep (Quotes, OHLCV, Financial Ratios)
        'fmp' => [
            'api_key'  => '',
            'base_url' => 'https://financialmodelingprep.com/api/v3',
        ],

        // Finnhub (Company News, Macro Trends, Calendar)
        'finnhub' => [
            'api_key'  => '',
            'base_url' => 'https://finnhub.io/api/v1',
        ],

        // Cache TTL in seconds (prevents burning API quotas on repeat views)
        'cache_ttl' => 900, // 15 minutes
    ],

    // -------------------------------------------------------------------------
    // Multi-Agent LLM Intelligence Engine
    // -------------------------------------------------------------------------
    'ai' => [
        // OpenRouter or direct provider key
        'api_key'  => '',
        'base_url' => 'https://openrouter.ai/api/v1',

        // Model assignments for each council role
        'models' => [
            // Cost-efficient model for debate generation (Bull & Bear)
            'analyst' => 'deepseek/deepseek-chat', 
            
            // High-reasoning model for the final Arbiter / Risk Officer verdict
            'arbiter' => 'anthropic/claude-sonnet-4.5',
        ],

        // Request timeout in seconds for agent completions
        'timeout' => 45,
    ],

    // -------------------------------------------------------------------------
    // Notification & Alerts (Optional)
    // -------------------------------------------------------------------------
    'notifications' => [
        'telegram' => [
            'enabled'   => false,
            'bot_token' => '',
            'chat_id'   => '',
        ],
    ],

    // -------------------------------------------------------------------------
    // System Execution & Rate Limits
    // -------------------------------------------------------------------------
    'limits' => [
        'max_daily_fmp_calls' => 200, // Safety threshold for free tiers
        'sleep_between_scans' => 2,   // Seconds between ticker checks in cron sweeps
    ],
];
