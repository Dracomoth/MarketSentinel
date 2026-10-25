<?php
/**
 * Markdown Service - Institutional Wealth Terminal
 * 
 * High-performance, lightweight and XSS-safe parser for simple Markdown standard.
 * Formats AI-generated Council Theses, Guardian Diagnoses, and Strategy Reports
 * into clean, structured HTML tailored for executive financial interfaces.
 * 
 * Supports:
 * - Headings: #, ##, ###, ####, #####, ######
 * - Inline formatting: **bold**, *italic*, ***bold italic***
 * - Monospace code: `inline code` and ``` fenced code blocks ```
 * - Lists: Unordered (- , * , • ) and Ordered (1. , 2. )
 * - Blockquotes: > quote text
 * - Dividers: ---, ***, ___
 * - Executive Council Banners: === TITLE === and --- TITLE ---
 * - Safe hyperlinks: [label](https://...)
 */

class MarkdownService {
    /**
     * Render simple markdown text into clean, safe institutional HTML
     *
     * @param string|null $text
     * @return string
     */
    public static function render(?string $text): string {
        if ($text === null || trim($text) === '') {
            return '';
        }

        // 1. Normalize line endings
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        // 2. Escape HTML entities for strict XSS safety before applying markdown transformations
        $safeText = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

        // 3. Extract fenced code blocks (``` ... ```) to prevent formatting collisions
        $codeBlocks = [];
        $safeText = preg_replace_callback('/```(?:([a-zA-Z0-9_-]+)?\n)?([\s\S]*?)```/', function($matches) use (&$codeBlocks) {
            $lang = !empty($matches[1]) ? trim($matches[1]) : '';
            $code = $matches[2];
            $idx = count($codeBlocks);
            $langAttr = $lang !== '' ? ' data-lang="' . htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') . '"' : '';
            $codeBlocks[$idx] = '<pre class="md-pre"' . $langAttr . '><code class="md-code-block">' . trim($code) . '</code></pre>';
            return "\n\n@@@CODEBLOCK_{$idx}@@@\n\n";
        }, $safeText);

        // 4. Extract executive consensus verdict (:::verdict ... :::)
        $safeText = preg_replace_callback('/:::verdict\s*\n?([\s\S]*?):::/i', function($matches) {
            $innerContent = trim($matches[1]);
            return "\n\n@@@VERDICT_START@@@\n" . $innerContent . "\n@@@VERDICT_END@@@\n\n";
        }, $safeText);

        // 4b. Also auto-wrap loose Consensus/Executive Verdict sections if not already in :::verdict
        if (!str_contains($safeText, '@@@VERDICT_START@@@')) {
            $safeText = preg_replace('/(?:\#{1,4}\s*(?:🎯\s*)?(?:Executive|Consensus)\s*Verdict|\*\*(?:🎯\s*)?(?:Executive|Consensus)\s*Verdict:?\*\*)\s*(.*?)(?=(?:\n\s*\n|\n:::agent|\n\#{1,4}|$))/is', "\n\n@@@VERDICT_START@@@\n$1\n@@@VERDICT_END@@@\n\n", $safeText);
        }

        // 4c. Extract specialized agent groupboxes (:::agent-macro ... :::)
        $safeText = preg_replace_callback('/:::agent-([a-zA-Z0-9_-]+)\s*\n?([\s\S]*?):::/i', function($matches) {
            $agentKey = strtolower(trim($matches[1]));
            $innerContent = trim($matches[2]);
            return "\n\n@@@AGENT_START_{$agentKey}@@@\n" . $innerContent . "\n@@@AGENT_END@@@\n\n";
        }, $safeText);

        $agentMetadata = [
            'macro' => [
                'name'  => 'Macro Strategist',
                'icon'  => '🌐',
                'badge' => 'Regime & Macro',
            ],
            'fundamental' => [
                'name'  => 'Fundamental Auditor',
                'icon'  => '📊',
                'badge' => 'Valuation & Balance Sheet',
            ],
            'quant' => [
                'name'  => 'Technical Quant',
                'icon'  => '📈',
                'badge' => 'Price Action & Trends',
            ],
            'technical' => [
                'name'  => 'Technical Quant',
                'icon'  => '📈',
                'badge' => 'Price Action & Trends',
            ],
            'debate' => [
                'name'  => 'Bull vs. Bear Debate',
                'icon'  => '⚖️',
                'badge' => 'Catalysts vs. Risks',
            ],
            'bull_bear' => [
                'name'  => 'Bull vs. Bear Debate',
                'icon'  => '⚖️',
                'badge' => 'Catalysts vs. Risks',
            ],
            'bull-bear' => [
                'name'  => 'Bull vs. Bear Debate',
                'icon'  => '⚖️',
                'badge' => 'Catalysts vs. Risks',
            ],
            'risk' => [
                'name'  => 'Chief Risk Arbiter',
                'icon'  => '🛡️',
                'badge' => 'Capital & Floor Protection',
            ],
            'arbiter' => [
                'name'  => 'Chief Risk Arbiter',
                'icon'  => '🛡️',
                'badge' => 'Capital & Floor Protection',
            ],
        ];

        $lines = explode("\n", $safeText);
        $output = [];

        $state = 'none'; // 'none', 'p', 'ul', 'ol', 'quote'
        $quoteLines = [];

        $closeState = function() use (&$state, &$output, &$quoteLines) {
            if ($state === 'p') {
                $output[] = '</p>';
            } elseif ($state === 'ul') {
                $output[] = '</ul>';
            } elseif ($state === 'ol') {
                $output[] = '</ol>';
            } elseif ($state === 'quote') {
                $output[] = '<blockquote class="md-quote">' . implode('<br>', $quoteLines) . '</blockquote>';
                $quoteLines = [];
            }
            $state = 'none';
        };

        foreach ($lines as $rawLine) {
            $line = trim($rawLine);

            // Blank line closes open containers
            if ($line === '') {
                $closeState();
                continue;
            }

            // Code block placeholder
            if (preg_match('/^@@@CODEBLOCK_(\d+)@@@$/', $line, $cbMatch)) {
                $closeState();
                $idx = (int)$cbMatch[1];
                if (isset($codeBlocks[$idx])) {
                    $output[] = $codeBlocks[$idx];
                }
                continue;
            }

            // Consensus Verdict Box Start
            if ($line === '@@@VERDICT_START@@@') {
                $closeState();
                $output[] = '<div class="consensus-verdict-box"><div class="verdict-header"><span class="verdict-badge">🎯 Straight Answer</span><span class="verdict-title">Executive Consensus</span></div><div class="verdict-body">';
                continue;
            }

            // Consensus Verdict Box End
            if ($line === '@@@VERDICT_END@@@') {
                $closeState();
                $output[] = '</div></div>';
                continue;
            }

            // Specialized Agent Box Start
            if (preg_match('/^@@@AGENT_START_([a-zA-Z0-9_-]+)@@@$/', $line, $agMatch)) {
                $closeState();
                $key = strtolower($agMatch[1]);
                $meta = $agentMetadata[$key] ?? [
                    'name'  => ucfirst(str_replace(['_', '-'], ' ', $key)) . ' Specialist',
                    'icon'  => '🤖',
                    'badge' => 'Agent Analysis'
                ];
                $nameSafe = htmlspecialchars($meta['name'], ENT_QUOTES, 'UTF-8');
                $iconSafe = $meta['icon'];
                $badgeSafe = htmlspecialchars($meta['badge'], ENT_QUOTES, 'UTF-8');
                $keyClass = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');

                $output[] = "<details class=\"agent-box agent-box-{$keyClass}\"><summary class=\"agent-box-header\"><div class=\"agent-box-title\"><span class=\"agent-box-chevron\">▶</span><span class=\"agent-box-icon\">{$iconSafe}</span><span class=\"agent-box-name\">{$nameSafe}</span></div><div class=\"agent-box-header-right\"><span class=\"agent-box-badge\">{$badgeSafe}</span></div></summary><div class=\"agent-box-body\">";
                continue;
            }

            // Specialized Agent Box End
            if ($line === '@@@AGENT_END@@@') {
                $closeState();
                $output[] = '</div></details>';
                continue;
            }

            // Council banner: === TITLE === or --- TITLE ---
            if (preg_match('/^(?:={3,}|-{3,})\s*(.*?)\s*(?:={3,}|-{3,})$/', $line, $bMatch)) {
                $closeState();
                $title = trim($bMatch[1]);
                if ($title === '') {
                    $output[] = '<hr class="md-divider">';
                } else {
                    $output[] = '<div class="md-banner"><span class="md-banner-tag">' . $title . '</span></div>';
                }
                continue;
            }

            // Horizontal rule: ---, ***, ___
            if (preg_match('/^(\-{3,}|\*{3,}|_{3,})$/', $line)) {
                $closeState();
                $output[] = '<hr class="md-divider">';
                continue;
            }

            // Heading: #, ##, ###, ####, #####, ######
            if (preg_match('/^(#{1,6})\s+(.+)$/', $line, $hMatch)) {
                $closeState();
                $level = strlen($hMatch[1]);
                $headingText = self::parseInline($hMatch[2]);
                $tag = 'h4';
                if ($level === 1) $tag = 'h3';
                elseif ($level === 2) $tag = 'h4';
                elseif ($level === 3) $tag = 'h5';
                else $tag = 'h6';
                $output[] = "<{$tag} class=\"md-heading md-{$tag}\">{$headingText}</{$tag}>";
                continue;
            }

            // Blockquote line: > quote text
            if (preg_match('/^&gt;\s?(.*)$/', $line, $qMatch)) {
                if ($state !== 'quote') {
                    $closeState();
                    $state = 'quote';
                }
                $quoteLines[] = self::parseInline($qMatch[1]);
                continue;
            }

            // Unordered list item: - item, * item, • item
            if (preg_match('/^(?:[-*•])\s+(.+)$/', $line, $liMatch)) {
                if ($state !== 'ul') {
                    $closeState();
                    $output[] = '<ul class="md-list md-ul">';
                    $state = 'ul';
                }
                $output[] = '<li>' . self::parseInline($liMatch[1]) . '</li>';
                continue;
            }

            // Ordered list item: 1. item, 2. item
            if (preg_match('/^(\d+)\.\s+(.+)$/', $line, $olMatch)) {
                if ($state !== 'ol') {
                    $closeState();
                    $output[] = '<ol class="md-list md-ol">';
                    $state = 'ol';
                }
                $output[] = '<li>' . self::parseInline($olMatch[2]) . '</li>';
                continue;
            }

            // Regular paragraph line
            if ($state !== 'p') {
                $closeState();
                $output[] = '<p class="md-p">' . self::parseInline($line);
                $state = 'p';
            } else {
                $output[] = '<br>' . self::parseInline($line);
            }
        }

        $closeState();

        return implode("\n", $output);
    }

