<?php
/**
 * All email-sending logic (plain, HTML, MIME attachment).
 */
class EmailAction
{
    /**
     * Send an HTML email.
     *
     * @param string         $to       Recipient address.
     * @param string         $subject  Email subject.
     * @param string         $html     Full HTML body.
     * @param RequestContext  $ctx      Request metadata (used for From header).
     * @return void Exits on failure.
     */
    public static function sendHtmlEmail(
        string $to,
        string $subject,
        string $html,
        RequestContext $ctx
    ): void {
        $headers = [
            "From: webhook@{$ctx->fromDomain}",
            "MIME-Version: 1.0",
            "Content-Type: text/html; charset=UTF-8",
        ];

        $ok = mail($to, $subject, $html, implode("\r\n", $headers));
        if (!$ok) {
            http_response_code(500);
            exit('Mail failed (check server mail setup).');
        }
    }

    /**
     * Send a plain-text email.
     *
     * @param string         $to       Recipient address.
     * @param string         $subject  Email subject.
     * @param string         $body     Plain text body.
     * @param RequestContext  $ctx      Request metadata (used for From header).
     * @return void Exits on failure.
     */
    public static function sendPlainEmail(
        string $to,
        string $subject,
        string $body,
        RequestContext $ctx
    ): void {
        $headers = [
            "From: webhook@{$ctx->fromDomain}",
            "MIME-Version: 1.0",
            "Content-Type: text/plain; charset=UTF-8",
        ];

        $ok = mail($to, $subject, $body, implode("\r\n", $headers));
        if (!$ok) {
            http_response_code(500);
            exit('Mail failed (check server mail setup).');
        }
    }

    /**
     * Send an email with a file attachment (MIME multipart).
     *
     * @param string         $to        Recipient address.
     * @param string         $subject   Email subject.
     * @param string         $fileData  Raw file contents.
     * @param string         $filename  Original filename.
     * @param string         $metaHtml  HTML metadata block shown in the email body.
     * @param RequestContext  $ctx       Request metadata (used for From header).
     * @return void Exits on failure.
     */
    public static function sendFileEmail(
        string $to,
        string $subject,
        string $fileData,
        string $filename,
        string $metaHtml,
        RequestContext $ctx
    ): void {
        $boundary = md5(uniqid(strval(time())));

        $mimeBody = "--{$boundary}\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 8bit\r\n\r\n"
            . $metaHtml . "\r\n\r\n"
            . "--{$boundary}\r\n"
            . "Content-Type: application/octet-stream\r\n"
            . "Content-Disposition: attachment; filename=\"" . addslashes($filename) . "\"\r\n"
            . "Content-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($fileData)) . "\r\n"
            . "--{$boundary}--\r\n";

        $headers = [
            "From: webhook@{$ctx->fromDomain}",
            "MIME-Version: 1.0",
            "Content-Type: multipart/mixed; boundary=\"{$boundary}\"",
        ];

        $ok = mail($to, $subject, $mimeBody, implode("\r\n", $headers));
        if (!$ok) {
            http_response_code(500);
            exit('Mail failed (check server mail setup).');
        }
    }
}
