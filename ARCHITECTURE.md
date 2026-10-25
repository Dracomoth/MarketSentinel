# ARCHITECTURE: Argus Wealth (PHP 8.2+ / MySQL 8.0+ / Multi-Agent Council)

## 1. System Overview & Technology Stack
* **Runtime Environment:** PHP 8.2+ (Hostinger Cloud / Shared Web Hosting & CLI).
* **Database Engine:** MySQL 8.0+ with InnoDB, UTF8MB4, foreign key constraints.
* **Frontend:** Server-rendered PHP templates, Tailwind CSS (CDN/inline), native vanilla JavaScript (no heavy node build requirements).
* **AI Orchestration:** OpenRouter API interfacing with multi-model tiers (e.g. DeepSeek for high-volume analysis/debate; Claude 3.5 Sonnet / Claude Sonnet 4.5 for arbitration and plain English synthesis).
* **Market Data Ingestion:**
  * **Financial Modeling Prep (FMP):** Stock Screener API, live quotes, company profiles, balance sheet summaries, historical candles.
  * **Finnhub:** Company news, sentiment, global macroeconomic news feeds.
* **Security & Isolation:** Native PHP session-based authentication, strict RBAC table controls, air-gapped from brokerages (no programmatic trade execution rights).

---

## 2. Directory Layout & Module Structure

```text
├── config/
│   ├── config.example.php         # Template with placeholder credentials
│   └── config.php                 # Git-ignored production keys (DB, OpenRouter, FMP, Finnhub)
├── database/
│   ├── 000_database_creation.sql  # User management, roles, permissions base
│   └── 001_portfolio_system.sql   # Argus Wealth core tables (portfolios, items, tracker)
├── source/
│   ├── controllers/
│   │   ├── PortfolioBuilderController.php # Handles natural language prompt, scouting & saving
│   │   ├── PortfoliosController.php       # Handles portfolio dashboard, recalculations, buys
│   │   └── TrackerController.php          # Handles standalone asset watchlist & deep inspect
│   ├── repositories/
│   │   ├── PortfolioRepository.php        # CRUD for portfolios and performance metrics
│   │   ├── PortfolioItemRepository.php    # CRUD for holdings, purchase logging, statuses
│   │   └── TrackedAssetRepository.php     # Standalone watchlist items
│   ├── services/
│   │   ├── Database.php                   # PDO singleton with ping/reconnect resilience
│   │   ├── MarketHunterService.php        # Queries FMP screener API using dynamic filters
│   │   ├── MarketDataService.php          # Quotes, company profile, exchange metadata
│   │   ├── NewsService.php                # Company and macro news ingestion
│   │   ├── StrategyArchitectService.php   # LLM prompt deconstruction into screen filters
│   │   ├── AgentCouncilService.php        # Parallel specialists -> Bull/Bear debate -> Risk sizing
│   │   ├── TimezoneScheduleService.php    # Exchange hours, UTC alignment & cadence calculations
│   │   └── GuardianVigilanceService.php   # Background position audit & alert trigger engine
│   └── views/
│       ├── layout/                        # Header, sidebar, footer navigation
│       ├── portfolio_builder.php          # Prompt input, generation progress, proposal cards
│       ├── portfolios.php                 # Portfolio list, health status, detail drawer
│       ├── portfolio_detail.php           # Items table, mark-as-bought, rebalance action
│       └── tracker.php                    # Standalone watchlist and inspection modal
├── cli/
│   ├── scout_portfolio.php                # CLI runner for test scouting prompts
│   └── run_guardian_check.php             # Hostinger cron entrypoint (respects cadence & hours)
└── index.php                              # Application router & RBAC gatekeeper
```

---

## 3. Database Schema Design (`001_portfolio_system.sql`)

### 3.1. `portfolios`
Stores the overall portfolio collection generated from a user prompt.