    /**
     * Parse inline markdown tags (code, bold, italic, links)
     *
     * @param string $inline
     * @return string
     */
    protected static function parseInline(string $inline): string {
        // Inline code: `code`
        $inline = preg_replace('/`([^`]+)`/', '<code class="md-code">$1</code>', $inline);

        // Bold Italic: ***text*** or ___text___
        $inline = preg_replace('/(?:\*\*\*|___)(.*?)(?:\*\*\*|___)/s', '<strong><em>$1</em></strong>', $inline);

        // Bold: **text** or __text__
        $inline = preg_replace('/(?:\*\*|__)(.*?)(?:\*\*|__)/s', '<strong>$1</strong>', $inline);

        // Italic: *text* or _text_
        $inline = preg_replace('/(?:\*|_)(.*?)(?:\*|_)/s', '<em>$1</em>', $inline);

        // Safe Links: [text](https://...)
        $inline = preg_replace_callback('/\[([^\]]+)\]\((https?:\/\/[^\s\)\"\']+)\)/i', function($m) {
            $label = $m[1];
            $url = htmlspecialchars($m[2], ENT_QUOTES, 'UTF-8');
            return '<a href="' . $url . '" target="_blank" rel="noopener noreferrer" class="md-link">' . $label . '</a>';
        }, $inline);

        return $inline;
    }
}
