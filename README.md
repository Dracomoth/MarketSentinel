# MarketSentinel // Institutional Wealth Management & AI Advisory Terminal

> **DISCLAIMER:** This software is a personal tool created for educational and experimental purposes. It is **not** professional financial advice, nor is it a recommendation, endorsement, or promotion of any specific investment strategy, asset, or financial product.

---

## 1. Purpose of the Software

**MarketSentinel** is a self-hosted, multi-agent financial advisory and portfolio monitoring terminal built for individual operators. It delivers institutional-grade market research, adversarial vetting, and ongoing position vigilance in clear, plain language with zero financial jargon. 

The application bridges the gap between raw quantitative market data and actionable investment intelligence by orchestrating a **Multi-Agent AI Council** (Macro Strategist, Fundamental Auditor, Technical Quant, Bull/Bear Advocates, and Chief Risk Arbiter) that evaluates assets, conducts structured debates, and establishes risk guardrails.

---

## 2. Core Features

* **Intent-Driven Discovery ("The Hunter"):** Translates natural language investment prompts (e.g., *"I have $3,000 for high-growth penny tech stocks over 6 months"*) into structured screening parameters, queries live exchange data, and proposes a complete portfolio allocation.
* **Adversarial Multi-Agent Council:** Each candidate asset undergoes concurrent evaluation by specialist agents. A Bull Analyst presents upside catalysts, a Bear Analyst identifies primary risks and failure mechanisms, and a Risk Arbiter establishes share counts, stop-loss floors, and monitoring cadences.
* **Interactive Council Chat Orchestrator:** A dedicated AI chat room per portfolio where you can ask questions, request strategy recalculations, and review structured action proposals that require your explicit confirmation before executing.
* **Timezone-Aware Background Guardian (`cli/run_guardian.php`):** Resource-efficient background surveillance that runs only during active exchange trading hours (US, European, Asian-Pacific, and 24/7 Crypto) to prevent wasted API consumption on closed markets.
* **Standalone Asset Tracker:** Lightweight watchlist for monitoring individual ideas, tracking price changes, viewing exchange session states, and launching on-demand AI council audits.
* **Role-Based Access Control (RBAC) & Security:** Granular screen permissions (Read/Write/None) configurable per role, device context detection, and secure password hashing.

---

## 3. Prerequisites & Requirements

### System Requirements
* **PHP:** Version 8.2 or higher (with `pdo`, `curl`, and `mbstring` extensions enabled).
* **Database:** MySQL 8.0+ or MariaDB 10.3+ with InnoDB support.
* **Web Server:** Apache (with `mod_rewrite` enabled for `.htaccess` routing) or the PHP Built-in Web Server for local development.
* **Hosting Host:** Compatible with standard shared web hosting or cloud VPS (e.g., Hostinger Cloud/Shared).

### Required API Keys
To power the AI council and live market data ingestion, you must obtain API keys from:
1. **OpenRouter API Key:** For accessing LLM tiers (DeepSeek for high-volume analysis; Claude 3.5 Sonnet / Claude Sonnet 4.5 for arbitration and synthesis).
2. **Financial Modeling Prep (FMP) API Key:** For real-time quotes, stock screeners, and company profiles.
3. **Finnhub API Key:** For company news feeds, sentiment analysis, and macroeconomic updates.

---

## 4. Deployment Guide

### Step 1: Upload Files
Upload the complete project codebase to your web server document root (or subfolder). Ensure that `.htaccess` (for Apache) is placed in the root directory.

### Step 2: Configure Environment (`config.php`)
1. Locate the configuration template at `config/config.example.php`.
2. Copy or rename it to `config/config.php` (or `/config.php` depending on your server layout).
3. Open `config/config.php` and fill in your database credentials, FMP API key, Finnhub API key, and OpenRouter API key:

```php
return [
    'database' => [
        'host'      => 'your_db_host',
        'database'  => 'your_db_name',
        'username'  => 'your_db_user',
        'password'  => 'your_db_password',
    ],
    'market_data' => [
        'fmp' => ['api_key' => 'YOUR_FMP_API_KEY'],
        'finnhub' => ['api_key' => 'YOUR_FINNHUB_API_KEY'],
    ],
    'ai' => [
        'api_key' => 'YOUR_OPENROUTER_API_KEY',
    ],
];
```

### Step 3: Run Database Migrations
Execute the SQL migration scripts in your database management tool (such as phpMyAdmin or MySQL CLI) in chronological order:
1. `database/000_database_creation.sql` (Sets up system info, screens, roles, and default superadmin).
2. `database/001_portfolio_system.sql` (Establishes portfolios, portfolio items, and tracked assets).
3. `database/002_portfolio_risk_and_chat.sql` (Adds risk metrics, broker fees, and persistent AI chat tables).

---

## 5. Changing the Super Admin Username and Password

By default, the initial installation seeds a SuperAdmin account with standard credentials. For production security, you must update these credentials immediately.

### Option A: Via Database (Recommended for Initial Setup)
You can update the default superadmin credentials directly in your database using SQL:

```sql
-- 1. Update SuperAdmin username and email
UPDATE `users` 
SET `username` = 'your_new_admin_username', 
    `email` = 'your_new_email@domain.com',
    `first_name` = 'YourFirstName',
    `last_name` = 'YourLastName'
WHERE `username` = 'superadmin';

-- 2. Update SuperAdmin password hash
-- Note: Replace '$2y$10$YOUR_NEW_BCRYPT_HASH' with a valid bcrypt hash generated via PHP password_hash()
UPDATE `users` 
SET `password_hash` = '$2y$10$8/7xZ4MZRLYIxLCtOZUhR.MnUlh14YwnjSb9CTOlAhOzAlr0wecKW'
WHERE `username` = 'your_new_admin_username';
```

*(Tip: To generate a secure bcrypt hash in PHP, run `php -r "echo password_hash('YourSecurePassword123!', PASSWORD_BCRYPT);"` in your terminal).*

### Option B: Via the Application UI
1. Log in to the application using the default credentials (`superadmin` / `SuperAdmin123!`).
2. Navigate to **Users Management** (`/users`) in the sidebar under *Security & Governance*.
3. Click **Edit** next to the `superadmin` account.
4. Modify the Username, First Name, Last Name, and Email Address.
5. Enter your new password in the **Password** field (leave blank if keeping unchanged).
6. Click **Update User** to save your changes.

---

## 6. Background Guardian Cron Setup (Hostinger / Linux)

To enable automated portfolio surveillance, configure a cron job on your server to execute the Guardian runner CLI script every 15 minutes:

```bash
*/15 * * * * /usr/bin/php /path/to/your/project/scripts/run_guardian.php >/dev/null 2>&1
```