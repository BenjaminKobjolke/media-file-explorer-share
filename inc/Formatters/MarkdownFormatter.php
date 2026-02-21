<?php
declare(strict_types=1);

namespace App\Formatters;

use App\RequestContext;

/**
 * Markdown-to-HTML conversion and subject extraction for email formatting.
 */
class MarkdownFormatter
{
    /**
     * Extract a subject line from the shared text.
     * Tries YAML front-matter, first heading, or first non-empty line.
     *
     * @param string $text Raw shared text.
     * @return string Subject line (prefixed with "Shared: ").
     */
    public static function extractSubject(string $text): string
    {
        $lines = explode("\n", $text);

        $inFrontMatter = false;
        $firstHeading  = null;
        $firstLine     = null;

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '---') {
                $inFrontMatter = !$inFrontMatter;
                continue;
            }

            if ($inFrontMatter) {
                continue;
            }

            if ($trimmed === '') {
                continue;
            }

            if ($firstHeading === null && preg_match('/^#{1,6}\s+(.+)$/', $trimmed, $m)) {
                $firstHeading = trim($m[1]);
                break;
            }

            if ($firstLine === null) {
                $firstLine = $trimmed;
            }
        }

        $raw = $firstHeading ?? $firstLine ?? 'Shared content';

        // Strip markdown bold/italic
        $raw = preg_replace('/\*{1,2}([^*]+)\*{1,2}/', '$1', $raw);

        if (mb_strlen($raw) > 80) {
            $raw = mb_substr($raw, 0, 77) . '...';
        }

        return "Shared: {$raw}";
    }

    /**
     * Build the full HTML email wrapper for general markdown content.
     *
     * @param string         $text        Raw shared text.
     * @param string         $subject     Email subject.
     * @param RequestContext  $ctx         Request metadata.
     * @param array          $extraFields Additional key-value fields from the JSON payload.
     * @return string Full HTML document.
     */
    public static function buildHtml(string $text, string $subject, RequestContext $ctx, array $extraFields = []): string
    {
        $bodyHtml = self::markdownToHtml($text);

        // Append extra fields as a styled key-value table
        if (!empty($extraFields)) {
            $rows = '';
            foreach ($extraFields as $key => $value) {
                $safeKey   = htmlspecialchars((string) $key);
                $safeValue = htmlspecialchars((string) $value);
                $rows .= "<tr><td style=\"padding:4px 12px 4px 0;color:#757575;font-weight:bold;white-space:nowrap;vertical-align:top;\">{$safeKey}</td>"
                    . "<td style=\"padding:4px 0;color:#333;\">{$safeValue}</td></tr>";
            }
            $bodyHtml .= '<hr style="border:none;border-top:1px solid #e0e0e0;margin:20px 0;">'
                . '<div style="font-size:12px;color:#757575;margin-bottom:6px;font-weight:bold;">Additional Fields</div>'
                . '<table style="width:100%;border-collapse:collapse;margin-bottom:16px;font-size:13px;">'
                . $rows . '</table>';
        }

        // Strip "Shared: " prefix for the display title
        $displayTitle = preg_replace('/^Shared:\s*/', '', $subject);

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f0f0f0;font-family:Arial,Helvetica,sans-serif;">
  <div style="max-width:700px;margin:20px auto;background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.1);">

    <!-- Header -->
    <div style="background:#37474f;color:#ffffff;padding:20px 24px;">
      <h1 style="margin:0;font-size:20px;font-weight:bold;">{$displayTitle}</h1>
    </div>

    <!-- Content -->
    <div style="padding:20px 24px;">
      {$bodyHtml}
    </div>

    <!-- Footer -->
    <div style="background:#f5f5f5;padding:14px 24px;font-size:11px;color:#999;border-top:1px solid #e0e0e0;">
      Received: {$ctx->time} &middot; IP: {$ctx->ip} &middot; UA: {$ctx->ua}
    </div>

  </div>
</body>
</html>
HTML;

        return $html;
    }

    /**
     * Convert markdown-ish text to HTML for email.
     * Handles headings, bold, italic, lists, horizontal rules, code blocks,
     * blockquotes, and YAML front-matter.
     *
     * @param string $text Raw markdown text.
     * @return string HTML fragment.
     */
    public static function markdownToHtml(string $text): string
    {
        $lines           = explode("\n", $text);
        $html            = '';
        $inFrontMatter   = false;
        $frontMatterLines = [];
        $inCodeBlock     = false;
        $inList          = false;
        $listType        = ''; // 'ul' or 'ol'

        foreach ($lines as $line) {
            $trimmed = trim($line);

            // --- YAML front-matter ---
            if ($trimmed === '---' && !$inCodeBlock) {
                if (!$inFrontMatter && empty($frontMatterLines) && $html === '') {
                    $inFrontMatter = true;
                    continue;
                } elseif ($inFrontMatter) {
                    $inFrontMatter = false;
                    $html .= self::buildFrontMatterHtml($frontMatterLines);
                    continue;
                } else {
                    if ($inList) {
                        $html .= "</{$listType}>";
                        $inList = false;
                    }
                    $html .= '<hr style="border:none;border-top:1px solid #e0e0e0;margin:20px 0;">';
                    continue;
                }
            }

            if ($inFrontMatter) {
                $frontMatterLines[] = $trimmed;
                continue;
            }

            // --- Code blocks (```) ---
            if (preg_match('/^```/', $trimmed)) {
                if ($inCodeBlock) {
                    $html .= '</code></pre>';
                    $inCodeBlock = false;
                } else {
                    if ($inList) {
                        $html .= "</{$listType}>";
                        $inList = false;
                    }
                    $html .= '<pre style="background:#f5f5f5;border:1px solid #e0e0e0;border-radius:4px;padding:12px;overflow-x:auto;font-size:13px;line-height:1.5;"><code>';
                    $inCodeBlock = true;
                }
                continue;
            }

            if ($inCodeBlock) {
                $html .= htmlspecialchars($line) . "\n";
                continue;
            }

            // --- Horizontal rules (---, ***, ___) ---
            if (preg_match('/^(\*{3,}|-{3,}|_{3,})$/', $trimmed)) {
                if ($inList) {
                    $html .= "</{$listType}>";
                    $inList = false;
                }
                $html .= '<hr style="border:none;border-top:1px solid #e0e0e0;margin:20px 0;">';
                continue;
            }

            // --- Headings ---
            if (preg_match('/^(#{1,6})\s+(.+)$/', $trimmed, $m)) {
                if ($inList) {
                    $html .= "</{$listType}>";
                    $inList = false;
                }
                $level       = strlen($m[1]);
                $headingText = self::inlineMarkdown($m[2]);
                $sizes       = [1 => '22px', 2 => '18px', 3 => '16px', 4 => '14px', 5 => '13px', 6 => '12px'];
                $size        = $sizes[$level] ?? '14px';
                $marginTop   = $level <= 2 ? '24px' : '18px';
                $borderBottom = $level <= 2
                    ? 'border-bottom:1px solid #e0e0e0;padding-bottom:6px;'
                    : '';
                $html .= "<h{$level} style=\"font-size:{$size};color:#1a237e;margin:{$marginTop} 0 10px 0;{$borderBottom}\">{$headingText}</h{$level}>";
                continue;
            }

            // --- Ordered list items (1. 2. etc) ---
            if (preg_match('/^(\d+)\.\s+(.+)$/', $trimmed, $m)) {
                if (!$inList || $listType !== 'ol') {
                    if ($inList) {
                        $html .= "</{$listType}>";
                    }
                    $html .= '<ol style="margin:8px 0;padding-left:24px;line-height:1.7;">';
                    $inList   = true;
                    $listType = 'ol';
                }
                $html .= '<li style="margin-bottom:6px;">' . self::inlineMarkdown($m[2]) . '</li>';
                continue;
            }

            // --- Unordered list items (- or *) ---
            if (preg_match('/^[-*]\s+(.+)$/', $trimmed, $m)) {
                if (!$inList || $listType !== 'ul') {
                    if ($inList) {
                        $html .= "</{$listType}>";
                    }
                    $html .= '<ul style="margin:8px 0;padding-left:24px;line-height:1.7;">';
                    $inList   = true;
                    $listType = 'ul';
                }
                $html .= '<li style="margin-bottom:6px;">' . self::inlineMarkdown($m[1]) . '</li>';
                continue;
            }

            // --- Blockquote ---
            if (preg_match('/^>\s*(.*)$/', $trimmed, $m)) {
                if ($inList) {
                    $html .= "</{$listType}>";
                    $inList = false;
                }
                $quoteContent = self::inlineMarkdown($m[1]);
                $html .= '<blockquote style="margin:10px 0;padding:8px 16px;border-left:3px solid #90caf9;background:#f8f9ff;color:#555;font-style:italic;">'
                    . $quoteContent . '</blockquote>';
                continue;
            }

            // --- Indented continuation (4 spaces or tab) ---
            if ($inList && preg_match('/^(\s{4}|\t)(.+)$/', $line, $m)) {
                $html .= '<br>' . self::inlineMarkdown(trim($m[2]));
                continue;
            }

            // --- Empty line ---
            if ($trimmed === '') {
                if ($inList) {
                    $html .= "</{$listType}>";
                    $inList = false;
                }
                continue;
            }

            // --- Regular paragraph ---
            if ($inList) {
                $html .= "</{$listType}>";
                $inList = false;
            }
            $html .= '<p style="margin:8px 0;line-height:1.7;color:#333;">' . self::inlineMarkdown($trimmed) . '</p>';
        }

        // Close any open tags
        if ($inList) {
            $html .= "</{$listType}>";
        }
        if ($inCodeBlock) {
            $html .= '</code></pre>';
        }

        return $html;
    }

    /**
     * Process inline markdown: bold, italic, inline code, links.
     *
     * @param string $text Raw inline text.
     * @return string HTML with inline formatting.
     */
    public static function inlineMarkdown(string $text): string
    {
        $text = htmlspecialchars($text);

        // Inline code: `code`
        $text = preg_replace(
            '/`([^`]+)`/',
            '<code style="background:#f0f0f0;padding:1px 5px;border-radius:3px;font-size:0.9em;">$1</code>',
            $text
        );

        // Bold + italic: ***text*** or ___text___
        $text = preg_replace('/\*{3}([^*]+)\*{3}/', '<strong><em>$1</em></strong>', $text);
        $text = preg_replace('/_{3}([^_]+)_{3}/', '<strong><em>$1</em></strong>', $text);

        // Bold: **text** or __text__
        $text = preg_replace('/\*{2}([^*]+)\*{2}/', '<strong>$1</strong>', $text);
        $text = preg_replace('/_{2}([^_]+)_{2}/', '<strong>$1</strong>', $text);

        // Italic: *text* or _text_
        $text = preg_replace('/\*([^*]+)\*/', '<em>$1</em>', $text);
        $text = preg_replace('/(?<!\w)_([^_]+)_(?!\w)/', '<em>$1</em>', $text);

        // Links: [text](url)
        $text = preg_replace(
            '/\[([^\]]+)\]\(([^)]+)\)/',
            '<a href="$2" style="color:#1565c0;">$1</a>',
            $text
        );

        return $text;
    }

    /**
     * Render YAML front-matter as a styled metadata box.
     *
     * @param array $lines Front-matter lines (without --- delimiters).
     * @return string HTML table or empty string.
     */
    private static function buildFrontMatterHtml(array $lines): string
    {
        if (empty($lines)) {
            return '';
        }

        $rows = '';
        foreach ($lines as $line) {
            if (preg_match('/^([^:]+):\s*(.*)$/', $line, $m)) {
                $key   = htmlspecialchars(trim($m[1]));
                $value = htmlspecialchars(trim($m[2], " \t'\""));
                $rows .= "<tr><td style=\"padding:4px 12px 4px 0;color:#757575;font-weight:bold;white-space:nowrap;vertical-align:top;\">{$key}</td>"
                    . "<td style=\"padding:4px 0;color:#333;\">{$value}</td></tr>";
            }
        }

        if ($rows === '') {
            return '';
        }

        return '<table style="width:100%;border-collapse:collapse;margin-bottom:16px;font-size:13px;">'
            . $rows . '</table>';
    }
}
