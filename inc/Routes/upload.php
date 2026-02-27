<?php
declare(strict_types=1);

// Expects $app and $config in scope (provided by api.php require).

use App\Actions\DatabaseAction;
use App\Actions\EmailAction;
use App\Actions\StorageAction;
use App\Formatters\LogarteFormatter;
use App\Formatters\MarkdownFormatter;
use App\RequestContext;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

$app->post('/upload', function (Request $request, Response $response) use ($config): Response {
    $ctx = new RequestContext();
    $uploadedFiles = $request->getUploadedFiles();

    if (!empty($uploadedFiles['file'])) {
        // ── FILE UPLOAD ─────────────────────────────────────────────
        /** @var \Psr\Http\Message\UploadedFileInterface $file */
        $file = $uploadedFiles['file'];

        if ($file->getError() !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload_max_filesize',
                UPLOAD_ERR_FORM_SIZE  => 'File exceeds form MAX_FILE_SIZE',
                UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded',
                UPLOAD_ERR_NO_FILE    => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder on server',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION  => 'Upload stopped by a PHP extension',
            ];
            $errCode = $file->getError();
            $msg = $errorMessages[$errCode] ?? "Unknown upload error ({$errCode})";
            $response->getBody()->write('Upload error: ' . $msg);
            return $response->withStatus(400);
        }

        $fileSize = $file->getSize() ?? 0;
        if ($fileSize > $config['max_file_size']) {
            $limitMB = round($config['max_file_size'] / (1024 * 1024), 1);
            $response->getBody()->write("File too large (max {$limitMB} MB)");
            return $response->withStatus(413);
        }

        $filename = $file->getClientFilename() ?: 'attachment';
        $fileData = (string) $file->getStream();

        $postFields = (array) ($request->getParsedBody() ?? []);

        // Extract reserved _-prefixed fields
        $entryId = isset($postFields['_id']) ? (int) $postFields['_id'] : null;
        $emailOverride = null;
        if (isset($postFields['_email'])) {
            $raw = $postFields['_email'];
            $emailOverride = ($raw === 'false' || $raw === '0' || $raw === '') ? false : $raw;
        }

        $fieldValues = [];
        $fieldExcludes = [];
        if (!empty($config['db_enabled'])) {
            $customFields = DatabaseAction::getAllCustomFields($config['db_path']);
            foreach ($customFields as $cf) {
                $key = '_' . $cf['name'];
                $fieldExcludes[$key] = true;
                if (isset($postFields[$key])) {
                    $optId = (int) $postFields[$key];
                    if (DatabaseAction::getOptionById($config['db_path'], $cf['name'], $optId) === null) {
                        $response->getBody()->write(ucfirst($cf['name']) . ' not found');
                        return $response->withStatus(400);
                    }
                    $fieldValues[$cf['name']] = $optId;
                }
            }
        }

        if ($entryId !== null && !empty($config['db_enabled'])) {
            $parent = DatabaseAction::getById($config['db_path'], $entryId);
            if ($parent === null) {
                $response->getBody()->write('Parent entry not found');
                return $response->withStatus(404);
            }
        } elseif ($entryId !== null) {
            $response->getBody()->write('_id requires db_enabled');
            return $response->withStatus(400);
        }

        // Extra POST fields as JSON body
        $extraFields = array_diff_key($postFields, array_merge(['file' => true, '_id' => true, '_email' => true], $fieldExcludes));
        $body = !empty($extraFields) ? json_encode($extraFields, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;

        // Metadata HTML for email
        $metaHtml = "<p><strong>Time:</strong> {$ctx->time}</p>"
            . "<p><strong>IP:</strong> {$ctx->ip}</p>"
            . "<p><strong>User-Agent:</strong> {$ctx->ua}</p>"
            . "<p><strong>Filename:</strong> " . htmlspecialchars($filename) . "</p>"
            . "<p><strong>Size:</strong> " . number_format($fileSize) . " bytes</p>";
        foreach ($extraFields as $k => $v) {
            $metaHtml .= '<p><strong>' . htmlspecialchars($k) . ':</strong> ' . htmlspecialchars($v) . '</p>';
        }

        $subject = "File: " . mb_substr($filename, 0, 80);

        // Storage
        $filePath = null;
        if ($config['storage_enabled']) {
            $filePath = StorageAction::saveFile($config['storage_path'], $filename, $fileData);
        }

        // Database
        $insertId = null;
        if (!empty($config['db_enabled'])) {
            if ($entryId !== null) {
                $attachmentId = DatabaseAction::appendFile(
                    $config['db_path'], $entryId, $subject, $filename, $fileSize, $filePath, $body, $ctx
                );
                foreach ($fieldValues as $fn => $optId) {
                    DatabaseAction::setAttachmentFieldValue($config['db_path'], $attachmentId, $fn, $optId);
                }
                $insertId = $entryId;
            } else {
                $insertId = DatabaseAction::saveFile(
                    $config['db_path'], $subject, $filename, $fileSize, $filePath, $ctx, $body
                );
                foreach ($fieldValues as $fn => $optId) {
                    DatabaseAction::setEntryFieldValue($config['db_path'], $insertId, $fn, $optId);
                }
            }
        }

        // Email
        if ($config['email_enabled'] && $emailOverride !== false) {
            EmailAction::sendFileEmail($config['email_to'], $subject, $fileData, $filename, $metaHtml, $ctx);
        }

        $response->getBody()->write((string) ($insertId ?? ''));
        return $response;
    } else {
        // ── TEXT UPLOAD ─────────────────────────────────────────────
        $rawBody = (string) $request->getBody();
        if ($rawBody === '') {
            $response->getBody()->write('Empty body');
            return $response->withStatus(400);
        }

        if (strlen($rawBody) > $config['max_text_size']) {
            $response->getBody()->write('Payload too large');
            return $response->withStatus(413);
        }

        $contentType   = $request->getHeaderLine('Content-Type');
        $subject       = "Webhook payload {$ctx->time}";
        $htmlMessage   = null;
        $decoded       = null;
        $entryId       = null;
        $emailOverride = null;
        $fieldValues   = [];
        $body          = $rawBody;

        if (stripos($contentType, 'application/json') !== false) {
            $decoded = json_decode($rawBody, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                if (isset($decoded['_id'])) {
                    $entryId = (int) $decoded['_id'];
                    unset($decoded['_id']);
                }
                if (array_key_exists('_email', $decoded)) {
                    $emailOverride = $decoded['_email'];
                    if ($emailOverride === 'false' || $emailOverride === '0' || $emailOverride === '') {
                        $emailOverride = false;
                    }
                    unset($decoded['_email']);
                }
                if (!empty($config['db_enabled'])) {
                    $customFields = DatabaseAction::getAllCustomFields($config['db_path']);
                    foreach ($customFields as $cf) {
                        $key = '_' . $cf['name'];
                        if (isset($decoded[$key])) {
                            $optId = (int) $decoded[$key];
                            if (DatabaseAction::getOptionById($config['db_path'], $cf['name'], $optId) === null) {
                                $response->getBody()->write(ucfirst($cf['name']) . ' not found');
                                return $response->withStatus(400);
                            }
                            $fieldValues[$cf['name']] = $optId;
                            unset($decoded[$key]);
                        }
                    }
                }

                if ($entryId !== null || $emailOverride !== null || !empty($fieldValues)) {
                    $body = json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                }

                if ($entryId !== null) {
                    if (!empty($config['db_enabled'])) {
                        $parent = DatabaseAction::getById($config['db_path'], $entryId);
                        if ($parent === null) {
                            $response->getBody()->write('Parent entry not found');
                            return $response->withStatus(404);
                        }
                    } else {
                        $response->getBody()->write('_id requires db_enabled');
                        return $response->withStatus(400);
                    }
                }
            }

            if (json_last_error() === JSON_ERROR_NONE && isset($decoded['text_or_url'])) {
                $sharedText  = $decoded['text_or_url'];
                $extraFields = array_diff_key($decoded, ['text_or_url' => true, '_email' => true]);

                $parsed = LogarteFormatter::parse($sharedText);
                if ($parsed !== null) {
                    $subject     = $parsed['subject'];
                    $htmlMessage = LogarteFormatter::buildHtml($parsed, $ctx);
                } else {
                    $subject     = MarkdownFormatter::extractSubject($sharedText);
                    $htmlMessage = MarkdownFormatter::buildHtml($sharedText, $subject, $ctx, $extraFields);
                }
            } elseif (json_last_error() === JSON_ERROR_NONE && is_array($decoded) && !empty($decoded)) {
                $firstValue  = reset($decoded);
                $subject     = !empty($firstValue) ? "Custom Fields: " . mb_substr((string) $firstValue, 0, 60) : "Custom Fields";
                // Inline fields-only HTML builder
                $rows = '';
                foreach ($decoded as $fk => $fv) {
                    $rows .= '<tr><td style="padding:8px 16px 8px 0;color:#757575;font-weight:bold;white-space:nowrap;vertical-align:top;">'
                        . htmlspecialchars((string) $fk) . '</td><td style="padding:8px 0;color:#333;">'
                        . htmlspecialchars((string) $fv) . '</td></tr>';
                }
                $htmlMessage = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head>'
                    . '<body style="margin:0;padding:0;background:#f0f0f0;font-family:Arial,Helvetica,sans-serif;">'
                    . '<div style="max-width:700px;margin:20px auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.1);">'
                    . '<div style="background:#37474f;color:#fff;padding:20px 24px;"><h1 style="margin:0;font-size:20px;font-weight:bold;">Custom Fields</h1></div>'
                    . '<div style="padding:20px 24px;"><table style="width:100%;border-collapse:collapse;font-size:14px;">' . $rows . '</table></div>'
                    . '<div style="background:#f5f5f5;padding:14px 24px;font-size:11px;color:#999;border-top:1px solid #e0e0e0;">'
                    . "Received: {$ctx->time} &middot; IP: {$ctx->ip} &middot; UA: {$ctx->ua}</div></div></body></html>";
            }
        }

        // Storage
        if ($config['storage_enabled']) {
            StorageAction::saveText($config['storage_path'], $body, $ctx);
        }

        // Database
        $insertId = null;
        if (!empty($config['db_enabled'])) {
            if ($entryId !== null) {
                $attachmentId = DatabaseAction::appendText($config['db_path'], $entryId, $subject, $body, $ctx);
                foreach ($fieldValues as $fn => $optId) {
                    DatabaseAction::setAttachmentFieldValue($config['db_path'], $attachmentId, $fn, $optId);
                }
                $insertId = $entryId;
            } else {
                $insertId = DatabaseAction::saveText($config['db_path'], $subject, $body, $ctx);
                foreach ($fieldValues as $fn => $optId) {
                    DatabaseAction::setEntryFieldValue($config['db_path'], $insertId, $fn, $optId);
                }
            }
        }

        // Email
        if ($config['email_enabled'] && $emailOverride !== false) {
            if ($htmlMessage !== null) {
                EmailAction::sendHtmlEmail($config['email_to'], $subject, $htmlMessage, $ctx);
            } else {
                if (stripos($contentType, 'application/json') !== false) {
                    $decoded = $decoded ?? json_decode($body, true);
                    if (is_array($decoded)) {
                        $body = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                    }
                }
                $message = "Time: {$ctx->time}\nIP: {$ctx->ip}\nMethod: POST\n"
                    . "Content-Type: {$contentType}\nUser-Agent: {$ctx->ua}\n\nBody:\n{$body}\n";
                EmailAction::sendPlainEmail($config['email_to'], $subject, $message, $ctx);
            }
        }

        $response->getBody()->write((string) ($insertId ?? ''));
        return $response;
    }
});
