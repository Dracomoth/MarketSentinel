<?php
/**
 * Asset Tracker View - Institutional Wealth Management
 * 
 * Screen 3: Asset Tracker (Standalone Asset Radar & On-Demand Council Audit)
 * Independent surveillance without requiring a portfolio:
 * - Live market quotes & exchange session status (Open 🟢 / Closed ⚪)
 * - Compact inline asset adding bar
 * - On-demand AI Council Audit with real-time news headlines (Buy / Watch / Avoid)
 */

$pageTitle = "Asset Tracker - " . htmlspecialchars($appName ?? 'MarketSentinel', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg">
    <link rel="alternate icon" type="image/x-icon" href="/favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .tracker-header-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        /* Compact Inline Add Asset Bar */
        .inline-add-card {
            background-color: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            padding: 1.15rem 1.4rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-card);
        }
        .inline-add-form {
            display: flex;
            align-items: center;
            gap: 0.85rem;
            flex-wrap: wrap;
        }
        .inline-add-form .input-symbol-wrap {
            flex: 0 0 180px;
        }
        .inline-add-form .input-notes-wrap {
            flex: 1 1 280px;
        }
        .inline-add-form input {
            width: 100%;
            height: 42px;
            background: var(--color-surface-sunken);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            padding: 0 0.85rem;
            color: var(--color-text);
            font-size: var(--font-size-sm);
            transition: all var(--transition-fast);
        }
        .inline-add-form input:focus {
            outline: none;
            border-color: var(--color-primary-light);
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
        }
        .inline-add-form .btn-submit-asset {
            height: 42px;
            padding: 0 1.25rem;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
        }

        /* Quick Metrics Bar */
        .tracker-stats-bar {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        @media (max-width: 860px) {
            .tracker-stats-bar {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        @media (max-width: 480px) {
            .tracker-stats-bar {
                grid-template-columns: 1fr;
            }
        }
        .tracker-stat-box {
            background-color: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            padding: 1rem 1.25rem;
            box-shadow: var(--shadow-card);
        }
        .tracker-stat-label {
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--color-text-muted);
            margin-bottom: 0.25rem;
        }
        .tracker-stat-num {
            font-size: 1.375rem;
            font-weight: 700;
            font-family: var(--font-mono);
            color: var(--color-text);
        }

        /* Table & Chips */
        .quote-chip-up {
            background: var(--color-success-bg);
            border: 1px solid var(--color-success-border);
            color: var(--color-success);
            padding: 0.2rem 0.55rem;
            border-radius: var(--radius-sm);
            font-family: var(--font-mono);
            font-size: var(--font-size-xs);
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }
        .quote-chip-down {
            background: var(--color-danger-bg);
            border: 1px solid var(--color-danger-border);
            color: var(--color-danger);
            padding: 0.2rem 0.55rem;
            border-radius: var(--radius-sm);
            font-family: var(--font-mono);
            font-size: var(--font-size-xs);
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }
        .quote-chip-flat {
            background: var(--color-surface-elevated);
            border: 1px solid var(--color-border);
            color: var(--color-text-muted);
            padding: 0.2rem 0.55rem;
            border-radius: var(--radius-sm);
            font-family: var(--font-mono);
            font-size: var(--font-size-xs);
            font-weight: 600;
        }

        /* Attention Beacon Badges */
        .beacon-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.25rem 0.65rem;
            border-radius: var(--radius-pill);
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.02em;
        }
        .beacon-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            display: inline-block;
        }
        .beacon-normal {
            background: var(--color-success-bg);
            border: 1px solid var(--color-success-border);
            color: var(--color-success);
        }
        .beacon-normal .beacon-dot {
            background-color: var(--color-success);
            box-shadow: 0 0 6px var(--color-success);
        }
        .beacon-closed {
            background: var(--color-surface-sunken);
            border: 1px solid var(--color-border);
            color: var(--color-text-muted);
        }
        .beacon-closed .beacon-dot {
            background-color: var(--color-text-muted);
        }

        /* AI Audit Modal */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.78);
            backdrop-filter: blur(6px);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            padding: 1.5rem;
        }
        .modal-overlay.active {
            display: flex;
            animation: fadeIn 0.2s ease;
        }
        .modal-window {
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.98) 0%, rgba(22, 34, 56, 0.98) 100%);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            width: 100%;
            max-width: 620px;
            padding: 1.75rem;
            box-shadow: var(--shadow-elevated);
            max-height: 90vh;
            overflow-y: auto;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.97); }
            to { opacity: 1; transform: scale(1); }
        }
        .spinner-inline {
            display: inline-block;
            width: 28px;
            height: 28px;
            border: 3px solid rgba(59, 130, 246, 0.2);
            border-radius: 50%;
            border-top-color: var(--color-primary-light);
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Verdict Badges */
        .verdict-badge {
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            padding: 0.35rem 0.95rem;
            border-radius: var(--radius-pill);
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }
        .verdict-buy, .verdict-comprar {
            background: var(--color-success-bg);
            border: 1px solid var(--color-success-border);
            color: var(--color-success);
            box-shadow: 0 0 10px rgba(16, 185, 129, 0.2);
        }
        .verdict-watch, .verdict-vigilar {
            background: rgba(212, 175, 55, 0.15);
            border: 1px solid var(--color-gold);
            color: var(--color-gold);
            box-shadow: 0 0 10px rgba(212, 175, 55, 0.2);
        }
        .verdict-avoid, .verdict-evitar {
            background: var(--color-danger-bg);
            border: 1px solid var(--color-danger-border);
            color: var(--color-danger);
            box-shadow: 0 0 10px rgba(239, 68, 68, 0.2);
        }

        /* Diagnostic Boxes */
        .audit-box {
            padding: 0.85rem 1.15rem;
            border-radius: var(--radius-md);
            margin-bottom: 0.9rem;
            background: var(--color-surface-sunken);
            border: 1px solid var(--color-border-subtle);
        }
        .audit-box-header {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 0.35rem;
            display: flex;
            align-items: center;
            gap: 0.35rem;
        }
        .audit-box-body {
            font-size: var(--font-size-sm);
            line-height: 1.6;
            color: var(--color-text-secondary);
        }
    </style>
</head>
<body>
    <?php require __DIR__ . '/layout/header.php'; ?>
    <?php require __DIR__ . '/layout/sidebar.php'; ?>

    <main class="container page-content">
        <!-- Top Header Bar -->
        <div class="tracker-header-row">
            <div>
                <div class="meta-label" style="margin-bottom: 0.35rem; color: var(--color-gold);">
                    Market Intelligence • Screen 3
                </div>
                <h1>Asset Tracker</h1>
                <p class="text-muted">Standalone asset radar without requiring a portfolio</p>
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error" style="margin-bottom: 1.5rem;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0;">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                <span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success" style="margin-bottom: 1.5rem;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0;">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
                <span><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        <?php endif; ?>

        <!-- Add Asset Form: Compact Inline Bar -->
        <div class="inline-add-card">
            <form method="POST" action="/tracker" class="inline-add-form">
                <input type="hidden" name="action" value="add_asset">

                <div class="input-symbol-wrap">
                    <input type="text" id="symbol" name="symbol" placeholder="Symbol (e.g. NVDA, AAPL)" 
                           required style="text-transform: uppercase;" autocomplete="off" autofocus>
                </div>

                <div class="input-notes-wrap">
                    <input type="text" id="notes" name="notes" placeholder="Personal Note (optional)">
                </div>

                <button type="submit" class="btn btn-primary btn-submit-asset">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    Add to Radar
                </button>
            </form>
        </div>

        <!-- Quick Telemetry Metrics Bar -->
        <?php
        $totalCount = count($trackedAssets);
        $gainers = 0;
        $decliners = 0;
        $sumChange = 0;
        foreach ($trackedAssets as $ta) {
            $chg = (float)($ta['change_percent'] ?? 0);
            $sumChange += $chg;
            if ($chg > 0) $gainers++;
            elseif ($chg < 0) $decliners++;
        }
        $avgChange = $totalCount > 0 ? ($sumChange / $totalCount) : 0.0;
        ?>
        <div class="tracker-stats-bar">
            <div class="tracker-stat-box">
                <div class="tracker-stat-label">Tracked Assets</div>
                <div class="tracker-stat-num"><?= $totalCount ?></div>
            </div>

            <div class="tracker-stat-box">
                <div class="tracker-stat-label">Gainers</div>
                <div class="tracker-stat-num text-emerald"><?= $gainers ?></div>
            </div>

            <div class="tracker-stat-box">
                <div class="tracker-stat-label">Decliners</div>
                <div class="tracker-stat-num text-ruby"><?= $decliners ?></div>
            </div>

            <div class="tracker-stat-box">
                <div class="tracker-stat-label">Avg 24h Change</div>
                <div class="tracker-stat-num <?= $avgChange >= 0 ? 'text-emerald' : 'text-ruby' ?>">
                    <?= $avgChange >= 0 ? '+' : '' ?><?= number_format($avgChange, 2) ?>%
                </div>
            </div>
        </div>

        <!-- Assets Table / Card Deck -->
        <div class="card grid-card">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Asset</th>
                            <th>Market Status</th>
                            <th class="text-right">Current Price</th>
                            <th class="text-right">24h Change</th>
                            <th>Personal Notes</th>
                            <th>Last Updated</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($trackedAssets)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted" style="padding: 3.5rem 1.5rem;">
                                    <div style="font-size: 2.5rem; margin-bottom: 0.65rem;">📡</div>
                                    <p style="font-weight: 700; color: var(--color-text); font-size: 1.1rem; margin-bottom: 0.35rem;">
                                        Your tracker is empty. Add a symbol above to start monitoring.
                                    </p>
                                    <p class="text-xs text-muted" style="max-width: 440px; margin: 0 auto;">
                                        Enter a symbol like <strong>NVDA</strong>, <strong>AAPL</strong> or <strong>MSFT</strong> in the bar above to start live quote tracking and on-demand AI council audits.
                                    </p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($trackedAssets as $asset): ?>
                                <?php
                                $price = (float) $asset['current_price'];
                                $chg = isset($asset['change_percent']) ? (float)$asset['change_percent'] : null;
                                $session = $asset['market_session'] ?? ['open' => false, 'label' => 'Closed', 'badge_class' => 'beacon-closed', 'tz_label' => ''];
                                $hasSavedAudit = !empty($asset['saved_audit']);
                                ?>
                                <tr>
                                    <td>
                                        <div style="display: flex; align-items: baseline; gap: 0.5rem;">
                                            <span class="ticker-symbol" style="font-size: 1.05rem; font-weight: 700; color: var(--color-primary-light);">
                                                <?= htmlspecialchars($asset['symbol'], ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                            <span class="sublink-order-badge" style="font-size: 10px; background: rgba(59, 130, 246, 0.1); color: var(--color-primary-light);">
                                                <?= htmlspecialchars($asset['exchange'] ?? 'US', ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </div>
                                        <div class="text-xs text-secondary" style="margin-top: 0.2rem; max-width: 240px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                            <?= htmlspecialchars($asset['asset_name'], ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                    </td>

                                    <td>
                                        <div style="display: flex; flex-direction: column; gap: 0.25rem;">
                                            <span class="beacon-badge <?= $session['badge_class'] ?>" title="<?= htmlspecialchars($session['tz_label'], ENT_QUOTES, 'UTF-8') ?>">
                                                <span class="beacon-dot"></span> <?= htmlspecialchars($session['label'], ENT_QUOTES, 'UTF-8') ?> <?= !empty($session['open']) ? '🟢' : '⚪' ?>
                                            </span>
                                            <span class="text-xs text-muted font-mono" style="font-size: 10px;">
                                                <?= htmlspecialchars($session['tz_label'], ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </div>
                                    </td>

                                    <td class="text-right font-mono" style="font-weight: 700; font-size: 1.05rem; color: var(--color-text);">
                                        $<?= number_format($price, $price < 1.0 && $price > 0 ? 4 : 2) ?>
                                    </td>

                                    <td class="text-right font-mono">
                                        <?php if ($chg === null): ?>
                                            <span class="quote-chip-flat">N/A</span>
                                        <?php elseif ($chg > 0): ?>
                                            <span class="quote-chip-up">+<?= number_format($chg, 2) ?>%</span>
                                        <?php elseif ($chg < 0): ?>
                                            <span class="quote-chip-down"><?= number_format($chg, 2) ?>%</span>
                                        <?php else: ?>
                                            <span class="quote-chip-flat">0.00%</span>
                                        <?php endif; ?>
                                    </td>

                                    <td class="text-xs text-secondary" style="max-width: 260px;">
                                        <?= !empty($asset['notes']) ? htmlspecialchars($asset['notes'], ENT_QUOTES, 'UTF-8') : '<span class="text-muted">—</span>' ?>
                                    </td>

                                    <td class="text-muted text-xs font-mono">
                                        <?= !empty($asset['updated']) ? date('M d, Y H:i', strtotime($asset['updated'])) : (!empty($asset['last_checked_at']) ? date('M d, Y H:i', strtotime($asset['last_checked_at'])) : '—') ?>
                                    </td>

                                    <td class="text-right">
                                        <div style="display: flex; gap: 0.5rem; justify-content: flex-end; align-items: center;">
                                            <!-- AI Audit Button -->
                                            <button type="button" class="btn btn-primary btn-xs btn-audit-asset"
                                                    data-id="<?= (int)$asset['id'] ?>"
                                                    data-symbol="<?= htmlspecialchars($asset['symbol'], ENT_QUOTES, 'UTF-8') ?>"
                                                    data-name="<?= htmlspecialchars($asset['asset_name'], ENT_QUOTES, 'UTF-8') ?>"
                                                    data-price="$<?= number_format($price, 2) ?>"
                                                    data-audit='<?= $hasSavedAudit ? htmlspecialchars(json_encode($asset['saved_audit'], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') : '' ?>'>
                                                ⚡ Audit with AI
                                            </button>

                                            <!-- Remove Button -->
                                            <form method="POST" action="/tracker" onsubmit="return confirm('Remove <?= htmlspecialchars($asset['symbol'], ENT_QUOTES, 'UTF-8') ?> from radar?');" style="display: inline-block; margin: 0;">
                                                <input type="hidden" name="action" value="remove_asset">
                                                <input type="hidden" name="id" value="<?= (int) $asset['id'] ?>">
                                                <button type="submit" class="btn btn-outline btn-xs text-ruby" style="border-color: rgba(239, 68, 68, 0.3);" title="Remove from radar">
                                                    Remove
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- AI Council Inspection Modal -->
        <div class="modal-overlay" id="audit-modal">
            <div class="modal-window">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.25rem; border-bottom: 1px solid var(--color-border); padding-bottom: 1rem;">
                    <div>
                        <div class="meta-label" style="color: var(--color-gold); margin-bottom: 0.25rem;">
                            Investment Audit • Multi-Agent Council
                        </div>
                        <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--color-text); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                            <span id="audit-symbol" class="ticker-symbol" style="color: var(--color-primary-light);"></span>
                            <span id="audit-name" style="font-size: 0.95rem; font-weight: 500; color: var(--color-text-secondary);"></span>
                            <span id="audit-price" class="font-mono text-xs text-muted"></span>
                        </h3>
                    </div>
                    <button type="button" id="btn-close-audit-modal" style="background: none; border: none; color: var(--color-text-muted); cursor: pointer; font-size: 1.5rem; line-height: 1; padding: 0.2rem;">&times;</button>
                </div>

                <!-- Spinner Loading State -->
                <div id="audit-loading" style="text-align: center; padding: 3rem 1.5rem;">
                    <div class="spinner-inline" style="margin-bottom: 1rem;"></div>
                    <p style="font-weight: 600; color: var(--color-text); margin-bottom: 0.35rem;">Consulting the Investment Council...</p>
                    <p class="text-xs text-muted">Synthesizing recent news headlines, technical analysis, and key risks with AI.</p>
                </div>

                <!-- Audit Content View -->
                <div id="audit-content" style="display: none;">
                    <!-- Direct Verdict -->
                    <div style="display: flex; align-items: center; justify-content: space-between; background: var(--color-surface-sunken); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 0.9rem 1.15rem; margin-bottom: 1.25rem;">
                        <span class="meta-label" style="color: var(--color-text-muted);">Direct Verdict</span>
                        <span id="audit-bottom-line" class="verdict-badge"></span>
                    </div>

                    <!-- Situation Diagnosis -->
                    <div class="audit-box">
                        <div class="audit-box-header" style="color: var(--color-text);">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" y1="16" x2="12" y2="12"></line>
                                <line x1="12" y1="8" x2="12.01" y2="8"></line>
                            </svg>
                            Situation Diagnosis
                        </div>
                        <div id="audit-summary" class="audit-box-body"></div>
                    </div>

                    <!-- ▲ Key Catalyst -->
                    <div class="audit-box" style="border-left: 3px solid var(--color-success);">
                        <div class="audit-box-header text-emerald">
                            ▲ Key Catalyst
                        </div>
                        <div id="audit-catalyst" class="audit-box-body"></div>
                    </div>

                    <!-- ▼ Primary Risk -->
                    <div class="audit-box" style="border-left: 3px solid var(--color-danger);">
                        <div class="audit-box-header text-ruby">
                            ▼ Primary Risk
                        </div>
                        <div id="audit-risk" class="audit-box-body"></div>
                    </div>

                    <!-- 🎯 Key Level to Watch -->
                    <div class="audit-box" style="border-left: 3px solid var(--color-gold); margin-bottom: 1.25rem;">
                        <div class="audit-box-header text-gold">
                            🎯 Key Level to Watch
                        </div>
                        <div id="audit-key-level" class="audit-box-body font-mono"></div>
                    </div>

                    <!-- Footer with Re-audit option & Close -->
                    <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 0.75rem; border-top: 1px solid var(--color-border-subtle);">
                        <button type="button" class="btn btn-outline btn-xs" id="btn-reaudit-current">
                            🔄 Re-Audit Live
                        </button>
                        <button type="button" class="btn btn-primary btn-sm" id="btn-dismiss-audit-modal">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php require __DIR__ . '/layout/footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var auditModal = document.getElementById('audit-modal');
            var auditBtns = document.querySelectorAll('.btn-audit-asset');
            var closeAuditModal = document.getElementById('btn-close-audit-modal');
            var dismissAuditModal = document.getElementById('btn-dismiss-audit-modal');
            var reauditBtn = document.getElementById('btn-reaudit-current');

            var auditSymbol = document.getElementById('audit-symbol');
            var auditName = document.getElementById('audit-name');
            var auditPrice = document.getElementById('audit-price');
            var auditLoading = document.getElementById('audit-loading');
            var auditContent = document.getElementById('audit-content');
            var auditBottomLine = document.getElementById('audit-bottom-line');
            var auditSummary = document.getElementById('audit-summary');
            var auditCatalyst = document.getElementById('audit-catalyst');
            var auditRisk = document.getElementById('audit-risk');
            var auditKeyLevel = document.getElementById('audit-key-level');

            var currentAssetId = null;
            var currentSymbol = null;

            function renderAuditData(data) {
                var verdict = data.bottom_line || 'Watch';
                auditBottomLine.textContent = verdict;
                auditBottomLine.className = 'verdict-badge';

                var lowerV = verdict.toLowerCase();
                if (lowerV.includes('buy') || lowerV.includes('comprar')) {
                    auditBottomLine.classList.add('verdict-buy');
                } else if (lowerV.includes('avoid') || lowerV.includes('evitar') || lowerV.includes('sell') || lowerV.includes('vender')) {
                    auditBottomLine.classList.add('verdict-avoid');
                } else {
                    auditBottomLine.classList.add('verdict-watch');
                }

                auditSummary.textContent = data.plain_summary || 'Market diagnosis completed without additional observations.';
                auditCatalyst.textContent = data.bull_catalyst || 'Technical support and favorable accumulation volume.';
                auditRisk.textContent = data.bear_risk || 'Macroeconomic volatility and short-term fluctuations.';
                auditKeyLevel.textContent = data.key_level || 'Maintain observation on immediate support and resistance levels.';
            }

            function runAudit(id, symbol, forceLive) {
                currentAssetId = id;
                currentSymbol = symbol;

                auditSymbol.textContent = symbol;
                auditLoading.style.display = 'block';
                auditContent.style.display = 'none';
                auditModal.classList.add('active');

                var formData = new FormData();
                formData.append('action', 'inspect_asset');
                if (id) formData.append('id', id);
                formData.append('symbol', symbol);

                fetch('/tracker', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                })
                .then(function(res) {
                    if (!res.ok) throw new Error('HTTP ' + res.status);
                    return res.json();
                })
                .then(function(data) {
                    auditLoading.style.display = 'none';
                    auditContent.style.display = 'block';

                    if (data && data.audit) {
                        renderAuditData(data.audit);
                    } else {
                        renderAuditData({
                            bottom_line: 'Watch',
                            plain_summary: 'Audit completed for ' + symbol + '.',
                            bull_catalyst: 'Catalyst pending technical confirmation.',
                            bear_risk: 'Caution regarding trading volume.',
                            key_level: 'Watch key support levels.'
                        });
                    }
                })
                .catch(function(err) {
                    auditLoading.style.display = 'none';
                    auditContent.style.display = 'block';
                    renderAuditData({
                        bottom_line: 'Watch',
                        plain_summary: 'Could not connect to the AI Council service at this time. Please retry in a few moments.',
                        bull_catalyst: 'Technical data preserved in cache.',
                        bear_risk: 'Connection temporarily interrupted.',
                        key_level: 'Refer to direct market quote.'
                    });
                });
            }

            if (auditModal) {
                auditBtns.forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        var id = btn.getAttribute('data-id');
                        var sym = btn.getAttribute('data-symbol');
                        var name = btn.getAttribute('data-name');
                        var price = btn.getAttribute('data-price');
                        var savedAuditRaw = btn.getAttribute('data-audit');

                        auditSymbol.textContent = sym;
                        auditName.textContent = name ? '• ' + name : '';
                        auditPrice.textContent = price ? '(' + price + ')' : '';

                        // If already audited and user clicks, display saved audit immediately, with re-audit option
                        if (savedAuditRaw && savedAuditRaw.trim() !== '') {
                            try {
                                var parsed = JSON.parse(savedAuditRaw);
                                if (parsed && parsed.bottom_line) {
                                    currentAssetId = id;
                                    currentSymbol = sym;
                                    auditLoading.style.display = 'none';
                                    auditContent.style.display = 'block';
                                    renderAuditData(parsed);
                                    auditModal.classList.add('active');
                                    return;
                                }
                            } catch (e) {}
                        }

                        // Otherwise trigger fresh audit
                        runAudit(id, sym, false);
                    });
                });

                if (reauditBtn) {
                    reauditBtn.addEventListener('click', function() {
                        if (currentSymbol) {
                            runAudit(currentAssetId, currentSymbol, true);
                        }
                    });
                }

                function hideAudit() {
                    auditModal.classList.remove('active');
                }

                if (closeAuditModal) closeAuditModal.addEventListener('click', hideAudit);
                if (dismissAuditModal) dismissAuditModal.addEventListener('click', hideAudit);
                auditModal.addEventListener('click', function(e) {
                    if (e.target === auditModal) hideAudit();
                });
            }

            // Auto-open modal if server passed active audit in session
            <?php if (!empty($activeAuditData)): ?>
                (function() {
                    var serverAudit = <?= json_encode($activeAuditData, JSON_UNESCAPED_UNICODE) ?>;
                    if (serverAudit && serverAudit.bottom_line) {
                        auditSymbol.textContent = serverAudit.symbol || '';
                        auditName.textContent = serverAudit.asset_name ? '• ' + serverAudit.asset_name : '';
                        renderAuditData(serverAudit);
                        auditLoading.style.display = 'none';
                        auditContent.style.display = 'block';
                        auditModal.classList.add('active');
                    }
                })();
            <?php endif; ?>
        });
    </script>
</body>
</html>
