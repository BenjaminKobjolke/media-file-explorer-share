<?php
declare(strict_types=1);

namespace App\Handlers;

use App\Actions\EmailAction;
use App\Actions\StorageAction;
use App\Formatters\LogarteFormatter;
use App\Formatters\MarkdownFormatter;
use App\RequestContext;

/**
 * Handles text/JSON payloads (non-file requests).
 */
class TextHandler
{
    /**
     * Process a text/JSON payload from php://input.
     *
     * @param array          $config  Global config array.
     * @param RequestContext  $ctx     Request metadata.
     * @return void Exits on error.
     */
    public static function handle(array $config, RequestContext $ctx): void
    {
        $body = file_get_contents('php://input');
        if ($body === false || $body === '') {
            http_response_code(400);
            exit('Empty body');
        }

        // -- Size limit ------------------------------------
        if (strlen($body) > $config['max_text_size']) {
            http_response_code(413);
            exit('Payload too large');
        }

        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $subject     = "Webhook payload {$ctx->time}";
        $htmlMessage = null;
        $decoded     = null;

        // -- JSON with text_or_url field -------------------
        if (stripos($contentType, 'application/json') !== false) {
            $decoded = json_decode($body, true);

            if (json_last_error() === JSON_ERROR_NONE && isset($decoded['text_or_url'])) {
                $sharedText  = $decoded['text_or_url'];
                $extraFields = array_diff_key($decoded, ['text_or_url' => true]);

                // Try Logarte format first
                $parsed = LogarteFormatter::parse($sharedText);
                if ($parsed !== null) {
                    $subject     = $parsed['subject'];
                    $htmlMessage = LogarteFormatter::buildHtml($parsed, $ctx);
                } else {
                    // General text / markdown
                    $subject     = MarkdownFormatter::extractSubject($sharedText);
                    $htmlMessage = MarkdownFormatter::buildHtml($sharedText, $subject, $ctx, $extraFields);
                }
            } elseif (json_last_error() === JSON_ERROR_NONE && is_array($decoded) && !empty($decoded)) {
                // Fields-only payload (no text_or_url key)
                $firstValue = reset($decoded);
                $subject    = !empty($firstValue) ? "Custom Fields: " . mb_substr((string) $firstValue, 0, 60) : "Custom Fields";
                $htmlMessage = self::buildFieldsOnlyHtml($decoded, $ctx);
            }
        }

        // -- Storage action --------------------------------
        if ($config['storage_enabled']) {
            StorageAction::saveText(
                $config['storage_path'],
                $body,
                $ctx
            );
        }

        // -- Email action ----------------------------------
        if ($config['email_enabled']) {
            if ($htmlMessage !== null) {
                EmailAction::sendHtmlEmail(
                    $config['email_to'],
                    $subject,
                    $htmlMessage,
                    $ctx
                );
            } else {
                // Fallback: plain-text email
                if (stripos($contentType, 'application/json') !== false) {
                    $decoded = $decoded ?? json_decode($body, true);
                    if (isset($decoded) && is_array($decoded)) {
                        $body = json_encode(
                            $decoded,
                            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                        );
                    }
                }

                $message =
                    "Time: {$ctx->time}\n" .
                    "IP: {$ctx->ip}\n" .
                    "Method: POST\n" .
                    "Content-Type: {$contentType}\n" .
                    "User-Agent: {$ctx->ua}\n\n" .
                    "Body:\n{$body}\n";

                EmailAction::sendPlainEmail(
                    $config['email_to'],
                    $subject,
                    $message,
                    $ctx
                );
            }
        }
    }

    /**
     * Build HTML email for a fields-only JSON payload.
     *
     * @param array          $fields Key-value map of custom fields.
     * @param RequestContext  $ctx    Request metadata.
     * @return string Full HTML document.
     */
    private static function buildFieldsOnlyHtml(array $fields, RequestContext $ctx): string
    {
        $rows = '';
        foreach ($fields as $key => $value) {
            $safeKey   = htmlspecialchars((string) $key);
            $safeValue = htmlspecialchars((string) $value);
            $rows .= "<tr><td style=\"padding:8px 16px 8px 0;color:#757575;font-weight:bold;white-space:nowrap;vertical-align:top;\">{$safeKey}</td>"
                . "<td style=\"padding:8px 0;color:#333;\">{$safeValue}</td></tr>";
        }

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f0f0f0;font-family:Arial,Helvetica,sans-serif;">
  <div style="max-width:700px;margin:20px auto;background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.1);">

    <!-- Header -->
    <div style="background:#37474f;color:#ffffff;padding:20px 24px;">
      <h1 style="margin:0;font-size:20px;font-weight:bold;">Custom Fields</h1>
    </div>

    <!-- Content -->
    <div style="padding:20px 24px;">
      <table style="width:100%;border-collapse:collapse;font-size:14px;">
        {$rows}
      </table>
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
}
