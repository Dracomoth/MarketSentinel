<?php
/**
 * Portfolios View - Institutional Wealth Management
 * 
 * Screen 2: My Portfolios (Portfolio List & Detailed Guardian View)
 * - Detail Mode (2-Column Architecture):
 *   * Left Column (~35%): AI Council Interactive Chat Orchestrator with structured action proposals.
 *   * Right Column (~65%): Metrics, Action Bar (Recalculate, Add Asset, Adjust Capital, Delete safeguard),
 *     and Advanced Holdings Table (Target Price, Stop Loss, Strategy Badges, Broker Fees, Buy/Sell/Delete/Edit/Inspect).
 * - List Mode: Institutional portfolio cards, attention beacons, live valuations, return rates.
 */

require_once dirname(__DIR__) . '/services/MarkdownService.php';

$isDetail = !empty($portfolio);
$pageTitle = ($isDetail ? htmlspecialchars($portfolio['name'], ENT_QUOTES, 'UTF-8') : "My Portfolios") . " - " . htmlspecialchars($appName ?? 'MarketSentinel', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        /* Breadcrumb navigation */
        .breadcrumbs-bar {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: var(--font-size-sm);
            color: var(--color-text-muted);
            margin-bottom: 0.75rem;
            flex-wrap: wrap;
        }
        .breadcrumbs-bar a {
            color: var(--color-primary-light);
            text-decoration: none;
            font-weight: 500;
        }
        .breadcrumbs-bar a:hover {
            text-decoration: underline;
        }
        .breadcrumb-sep {
            color: var(--color-text-dim);
        }

        /* Attention Beacon Badges */
        .beacon-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.25rem 0.65rem;
            border-radius: var(--radius-pill);
            font-size: var(--font-size-xs);
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
        .beacon-action {
            background: var(--color-warning-bg);
            border: 1px solid var(--color-warning-border);
            color: var(--color-warning);
        }
        .beacon-action .beacon-dot {
            background-color: var(--color-warning);
            box-shadow: 0 0 6px var(--color-warning);
        }
        .beacon-urgent {
            background: var(--color-danger-bg);
            border: 1px solid var(--color-danger-border);
            color: var(--color-danger);
        }
        .beacon-urgent .beacon-dot {
            background-color: var(--color-danger);
            box-shadow: 0 0 6px var(--color-danger);
            animation: pulse-dot 1.5s infinite;
        }
        @keyframes pulse-dot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.4; transform: scale(1.3); }
        }

        /* Container safety */
        .container.page-content {
            width: 100%;
            max-width: var(--container-max-width);
            box-sizing: border-box;
            overflow-x: clip;
        }

        /* Detail Hero Section */
        .detail-hero {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
            margin-bottom: 1.25rem;
            flex-wrap: wrap;
            width: 100%;
            min-width: 0;
        }
        .detail-hero h1 {
            word-break: break-word;
            font-size: 1.625rem;
        }

        /* 2-Column Detail Layout with Guaranteed Containment */
        .portfolio-detail-layout {
            display: grid;
            grid-template-columns: minmax(320px, 380px) minmax(0, 1fr);
            gap: 1.5rem;
            align-items: start;
            margin-bottom: 2.5rem;
            width: 100%;
            max-width: 100%;
            min-width: 0;
        }
        @media (max-width: 1280px) {
            .portfolio-detail-layout {
                grid-template-columns: minmax(300px, 340px) minmax(0, 1fr);
                gap: 1.25rem;
            }
        }
        @media (max-width: 1100px) {
            .portfolio-detail-layout {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
        }

        /* Right Column: Portfolio Main Panel */
        .portfolio-main-panel {
            min-width: 0;
            max-width: 100%;
            width: 100%;
            display: flex;
            flex-direction: column;
        }

        /* Left Column: AI Council Chat Panel (Sticky & Viewport Constrained) */
        .council-panel {
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-card);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            position: sticky;
            top: 1.25rem;
            max-height: calc(100vh - 4.5rem);
            min-width: 0;
            max-width: 100%;
        }
        @media (max-width: 1100px) {
            .council-panel {
                position: static;
                max-height: none;
            }
        }
        .council-panel-header {
            padding: 0.85rem 1.15rem;
            background: var(--color-surface-elevated);
            border-bottom: 1px solid var(--color-border-subtle);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .council-panel-header h3 {
            font-size: 0.95rem;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--color-text);
        }
        .council-roster-tag {
            font-size: 0.68rem;
            color: var(--color-gold);
            background: rgba(245, 158, 11, 0.1);
            border: 1px solid rgba(245, 158, 11, 0.25);
            padding: 0.15rem 0.5rem;
            border-radius: var(--radius-pill);
            font-weight: 600;
        }
        .council-stream {
            padding: 0.85rem;
            height: auto;
            max-height: calc(100vh - 275px);
            min-height: 280px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 0.85rem;
            background: var(--color-bg);
            scroll-behavior: smooth;
        }
        @media (max-width: 1100px) {
            .council-stream {
                max-height: 480px;
            }
        }
        .chat-bubble {
            max-width: 92%;
            padding: 0.75rem 0.95rem;
            border-radius: var(--radius-md);
            font-size: var(--font-size-sm);
            line-height: 1.45;
            position: relative;
            word-break: break-word;
        }
        .chat-bubble-user {
            align-self: flex-end;
            background: rgba(59, 130, 246, 0.15);
            border: 1px solid rgba(59, 130, 246, 0.35);
            color: var(--color-text);
            border-bottom-right-radius: 2px;
        }
        .chat-bubble-user .chat-meta {
            color: var(--color-primary-light);
        }
        .chat-bubble-assistant {
            align-self: flex-start;
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            color: var(--color-text);
            border-bottom-left-radius: 2px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.25);
        }
        .chat-bubble-assistant .chat-meta {
            color: var(--color-gold);
        }
        .chat-bubble-system {
            align-self: center;
            max-width: 95%;
            background: var(--color-surface-elevated);
            border: 1px dashed var(--color-border);
            color: var(--color-text-muted);
            font-size: var(--font-size-xs);
            text-align: center;
            padding: 0.4rem 0.75rem;
        }
        .chat-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.68rem;
            font-weight: 600;
            margin-bottom: 0.35rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .chat-content {
            color: var(--color-text-secondary);
        }
        .chat-content p {
            margin: 0 0 0.5rem 0;
        }
        .chat-content p:last-child {
            margin-bottom: 0;
        }

        /* Action Proposal Cards inside Chat */
        .action-card {
            margin-top: 0.75rem;
            background: var(--color-surface-elevated);
            border: 1px solid rgba(245, 158, 11, 0.4);
            border-radius: var(--radius-md);
            padding: 0.75rem 0.85rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }
        .action-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.4rem;
        }
        .action-badge {
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            padding: 0.15rem 0.45rem;
            border-radius: 3px;
            background: rgba(245, 158, 11, 0.2);
            color: var(--color-gold);
            border: 1px solid rgba(245, 158, 11, 0.4);
        }
        .action-summary {
            font-size: var(--font-size-xs);
            font-weight: 600;
            color: var(--color-text);
            margin-bottom: 0.5rem;
            line-height: 1.35;
        }
        .action-meta-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.35rem;
            font-size: 0.72rem;
            color: var(--color-text-muted);
            margin-bottom: 0.65rem;
            background: var(--color-surface-sunken);
            padding: 0.45rem;
            border-radius: 4px;
        }
        .action-meta-grid span strong {
            color: var(--color-text);
        }
        .action-executed-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--color-success);
            padding: 0.35rem 0.65rem;
            background: var(--color-success-bg);
            border: 1px solid var(--color-success-border);
            border-radius: var(--radius-sm);
            width: 100%;
            justify-content: center;
        }

        /* Consensus Verdict Straight Answer Card */
        .consensus-verdict-box {
            background: rgba(245, 158, 11, 0.07);
            border: 1px solid rgba(245, 158, 11, 0.35);
            border-left: 4px solid var(--color-gold);
            border-radius: var(--radius-md);
            padding: 0.75rem 0.85rem;
            margin-bottom: 0.85rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.25);
        }
        .verdict-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 0.45rem;
            border-bottom: 1px solid rgba(245, 158, 11, 0.2);
            padding-bottom: 0.35rem;
        }
        .verdict-badge {
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--color-gold);
            background: rgba(245, 158, 11, 0.2);
            padding: 0.15rem 0.5rem;
            border-radius: var(--radius-pill);
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }
        .verdict-title {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--color-text-muted);
            text-transform: uppercase;
        }
        .verdict-body {
            font-size: var(--font-size-sm);
            line-height: 1.45;
            color: var(--color-text);
            font-weight: 500;
        }

        /* Specialized Agent Groupboxes (Collapsible & Color-Themed) */
        details.agent-box {
            margin-top: 0.55rem;
            margin-bottom: 0.55rem;
            border-radius: var(--radius-md);
            border: 1px solid var(--color-border);
            background: var(--color-surface-elevated);
            overflow: hidden;
            box-shadow: 0 2px 6px rgba(0,0,0,0.18);
            transition: all var(--transition-fast);
        }
        details.agent-box:last-child {
            margin-bottom: 0.25rem;
        }
        details.agent-box summary.agent-box-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.45rem 0.75rem;
            font-size: 0.72rem;
            font-weight: 700;
            cursor: pointer;
            user-select: none;
            list-style: none;
            transition: filter var(--transition-fast), background-color var(--transition-fast);
        }
        details.agent-box summary.agent-box-header::-webkit-details-marker {
            display: none;
        }
        details.agent-box summary.agent-box-header:hover {
            filter: brightness(1.15);
        }
        details.agent-box:not([open]) summary.agent-box-header {
            border-bottom: none;
        }
        details.agent-box[open] summary.agent-box-header {
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }
        .agent-box-chevron {
            display: inline-block;
            font-size: 0.62rem;
            transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            opacity: 0.75;
            margin-right: 0.35rem;
            width: 10px;
            text-align: center;
            line-height: 1;
        }
        details.agent-box[open] .agent-box-chevron {
            transform: rotate(90deg);
        }
        .agent-box-title {
            display: flex;
            align-items: center;
            gap: 0.35rem;
        }
        .agent-box-icon {
            font-size: 0.85rem;
        }
        .agent-box-name {
            letter-spacing: 0.02em;
        }
        .agent-box-header-right {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
        }
        .agent-box-badge {
            font-size: 0.62rem;
            font-weight: 600;
            text-transform: uppercase;
            padding: 0.1rem 0.4rem;
            border-radius: var(--radius-pill);
            opacity: 0.9;
        }
        .agent-box-body {
            padding: 0.65rem 0.85rem;
            font-size: var(--font-size-xs);
            line-height: 1.45;
            color: var(--color-text-secondary);
            animation: agent-body-fade-in 0.2s ease-in-out;
        }
        @keyframes agent-body-fade-in {
            from {
                opacity: 0;
                transform: translateY(-4px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .agent-box-body p {
            margin: 0 0 0.4rem 0;
        }
        .agent-box-body p:last-child {
            margin-bottom: 0;
        }
        .agent-box-body ul, .agent-box-body ol {
            margin: 0.3rem 0;
            padding-left: 1.2rem;
        }

        /* 1. Macro Strategist: Sky Blue / Cyan */
        .agent-box-macro {
            border-color: rgba(56, 189, 248, 0.35);
            background: rgba(56, 189, 248, 0.04);
            border-left: 3px solid #38bdf8;
        }
        .agent-box-macro .agent-box-header {
            background: rgba(56, 189, 248, 0.12);
            color: #38bdf8;
        }
        .agent-box-macro .agent-box-badge {
            background: rgba(56, 189, 248, 0.2);
            color: #bae6fd;
            border: 1px solid rgba(56, 189, 248, 0.3);
        }

        /* 2. Fundamental Auditor: Emerald Green */
        .agent-box-fundamental {
            border-color: rgba(16, 185, 129, 0.35);
            background: rgba(16, 185, 129, 0.04);
            border-left: 3px solid #10b981;
        }
        .agent-box-fundamental .agent-box-header {
            background: rgba(16, 185, 129, 0.12);
            color: #10b981;
        }
        .agent-box-fundamental .agent-box-badge {
            background: rgba(16, 185, 129, 0.2);
            color: #a7f3d0;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        /* 3. Technical Quant: Violet / Purple */
        .agent-box-quant, .agent-box-technical {
            border-color: rgba(168, 85, 247, 0.35);
            background: rgba(168, 85, 247, 0.04);
            border-left: 3px solid #a855f7;
        }
        .agent-box-quant .agent-box-header, .agent-box-technical .agent-box-header {
            background: rgba(168, 85, 247, 0.12);
            color: #c084fc;
        }
        .agent-box-quant .agent-box-badge, .agent-box-technical .agent-box-badge {
            background: rgba(168, 85, 247, 0.2);
            color: #e9d5ff;
            border: 1px solid rgba(168, 85, 247, 0.3);
        }

        /* 4. Bull & Bear Debate: Amber / Gold */
        .agent-box-debate, .agent-box-bull_bear, .agent-box-bull-bear {
            border-color: rgba(245, 158, 11, 0.35);
            background: rgba(245, 158, 11, 0.04);
            border-left: 3px solid #f59e0b;
        }
        .agent-box-debate .agent-box-header, .agent-box-bull_bear .agent-box-header, .agent-box-bull-bear .agent-box-header {
            background: rgba(245, 158, 11, 0.12);
            color: #fbbf24;
        }
        .agent-box-debate .agent-box-badge, .agent-box-bull_bear .agent-box-badge, .agent-box-bull-bear .agent-box-badge {
            background: rgba(245, 158, 11, 0.2);
            color: #fde68a;
            border: 1px solid rgba(245, 158, 11, 0.3);
        }

        /* 5. Chief Risk Arbiter: Crimson / Red */
        .agent-box-risk, .agent-box-arbiter {
            border-color: rgba(239, 68, 68, 0.35);
            background: rgba(239, 68, 68, 0.04);
            border-left: 3px solid #ef4444;
        }
        .agent-box-risk .agent-box-header, .agent-box-arbiter .agent-box-header {
            background: rgba(239, 68, 68, 0.12);
            color: #f87171;
        }
        .agent-box-risk .agent-box-badge, .agent-box-arbiter .agent-box-badge {
            background: rgba(239, 68, 68, 0.2);
            color: #fecaca;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        /* Default fallback agent box */
        .agent-box-default {
            border-color: rgba(148, 163, 184, 0.35);
            background: rgba(148, 163, 184, 0.04);
            border-left: 3px solid #94a3b8;
        }
        .agent-box-default .agent-box-header {
            background: rgba(148, 163, 184, 0.12);
            color: #cbd5e1;
        }

        /* Live Animated Typing Loader Bubble */
        .typing-loader-bubble {
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-bottom-left-radius: 2px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            max-width: 85%;
        }
        .typing-indicator-container {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            padding: 0.25rem 0.15rem;
        }
        .typing-dots {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 0.2rem 0.35rem;
            background: rgba(245, 158, 11, 0.08);
            border-radius: var(--radius-pill);
            border: 1px solid rgba(245, 158, 11, 0.2);
        }
        .typing-dot {
            width: 7px;
            height: 7px;
            background: var(--color-gold);
            border-radius: 50%;
            display: inline-block;
            animation: typing-bounce 1.4s infinite ease-in-out both;
        }
        .typing-dot:nth-child(1) {
            animation-delay: -0.32s;
        }
        .typing-dot:nth-child(2) {
            animation-delay: -0.16s;
        }
        .typing-dot:nth-child(3) {
            animation-delay: 0s;
        }
        @keyframes typing-bounce {
            0%, 80%, 100% {
                transform: scale(0.6);
                opacity: 0.35;
            }
            40% {
                transform: scale(1.15);
                opacity: 1;
            }
        }
        .typing-label {
            font-size: 0.72rem;
            color: var(--color-text-muted);
            font-style: italic;
        }

        .council-input-box {
            padding: 0.85rem 1rem;
            background: var(--color-surface-elevated);
            border-top: 1px solid var(--color-border-subtle);
        }
        .council-input-box textarea {
            width: 100%;
            height: 68px;
            background: var(--color-bg);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            color: var(--color-text);
            padding: 0.5rem 0.75rem;
            font-size: var(--font-size-sm);
            resize: none;
            font-family: inherit;
        }
        .council-input-box textarea:focus {
            outline: none;
            border-color: var(--color-primary-light);
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
        }
        .council-input-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 0.5rem;
        }

        /* Top Action Bar Buttons */
        .detail-action-bar {
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            padding: 0.75rem 1.25rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        /* Strategy Badges */
        .strategy-badge {
            display: inline-block;
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            padding: 0.2rem 0.5rem;
            border-radius: var(--radius-pill);
            letter-spacing: 0.03em;
        }
        .strategy-buy { background: rgba(16, 185, 129, 0.15); color: var(--color-success); border: 1px solid rgba(16, 185, 129, 0.35); }
        .strategy-hold { background: rgba(59, 130, 246, 0.15); color: var(--color-primary-light); border: 1px solid rgba(59, 130, 246, 0.35); }
        .strategy-sell { background: rgba(239, 68, 68, 0.15); color: var(--color-danger); border: 1px solid rgba(239, 68, 68, 0.35); }
        .strategy-watch { background: rgba(168, 85, 247, 0.15); color: #c084fc; border: 1px solid rgba(168, 85, 247, 0.35); }
        .strategy-rebalance { background: rgba(245, 158, 11, 0.15); color: var(--color-gold); border: 1px solid rgba(245, 158, 11, 0.35); }

        /* Target & Stop Chips */
        .risk-metric-box {
            font-size: var(--font-size-xs);
            font-family: var(--font-mono);
            line-height: 1.25;
        }
        .risk-distance-positive { color: var(--color-success); font-size: 0.68rem; font-weight: 600; }
        .risk-distance-danger { color: var(--color-danger); font-size: 0.68rem; font-weight: 600; }

        /* Disabled Deletion Safeguard Tooltip */
        .btn-disabled-wrapper {
            position: relative;
            display: inline-block;
        }
        .btn-disabled-wrapper[data-tooltip]:hover::after {
            content: attr(data-tooltip);
            position: absolute;
            bottom: 125%;
            right: 0;
            background: #1e293b;
            color: #f1f5f9;
            padding: 0.4rem 0.75rem;
            border-radius: 4px;
            font-size: 0.75rem;
            white-space: nowrap;
            z-index: 100;
            border: 1px solid #475569;
            box-shadow: 0 4px 12px rgba(0,0,0,0.5);
            pointer-events: none;
        }

        /* Portfolio Cards Grid (List Mode) */
        .portfolios-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        @media (max-width: 900px) {
            .portfolios-grid {
                grid-template-columns: 1fr;
            }
        }
        .p-card {
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
        .p-card:hover {
            border-color: var(--color-border-hover);
            transform: translateY(-2px);
            box-shadow: var(--shadow-elevated);
        }
        .p-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .p-card-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--color-text);
            text-decoration: none;
            display: block;
        }
        .p-card-title:hover {
            color: var(--color-primary-light);
        }
        .p-card-meta {
            font-size: var(--font-size-xs);
            color: var(--color-text-muted);
            margin-top: 0.25rem;
        }
        .p-card-prompt {
            font-size: var(--font-size-xs);
            color: var(--color-text-secondary);
            background-color: var(--color-surface-sunken);
            border-left: 3px solid var(--color-primary);
            padding: 0.65rem 0.85rem;
            border-radius: 0 var(--radius-sm) var(--radius-sm) 0;
            margin-bottom: 1.25rem;
            line-height: 1.45;
            font-style: italic;
        }
        .p-card-metrics {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.75rem;
            margin-bottom: 1.25rem;
        }
        .m-box {
            background-color: var(--color-surface-elevated);
            border: 1px solid var(--color-border-subtle);
            border-radius: var(--radius-md);
            padding: 0.65rem 0.75rem;
            text-align: center;
        }
        .m-label {
            font-size: 0.68rem;
            color: var(--color-text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.25rem;
        }
        .m-val {
            font-size: 1.05rem;
            font-weight: 700;
            font-family: var(--font-mono);
            color: var(--color-text);
        }
        .p-card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 1rem;
            border-top: 1px solid var(--color-border-subtle);
        }

        /* Detail Mode Top Cards */
        .stats-grid-4 {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 0.85rem;
            margin-bottom: 1.25rem;
            width: 100%;
        }
        @media (max-width: 1366px) {
            .stats-grid-4 {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
        @media (max-width: 640px) {
            .stats-grid-4 {
                grid-template-columns: 1fr;
            }
        }
        .stat-card {
            background-color: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            padding: 1rem 1.15rem;
            box-shadow: var(--shadow-card);
            min-width: 0;
        }
        .stat-label {
            font-size: var(--font-size-xs);
            color: var(--color-text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.35rem;
        }
        .stat-value {
            font-size: 1.35rem;
            font-weight: 700;
            font-family: var(--font-mono);
            color: var(--color-text);
            margin-bottom: 0.2rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .stat-sub {
            font-size: var(--font-size-xs);
            color: var(--color-text-secondary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Mandate Thesis Card */
        .mandate-card {
            background-color: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            padding: 1.25rem 1.5rem;
            box-shadow: var(--shadow-card);
            margin-bottom: 1.25rem;
            min-width: 0;
            max-width: 100%;
            word-break: break-word;
        }
        .mandate-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--color-border-subtle);
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .mandate-card-header h3 {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--color-text);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0;
        }
        .thesis-content-text {
            font-size: var(--font-size-sm);
            color: var(--color-text-secondary);
            line-height: 1.6;
            word-break: break-word;
            overflow-wrap: break-word;
        }

        /* Holdings Table Card */
        .holdings-card {
            background-color: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-card);
            overflow: hidden;
            margin-bottom: 1.5rem;
            min-width: 0;
            max-width: 100%;
            width: 100%;
        }
        .holdings-card-header {
            padding: 0.85rem 1.25rem;
            background-color: var(--color-surface-elevated);
            border-bottom: 1px solid var(--color-border-subtle);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .holdings-card-header h3 {
            font-size: 1rem;
            font-weight: 700;
            color: var(--color-text);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .holdings-card .table-responsive {
            width: 100%;
            max-width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            display: block;
        }
        .holdings-card .table {
            width: 100%;
            min-width: 820px;
            border-collapse: collapse;
        }
        .holdings-card .table th {
            padding: 0.75rem 0.65rem;
            font-size: 0.68rem;
            letter-spacing: 0.05em;
            white-space: nowrap;
        }
        .holdings-card .table td {
            padding: 0.75rem 0.65rem;
            font-size: var(--font-size-xs);
            white-space: nowrap;
        }
        .holdings-card .table td.risk-metric-box {
            padding: 0.75rem 0.5rem;
        }
        .table-actions-cell {
            white-space: nowrap;
        }
        .action-btn-group {
            display: inline-flex;
            gap: 0.25rem;
            justify-content: flex-end;
            align-items: center;
        }
        .holdings-card .btn-xs {
            padding: 0.22rem 0.45rem;
            font-size: 0.68rem;
            line-height: 1.2;
        }

        /* Modals */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(4px);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        .modal-overlay.active {
            display: flex;
        }
        .modal-window {
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            width: 100%;
            max-width: 500px;
            padding: 1.5rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5);
            max-height: 90vh;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <?php require __DIR__ . '/layout/header.php'; ?>
    <?php require __DIR__ . '/layout/sidebar.php'; ?>

    <main class="container page-content">
        <!-- Flash Alerts -->
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

        <?php if ($isDetail): ?>
            <!-- =============================================================== -->
            <!-- 1. DETAIL MODE: 2-COLUMN INSTITUTIONAL ARCHITECTURE             -->
            <!-- =============================================================== -->

            <!-- Breadcrumbs -->
            <div class="breadcrumbs-bar">
                <a href="/portfolios">My Portfolios</a>
                <span class="breadcrumb-sep">&gt;</span>
                <span class="text-white font-semibold"><?= htmlspecialchars($portfolio['name'], ENT_QUOTES, 'UTF-8') ?></span>
                <span class="breadcrumb-sep">•</span>
                <span>Mandate #<?= (int) $portfolio['id'] ?></span>
            </div>

            <!-- Detail Hero -->
            <div class="detail-hero" style="margin-bottom: 1.25rem;">
                <div>
                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.35rem; flex-wrap: wrap;">
                        <span class="sublink-order-badge" style="text-transform: uppercase;">
                            <?= htmlspecialchars(str_replace('_', ' ', $portfolio['time_horizon'] ?? 'mid_term'), ENT_QUOTES, 'UTF-8') ?>
                        </span>

                        <?php if ($portfolio['alert_status'] === 'urgent'): ?>
                            <span class="beacon-badge beacon-urgent"><span class="beacon-dot"></span> Urgent</span>
                        <?php elseif ($portfolio['alert_status'] === 'action_needed'): ?>
                            <span class="beacon-badge beacon-action"><span class="beacon-dot"></span> Attention Required</span>
                        <?php else: ?>
                            <span class="beacon-badge beacon-normal"><span class="beacon-dot"></span> Normal / Monitored</span>
                        <?php endif; ?>
                    </div>
                    <h1><?= htmlspecialchars($portfolio['name'], ENT_QUOTES, 'UTF-8') ?></h1>
                    <p class="text-muted">Mandate active since <?= date('M d, Y', strtotime($portfolio['created'])) ?> • Multi-Agent Surveillance & Interactive Advisory</p>
                </div>

                <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
                    <a href="/portfolio-builder" class="btn btn-outline btn-sm">
                        + New Portfolio
                    </a>
                </div>
            </div>

            <!-- Two-Column Grid: Left Column (Chat Orchestrator) | Right Column (Metrics & Grid) -->
            <div class="portfolio-detail-layout">
                
                <!-- ----------------------------------------------------------- -->
                <!-- LEFT COLUMN: AI Council Chat Orchestrator (35% Width)       -->
                <!-- ----------------------------------------------------------- -->
                <aside class="council-panel">
                    <div class="council-panel-header">
                        <h3>
                            <span>🧠</span>
                            Council Orchestrator
                        </h3>
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <span class="council-roster-tag">5 Agents</span>
                            <form method="POST" action="/portfolios" id="clear-chat-form" onsubmit="return confirm('Are you sure you want to clear the entire Council conversation history for this portfolio?');" style="margin: 0; display: inline-flex;">
                                <input type="hidden" name="action" value="clear_council_chat">
                                <input type="hidden" name="portfolio_id" value="<?= (int) $portfolio['id'] ?>">
                                <button type="submit" class="btn btn-outline btn-xs text-muted" style="border-color: var(--color-border); padding: 0.18rem 0.55rem; font-size: 0.68rem; display: inline-flex; align-items: center; gap: 0.3rem;" title="Clear conversation history">
                                    <span>🗑️</span> Clear Chat
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Chat Message Stream -->
                    <div class="council-stream" id="chat-stream">
                        <?php if (empty($chatHistory)): ?>
                            <div class="chat-bubble chat-bubble-system">
                                Institutional Council online. Reviewing portfolio positions, risk tolerances, and execution limits. Ask a question or request a strategy rebalance below.
                            </div>
                        <?php else: ?>
                            <?php foreach ($chatHistory as $msg): ?>
                                <?php 
                                $role = $msg['role'] ?? 'user'; 
                                $createdTime = !empty($msg['created']) ? date('H:i', strtotime($msg['created'])) : '';
                                ?>
                                <?php if ($role === 'system'): ?>
                                    <div class="chat-bubble chat-bubble-system">
                                        <?= htmlspecialchars($msg['message'], ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                <?php elseif ($role === 'user'): ?>
                                    <div class="chat-bubble chat-bubble-user">
                                        <div class="chat-meta">
                                            <span>Investor Directive</span>
                                            <span><?= $createdTime ?></span>
                                        </div>
                                        <div class="chat-content">
                                            <?= nl2br(htmlspecialchars($msg['message'], ENT_QUOTES, 'UTF-8')) ?>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="chat-bubble chat-bubble-assistant">
                                        <div class="chat-meta">
                                            <span>Council Synthesis</span>
                                            <span><?= $createdTime ?></span>
                                        </div>
                                        <div class="chat-content">
                                            <?= MarkdownService::render($msg['message']) ?>
                                        </div>

                                        <!-- Structured Action Card (Proposal requiring confirmation) -->
                                        <?php if (!empty($msg['structured_action']) && is_array($msg['structured_action'])): ?>
                                            <?php 
                                            $act = $msg['structured_action']; 
                                            $actType = $act['action_type'] ?? 'proposal';
                                            $actSummary = $act['summary'] ?? 'Action proposed by Council';
                                            $isExecuted = !empty($act['executed']);
                                            $payload = $act['payload'] ?? [];
                                            ?>
                                            <div class="action-card">
                                                <div class="action-card-header">
                                                    <span class="action-badge"><?= htmlspecialchars(str_replace('_', ' ', $actType), ENT_QUOTES, 'UTF-8') ?></span>
                                                    <span style="font-size: 0.68rem; color: var(--color-gold);">Requires Approval</span>
                                                </div>
                                                <div class="action-summary">
                                                    <?= htmlspecialchars($actSummary, ENT_QUOTES, 'UTF-8') ?>
                                                </div>

                                                <!-- Payload Details -->
                                                <div class="action-meta-grid">
                                                    <?php if (!empty($payload['symbol'])): ?>
                                                        <span>Ticker: <strong><?= htmlspecialchars($payload['symbol'], ENT_QUOTES, 'UTF-8') ?></strong></span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($payload['target_price'])): ?>
                                                        <span>Target: <strong>$<?= number_format((float)$payload['target_price'], 2) ?></strong></span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($payload['stop_loss_price'])): ?>
                                                        <span>Stop-Loss: <strong>$<?= number_format((float)$payload['stop_loss_price'], 2) ?></strong></span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($payload['asset_strategy'])): ?>
                                                        <span>Strategy: <strong><?= strtoupper(htmlspecialchars($payload['asset_strategy'], ENT_QUOTES, 'UTF-8')) ?></strong></span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($payload['new_capital'])): ?>
                                                        <span>New Capital: <strong>$<?= number_format((float)$payload['new_capital'], 2) ?></strong></span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($payload['trade_type'])): ?>
                                                        <span>Trade: <strong><?= strtoupper($payload['trade_type']) ?> <?= (int)($payload['shares'] ?? 0) ?> shares</strong></span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($payload['price'])): ?>
                                                        <span>Price: <strong>$<?= number_format((float)$payload['price'], 2) ?></strong></span>
                                                    <?php endif; ?>
                                                </div>

                                                <?php if ($isExecuted): ?>
                                                    <div class="action-executed-badge">
                                                        ✓ Executed on <?= date('M d, H:i', strtotime($act['executed_at'] ?? 'now')) ?>
                                                    </div>
                                                <?php else: ?>
                                                    <form method="POST" action="/portfolios" style="margin: 0;">
                                                        <input type="hidden" name="action" value="execute_chat_action">
                                                        <input type="hidden" name="portfolio_id" value="<?= (int) $portfolio['id'] ?>">
                                                        <input type="hidden" name="message_id" value="<?= (int) $msg['id'] ?>">
                                                        <button type="submit" class="btn btn-primary btn-xs btn-block" style="width: 100%; font-weight: 600;">
                                                            ⚡ Confirm & Execute Action
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Input Box -->
                    <div class="council-input-box">
                        <form method="POST" action="/portfolios" id="council-chat-form" style="margin: 0;">
                            <input type="hidden" name="action" value="send_council_chat">
                            <input type="hidden" name="portfolio_id" value="<?= (int) $portfolio['id'] ?>">
                            <textarea name="message" id="council-msg-input" placeholder="Ask Council (e.g. 'What is our risk floor on GPUS?' or 'Draft a buy of 20 shares of LUCD')..." required></textarea>
                            <div class="council-input-actions">
                                <span class="text-xs text-muted" id="chat-typing-status" style="font-size: 0.7rem;">Enter to ask Council</span>
                                <button type="submit" class="btn btn-primary btn-xs" id="btn-send-chat" style="font-weight: 600;">
                                    Send Directive &rarr;
                                </button>
                            </div>
                        </form>
                    </div>
                </aside>

                <!-- ----------------------------------------------------------- -->
                <!-- RIGHT COLUMN: Metrics, Action Bar & Holdings Grid (65%)     -->
                <!-- ----------------------------------------------------------- -->
                <section class="portfolio-main-panel">
                    
                    <!-- Top Summary Bar (4 Primary Financial Cards) -->
                    <div class="stats-grid-4">
                        <div class="stat-card">
                            <div class="stat-label">Initial Capital</div>
                            <div class="stat-value">$<?= number_format($metrics['initial_capital'], 2) ?></div>
                            <div class="stat-sub">Mandate budget</div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-label">Current Value</div>
                            <div class="stat-value">$<?= number_format($metrics['current_value'], 2) ?></div>
                            <div class="stat-sub">Holdings + cash buffer</div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-label">Net Profit/Loss</div>
                            <div class="stat-value <?= $metrics['net_pnl_dollar'] >= 0 ? 'text-emerald' : 'text-ruby' ?>">
                                <?= $metrics['net_pnl_dollar'] >= 0 ? '+' : '' ?>$<?= number_format($metrics['net_pnl_dollar'], 2) ?>
                            </div>
                            <div class="stat-sub">Across executed positions</div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-label">Return Rate</div>
                            <div class="stat-value <?= $metrics['net_return_percent'] >= 0 ? 'text-emerald' : 'text-ruby' ?>">
                                <?= $metrics['net_return_percent'] >= 0 ? '+' : '' ?><?= number_format($metrics['net_return_percent'], 2) ?>%
                            </div>
                            <div class="stat-sub"><?= $metrics['purchased_count'] ?> of <?= $metrics['total_items_count'] ?> assets bought</div>
                        </div>
                    </div>

                    <!-- Strategy Card (Mandate & Thesis) -->
                    <div class="mandate-card">
                        <div class="mandate-card-header">
                            <h3>
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
                                    <polyline points="2 17 12 22 22 17"></polyline>
                                    <polyline points="2 12 12 17 22 12"></polyline>
                                </svg>
                                Council Strategy & Thesis
                            </h3>
                            <span class="text-xs text-muted">Real-Time Market Grounding</span>
                        </div>

                        <div class="thesis-content-text">
                            <?= MarkdownService::render($portfolio['strategy_summary'] ?? '') ?>
                        </div>

                        <?php if (!empty($portfolio['user_prompt'])): ?>
                            <div style="margin-top: 0.85rem; padding-top: 0.85rem; border-top: 1px solid var(--color-border-subtle); font-size: var(--font-size-xs); color: var(--color-text-muted);">
                                <strong>Initial investor thesis:</strong> "<?= htmlspecialchars($portfolio['user_prompt'], ENT_QUOTES, 'UTF-8') ?>"
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($portfolio['alert_message'])): ?>
                            <div style="margin-top: 1rem; padding: 0.75rem 1rem; background: var(--color-warning-bg); border: 1px solid var(--color-warning-border); border-radius: var(--radius-md); font-size: var(--font-size-sm); color: var(--color-warning);">
                                <strong>Sentinel Guardian Notice:</strong> <?= htmlspecialchars($portfolio['alert_message'], ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Top Action Bar: Recalculate, Add Asset, Adjust Capital, Delete -->
                    <div class="detail-action-bar">
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <span class="text-xs text-secondary">
                                Available Cash Buffer: <strong class="font-mono text-gold">$<?= number_format($metrics['cash_buffer'], 2) ?></strong>
                            </span>
                        </div>

                        <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
                            <!-- Recalculate Strategy Form -->
                            <form method="POST" action="/portfolios" id="recalc-form" style="margin: 0;">
                                <input type="hidden" name="action" value="recalculate_strategy">
                                <input type="hidden" name="portfolio_id" value="<?= (int) $portfolio['id'] ?>">
                                <button type="submit" class="btn btn-primary btn-sm" id="btn-recalc" style="font-weight: 600;">
                                    ⚡ Recalculate Strategy
                                </button>
                            </form>

                            <!-- Add Asset Button -->
                            <button type="button" class="btn btn-secondary btn-sm" id="btn-open-add-asset" style="font-weight: 600;">
                                + Add Asset
                            </button>

                            <!-- Adjust Capital Button -->
                            <button type="button" class="btn btn-outline btn-sm" id="btn-open-adjust-capital" style="font-weight: 600;">
                                ⚙ Adjust Capital
                            </button>

                            <!-- Delete Portfolio Form (Safeguarded) -->
                            <?php if ($hasActivePositions): ?>
                                <div class="btn-disabled-wrapper" data-tooltip="Cannot delete portfolio with active holdings. Sell all shares first.">
                                    <button type="button" class="btn btn-outline btn-sm text-muted" style="border-color: rgba(239, 68, 68, 0.2); opacity: 0.5; cursor: not-allowed;" disabled>
                                        Delete Portfolio
                                    </button>
                                </div>
                            <?php else: ?>
                                <form method="POST" action="/portfolios" onsubmit="return confirm('Are you sure you want to permanently delete this portfolio?');" style="margin: 0;">
                                    <input type="hidden" name="action" value="delete_portfolio">
                                    <input type="hidden" name="id" value="<?= (int) $portfolio['id'] ?>">
                                    <button type="submit" class="btn btn-outline btn-sm text-ruby" style="border-color: rgba(239, 68, 68, 0.3);">
                                        Delete Portfolio
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Holdings & Position Deck Table -->
                    <div class="holdings-card">
                        <div class="holdings-card-header">
                            <h3>
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--color-primary-light);">
                                    <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                                    <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                                </svg>
                                Holdings & Execution Radar
                            </h3>
                            <span class="meta-label"><?= count($items) ?> Monitored Positions</span>
                        </div>

                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Asset</th>
                                        <th>Status</th>
                                        <th class="text-right">Live Price</th>
                                        <th class="text-right">Target Price</th>
                                        <th class="text-right">Stop-Loss</th>
                                        <th class="text-center">Strategy</th>
                                        <th class="text-right">Position Value</th>
                                        <th class="text-right">Fees Paid</th>
                                        <th class="text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($items)): ?>
                                        <tr>
                                            <td colspan="9" class="text-center text-muted" style="padding: 2.5rem 1.5rem;">
                                                No assets recorded in this portfolio. Use "+ Add Asset" above or ask the Council.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($items as $it): ?>
                                            <?php
                                            $isBought = !empty($it['is_purchased']) && (int)($it['purchase_shares'] ?? 0) > 0;
                                            $currPrice = (float) $it['current_price'];
                                            $quoteChange = (float) ($it['quote_change_pct'] ?? 0);
                                            $targetPrice = $it['target_price'] !== null ? (float)$it['target_price'] : null;
                                            $stopLossPrice = $it['stop_loss_price'] !== null ? (float)$it['stop_loss_price'] : null;
                                            $strategy = strtolower($it['asset_strategy'] ?? 'hold');
                                            $feesPaid = (float)($it['total_fees_paid'] ?? 0.0);
                                            ?>
                                            <tr>
                                                <td>
                                                    <div style="display: flex; align-items: baseline; gap: 0.45rem;">
                                                        <strong class="ticker-symbol" style="color: var(--color-primary-light); font-size: 1.05rem;">
                                                            <?= htmlspecialchars($it['symbol'], ENT_QUOTES, 'UTF-8') ?>
                                                        </strong>
                                                        <span class="text-xs text-muted"><?= htmlspecialchars($it['exchange'], ENT_QUOTES, 'UTF-8') ?></span>
                                                    </div>
                                                    <div class="text-xs text-secondary" style="margin-top: 0.15rem; max-width: 180px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                                        <?= htmlspecialchars($it['asset_name'], ENT_QUOTES, 'UTF-8') ?>
                                                    </div>
                                                </td>

                                                <td>
                                                    <?php if ($isBought): ?>
                                                        <span class="badge" style="background: var(--color-success-bg); border: 1px solid var(--color-success-border); color: var(--color-success); font-weight: 600;">
                                                            Holding ✓
                                                        </span>
                                                        <div class="text-xs text-muted font-mono" style="margin-top: 0.2rem;">
                                                            <?= (int) $it['purchase_shares'] ?> shs @ $<?= number_format((float) $it['purchase_price'], 2) ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="badge" style="background: var(--color-primary-bg); border: 1px solid var(--color-primary-border); color: var(--color-primary-light);">
                                                            Suggested
                                                        </span>
                                                        <div class="text-xs text-muted font-mono" style="margin-top: 0.2rem;">
                                                            Target: <?= (int) $it['suggested_shares'] ?> shs
                                                        </div>
                                                    <?php endif; ?>
                                                </td>

                                                <td class="text-right font-mono">
                                                    <strong>$<?= number_format($currPrice, $currPrice < 1.0 ? 4 : 2) ?></strong>
                                                    <div class="text-xs">
                                                        <?php if ($quoteChange != 0): ?>
                                                            <span class="<?= $quoteChange > 0 ? 'quote-chip-up' : 'quote-chip-down' ?>">
                                                                <?= $quoteChange > 0 ? '+' : '' ?><?= number_format($quoteChange, 2) ?>%
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="quote-chip-flat">0.00%</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>

                                                <!-- Target Price -->
                                                <td class="text-right risk-metric-box">
                                                    <?php if ($targetPrice !== null && $targetPrice > 0): ?>
                                                        <div>$<?= number_format($targetPrice, 2) ?></div>
                                                        <?php 
                                                        $diffTarget = $targetPrice - $currPrice;
                                                        $pctTarget = $currPrice > 0 ? ($diffTarget / $currPrice) * 100 : 0;
                                                        ?>
                                                        <div class="risk-distance-positive">
                                                            <?= $pctTarget >= 0 ? '+' : '' ?><?= number_format($pctTarget, 1) ?>%
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-muted">—</span>
                                                    <?php endif; ?>
                                                </td>

                                                <!-- Stop-Loss Floor -->
                                                <td class="text-right risk-metric-box">
                                                    <?php if ($stopLossPrice !== null && $stopLossPrice > 0): ?>
                                                        <div>$<?= number_format($stopLossPrice, 2) ?></div>
                                                        <?php 
                                                        $diffStop = $stopLossPrice - $currPrice;
                                                        $pctStop = $currPrice > 0 ? ($diffStop / $currPrice) * 100 : 0;
                                                        ?>
                                                        <div class="risk-distance-danger">
                                                            <?= number_format($pctStop, 1) ?>%
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-muted">—</span>
                                                    <?php endif; ?>
                                                </td>

                                                <!-- Strategy Signal Badge -->
                                                <td class="text-center">
                                                    <span class="strategy-badge strategy-<?= htmlspecialchars($strategy, ENT_QUOTES, 'UTF-8') ?>">
                                                        <?= strtoupper(htmlspecialchars($strategy, ENT_QUOTES, 'UTF-8')) ?>
                                                    </span>
                                                </td>

                                                <!-- Position Value & PnL -->
                                                <td class="text-right font-mono">
                                                    <div>$<?= number_format($it['market_val'], 2) ?></div>
                                                    <?php if ($isBought): ?>
                                                        <div class="text-xs font-semibold <?= $it['pnl_dollar'] >= 0 ? 'text-emerald' : 'text-ruby' ?>">
                                                            <?= $it['pnl_dollar'] >= 0 ? '+' : '' ?>$<?= number_format($it['pnl_dollar'], 2) ?>
                                                            (<?= $it['pnl_percent'] >= 0 ? '+' : '' ?><?= number_format($it['pnl_percent'], 1) ?>%)
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="text-xs text-muted">Unbought</div>
                                                    <?php endif; ?>
                                                </td>

                                                <!-- Broker Fees Paid -->
                                                <td class="text-right font-mono text-xs">
                                                    $<?= number_format($feesPaid, 2) ?>
                                                </td>

                                                <!-- Action Buttons: Buy, Sell/Delete, Edit, Inspect -->
                                                <td class="text-right table-actions-cell">
                                                    <div class="action-btn-group">
                                                        <!-- Buy Button -->
                                                        <button type="button" class="btn btn-primary btn-xs btn-open-buy"
                                                                data-id="<?= (int) $it['id'] ?>"
                                                                data-symbol="<?= htmlspecialchars($it['symbol'], ENT_QUOTES, 'UTF-8') ?>"
                                                                data-name="<?= htmlspecialchars($it['asset_name'], ENT_QUOTES, 'UTF-8') ?>"
                                                                data-price="<?= htmlspecialchars($currPrice > 0 ? $currPrice : $it['suggested_price'], ENT_QUOTES, 'UTF-8') ?>"
                                                                data-shares="<?= (int) ($isBought ? 1 : $it['suggested_shares']) ?>"
                                                                data-fee-type="<?= htmlspecialchars($it['broker_fee_type'] ?? 'fixed', ENT_QUOTES, 'UTF-8') ?>"
                                                                data-fee-val="<?= htmlspecialchars($it['broker_fee_value'] ?? '0', ENT_QUOTES, 'UTF-8') ?>"
                                                                title="Buy shares of this asset">
                                                            Buy
                                                        </button>

                                                        <!-- Sell vs Delete Dynamic Action -->
                                                        <?php if ($isBought): ?>
                                                            <button type="button" class="btn btn-outline btn-xs text-amber btn-open-sell"
                                                                    style="border-color: rgba(245, 158, 11, 0.4);"
                                                                    data-id="<?= (int) $it['id'] ?>"
                                                                    data-symbol="<?= htmlspecialchars($it['symbol'], ENT_QUOTES, 'UTF-8') ?>"
                                                                    data-name="<?= htmlspecialchars($it['asset_name'], ENT_QUOTES, 'UTF-8') ?>"
                                                                    data-price="<?= htmlspecialchars($currPrice, ENT_QUOTES, 'UTF-8') ?>"
                                                                    data-shares="<?= (int) $it['purchase_shares'] ?>"
                                                                    data-fee-type="<?= htmlspecialchars($it['broker_fee_type'] ?? 'fixed', ENT_QUOTES, 'UTF-8') ?>"
                                                                    data-fee-val="<?= htmlspecialchars($it['broker_fee_value'] ?? '0', ENT_QUOTES, 'UTF-8') ?>"
                                                                    title="Sell held shares back to cash buffer">
                                                                Sell
                                                            </button>
                                                        <?php else: ?>
                                                            <form method="POST" action="/portfolios" onsubmit="return confirm('Remove unbought asset <?= htmlspecialchars(addslashes($it['symbol']), ENT_QUOTES, 'UTF-8') ?> from portfolio?');" style="margin: 0;">
                                                                <input type="hidden" name="action" value="delete_item">
                                                                <input type="hidden" name="portfolio_id" value="<?= (int) $portfolio['id'] ?>">
                                                                <input type="hidden" name="item_id" value="<?= (int) $it['id'] ?>">
                                                                <button type="submit" class="btn btn-outline btn-xs text-ruby" style="border-color: rgba(239, 68, 68, 0.25);" title="Delete unbought asset">
                                                                    Delete
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>

                                                        <!-- Edit Asset Button -->
                                                        <button type="button" class="btn btn-outline btn-xs btn-open-edit"
                                                                data-id="<?= (int) $it['id'] ?>"
                                                                data-symbol="<?= htmlspecialchars($it['symbol'], ENT_QUOTES, 'UTF-8') ?>"
                                                                data-name="<?= htmlspecialchars($it['asset_name'], ENT_QUOTES, 'UTF-8') ?>"
                                                                data-target="<?= htmlspecialchars($it['target_price'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                                                data-stop="<?= htmlspecialchars($it['stop_loss_price'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                                                data-strategy="<?= htmlspecialchars($it['asset_strategy'] ?? 'hold', ENT_QUOTES, 'UTF-8') ?>"
                                                                data-shares="<?= (int) $it['suggested_shares'] ?>"
                                                                data-horizon="<?= htmlspecialchars($it['time_horizon'] ?? 'mid_term', ENT_QUOTES, 'UTF-8') ?>"
                                                                data-interval="<?= (int) ($it['check_interval_hours'] ?? 24) ?>"
                                                                data-why="<?= htmlspecialchars($it['why_picked'], ENT_QUOTES, 'UTF-8') ?>"
                                                                data-trigger="<?= htmlspecialchars($it['invalidation_trigger'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                                                title="Edit risk and strategy parameters">
                                                            Edit
                                                        </button>

                                                        <!-- Inspect Button -->
                                                        <button type="button" class="btn btn-outline btn-xs btn-open-theses"
                                                                data-symbol="<?= htmlspecialchars($it['symbol'], ENT_QUOTES, 'UTF-8') ?>"
                                                                data-name="<?= htmlspecialchars($it['asset_name'], ENT_QUOTES, 'UTF-8') ?>"
                                                                data-why="<?= htmlspecialchars($it['why_picked'], ENT_QUOTES, 'UTF-8') ?>"
                                                                data-bull="<?= htmlspecialchars($it['bull_thesis'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                                                data-bear="<?= htmlspecialchars($it['bear_thesis'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                                                data-trigger="<?= htmlspecialchars($it['invalidation_trigger'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                                                data-interval="<?= (int) ($it['check_interval_hours'] ?? 24) ?>"
                                                                title="Deep dive council theses">
                                                            Inspect
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            </div>

            <!-- =============================================================== -->
            <!-- MODALS (Buy, Sell, Edit Asset, Add Asset, Adjust Capital, Theses)-->
            <!-- =============================================================== -->

            <!-- Modal 1: Buy Position Modal (with fee calculation) -->
            <div class="modal-overlay" id="buy-modal">
                <div class="modal-window">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                        <h3 style="font-size: 1.15rem; font-weight: 700; color: var(--color-text);">
                            Buy Shares: <span id="modal-buy-symbol" class="ticker-symbol" style="color: var(--color-primary-light);"></span>
                        </h3>
                        <button type="button" class="btn-close-modal" id="btn-close-buy-modal" style="background: none; border: none; color: var(--color-text-muted); cursor: pointer; font-size: 1.35rem;">&times;</button>
                    </div>

                    <p class="text-xs text-muted" id="modal-buy-name" style="margin-bottom: 1.25rem;"></p>

                    <form method="POST" action="/portfolios" id="buy-form">
                        <input type="hidden" name="action" value="buy_item">
                        <input type="hidden" name="portfolio_id" value="<?= (int) $portfolio['id'] ?>">
                        <input type="hidden" name="item_id" id="modal-buy-item-id" value="">

                        <div class="form-group" style="margin-bottom: 1rem;">
                            <label for="modal-buy-price" style="font-size: var(--font-size-sm); font-weight: 600;">Purchase Price ($) *</label>
                            <input type="number" step="0.0001" id="modal-buy-price" name="price" required class="font-mono">
                        </div>

                        <div class="form-group" style="margin-bottom: 1rem;">
                            <label for="modal-buy-shares" style="font-size: var(--font-size-sm); font-weight: 600;">Shares to Buy *</label>
                            <input type="number" step="1" min="1" id="modal-buy-shares" name="shares" required class="font-mono">
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 1.25rem;">
                            <div class="form-group">
                                <label for="modal-buy-fee-type" style="font-size: var(--font-size-sm); font-weight: 600;">Broker Fee Type</label>
                                <select id="modal-buy-fee-type" name="broker_fee_type">
                                    <option value="fixed">Fixed ($)</option>
                                    <option value="percent">Percentage (%)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="modal-buy-fee-val" style="font-size: var(--font-size-sm); font-weight: 600;">Fee Value</label>
                                <input type="number" step="0.0001" min="0" id="modal-buy-fee-val" name="broker_fee_value" value="0.0000" class="font-mono">
                            </div>
                        </div>

                        <!-- Total Cost Summary Box -->
                        <div style="background: var(--color-surface-elevated); padding: 0.75rem 1rem; border-radius: var(--radius-md); border: 1px solid var(--color-border-subtle); margin-bottom: 1.25rem;">
                            <div style="display: flex; justify-content: space-between; font-size: var(--font-size-xs); color: var(--color-text-muted); margin-bottom: 0.25rem;">
                                <span>Estimated Fee:</span>
                                <span class="font-mono" id="modal-buy-fee-calc">$0.00</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; font-size: var(--font-size-sm); font-weight: 700; color: var(--color-text);">
                                <span>Total Cost (Deducted from Cash):</span>
                                <span class="font-mono text-gold" id="modal-buy-total-calc">$0.00</span>
                            </div>
                        </div>

                        <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                            <button type="button" class="btn btn-outline" id="btn-cancel-buy-modal">Cancel</button>
                            <button type="submit" class="btn btn-primary" id="btn-confirm-buy">Confirm Purchase</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Modal 2: Sell Position Modal (with proceeds return) -->
            <div class="modal-overlay" id="sell-modal">
                <div class="modal-window">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                        <h3 style="font-size: 1.15rem; font-weight: 700; color: var(--color-text);">
                            Sell Position: <span id="modal-sell-symbol" class="ticker-symbol" style="color: var(--color-primary-light);"></span>
                        </h3>
                        <button type="button" class="btn-close-modal" id="btn-close-sell-modal" style="background: none; border: none; color: var(--color-text-muted); cursor: pointer; font-size: 1.35rem;">&times;</button>
                    </div>

                    <p class="text-xs text-muted" id="modal-sell-name" style="margin-bottom: 1.25rem;"></p>

                    <form method="POST" action="/portfolios" id="sell-form">
                        <input type="hidden" name="action" value="sell_item">
                        <input type="hidden" name="portfolio_id" value="<?= (int) $portfolio['id'] ?>">
                        <input type="hidden" name="item_id" id="modal-sell-item-id" value="">

                        <div class="form-group" style="margin-bottom: 1rem;">
                            <label for="modal-sell-price" style="font-size: var(--font-size-sm); font-weight: 600;">Selling Price ($) *</label>
                            <input type="number" step="0.0001" id="modal-sell-price" name="price" required class="font-mono">
                        </div>

                        <div class="form-group" style="margin-bottom: 1rem;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <label for="modal-sell-shares" style="font-size: var(--font-size-sm); font-weight: 600;">Shares to Sell *</label>
                                <span class="text-xs text-muted">Currently Holding: <strong id="modal-sell-max-shares" class="text-white">0</strong></span>
                            </div>
                            <input type="number" step="1" min="1" id="modal-sell-shares" name="shares" required class="font-mono">
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 1.25rem;">
                            <div class="form-group">
                                <label for="modal-sell-fee-type" style="font-size: var(--font-size-sm); font-weight: 600;">Broker Fee Type</label>
                                <select id="modal-sell-fee-type" name="broker_fee_type">
                                    <option value="fixed">Fixed ($)</option>
                                    <option value="percent">Percentage (%)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="modal-sell-fee-val" style="font-size: var(--font-size-sm); font-weight: 600;">Fee Value</label>
                                <input type="number" step="0.0001" min="0" id="modal-sell-fee-val" name="broker_fee_value" value="0.0000" class="font-mono">
                            </div>
                        </div>

                        <!-- Net Proceeds Summary Box -->
                        <div style="background: var(--color-surface-elevated); padding: 0.75rem 1rem; border-radius: var(--radius-md); border: 1px solid var(--color-border-subtle); margin-bottom: 1.25rem;">
                            <div style="display: flex; justify-content: space-between; font-size: var(--font-size-xs); color: var(--color-text-muted); margin-bottom: 0.25rem;">
                                <span>Estimated Commission:</span>
                                <span class="font-mono" id="modal-sell-fee-calc">$0.00</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; font-size: var(--font-size-sm); font-weight: 700; color: var(--color-text);">
                                <span>Net Proceeds (Credited to Cash):</span>
                                <span class="font-mono text-emerald" id="modal-sell-net-calc">$0.00</span>
                            </div>
                        </div>

                        <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                            <button type="button" class="btn btn-outline" id="btn-cancel-sell-modal">Cancel</button>
                            <button type="submit" class="btn btn-primary" id="btn-confirm-sell">Execute Sale</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Modal 3: Edit Asset Details & Risk Parameters -->
            <div class="modal-overlay" id="edit-modal">
                <div class="modal-window">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                        <h3 style="font-size: 1.15rem; font-weight: 700; color: var(--color-text);">
                            Edit Asset: <span id="modal-edit-symbol" class="ticker-symbol" style="color: var(--color-primary-light);"></span>
                        </h3>
                        <button type="button" class="btn-close-modal" id="btn-close-edit-modal" style="background: none; border: none; color: var(--color-text-muted); cursor: pointer; font-size: 1.35rem;">&times;</button>
                    </div>

                    <p class="text-xs text-muted" id="modal-edit-name" style="margin-bottom: 1.25rem;"></p>

                    <form method="POST" action="/portfolios">
                        <input type="hidden" name="action" value="edit_asset">
                        <input type="hidden" name="portfolio_id" value="<?= (int) $portfolio['id'] ?>">
                        <input type="hidden" name="item_id" id="modal-edit-item-id" value="">

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 1rem;">
                            <div class="form-group">
                                <label for="modal-edit-target" style="font-size: var(--font-size-sm); font-weight: 600;">Target Price ($)</label>
                                <input type="number" step="0.0001" id="modal-edit-target" name="target_price" class="font-mono" placeholder="e.g. 250.00">
                            </div>
                            <div class="form-group">
                                <label for="modal-edit-stop" style="font-size: var(--font-size-sm); font-weight: 600;">Stop-Loss Floor ($)</label>
                                <input type="number" step="0.0001" id="modal-edit-stop" name="stop_loss_price" class="font-mono" placeholder="e.g. 195.00">
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 1rem;">
                            <div class="form-group">
                                <label for="modal-edit-strategy" style="font-size: var(--font-size-sm); font-weight: 600;">Strategy Signal</label>
                                <select id="modal-edit-strategy" name="asset_strategy">
                                    <option value="hold">HOLD</option>
                                    <option value="buy">BUY</option>
                                    <option value="sell">SELL</option>
                                    <option value="watch">WATCH</option>
                                    <option value="rebalance">REBALANCE</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="modal-edit-shares" style="font-size: var(--font-size-sm); font-weight: 600;">Suggested Shares</label>
                                <input type="number" step="1" min="0" id="modal-edit-shares" name="suggested_shares" class="font-mono">
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 1rem;">
                            <div class="form-group">
                                <label for="modal-edit-horizon" style="font-size: var(--font-size-sm); font-weight: 600;">Time Horizon</label>
                                <select id="modal-edit-horizon" name="time_horizon">
                                    <option value="short_term">Short-term (1-3 mos)</option>
                                    <option value="mid_term">Mid-term (6-12 mos)</option>
                                    <option value="long_term">Long-term (1-3 yrs)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="modal-edit-interval" style="font-size: var(--font-size-sm); font-weight: 600;">Check Interval (hours)</label>
                                <input type="number" step="1" min="1" id="modal-edit-interval" name="check_interval_hours" class="font-mono">
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom: 1rem;">
                            <label for="modal-edit-why" style="font-size: var(--font-size-sm); font-weight: 600;">Thesis / Why Picked</label>
                            <textarea id="modal-edit-why" name="why_picked" rows="2" style="width: 100%; resize: vertical;"></textarea>
                        </div>

                        <div class="form-group" style="margin-bottom: 1.25rem;">
                            <label for="modal-edit-trigger" style="font-size: var(--font-size-sm); font-weight: 600;">Invalidation Trigger</label>
                            <textarea id="modal-edit-trigger" name="invalidation_trigger" rows="2" style="width: 100%; resize: vertical;"></textarea>
                        </div>

                        <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                            <button type="button" class="btn btn-outline" id="btn-cancel-edit-modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Modal 4: Add New Asset to Portfolio -->
            <div class="modal-overlay" id="add-asset-modal">
                <div class="modal-window">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                        <h3 style="font-size: 1.15rem; font-weight: 700; color: var(--color-text);">
                            Add Asset to Portfolio
                        </h3>
                        <button type="button" class="btn-close-modal" id="btn-close-add-modal" style="background: none; border: none; color: var(--color-text-muted); cursor: pointer; font-size: 1.35rem;">&times;</button>
                    </div>

                    <form method="POST" action="/portfolios">
                        <input type="hidden" name="action" value="add_asset">
                        <input type="hidden" name="portfolio_id" value="<?= (int) $portfolio['id'] ?>">

                        <div class="form-group" style="margin-bottom: 1rem;">
                            <label for="modal-add-symbol" style="font-size: var(--font-size-sm); font-weight: 600;">Ticker Symbol *</label>
                            <input type="text" id="modal-add-symbol" name="symbol" required placeholder="e.g. NVDA, AAPL, MSFT" style="text-transform: uppercase;">
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 1rem;">
                            <div class="form-group">
                                <label for="modal-add-target" style="font-size: var(--font-size-sm); font-weight: 600;">Target Price ($)</label>
                                <input type="number" step="0.0001" id="modal-add-target" name="target_price" class="font-mono" placeholder="Optional">
                            </div>
                            <div class="form-group">
                                <label for="modal-add-stop" style="font-size: var(--font-size-sm); font-weight: 600;">Stop-Loss Floor ($)</label>
                                <input type="number" step="0.0001" id="modal-add-stop" name="stop_loss_price" class="font-mono" placeholder="Optional">
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 1rem;">
                            <div class="form-group">
                                <label for="modal-add-strategy" style="font-size: var(--font-size-sm); font-weight: 600;">Initial Strategy</label>
                                <select id="modal-add-strategy" name="asset_strategy">
                                    <option value="watch">WATCH</option>
                                    <option value="buy">BUY</option>
                                    <option value="hold">HOLD</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="modal-add-shares" style="font-size: var(--font-size-sm); font-weight: 600;">Suggested Shares</label>
                                <input type="number" step="1" min="1" id="modal-add-shares" name="suggested_shares" value="10" class="font-mono">
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom: 1.25rem;">
                            <label for="modal-add-why" style="font-size: var(--font-size-sm); font-weight: 600;">Thesis / Rationale</label>
                            <textarea id="modal-add-why" name="why_picked" rows="2" placeholder="Brief rationale for scouting this asset into the portfolio..."></textarea>
                        </div>

                        <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                            <button type="button" class="btn btn-outline" id="btn-cancel-add-modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Add Asset</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Modal 5: Adjust Capital / Cash Buffer -->
            <div class="modal-overlay" id="adjust-capital-modal">
                <div class="modal-window">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                        <h3 style="font-size: 1.15rem; font-weight: 700; color: var(--color-text);">
                            Adjust Portfolio Capital
                        </h3>
                        <button type="button" class="btn-close-modal" id="btn-close-cap-modal" style="background: none; border: none; color: var(--color-text-muted); cursor: pointer; font-size: 1.35rem;">&times;</button>
                    </div>

                    <form method="POST" action="/portfolios">
                        <input type="hidden" name="action" value="adjust_capital">
                        <input type="hidden" name="portfolio_id" value="<?= (int) $portfolio['id'] ?>">

                        <div style="background: var(--color-surface-elevated); padding: 0.75rem 1rem; border-radius: var(--radius-md); border: 1px solid var(--color-border-subtle); margin-bottom: 1.25rem; font-size: var(--font-size-xs);">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 0.25rem;">
                                <span class="text-muted">Current Initial Capital:</span>
                                <strong class="font-mono text-white">$<?= number_format($metrics['initial_capital'], 2) ?></strong>
                            </div>
                            <div style="display: flex; justify-content: space-between;">
                                <span class="text-muted">Current Cash Buffer:</span>
                                <strong class="font-mono text-gold">$<?= number_format($metrics['cash_buffer'], 2) ?></strong>
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom: 1.25rem;">
                            <label for="modal-new-capital" style="font-size: var(--font-size-sm); font-weight: 600;">New Total Capital ($) *</label>
                            <input type="number" step="0.01" min="1" id="modal-new-capital" name="new_capital" value="<?= htmlspecialchars($portfolio['initial_capital'], ENT_QUOTES, 'UTF-8') ?>" required class="font-mono">
                            <span class="text-xs text-muted" style="margin-top: 0.35rem; display: block;">
                                Changing capital increases or decreases the uninvested cash buffer by the net difference.
                            </span>
                        </div>

                        <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                            <button type="button" class="btn btn-outline" id="btn-cancel-cap-modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Update Capital</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Modal 6: Inspect Asset Theses Modal -->
            <div class="modal-overlay" id="theses-modal">
                <div class="modal-window">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                        <h3 style="font-size: 1.15rem; font-weight: 700; color: var(--color-text);">
                            Inspect Asset: <span id="theses-modal-symbol" class="ticker-symbol" style="color: var(--color-primary-light);"></span>
                        </h3>
                        <button type="button" class="btn-close-modal" id="btn-close-theses-modal" style="background: none; border: none; color: var(--color-text-muted); cursor: pointer; font-size: 1.35rem;">&times;</button>
                    </div>

                    <p class="text-xs text-secondary" id="theses-modal-name" style="margin-bottom: 1.25rem; font-weight: 600;"></p>

                    <div style="margin-bottom: 1rem; padding: 0.85rem 1rem; background: var(--color-surface-elevated); border-radius: var(--radius-md); border: 1px solid var(--color-border-subtle);">
                        <strong class="meta-label" style="display: block; margin-bottom: 0.35rem; color: var(--color-text);">Why Picked</strong>
                        <p class="text-xs text-secondary" id="theses-modal-why" style="line-height: 1.5;"></p>
                    </div>

                    <div style="margin-bottom: 1rem; padding: 0.85rem 1rem; background: var(--color-surface-sunken); border-radius: var(--radius-md); border-left: 3px solid var(--color-success);">
                        <strong class="meta-label text-emerald" style="display: block; margin-bottom: 0.35rem;">▲ Bull Thesis</strong>
                        <p class="text-xs text-secondary" id="theses-modal-bull" style="line-height: 1.5;"></p>
                    </div>

                    <div style="margin-bottom: 1rem; padding: 0.85rem 1rem; background: var(--color-surface-sunken); border-radius: var(--radius-md); border-left: 3px solid var(--color-danger);">
                        <strong class="meta-label text-ruby" style="display: block; margin-bottom: 0.35rem;">▼ Downside Risks</strong>
                        <p class="text-xs text-secondary" id="theses-modal-bear" style="line-height: 1.5;"></p>
                    </div>

                    <div style="margin-bottom: 1.5rem; padding: 0.85rem 1rem; background: var(--color-surface-sunken); border-radius: var(--radius-md); border-left: 3px solid var(--color-warning);">
                        <strong class="meta-label text-amber" style="display: block; margin-bottom: 0.35rem;">⚠️ Invalidation Trigger</strong>
                        <p class="text-xs text-secondary" id="theses-modal-trigger" style="line-height: 1.5;"></p>
                        <div class="text-xs text-muted" style="margin-top: 0.5rem;" id="theses-modal-interval"></div>
                    </div>

                    <div style="display: flex; justify-content: flex-end;">
                        <button type="button" class="btn btn-outline btn-sm" id="btn-dismiss-theses-modal">Close</button>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <!-- =============================================================== -->
            <!-- 2. LIST MODE: PORTFOLIOS OVERVIEW GRID                          -->
            <!-- =============================================================== -->

            <div class="detail-hero">
                <div>
                    <div class="meta-label" style="margin-bottom: 0.35rem; color: var(--color-gold);">
                        Institutional Wealth Terminal • Screen 2
                    </div>
                    <h1>My Portfolios</h1>
                    <p class="text-muted">Active portfolios, strategic mandates, and autonomous AI Council surveillance.</p>
                </div>

                <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
                    <form method="POST" action="/portfolios" id="form-scan-portfolios" style="margin: 0; display: inline-flex;">
                        <input type="hidden" name="action" value="run_guardian_now">
                        <button type="submit" name="action" value="run_guardian_now" class="btn btn-secondary" id="btn-scan-portfolios" title="Scan all portfolio assets against live market quotes and threshold triggers">
                            <span id="scan-btn-icon">🛡️</span>
                            <span id="scan-btn-text">Scan Portfolios Now</span>
                        </button>
                    </form>
                    <a href="/portfolio-builder" class="btn btn-primary" id="btn-new-portfolio-list">
                        + New Portfolio
                    </a>
                </div>
            </div>

            <?php if (empty($portfolios)): ?>
                <div class="card grid-card text-center" style="padding: 3.5rem 1.5rem;">
                    <div style="font-size: 3rem; margin-bottom: 0.75rem;">📊</div>
                    <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--color-text); margin-bottom: 0.35rem;">
                        No portfolios created yet
                    </h3>
                    <p class="text-xs text-muted" style="max-width: 440px; margin: 0 auto 1.5rem; line-height: 1.5;">
                        Formulate your first investment mandate in natural language and let our autonomous agents scout and build your target asset allocation.
                    </p>
                    <a href="/portfolio-builder" class="btn btn-primary btn-sm">
                        Build First Portfolio &rarr;
                    </a>
                </div>
            <?php else: ?>
                <div class="portfolios-grid">
                    <?php foreach ($portfolios as $p): ?>
                        <?php
                        $init = (float) $p['initial_capital'];
                        $curr = (float) $p['computed_current_value'];
                        $diff = (float) $p['computed_return_diff'];
                        $pct = (float) $p['computed_return_pct'];
                        $status = $p['alert_status'] ?? 'normal';
                        ?>
                        <div class="p-card">
                            <div>
                                <!-- Header: Title & Beacon Badge -->
                                <div class="p-card-header">
                                    <div>
                                        <a href="/portfolios?id=<?= (int) $p['id'] ?>" class="p-card-title">
                                            <?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                        <div class="p-card-meta">
                                            Created on <?= date('M d, Y', strtotime($p['created'])) ?> • 
                                            <span style="text-transform: uppercase;">
                                                <?= htmlspecialchars(str_replace('_', ' ', $p['time_horizon'] ?? 'mid_term'), ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </div>
                                    </div>

                                    <div>
                                        <?php if ($status === 'urgent'): ?>
                                            <span class="beacon-badge beacon-urgent"><span class="beacon-dot"></span> Urgent</span>
                                        <?php elseif ($status === 'action_needed'): ?>
                                            <span class="beacon-badge beacon-action"><span class="beacon-dot"></span> Attention Required</span>
                                        <?php else: ?>
                                            <span class="beacon-badge beacon-normal"><span class="beacon-dot"></span> Normal</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- User Thesis Preview -->
                                <div class="p-card-prompt">
                                    "<?= htmlspecialchars($p['user_prompt'] ?? 'No mandate recorded.', ENT_QUOTES, 'UTF-8') ?>"
                                </div>

                                <!-- 3-Metric Financial Box -->
                                <div class="p-card-metrics">
                                    <div class="m-box">
                                        <div class="m-label">Initial Capital</div>
                                        <div class="m-val">$<?= number_format($init, 2) ?></div>
                                    </div>

                                    <div class="m-box">
                                        <div class="m-label">Current Value</div>
                                        <div class="m-val">$<?= number_format($curr, 2) ?></div>
                                    </div>

                                    <div class="m-box">
                                        <div class="m-label">Total Return</div>
                                        <div class="m-val <?= $diff >= 0 ? 'text-emerald' : 'text-ruby' ?>">
                                            <?= $diff >= 0 ? '+' : '' ?><?= number_format($pct, 2) ?>%
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Footer Actions -->
                            <div class="p-card-footer">
                                <span class="text-xs text-muted">
                                    <?= (int) $p['computed_purchased_count'] ?> of <?= (int) $p['computed_total_items'] ?> in portfolio
                                </span>

                                <div style="display: flex; gap: 0.5rem; align-items: center;">
                                    <form method="POST" action="/portfolios" onsubmit="return confirm('Are you sure you want to delete portfolio \'<?= htmlspecialchars(addslashes($p['name']), ENT_QUOTES, 'UTF-8') ?>\'?');" style="margin: 0;">
                                        <input type="hidden" name="action" value="delete_portfolio">
                                        <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                        <button type="submit" class="btn btn-outline btn-xs text-ruby" style="border-color: rgba(239, 68, 68, 0.25);" title="Delete portfolio">
                                            Delete
                                        </button>
                                    </form>

                                    <a href="/portfolios?id=<?= (int) $p['id'] ?>" class="btn btn-primary btn-xs">
                                        Open Portfolio &rarr;
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <?php require __DIR__ . '/layout/footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Helper to close all modal overlays
            function closeAllModals() {
                document.querySelectorAll('.modal-overlay').forEach(function(m) {
                    m.classList.remove('active');
                });
            }

            // Close modal on escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') closeAllModals();
            });

            // Close modal on clicking outside window
            document.querySelectorAll('.modal-overlay').forEach(function(overlay) {
                overlay.addEventListener('click', function(e) {
                    if (e.target === overlay) closeAllModals();
                });
            });

            // Close buttons inside modals
            document.querySelectorAll('.btn-close-modal').forEach(function(btn) {
                btn.addEventListener('click', closeAllModals);
            });

            // Auto-scroll chat stream to bottom
            var chatStream = document.getElementById('chat-stream');
            if (chatStream) {
                chatStream.scrollTop = chatStream.scrollHeight;
            }

            // Chat input enter key support (Shift+Enter for newline)
            var chatInput = document.getElementById('council-msg-input');
            var chatForm = document.getElementById('council-chat-form');
            var btnSendChat = document.getElementById('btn-send-chat');
            var typingStatus = document.getElementById('chat-typing-status');

            if (chatInput && chatForm && chatStream) {
                function escapeHtml(str) {
                    var d = document.createElement('div');
                    d.textContent = str;
                    return d.innerHTML;
                }

                chatInput.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        if (chatInput.value.trim() !== '') {
                            chatForm.dispatchEvent(new Event('submit', { cancelable: true }));
                        }
                    }
                });

                chatForm.addEventListener('submit', function(e) {
                    e.preventDefault();

                    var userMsg = chatInput.value.trim();
                    if (!userMsg) return;

                    var pIdInput = chatForm.querySelector('input[name="portfolio_id"]');
                    var portfolioId = pIdInput ? pIdInput.value : '';

                    // 1. Immediately append user's directive bubble
                    var now = new Date();
                    var timeStr = ('0' + now.getHours()).slice(-2) + ':' + ('0' + now.getMinutes()).slice(-2);

                    var userBubble = document.createElement('div');
                    userBubble.className = 'chat-bubble chat-bubble-user';
                    userBubble.style.animation = 'fadeIn 0.25s ease';
                    userBubble.innerHTML = '<div class="chat-meta">' +
                        '<span>Investor Directive</span>' +
                        '<span>' + timeStr + '</span>' +
                    '</div>' +
                    '<div class="chat-content">' +
                        escapeHtml(userMsg).replace(/\n/g, '<br>') +
                    '</div>';
                    chatStream.appendChild(userBubble);

                    // 2. Immediately clear input field
                    chatInput.value = '';

                    // 3. Immediately append live animated typing indicator bubble
                    var loaderBubble = document.createElement('div');
                    loaderBubble.className = 'chat-bubble chat-bubble-assistant typing-loader-bubble';
                    loaderBubble.id = 'live-typing-loader';
                    loaderBubble.style.animation = 'fadeIn 0.25s ease';
                    loaderBubble.innerHTML = '<div class="chat-meta" style="color: var(--color-gold);">' +
                        '<span>Council Orchestrator</span>' +
                        '<span style="text-transform: none; font-weight: 500; font-size: 0.65rem; color: var(--color-text-muted);">Convening 5 agents</span>' +
                    '</div>' +
                    '<div class="typing-indicator-container">' +
                        '<div class="typing-dots">' +
                            '<span class="typing-dot"></span>' +
                            '<span class="typing-dot"></span>' +
                            '<span class="typing-dot"></span>' +
                        '</div>' +
                        '<span class="typing-label">Specialist agents are deliberating...</span>' +
                    '</div>';
                    chatStream.appendChild(loaderBubble);

                    // 4. Smooth scroll to bottom
                    chatStream.scrollTop = chatStream.scrollHeight;

                    // 5. Update UI status
                    if (btnSendChat) {
                        btnSendChat.disabled = true;
                        btnSendChat.innerHTML = 'Deliberating...';
                    }
                    if (typingStatus) {
                        typingStatus.innerHTML = '<span style="color: var(--color-gold);">Council deliberating across 5 agents...</span>';
                    }

                    // 6. Send AJAX request to Council
                    var params = new URLSearchParams();
                    params.append('action', 'send_council_chat');
                    params.append('portfolio_id', portfolioId);
                    params.append('message', userMsg);
                    params.append('ajax', '1');

                    fetch('/portfolios', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: params.toString()
                    })
                    .then(function(res) {
                        return res.text().then(function(rawText) {
                            try {
                                return JSON.parse(rawText);
                            } catch (parseErr) {
                                var cleanText = rawText.replace(/<[^>]*>?/gm, ' ').replace(/\s+/g, ' ').trim();
                                if (!cleanText) {
                                    cleanText = 'Server error (HTTP ' + res.status + ').';
                                }
                                return {
                                    success: false,
                                    error: cleanText
                                };
                            }
                        });
                    })
                    .then(function(data) {
                        // Remove typing loader
                        var loader = document.getElementById('live-typing-loader');
                        if (loader) loader.remove();

                        if (data.success) {
                            var assistantBubble = document.createElement('div');
                            assistantBubble.className = 'chat-bubble chat-bubble-assistant';
                            assistantBubble.style.animation = 'fadeIn 0.25s ease';

                            var assistantMeta = '<div class="chat-meta">' +
                                '<span>Council Synthesis</span>' +
                                '<span>' + (data.created_time || timeStr) + '</span>' +
                            '</div>';

                            var assistantContent = '<div class="chat-content">' +
                                (data.rendered_html || '') +
                            '</div>';

                            var actionCardHtml = '';
                            if (data.structured_action && typeof data.structured_action === 'object') {
                                var act = data.structured_action;
                                var actType = act.action_type || 'proposal';
                                var actSummary = act.summary || 'Action proposed by Council';
                                var payload = act.payload || {};

                                actionCardHtml = '<div class="action-card">' +
                                    '<div class="action-card-header">' +
                                        '<span class="action-badge">' + escapeHtml(actType.replace(/_/g, ' ')) + '</span>' +
                                        '<span style="font-size: 0.68rem; color: var(--color-gold);">Requires Approval</span>' +
                                    '</div>' +
                                    '<div class="action-summary">' +
                                        escapeHtml(actSummary) +
                                    '</div>' +
                                    '<div class="action-meta-grid">';

                                if (payload.symbol) {
                                    actionCardHtml += '<span>Ticker: <strong>' + escapeHtml(payload.symbol) + '</strong></span>';
                                }
                                if (payload.target_price) {
                                    actionCardHtml += '<span>Target: <strong>$' + parseFloat(payload.target_price).toFixed(2) + '</strong></span>';
                                }
                                if (payload.stop_loss_price) {
                                    actionCardHtml += '<span>Stop-Loss: <strong>$' + parseFloat(payload.stop_loss_price).toFixed(2) + '</strong></span>';
                                }
                                if (payload.asset_strategy) {
                                    actionCardHtml += '<span>Strategy: <strong>' + escapeHtml(payload.asset_strategy.toUpperCase()) + '</strong></span>';
                                }
                                if (payload.new_capital) {
                                    actionCardHtml += '<span>New Capital: <strong>$' + parseFloat(payload.new_capital).toFixed(2) + '</strong></span>';
                                }
                                if (payload.trade_type) {
                                    actionCardHtml += '<span>Trade: <strong>' + escapeHtml(payload.trade_type.toUpperCase()) + ' ' + (payload.shares || 0) + ' shares</strong></span>';
                                }
                                if (payload.price) {
                                    actionCardHtml += '<span>Price: <strong>$' + parseFloat(payload.price).toFixed(2) + '</strong></span>';
                                }

                                actionCardHtml += '</div>' +
                                    '<form method="POST" action="/portfolios" style="margin: 0;">' +
                                        '<input type="hidden" name="action" value="execute_chat_action">' +
                                        '<input type="hidden" name="portfolio_id" value="' + (data.portfolio_id || portfolioId) + '">' +
                                        '<input type="hidden" name="message_id" value="' + (data.message_id || 0) + '">' +
                                        '<button type="submit" class="btn btn-primary btn-xs btn-block" style="width: 100%; font-weight: 600;">' +
                                            '⚡ Confirm & Execute Action' +
                                        '</button>' +
                                    '</form>' +
                                '</div>';
                            }

                            assistantBubble.innerHTML = assistantMeta + assistantContent + actionCardHtml;
                            chatStream.appendChild(assistantBubble);
                        } else {
                            var errBubble = document.createElement('div');
                            errBubble.className = 'chat-bubble chat-bubble-system';
                            errBubble.style.color = 'var(--color-danger)';
                            errBubble.textContent = 'Council Error: ' + (data.error || 'Failed to receive advisory.');
                            chatStream.appendChild(errBubble);
                        }

                        chatStream.scrollTop = chatStream.scrollHeight;
                    })
                    .catch(function(err) {
                        var loader = document.getElementById('live-typing-loader');
                        if (loader) loader.remove();

                        var errBubble = document.createElement('div');
                        errBubble.className = 'chat-bubble chat-bubble-system';
                        errBubble.style.color = 'var(--color-danger)';
                        errBubble.textContent = 'Connection error: ' + err.message;
                        chatStream.appendChild(errBubble);
                        chatStream.scrollTop = chatStream.scrollHeight;
                    })
                    .finally(function() {
                        if (btnSendChat) {
                            btnSendChat.disabled = false;
                            btnSendChat.innerHTML = 'Send Directive &rarr;';
                        }
                        if (typingStatus) {
                            typingStatus.innerHTML = 'Enter to ask Council';
                        }
                        chatInput.focus();
                    });
                });
            }

            // -----------------------------------------------------------------
            // Modal 1: Buy Modal Handler with live cost calculation
            // -----------------------------------------------------------------
            var buyModal = document.getElementById('buy-modal');
            var buyPriceInput = document.getElementById('modal-buy-price');
            var buySharesInput = document.getElementById('modal-buy-shares');
            var buyFeeType = document.getElementById('modal-buy-fee-type');
            var buyFeeVal = document.getElementById('modal-buy-fee-val');
            var buyFeeCalc = document.getElementById('modal-buy-fee-calc');
            var buyTotalCalc = document.getElementById('modal-buy-total-calc');

            function updateBuyCalculations() {
                if (!buyPriceInput || !buySharesInput || !buyFeeType || !buyFeeVal) return;
                var p = parseFloat(buyPriceInput.value) || 0;
                var s = parseInt(buySharesInput.value, 10) || 0;
                var feeT = buyFeeType.value;
                var feeV = parseFloat(buyFeeVal.value) || 0;

                var estFee = (feeT === 'percent') ? (p * s * (feeV / 100)) : feeV;
                var total = (p * s) + estFee;

                if (buyFeeCalc) buyFeeCalc.textContent = '$' + estFee.toFixed(2);
                if (buyTotalCalc) buyTotalCalc.textContent = '$' + total.toFixed(2);
            }

            if (buyPriceInput) buyPriceInput.addEventListener('input', updateBuyCalculations);
            if (buySharesInput) buySharesInput.addEventListener('input', updateBuyCalculations);
            if (buyFeeType) buyFeeType.addEventListener('change', updateBuyCalculations);
            if (buyFeeVal) buyFeeVal.addEventListener('input', updateBuyCalculations);

            document.querySelectorAll('.btn-open-buy').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    document.getElementById('modal-buy-item-id').value = btn.getAttribute('data-id');
                    document.getElementById('modal-buy-symbol').textContent = btn.getAttribute('data-symbol');
                    document.getElementById('modal-buy-name').textContent = btn.getAttribute('data-name');
                    buyPriceInput.value = btn.getAttribute('data-price');
                    buySharesInput.value = btn.getAttribute('data-shares');
                    buyFeeType.value = btn.getAttribute('data-fee-type') || 'fixed';
                    buyFeeVal.value = btn.getAttribute('data-fee-val') || '0.0000';
                    updateBuyCalculations();
                    if (buyModal) buyModal.classList.add('active');
                });
            });

            var btnCancelBuy = document.getElementById('btn-cancel-buy-modal');
            if (btnCancelBuy) btnCancelBuy.addEventListener('click', closeAllModals);

            // -----------------------------------------------------------------
            // Modal 2: Sell Modal Handler with live proceeds calculation
            // -----------------------------------------------------------------
            var sellModal = document.getElementById('sell-modal');
            var sellPriceInput = document.getElementById('modal-sell-price');
            var sellSharesInput = document.getElementById('modal-sell-shares');
            var sellFeeType = document.getElementById('modal-sell-fee-type');
            var sellFeeVal = document.getElementById('modal-sell-fee-val');
            var sellFeeCalc = document.getElementById('modal-sell-fee-calc');
            var sellNetCalc = document.getElementById('modal-sell-net-calc');

            function updateSellCalculations() {
                if (!sellPriceInput || !sellSharesInput || !sellFeeType || !sellFeeVal) return;
                var p = parseFloat(sellPriceInput.value) || 0;
                var s = parseInt(sellSharesInput.value, 10) || 0;
                var feeT = sellFeeType.value;
                var feeV = parseFloat(sellFeeVal.value) || 0;

                var estFee = (feeT === 'percent') ? (p * s * (feeV / 100)) : feeV;
                var net = Math.max(0, (p * s) - estFee);

                if (sellFeeCalc) sellFeeCalc.textContent = '$' + estFee.toFixed(2);
                if (sellNetCalc) sellNetCalc.textContent = '$' + net.toFixed(2);
            }

            if (sellPriceInput) sellPriceInput.addEventListener('input', updateSellCalculations);
            if (sellSharesInput) sellSharesInput.addEventListener('input', updateSellCalculations);
            if (sellFeeType) sellFeeType.addEventListener('change', updateSellCalculations);
            if (sellFeeVal) sellFeeVal.addEventListener('input', updateSellCalculations);

            document.querySelectorAll('.btn-open-sell').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var maxShares = parseInt(btn.getAttribute('data-shares'), 10) || 1;
                    document.getElementById('modal-sell-item-id').value = btn.getAttribute('data-id');
                    document.getElementById('modal-sell-symbol').textContent = btn.getAttribute('data-symbol');
                    document.getElementById('modal-sell-name').textContent = btn.getAttribute('data-name');
                    document.getElementById('modal-sell-max-shares').textContent = maxShares;
                    sellPriceInput.value = btn.getAttribute('data-price');
                    sellSharesInput.value = maxShares;
                    sellSharesInput.max = maxShares;
                    sellFeeType.value = btn.getAttribute('data-fee-type') || 'fixed';
                    sellFeeVal.value = btn.getAttribute('data-fee-val') || '0.0000';
                    updateSellCalculations();
                    if (sellModal) sellModal.classList.add('active');
                });
            });

            var btnCancelSell = document.getElementById('btn-cancel-sell-modal');
            if (btnCancelSell) btnCancelSell.addEventListener('click', closeAllModals);

            // -----------------------------------------------------------------
            // Modal 3: Edit Asset Modal
            // -----------------------------------------------------------------
            var editModal = document.getElementById('edit-modal');
            document.querySelectorAll('.btn-open-edit').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    document.getElementById('modal-edit-item-id').value = btn.getAttribute('data-id');
                    document.getElementById('modal-edit-symbol').textContent = btn.getAttribute('data-symbol');
                    document.getElementById('modal-edit-name').textContent = btn.getAttribute('data-name');
                    document.getElementById('modal-edit-target').value = btn.getAttribute('data-target') || '';
                    document.getElementById('modal-edit-stop').value = btn.getAttribute('data-stop') || '';
                    document.getElementById('modal-edit-strategy').value = btn.getAttribute('data-strategy') || 'hold';
                    document.getElementById('modal-edit-shares').value = btn.getAttribute('data-shares') || '0';
                    document.getElementById('modal-edit-horizon').value = btn.getAttribute('data-horizon') || 'mid_term';
                    document.getElementById('modal-edit-interval').value = btn.getAttribute('data-interval') || '24';
                    document.getElementById('modal-edit-why').value = btn.getAttribute('data-why') || '';
                    document.getElementById('modal-edit-trigger').value = btn.getAttribute('data-trigger') || '';
                    if (editModal) editModal.classList.add('active');
                });
            });

            var btnCancelEdit = document.getElementById('btn-cancel-edit-modal');
            if (btnCancelEdit) btnCancelEdit.addEventListener('click', closeAllModals);

            // -----------------------------------------------------------------
            // Modal 4: Add Asset Modal
            // -----------------------------------------------------------------
            var addAssetModal = document.getElementById('add-asset-modal');
            var btnOpenAddAsset = document.getElementById('btn-open-add-asset');
            if (btnOpenAddAsset && addAssetModal) {
                btnOpenAddAsset.addEventListener('click', function() {
                    addAssetModal.classList.add('active');
                    var symInp = document.getElementById('modal-add-symbol');
                    if (symInp) symInp.focus();
                });
            }
            var btnCancelAdd = document.getElementById('btn-cancel-add-modal');
            if (btnCancelAdd) btnCancelAdd.addEventListener('click', closeAllModals);

            // -----------------------------------------------------------------
            // Modal 5: Adjust Capital Modal
            // -----------------------------------------------------------------
            var capModal = document.getElementById('adjust-capital-modal');
            var btnOpenCap = document.getElementById('btn-open-adjust-capital');
            if (btnOpenCap && capModal) {
                btnOpenCap.addEventListener('click', function() {
                    capModal.classList.add('active');
                });
            }
            var btnCancelCap = document.getElementById('btn-cancel-cap-modal');
            if (btnCancelCap) btnCancelCap.addEventListener('click', closeAllModals);

            // -----------------------------------------------------------------
            // Modal 6: Inspect Asset Theses Modal
            // -----------------------------------------------------------------
            var thesesModal = document.getElementById('theses-modal');
            document.querySelectorAll('.btn-open-theses').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var sym = btn.getAttribute('data-symbol');
                    var name = btn.getAttribute('data-name');
                    var why = btn.getAttribute('data-why');
                    var bull = btn.getAttribute('data-bull');
                    var bear = btn.getAttribute('data-bear');
                    var trigger = btn.getAttribute('data-trigger');
                    var interval = btn.getAttribute('data-interval');

                    document.getElementById('theses-modal-symbol').textContent = sym;
                    document.getElementById('theses-modal-name').textContent = name;
                    document.getElementById('theses-modal-why').textContent = why;
                    document.getElementById('theses-modal-bull').textContent = bull || 'Growth thesis and positive market catalysts.';
                    document.getElementById('theses-modal-bear').textContent = bear || 'General sector volatility and macroeconomic pressures.';
                    document.getElementById('theses-modal-trigger').textContent = trigger || 'Loss of key technical support or deteriorating fundamentals.';
                    document.getElementById('theses-modal-interval').textContent = 'Sentinel audit frequency: Every ' + interval + ' hours.';

                    if (thesesModal) thesesModal.classList.add('active');
                });
            });

            var btnDismissTheses = document.getElementById('btn-dismiss-theses-modal');
            if (btnDismissTheses) btnDismissTheses.addEventListener('click', closeAllModals);

            // Loading state for Recalculate Strategy button
            var recalcForm = document.getElementById('recalc-form');
            var recalcBtn = document.getElementById('btn-recalc');
            if (recalcForm && recalcBtn) {
                recalcForm.addEventListener('submit', function() {
                    recalcBtn.disabled = true;
                    recalcBtn.style.opacity = '0.7';
                    recalcBtn.innerHTML = 'The Council is recalculating...';
                });
            }

            // Loading state for Scan Portfolios Now button
            var scanForm = document.getElementById('form-scan-portfolios');
            var scanBtn = document.getElementById('btn-scan-portfolios');
            if (scanForm && scanBtn) {
                scanForm.addEventListener('submit', function() {
                    var icon = document.getElementById('scan-btn-icon');
                    var text = document.getElementById('scan-btn-text');
                    scanBtn.disabled = true;
                    scanBtn.style.opacity = '0.75';
                    scanBtn.style.cursor = 'wait';
                    if (icon) icon.textContent = '⏳';
                    if (text) text.textContent = 'Scanning Markets...';
                });
            }
        });
    </script>
</body>
</html>