```sql
CREATE TABLE IF NOT EXISTS `portfolios` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `user_prompt` TEXT NOT NULL,
    `strategy_summary` TEXT NOT NULL,
    `time_horizon` ENUM('short_term', 'mid_term', 'long_term') NOT NULL DEFAULT 'mid_term',
    `initial_capital` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `cash_reserve` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `current_value` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `alert_status` ENUM('normal', 'action_needed', 'urgent') NOT NULL DEFAULT 'normal',
    `alert_message` VARCHAR(255) NULL,
    `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_alert_status` (`alert_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3.2. `portfolio_items`
Stores the individual components of a portfolio, their AI council findings, and actual purchase tracking.

```sql
CREATE TABLE IF NOT EXISTS `portfolio_items` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `portfolio_id` INT UNSIGNED NOT NULL,
    `symbol` VARCHAR(20) NOT NULL,
    `asset_name` VARCHAR(100) NOT NULL,
    `asset_type` VARCHAR(20) NOT NULL DEFAULT 'stock',
    `exchange` VARCHAR(20) NOT NULL DEFAULT 'NASDAQ',
    `exchange_timezone` VARCHAR(50) NOT NULL DEFAULT 'America/New_York',
    `time_horizon` ENUM('short_term', 'mid_term', 'long_term') NOT NULL DEFAULT 'mid_term',
    `check_interval_hours` SMALLINT UNSIGNED NOT NULL DEFAULT 24,
    `last_checked_at` DATETIME NULL,
    `current_price` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
    `suggested_shares` INT UNSIGNED NOT NULL DEFAULT 1,
    `suggested_price` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
    `risk_tier` ENUM('low', 'moderate', 'high', 'speculative') NOT NULL DEFAULT 'moderate',
    `why_picked` TEXT NOT NULL,
    `bull_thesis` TEXT NULL,
    `bear_thesis` TEXT NULL,
    `invalidation_trigger` TEXT NULL,
    `is_purchased` TINYINT(1) NOT NULL DEFAULT 0,
    `purchase_price` DECIMAL(12,4) NULL,
    `purchase_shares` INT UNSIGNED NULL,
    `purchased_at` DATETIME NULL,
    `item_status` ENUM('suggested', 'holding', 'sell_recommended', 'sold') NOT NULL DEFAULT 'suggested',
    `item_alert` VARCHAR(255) NULL,
    `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`portfolio_id`) REFERENCES `portfolios`(`id`) ON DELETE CASCADE,
    INDEX `idx_symbol` (`symbol`),
    INDEX `idx_schedule` (`exchange_timezone`, `last_checked_at`, `check_interval_hours`),
    INDEX `idx_status` (`item_status`, `is_purchased`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3.3. `tracked_assets`
For the standalone Asset Tracker screen (quick watch without portfolio commitments).

```sql
CREATE TABLE IF NOT EXISTS `tracked_assets` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `symbol` VARCHAR(20) NOT NULL UNIQUE,
    `asset_name` VARCHAR(100) NOT NULL,
    `asset_type` VARCHAR(20) NOT NULL DEFAULT 'stock',
    `exchange` VARCHAR(20) NOT NULL DEFAULT 'NASDAQ',
    `exchange_timezone` VARCHAR(50) NOT NULL DEFAULT 'America/New_York',
    `current_price` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
    `change_percent` DECIMAL(6,2) NULL,
    `notes` VARCHAR(255) NULL,
    `last_audited_at` DATETIME NULL,
    `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_symbol` (`symbol`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 4. Multi-Agent Pipeline & Data Ingestion Flow

### 4.1. Stage 1: Strategy Translation (`StrategyArchitectService.php`)
Translates the user prompt into structured screener parameters using an LLM JSON completion:
* **Prompt Input:** Natural language text, capital, horizon.
* **Output:**
  ```json
  {
    "portfolio_name": "Moonshot Micro-Tech 2026",
    "strategy_summary": "High-risk growth portfolio targeting sub-$5 tech innovators with active catalysts over 6-12 months.",
    "time_horizon": "mid_term",
    "screening_filters": {
      "price_max": 5.00,
      "market_cap_max": 1000000000,
      "volume_min": 250000,
      "sectors": "Technology,Healthcare",
      "limit": 25
    },
    "asset_count_target": 5,
    "cash_reserve_ratio": 0.05
  }
  ```

### 4.2. Stage 2: Screener Ingestion (`MarketHunterService.php`)
* Invokes FMP's `/stock-screener` endpoint with the parameters returned by the Architect.
* Obtains raw matching tickers, verifying minimum volume and market activity.

### 4.3. Stage 3: The Parallel Council & Adversarial Debate (`AgentCouncilService.php`)
For the candidate pool:
1. **Parallel Ingestion:**
   * Fundamental metrics (debt, cash flow, margins) fetched via FMP.
   * Recent news headlines and catalysts fetched via Finnhub.
   * Multi-day price trend and volatility calculated.
2. **Specialist Evaluation:**
   * Fundamental Agent checks cash runway and solvency.
   * Macro/News Agent checks pending legal, regulatory, or earnings catalysts.
   * Technical Agent checks trend consolidation.
3. **Adversarial Debate:**
   * Bull Agent presents the strongest growth thesis.
   * Bear Agent identifies the primary failure mechanism / thesis killer.
4. **Risk Sizing & Translation:**
   * Risk Arbiter selects the top 4–6 candidates from the pool, allocates specific share counts to match the user's capital, sets the monitoring cadence (`check_interval_hours`), and synthesizes the findings into plain English.

---

## 5. Timezone & Cadence Architecture (`TimezoneScheduleService.php`)

The background cron job (`cli/run_guardian_check.php`) runs every 15 minutes, but only processes assets that meet two strict conditions:

1. **Interval Elapsed:**
   `NOW() >= last_checked_at + INTERVAL check_interval_hours HOUR`
2. **Active Exchange Window:**
   The service converts current UTC time to the asset's `exchange_timezone`:
   * **US Equities (`America/New_York`):** Evaluated during 09:30–16:30 local time, or once daily within 45 minutes of market close (16:45 local).
   * **Asian Equities (`Asia/Tokyo`, `Asia/Hong_Kong`):** Evaluated during local trading hours or immediately following their respective closes.
   * **European Equities (`Europe/Madrid`, `Europe/London`):** Evaluated during 09:00–17:30 local time.
   * **Crypto:** Evaluated 24/7 strictly by `check_interval_hours`.

If an exchange is closed and has already received its post-close daily review, the runner skips the asset immediately with zero external API calls.

---

## 6. User Interface Architecture

1. **Portfolio Builder (`/portfolio-builder`):**
   * Single conversational text prompt box with quick strategy chips.
   * Interactive proposal view: editable portfolio title, total budget breakdown, cash reserve, and candidate asset cards.
   * Each card features: Company Name, Price, Quantity to buy, Plain English rationale, and an "Inspect Deep Debate" modal button.
   * One-click "Save to My Portfolios" button.

2. **My Portfolios (`/portfolios`):**
   * Overview cards of all saved portfolios with real-time portfolio valuation and return metrics.
   * High-contrast **"Action Needed" / "Urgent"** status pill on portfolios requiring investor attention.
   * Portfolio Detail View:
     * Table of holdings with one-click **"Mark as Bought"** modal (allows entering real purchase price and shares).
     * **"Recalculate Strategy"** button: adjusts advice based on real entered purchase prices.
     * Plain English advice tags (`HOLD`, `TAKE_PROFIT_PARTIAL`, `CUT_LOSS`).

3. **Asset Tracker (`/tracker`):**
   * Lightweight standalone watchlist for tracking individual ideas without building a portfolio.
   * Live price movement, notes, and an on-demand **"Inspect Asset"** button to summon the Council of Agents.