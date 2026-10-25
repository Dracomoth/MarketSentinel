<?php
/**
 * Portfolio Builder View - Institutional Wealth Management
 * 
 * Interactive 2-phase discovery terminal:
 * - Phase 1: Natural language prompt input, budget, and quick tags
 * - Phase 2: Live AI Council proposal review, asset cards, plain-English theses & persistence
 */

require_once dirname(__DIR__) . '/services/MarkdownService.php';

$pageTitle = "Portfolio Builder - " . htmlspecialchars($appName ?? 'MarketSentinel', ENT_QUOTES, 'UTF-8');
$hasDraft = !empty($draft) && !empty($draft['items']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .builder-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .builder-hero-card {
            background-color: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            padding: 2rem;
            box-shadow: var(--shadow-card);
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }
        .builder-hero-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--color-primary) 0%, var(--color-gold) 50%, var(--color-primary-light) 100%);
        }
        .chips-container {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 0.75rem;
            margin-bottom: 1.25rem;
        }
        .preset-chip {
            background: var(--color-surface-elevated);
            border: 1px solid var(--color-border);
            color: var(--color-text-secondary);
            font-size: var(--font-size-xs);
            font-weight: 500;
            padding: 0.45rem 0.85rem;
            border-radius: var(--radius-pill);
            cursor: pointer;
            transition: all var(--transition-fast);
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }
        .preset-chip:hover {
            border-color: var(--color-primary);
            color: var(--color-primary-light);
            background: var(--color-surface-hover);
            transform: translateY(-1px);
        }
        /* Loading Overlay & State */
        .scout-loading-overlay {
            display: none;
            background: var(--color-surface-sunken);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            padding: 1.5rem;
            margin-top: 1.5rem;
            text-align: center;
        }
        .scout-loading-overlay.is-active {
            display: block;
            animation: fadeIn 0.3s ease;
        }
        .spinner-ring {
            display: inline-block;
            width: 38px;
            height: 38px;
            border: 3px solid rgba(59, 130, 246, 0.2);
            border-radius: 50%;
            border-top-color: var(--color-primary);
            animation: spin 0.9s linear infinite;
            margin-bottom: 0.85rem;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(6px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Proposal Review Section */
        .proposal-wrapper {
            animation: fadeIn 0.4s ease;
        }
        .proposal-header-box {
            background-color: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            padding: 1.75rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-card);
        }
        .proposal-name-input {
            background: var(--color-surface-sunken);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            color: var(--color-text);
            font-size: 1.35rem;
            font-weight: 700;
            padding: 0.6rem 1rem;
            width: 100%;
            transition: border-color var(--transition-fast);
        }
        .proposal-name-input:focus {
            border-color: var(--color-primary);
            outline: none;
            background: var(--color-surface-elevated);
        }
        .proposal-stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-top: 1.25rem;
        }
        @media (max-width: 860px) {
            .proposal-stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        @media (max-width: 480px) {
            .proposal-stats-grid {
                grid-template-columns: 1fr;
            }
        }
        .p-stat-card {
            background: var(--color-surface-elevated);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            padding: 1rem 1.15rem;
        }
        .p-stat-label {
            font-size: var(--font-size-xs);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--color-text-muted);
            margin-bottom: 0.25rem;
        }
        .p-stat-val {
            font-size: 1.35rem;
            font-weight: 700;
            font-family: var(--font-mono);
            color: var(--color-text);
        }

        /* Strategy Summary Callout Banner */
        .strategy-banner {
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.95) 0%, rgba(22, 34, 56, 0.95) 100%);
            border: 1px solid var(--color-border);
            border-left: 4px solid var(--color-gold);
            border-radius: var(--radius-lg);
            padding: 1.5rem 1.75rem;
            margin-bottom: 1.75rem;
            box-shadow: var(--shadow-card);
        }
        .strategy-banner-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.65rem;
        }
        .strategy-banner-title h3 {
            font-size: 1.1rem;
            color: var(--color-gold);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .strategy-banner p {
            color: var(--color-text-secondary);
            font-size: var(--font-size-base);
            line-height: 1.6;
        }
        .prompt-echo-box {
            margin-top: 0.85rem;
            padding-top: 0.85rem;
            border-top: 1px solid var(--color-border-subtle);
            font-size: var(--font-size-xs);
            color: var(--color-text-muted);
        }

        /* Asset Cards Grid */
        .assets-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        @media (max-width: 860px) {
            .assets-grid {
                grid-template-columns: 1fr;
            }
        }
        .asset-card {
            background-color: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            box-shadow: var(--shadow-card);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: all var(--transition-fast);
        }
        .asset-card:hover {
            border-color: var(--color-border-hover);
            transform: translateY(-2px);
        }
        .asset-card-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }
        .asset-ticker-title {
            font-size: 1.35rem;
            font-weight: 800;
            color: var(--color-primary-light);
            font-family: var(--font-mono);
            display: inline-flex;
            align-items: baseline;
            gap: 0.5rem;
        }
        .asset-company-name {
            font-size: var(--font-size-sm);
            color: var(--color-text);
            font-weight: 600;
            margin-top: 0.15rem;
        }
        .asset-price-box {
            text-align: right;
            font-family: var(--font-mono);
        }
        .asset-price-val {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--color-text);
        }
        .asset-alloc-pill {
            background: var(--color-surface-sunken);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            padding: 0.75rem 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-family: var(--font-mono);
            font-size: var(--font-size-sm);
            margin-bottom: 1rem;
        }
        .why-picked-box {
            background: var(--color-surface-elevated);
            border: 1px solid var(--color-border-subtle);
            border-radius: var(--radius-md);
            padding: 0.875rem 1rem;
            font-size: var(--font-size-sm);
            color: var(--color-text-secondary);
            line-height: 1.45;
            margin-bottom: 1rem;
        }
        .theses-toggle-btn {
            background: none;
            border: none;
            color: var(--color-primary-light);
            font-size: var(--font-size-xs);
            font-weight: 600;
            cursor: pointer;
            padding: 0;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            text-decoration: underline;
            text-underline-offset: 3px;
        }
        .theses-drawer {
            display: none;
            margin-top: 0.85rem;
            padding-top: 0.85rem;
            border-top: 1px solid var(--color-border-subtle);
            font-size: var(--font-size-xs);
            line-height: 1.45;
        }
        .theses-drawer.is-open {
            display: block;
        }
        .thesis-item {
            margin-bottom: 0.65rem;
        }
        .thesis-item:last-child {
            margin-bottom: 0;
        }
        .thesis-label {
            font-weight: 700;
            text-transform: uppercase;
            font-size: 10px;
            letter-spacing: 0.05em;
            display: block;
            margin-bottom: 0.15rem;
        }

        /* Bottom Actions Bar */
        .proposal-actions-bar {
            background-color: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            padding: 1.25rem 1.75rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            box-shadow: var(--shadow-card);
        }
    </style>
