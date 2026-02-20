<?php
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

        // ── Size limit ────────────────────────────────────
        if (strlen($body) > $config['max_text_size']) {
            http_response_code(413);
            exit('Payload too large');
        }

        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $subject     = "Webhook payload {$ctx->time}";
        $htmlMessage = null;
        $decoded     = null;

        // ── JSON with text_or_url field ───────────────────
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
            }
        }

        // ── Storage action ────────────────────────────────
        if ($config['storage_enabled']) {
            StorageAction::saveText(
                $config['storage_path'],
                $body,
                $ctx
            );
        }

        // ── Email action ──────────────────────────────────
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
}
