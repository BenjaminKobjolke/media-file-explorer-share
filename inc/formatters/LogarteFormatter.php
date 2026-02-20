<?php
/**
 * Parse and format Logarte debug-console exports into HTML emails.
 */
class LogarteFormatter
{
    /**
     * Parse Logarte export text into structured data.
     *
     * @param string $text Raw shared text.
     * @return array|null Parsed data or null if not Logarte format.
     */
    public static function parse(string $text): ?array
    {
        $lines = explode("\n", $text);

        if (count($lines) < 3) {
            return null;
        }

        $firstLine = trim($lines[0]);
        if (strpos($firstLine, 'LOGARTE') === false) {
            return null;
        }

        $headerLines     = [];
        $entryStartIndex = 0;

        for ($i = 0; $i < count($lines); $i++) {
            if (preg_match('/^\[\d+:\d+:\d+\]/', trim($lines[$i]))) {
                $entryStartIndex = $i;
                break;
            }
            $line = trim($lines[$i]);
            if ($line !== '') {
                $headerLines[] = $line;
            }
        }

        $sessionInfo = '';
        if (isset($headerLines[1])) {
            $sessionInfo = preg_replace('/^[^\w]*/', '', $headerLines[1]);
        }

        $subject = 'Logarte';
        if ($sessionInfo !== '') {
            $subject = "Logarte: {$sessionInfo}";
        }

        $remainingText = implode("\n", array_slice($lines, $entryStartIndex));
        $entries       = [];

        $parts = preg_split('/(?=^\[\d+:\d+:\d+\]\s*\[\w+\])/m', $remainingText);
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            $entry = ['time' => '', 'type' => '', 'content' => ''];

            if (preg_match('/^\[(\d+:\d+:\d+)\]\s*\[(\w+)\](.*)$/s', $part, $m)) {
                $entry['time']    = $m[1];
                $entry['type']    = strtoupper($m[2]);
                $entry['content'] = trim($m[3]);
            } else {
                $entry['content'] = $part;
            }

            $entries[] = $entry;
        }

        return [
            'subject'     => $subject,
            'headerLines' => $headerLines,
            'entries'     => $entries,
        ];
    }

    /**
     * Build an HTML email from parsed Logarte data.
     *
     * @param array          $parsed Parsed Logarte data from parse().
     * @param RequestContext  $ctx    Request metadata.
     * @return string Full HTML document.
     */
    public static function buildHtml(array $parsed, RequestContext $ctx): string
    {
        $typeColors = [
            'NAVIGATION' => ['bg' => '#e3f2fd', 'fg' => '#1565c0', 'border' => '#90caf9'],
            'LOG'        => ['bg' => '#e8f5e9', 'fg' => '#2e7d32', 'border' => '#a5d6a7'],
            'NETWORK'    => ['bg' => '#fff3e0', 'fg' => '#e65100', 'border' => '#ffcc80'],
            'DATABASE'   => ['bg' => '#f3e5f5', 'fg' => '#6a1b9a', 'border' => '#ce93d8'],
        ];

        $defaultColor = ['bg' => '#f5f5f5', 'fg' => '#424242', 'border' => '#bdbdbd'];

        $headerHtml = '';
        foreach ($parsed['headerLines'] as $line) {
            $headerHtml .= htmlspecialchars($line) . '<br>';
        }

        $entriesHtml = '';
        foreach ($parsed['entries'] as $entry) {
            $type    = $entry['type'];
            $colors  = $typeColors[$type] ?? $defaultColor;
            $timeStr = $entry['time'] !== '' ? htmlspecialchars($entry['time']) : '';
            $typeStr = $type !== '' ? htmlspecialchars($type) : 'UNKNOWN';
            $content = htmlspecialchars($entry['content']);
            $content = nl2br($content);

            $badge = '';
            if ($timeStr !== '' || $type !== '') {
                $badge = '<span style="display:inline-block;padding:2px 8px;border-radius:3px;'
                    . "background:{$colors['bg']};color:{$colors['fg']};border:1px solid {$colors['border']};"
                    . 'font-weight:bold;font-size:12px;margin-right:8px;">'
                    . $typeStr
                    . '</span>';
                if ($timeStr !== '') {
                    $badge = '<span style="color:#757575;font-size:12px;margin-right:6px;">['
                        . $timeStr . ']</span>' . $badge;
                }
            }

            $entriesHtml .= <<<ENTRY
    <div style="margin-bottom:12px;padding:10px 14px;border-left:3px solid {$colors['border']};background:#fafafa;">
      <div style="margin-bottom:6px;">{$badge}</div>
      <div style="font-family:'Courier New',Courier,monospace;font-size:13px;line-height:1.5;color:#333;white-space:pre-wrap;word-break:break-word;">
        {$content}
      </div>
    </div>
ENTRY;
        }

        $statsLine = '';
        foreach ($parsed['headerLines'] as $line) {
            if (strpos($line, 'entries') !== false) {
                $statsLine = htmlspecialchars($line);
                break;
            }
        }

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f0f0f0;font-family:Arial,Helvetica,sans-serif;">
  <div style="max-width:700px;margin:20px auto;background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.1);">
    <div style="background:#1a237e;color:#ffffff;padding:20px 24px;">
      <h1 style="margin:0 0 8px 0;font-size:22px;font-weight:bold;">Logarte Export</h1>
      <div style="font-size:14px;opacity:0.9;">{$headerHtml}</div>
    </div>
    <div style="background:#e8eaf6;padding:10px 24px;font-size:13px;color:#3949ab;">
      {$statsLine}
    </div>
    <div style="padding:16px 24px;">
      <h2 style="font-size:16px;color:#333;margin:0 0 14px 0;border-bottom:1px solid #e0e0e0;padding-bottom:8px;">Log Entries</h2>
      {$entriesHtml}
    </div>
    <div style="background:#f5f5f5;padding:14px 24px;font-size:11px;color:#999;border-top:1px solid #e0e0e0;">
      Received: {$ctx->time} &middot; IP: {$ctx->ip} &middot; UA: {$ctx->ua}
    </div>
  </div>
</body>
</html>
HTML;

        return $html;
    }
}