</head>
<body>
    <?php require __DIR__ . '/layout/header.php'; ?>
    <?php require __DIR__ . '/layout/sidebar.php'; ?>

    <main class="container page-content">
        <div class="builder-container">
            <!-- Breadcrumbs / Top Navigation -->
            <div class="page-header" style="margin-bottom: 1.25rem;">
                <div>
                    <div class="meta-label" style="margin-bottom: 0.35rem; color: var(--color-gold);">
                        Argus Wealth • Discovery Engine & AI Council
                    </div>
                    <h1>Portfolio Builder</h1>
                    <p class="text-muted">Describe the portfolio you want to build in plain words</p>
                </div>
                <div style="display: flex; gap: 0.75rem; align-items: center;">
                    <a href="/portfolios" class="btn btn-outline btn-sm">
                        View My Portfolios &rarr;
                    </a>
                </div>
            </div>

            <!-- Flash Error / Success Notifications -->
            <?php if (!empty($error)): ?>
                <div class="alert alert-error" style="margin-bottom: 1.5rem;">
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success" style="margin-bottom: 1.5rem;">
                    <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <!-- =============================================================== -->
            <!-- 1. PROPOSAL ACTIVE: REVIEW & PERSISTENCE DRAFT                  -->
            <!-- =============================================================== -->
            <?php if ($hasDraft): ?>
                <div class="proposal-wrapper">
                    <form method="POST" action="/portfolio-builder" id="save-portfolio-form">
                        <input type="hidden" name="action" value="save_portfolio">

                        <!-- Proposal Header with Editable Name & Stat Chips -->
                        <div class="proposal-header-box">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; flex-wrap: wrap; gap: 0.5rem;">
                                <div class="meta-label" style="color: var(--color-gold);">
                                    Proposal Ready to Commit • Session Draft
                                </div>
                                <span class="badge badge-success">
                                    ✓ Allocation Verified by Council
                                </span>
                            </div>

                            <label for="portfolio_name" style="font-size: var(--font-size-xs); text-transform: uppercase; font-weight: 600; color: var(--color-text-muted); display: block; margin-bottom: 0.35rem;">
                                Portfolio Name (Customizable)
                            </label>
                            <input type="text" id="portfolio_name" name="portfolio_name" 
                                   value="<?= htmlspecialchars($draft['portfolio_name'] ?? 'Custom Portfolio', ENT_QUOTES, 'UTF-8') ?>" 
                                   required class="proposal-name-input">

                            <!-- Metric Cards Bar -->
                            <div class="proposal-stats-grid">
                                <div class="p-stat-card">
                                    <div class="p-stat-label">Target Budget</div>
                                    <div class="p-stat-val">$<?= number_format((float)($draft['budget'] ?? 0), 2) ?></div>
                                </div>

                                <div class="p-stat-card">
                                    <div class="p-stat-label">Allocated Capital</div>
                                    <div class="p-stat-val text-emerald">$<?= number_format((float)($draft['allocated_total'] ?? 0), 2) ?></div>
                                </div>

                                <div class="p-stat-card">
                                    <div class="p-stat-label">Cash Buffer</div>
                                    <div class="p-stat-val text-gold">$<?= number_format((float)($draft['cash_buffer'] ?? 0), 2) ?></div>
                                </div>

                                <div class="p-stat-card">
                                    <div class="p-stat-label">Time Horizon</div>
                                    <div class="p-stat-val" style="font-size: 1.15rem; text-transform: uppercase;">
                                        <?= htmlspecialchars(str_replace('_', ' ', $draft['time_horizon'] ?? 'mid_term'), ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Strategy Summary Callout Banner -->
                        <div class="strategy-banner">
                            <div class="strategy-banner-title">
                                <h3>
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
                                        <polyline points="2 17 12 22 22 17"></polyline>
                                        <polyline points="2 12 12 17 22 12"></polyline>
                                    </svg>
                                    Council Thesis & Strategy Diagnosis
                                </h3>
                                <span class="badge" style="font-size: 10px; background: rgba(212, 175, 55, 0.15); color: var(--color-gold); border: 1px solid var(--color-gold);">
                                    Plain English • No Jargon
                                </span>
                            </div>

                            <div class="thesis-content-text">
                                <?= MarkdownService::render($draft['strategy_summary'] ?? '') ?>
                            </div>

                            <?php if (!empty($draft['user_prompt'])): ?>
                                <div class="prompt-echo-box">
                                    <strong>Requested Mandate:</strong> "<?= htmlspecialchars($draft['user_prompt'], ENT_QUOTES, 'UTF-8') ?>"
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Grid of Scouted Asset Cards -->
                        <div class="assets-grid">
                            <?php foreach ($draft['items'] as $index => $item): ?>
                                <?php
                                $price = (float)($item['current_price'] ?? 0);
                                $shares = (int)($item['suggested_shares'] ?? 1);
                                $totalPos = round($price * $shares, 2);
                                ?>
                                <div class="asset-card">
                                    <div>
                                        <!-- Top Row: Ticker & Price -->
                                        <div class="asset-card-top">
                                            <div>
                                                <div class="asset-ticker-title">
                                                    <?= htmlspecialchars($item['symbol'], ENT_QUOTES, 'UTF-8') ?>
                                                    <span class="sublink-order-badge" style="font-size: 10px;">
                                                        <?= htmlspecialchars($item['exchange'] ?? 'US', ENT_QUOTES, 'UTF-8') ?>
                                                    </span>
                                                </div>
                                                <div class="asset-company-name">
                                                    <?= htmlspecialchars($item['asset_name'] ?? $item['symbol'], ENT_QUOTES, 'UTF-8') ?>
                                                </div>
                                            </div>

                                            <div class="asset-price-box">
                                                <div class="asset-price-val">$<?= number_format($price, $price < 1.0 ? 4 : 2) ?></div>
                                                <span class="text-xs text-muted">Market Price</span>
                                            </div>
                                        </div>

                                        <!-- Suggested Allocation Pill -->
                                        <div class="asset-alloc-pill">
                                            <div>
                                                <span class="text-muted text-xs">Allocation:</span>
                                                <strong><?= $shares ?> shares</strong>
                                            </div>
                                            <div>
                                                <span class="text-muted text-xs">Amount:</span>
                                                <strong class="text-emerald">$<?= number_format($totalPos, 2) ?></strong>
                                            </div>
                                        </div>

                                        <!-- Plain-language Why Picked -->
                                        <div class="why-picked-box">
                                            <strong style="color: var(--color-text); display: block; margin-bottom: 0.2rem; font-size: var(--font-size-xs); text-transform: uppercase; letter-spacing: 0.05em;">
                                                Why this was selected
                                            </strong>
                                            <?= htmlspecialchars($item['why_picked'], ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                    </div>

                                    <!-- Collapsible Theses & Invalidation Trigger -->
                                    <div>
                                        <button type="button" class="theses-toggle-btn" onclick="toggleTheses(<?= $index ?>)">
                                            <span id="theses-icon-<?= $index ?>">▾</span> View Bull Thesis, Risks & Invalidation Trigger
                                        </button>

                                        <div class="theses-drawer" id="theses-drawer-<?= $index ?>">
                                            <div class="thesis-item">
                                                <span class="thesis-label text-emerald">▲ Bull Thesis</span>
                                                <div style="color: var(--color-text-secondary);"><?= htmlspecialchars($item['bull_thesis'] ?? 'Business catalysts and favorable market tailwinds.', ENT_QUOTES, 'UTF-8') ?></div>
                                            </div>

                                            <div class="thesis-item" style="margin-top: 0.5rem;">
                                                <span class="thesis-label text-ruby">▼ Downside Risks</span>
                                                <div style="color: var(--color-text-secondary);"><?= htmlspecialchars($item['bear_thesis'] ?? 'Macro volatility and potential margin pressures.', ENT_QUOTES, 'UTF-8') ?></div>
                                            </div>

                                            <div class="thesis-item" style="margin-top: 0.5rem; padding: 0.5rem; background: var(--color-surface-sunken); border-radius: var(--radius-xs);">
                                                <span class="thesis-label text-amber">⚠️ When to Exit (Invalidation Trigger)</span>
                                                <div style="color: var(--color-text-secondary);"><?= htmlspecialchars($item['invalidation_trigger'] ?? 'Loss of primary technical support or fundamental deterioration.', ENT_QUOTES, 'UTF-8') ?></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Action Bar: Confirm & Save OR Discard -->
                        <div class="proposal-actions-bar">
                            <div>
                                <strong style="color: var(--color-text); font-size: 1rem; display: block;">Satisfied with this proposal?</strong>
                                <span class="text-xs text-muted">It will be registered in 'My Portfolios' with autonomous surveillance.</span>
                            </div>

                            <div style="display: flex; gap: 0.85rem; align-items: center; flex-wrap: wrap;">
                                <button type="button" class="btn btn-outline" onclick="discardProposal()">
                                    Discard / Try Another Prompt
                                </button>
                                <button type="submit" class="btn btn-primary" id="btn-confirm-save" style="padding: 0.75rem 1.75rem; font-weight: 700;">
                                    ✓ Save this Portfolio
                                </button>
                            </div>
                        </div>
                    </form>

                    <!-- Hidden Discard Form -->
                    <form method="POST" action="/portfolio-builder" id="discard-draft-form" style="display: none;">
                        <input type="hidden" name="action" value="discard_draft">
                    </form>
                </div>

            <!-- =============================================================== -->
            <!-- 2. NO DRAFT: PROMPT INPUT HERO FORM                             -->
            <!-- =============================================================== -->
            <?php else: ?>
                <div class="builder-hero-card">
                    <form method="POST" action="/portfolio-builder" id="portfolio-scout-form">
                        <input type="hidden" name="action" value="generate_portfolio">

                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                            <h2 style="font-size: 1.35rem; font-weight: 700; color: var(--color-text); display: flex; align-items: center; gap: 0.5rem;">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--color-primary-light);">
                                    <circle cx="11" cy="11" r="8"></circle>
                                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                                    <line x1="11" y1="8" x2="11" y2="14"></line>
                                    <line x1="8" y1="11" x2="14" y2="11"></line>
                                </svg>
                                Investment Mandate Designer
                            </h2>
                            <span class="badge" style="font-size: 11px;">Live FMP Screening + Claude Sonnet 4.5</span>
                        </div>

                        <!-- Natural Language Prompt Textarea -->
                        <div class="form-group">
                            <label for="user_prompt" style="font-size: var(--font-size-sm); font-weight: 600; color: var(--color-text);">
                                Natural Language Mandate *
                            </label>

                            <!-- Quick Preset Inspiration Chips -->
                            <div class="chips-container">
                                <button type="button" class="preset-chip" data-prompt="I want an aggressive penny stock portfolio under $5 with high short-term upside and strong upcoming catalysts.">
                                    🚀 High-Growth Penny Stocks
                                </button>
                                <button type="button" class="preset-chip" data-prompt="I want a $5,000 portfolio focused on defensive dividend-paying equities, low leverage, and predictable cash flows.">
                                    🛡️ Defensive Dividends
                                </button>
                                <button type="button" class="preset-chip" data-prompt="Construct a portfolio oriented towards traditional energy, uranium, and critical commodity producers for data centers.">
                                    ⚡ Energy & Commodities
                                </button>
                                <button type="button" class="preset-chip" data-prompt="Select 4 undisputed market leaders in Artificial Intelligence infrastructure and semiconductors with net margins exceeding 30%.">
                                    🧠 AI Infrastructure Leaders
                                </button>
                            </div>

                            <textarea id="user_prompt" name="user_prompt" rows="4" required class="form-control"
                                      placeholder="e.g. I have $3,000. I want high-growth tech stocks under $5 with a 6-month horizon, moderate risk..."><?= htmlspecialchars($_POST['user_prompt'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                        </div>

                        <!-- Budget Input Row -->
                        <div class="form-row" style="margin-top: 1.25rem;">
                            <div class="form-group" style="flex: 1;">
                                <label for="budget" style="font-size: var(--font-size-sm); font-weight: 600; color: var(--color-text);">
                                    Budget ($)
                                </label>
                                <input type="number" id="budget" name="budget" step="100" min="100" 
                                       value="<?= htmlspecialchars($_POST['budget'] ?? '2000', ENT_QUOTES, 'UTF-8') ?>" 
                                       class="font-mono" style="font-size: 1.15rem; font-weight: 700;">
                                <span class="text-xs text-muted" style="margin-top: 0.25rem; display: block;">
                                    The Investment Council will calibrate exact share counts to your provided budget.
                                </span>
                            </div>

                            <div class="form-group" style="flex: 1; display: flex; align-items: flex-end; justify-content: flex-end;">
                                <button type="submit" class="btn btn-primary" id="btn-submit-scout" style="padding: 0.85rem 1.85rem; font-size: 1rem; font-weight: 700;">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="11" cy="11" r="8"></circle>
                                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                                        <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
                                    </svg>
                                    Scout & Build Portfolio
                                </button>
                            </div>
                        </div>

                        <!-- Loading State Component -->
                        <div class="scout-loading-overlay" id="loading-overlay">
                            <div class="spinner-ring"></div>
                            <h4 style="font-size: 1.05rem; font-weight: 700; color: var(--color-text); margin-bottom: 0.35rem;">
                                The Multi-Agent Council is analyzing market conditions...
                            </h4>
                            <p class="text-xs text-muted" id="loading-step-text">
                                Step 1: Extracting quantitative screening parameters from your mandate...
                            </p>
                        </div>
                    </form>
                </div>

                <!-- Guidance & Features Strip -->
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.25rem;">
                    <div class="p-stat-card" style="background: var(--color-surface);">
                        <div class="meta-label" style="color: var(--color-primary-light); margin-bottom: 0.35rem;">1. DYNAMIC SCREENING</div>
                        <h4 style="font-size: 0.95rem; margin-bottom: 0.25rem;">FMP Stock Screener</h4>
                        <p class="text-xs text-muted">Filters active quotes, price ranges, trading liquidity, and sector metrics in real time.</p>
                    </div>

                    <div class="p-stat-card" style="background: var(--color-surface);">
                        <div class="meta-label" style="color: var(--color-gold); margin-bottom: 0.35rem;">2. MULTI-AGENT COUNCIL</div>
                        <h4 style="font-size: 0.95rem; margin-bottom: 0.25rem;">Arbitrage & Selection</h4>
                        <p class="text-xs text-muted">Analyst models examine fundamentals and catalysts to select high-conviction assets.</p>
                    </div>

                    <div class="p-stat-card" style="background: var(--color-surface);">
                        <div class="meta-label" style="color: var(--color-success); margin-bottom: 0.35rem;">3. RISK MANAGEMENT</div>
                        <h4 style="font-size: 0.95rem; margin-bottom: 0.25rem;">When to Exit</h4>
                        <p class="text-xs text-muted">Each position includes an explicit condition to cut losses, alongside scheduled audit routines.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <?php require __DIR__ . '/layout/footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Preset Inspiration Chips
            var chips = document.querySelectorAll('.preset-chip');
            var promptTextarea = document.getElementById('user_prompt');

            chips.forEach(function(chip) {
                chip.addEventListener('click', function() {
                    var presetText = chip.getAttribute('data-prompt');
                    if (presetText && promptTextarea) {
                        promptTextarea.value = presetText;
                        promptTextarea.focus();
                    }
                });
            });

            // Smooth Loading State on Form Submit
            var scoutForm = document.getElementById('portfolio-scout-form');
            var submitBtn = document.getElementById('btn-submit-scout');
            var loadingOverlay = document.getElementById('loading-overlay');
            var loadingStep = document.getElementById('loading-step-text');

            if (scoutForm && submitBtn && loadingOverlay) {
                scoutForm.addEventListener('submit', function() {
                    submitBtn.disabled = true;
                    submitBtn.style.opacity = '0.6';
                    submitBtn.innerHTML = 'Consulting Council...';
                    loadingOverlay.classList.add('is-active');

                    var steps = [
                        'Step 1: Extracting quantitative screening parameters from your mandate...',
                        'Step 2: Scanning candidates across Financial Modeling Prep...',
                        'Step 3: Deliberating in the AI Council and computing optimal capital allocation...',
                        'Step 4: Formulating investment theses and invalidation triggers...'
                    ];
                    var stepIdx = 0;
                    var interval = setInterval(function() {
                        stepIdx++;
                        if (stepIdx < steps.length && loadingStep) {
                            loadingStep.textContent = steps[stepIdx];
                        } else {
                            clearInterval(interval);
                        }
                    }, 4500);
                });
            }
        });

        // Theses Drawer Toggle
        function toggleTheses(index) {
            var drawer = document.getElementById('theses-drawer-' + index);
            var icon = document.getElementById('theses-icon-' + index);
            if (drawer) {
                var isOpen = drawer.classList.contains('is-open');
                if (isOpen) {
                    drawer.classList.remove('is-open');
                    if (icon) icon.textContent = '▾';
                } else {
                    drawer.classList.add('is-open');
                    if (icon) icon.textContent = '▴';
                }
            }
        }

        // Discard Draft
        function discardProposal() {
            if (confirm('Are you sure you want to discard this proposal and try another prompt?')) {
                var discardForm = document.getElementById('discard-draft-form');
                if (discardForm) discardForm.submit();
            }
        }
    </script>
</body>
</html>
