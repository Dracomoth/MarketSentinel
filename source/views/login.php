<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Institutional Sign In - MarketSentinel Wealth Advisory</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="auth-page">
    <div class="auth-wrapper">
        <div class="auth-card">
            <header class="auth-header">
                <div class="auth-crest-icon">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
                        <polyline points="2 17 12 22 22 17"></polyline>
                        <polyline points="2 12 12 17 22 12"></polyline>
                    </svg>
                </div>
                <h1>MarketSentinel</h1>
                <p>Institutional Wealth Advisory &amp; Surveillance Portal</p>
            </header>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="/login">
                <div class="form-group">
                    <label for="username">Operator ID or Corporate Email</label>
                    <input type="text" id="username" name="username" required autocomplete="username" placeholder="e.g. superadmin" autofocus>
                </div>

                <div class="form-group">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.45rem;">
                        <label for="password" style="margin-bottom: 0;">Access Passkey</label>
                        <span class="text-xs text-muted">256-Bit Encrypted</span>
                    </div>
                    <input type="password" id="password" name="password" required autocomplete="current-password" placeholder="••••••••••••">
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 0.5rem; padding: 0.75rem 1.25rem;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                        <polyline points="10 17 15 12 10 7"></polyline>
                        <line x1="15" y1="12" x2="3" y2="12"></line>
                    </svg>
                    Authenticate Session
                </button>
            </form>

            <div class="auth-security-notice">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                </svg>
                <span>Authorized Financial Operators Only &bull; Strict Audit Logged</span>
            </div>
        </div>

        <footer class="auth-footer">
            <span>Framework: <?= htmlspecialchars($systemVersion ?? '1.0.0', ENT_QUOTES, 'UTF-8') ?></span>
            <span class="dot">&bull;</span>
            <span>Schema: v<?= htmlspecialchars($databaseVersion ?? '1.0.0', ENT_QUOTES, 'UTF-8') ?></span>
            <span class="dot">&bull;</span>
            <span>Hostinger Cloud MySQL</span>
        </footer>
    </div>
</body>
</html>
