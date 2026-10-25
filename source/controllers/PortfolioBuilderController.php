<?php
/**
 * Portfolio Builder Controller - Institutional Wealth Management
 * 
 * Handles the interactive 2-phase portfolio generation flow:
 * 1. Prompt input & AI Council scouting via MarketHunterService
 * 2. Proposal review, customization & persistence via PortfolioRepository
 */

require_once dirname(__DIR__) . '/repositories/PortfolioRepository.php';
require_once dirname(__DIR__) . '/services/PermissionService.php';
require_once dirname(__DIR__) . '/services/MarketHunterService.php';

class PortfolioBuilderController {
    public static function handle(): void {
        // Enforce authentication
        if (empty($_SESSION['user'])) {
            $_SESSION['redirect_after_login'] = '/portfolio-builder';
            header('Location: /login', true, 302);
            exit;
        }

        $currentUser = $_SESSION['user'];

        // Enforce screen permission
        if (!PermissionService::canAccess($currentUser, 'portfolio-builder')) {
            http_response_code(403);
            echo "<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body><h1>403 Forbidden</h1><p>You do not have permission to access the Portfolio Builder.</p><p><a href='/'>Return to Dashboard</a></p></body></html>";
            exit;
        }

        $error = $_SESSION['flash_error'] ?? null;
        $success = $_SESSION['flash_success'] ?? null;
        unset($_SESSION['flash_error'], $_SESSION['flash_success']);

        $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $action = $_POST['action'] ?? null;

        // ---------------------------------------------------------------------
        // POST Action Handlers
        // ---------------------------------------------------------------------
        if ($requestMethod === 'POST') {
            // Action 1: Generate Proposal via MarketHunterService
            if ($action === 'generate_portfolio' || (!empty($_POST['user_prompt']) && empty($action))) {
                $userPrompt = trim($_POST['user_prompt'] ?? '');
                $rawBudget = trim($_POST['budget'] ?? $_POST['initial_capital'] ?? '');
                $budget = (float) str_replace([',', ' ', '€', '$'], '', $rawBudget);

                if ($userPrompt === '') {
                    $_SESSION['flash_error'] = 'Please provide a description for the portfolio.';
                    header('Location: /portfolio-builder', true, 302);
                    exit;
                }

                try {
                    $proposal = MarketHunterService::scout($userPrompt, $budget);
                    $proposal['user_prompt'] = $userPrompt;

                    $_SESSION['portfolio_draft'] = $proposal;
                    $_SESSION['flash_success'] = "The Multi-Agent Council has analyzed the market and structured your portfolio proposal!";
                    header('Location: /portfolio-builder', true, 302);
                    exit;
                } catch (Throwable $e) {
                    $_SESSION['flash_error'] = 'Error scouting the market: ' . $e->getMessage();
                    header('Location: /portfolio-builder', true, 302);
                    exit;
                }
            }

            // Action 2: Save Portfolio from Session Draft
            if ($action === 'save_portfolio') {
                $draft = $_SESSION['portfolio_draft'] ?? null;

                if (empty($draft) || empty($draft['items'])) {
                    $_SESSION['flash_error'] = 'There is no active draft proposal to save. Generate one first.';
                    header('Location: /portfolio-builder', true, 302);
                    exit;
                }

                $portfolioName = trim($_POST['portfolio_name'] ?? $draft['portfolio_name'] ?? '');
                if ($portfolioName === '') {
                    $portfolioName = 'Portfolio ' . date('M Y');
                }

                try {
                    $allocatedTotal = (float)($draft['allocated_total'] ?? 0.00);
                    $budget = (float)($draft['budget'] ?? $allocatedTotal);

                    // 1. Create main portfolio record
                    $portfolioId = PortfolioRepository::create([
                        'name'             => $portfolioName,
                        'user_prompt'      => $draft['user_prompt'] ?? '',
                        'strategy_summary' => $draft['strategy_summary'] ?? '',
                        'initial_capital'  => $allocatedTotal > 0 ? $allocatedTotal : $budget,
                        'current_value'    => $allocatedTotal > 0 ? $allocatedTotal : $budget,
                        'alert_status'     => 'normal',
                        'alert_message'    => null,
                        'time_horizon'     => $draft['time_horizon'] ?? 'mid_term',
                    ]);

                    // 2. Insert scouted portfolio items
                    foreach ($draft['items'] as $item) {
                        PortfolioRepository::addItem($portfolioId, [
                            'symbol'               => $item['symbol'],
                            'asset_name'           => $item['asset_name'],
                            'asset_type'           => $item['asset_type'] ?? 'stock',
                            'exchange'             => $item['exchange'] ?? 'NASDAQ',
                            'exchange_timezone'    => $item['exchange_timezone'] ?? 'America/New_York',
                            'current_price'        => $item['current_price'],
                            'suggested_shares'     => $item['suggested_shares'],
                            'suggested_price'      => $item['suggested_price'] ?? $item['current_price'],
                            'why_picked'           => $item['why_picked'],
                            'bull_thesis'          => $item['bull_thesis'] ?? null,
                            'bear_thesis'          => $item['bear_thesis'] ?? null,
                            'invalidation_trigger' => $item['invalidation_trigger'] ?? null,
                            'time_horizon'         => $item['time_horizon'] ?? ($draft['time_horizon'] ?? 'mid_term'),
                            'check_interval_hours' => $item['check_interval_hours'] ?? 24,
                            'item_status'          => 'suggested',
                        ]);
                    }

                    // 3. Clear draft and redirect to portfolios screen
                    unset($_SESSION['portfolio_draft']);
                    $_SESSION['flash_success'] = "Portfolio saved successfully!";
                    header('Location: /portfolios?id=' . $portfolioId, true, 302);
                    exit;
                } catch (Throwable $e) {
                    $_SESSION['flash_error'] = 'Error saving portfolio: ' . $e->getMessage();
                    header('Location: /portfolio-builder', true, 302);
                    exit;
                }
            }

            // Action 3: Discard Session Draft
            if ($action === 'discard_draft') {
                unset($_SESSION['portfolio_draft']);
                $_SESSION['flash_success'] = 'Draft discarded';
                header('Location: /portfolio-builder', true, 302);
                exit;
            }
        }

        // Active draft from session (if available)
        $draft = $_SESSION['portfolio_draft'] ?? null;

        require dirname(__DIR__) . '/views/portfolio_builder.php';
    }
}
