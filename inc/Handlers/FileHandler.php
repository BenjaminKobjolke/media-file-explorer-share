<?php
declare(strict_types=1);

namespace App\Handlers;

use App\Actions\DatabaseAction;
use App\Actions\EmailAction;
use App\Actions\StorageAction;
use App\RequestContext;

/**
 * Handles multipart/form-data file uploads.
 */
class FileHandler
{
    /**
     * Process a file upload from $_FILES['file'].
     *
     * @param array          $config  Global config array.
     * @param RequestContext  $ctx     Request metadata.
     * @return int|null Insert ID when database is enabled, null otherwise.
     */
    public static function handle(array $config, RequestContext $ctx): ?int
    {
        $file = $_FILES['file'];

        // -- Validate upload error code --------------------
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload_max_filesize',
                UPLOAD_ERR_FORM_SIZE  => 'File exceeds form MAX_FILE_SIZE',
                UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded',
                UPLOAD_ERR_NO_FILE    => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder on server',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION  => 'Upload stopped by a PHP extension',
            ];
            $msg = $errorMessages[$file['error']] ?? "Unknown upload error ({$file['error']})";
            http_response_code(400);
            exit("Upload error: {$msg}");
        }

        // -- Size limit ------------------------------------
        if ($file['size'] > $config['max_file_size']) {
            $limitMB = round($config['max_file_size'] / (1024 * 1024), 1);
            http_response_code(413);
            exit("File too large (max {$limitMB} MB)");
        }

        $filename = $file['name'] ?: 'attachment';
        $tmpPath  = $file['tmp_name'];
        $fileData = file_get_contents($tmpPath);
        if ($fileData === false) {
            http_response_code(500);
            exit('Failed to read uploaded file');
        }

        // -- Extract reserved _-prefixed fields -------------
        $entryId = isset($_POST['_id']) ? (int) $_POST['_id'] : null;
        $emailOverride = null;
        if (isset($_POST['_email'])) {
            $raw = $_POST['_email'];
            $emailOverride = ($raw === 'false' || $raw === '0' || $raw === '') ? false : $raw;
        }
        // Extract dynamic custom field values
        $fieldValues = [];
        $fieldExcludes = [];
        if (!empty($config['db_enabled'])) {
            $customFields = DatabaseAction::getAllCustomFields($config['db_path']);
            foreach ($customFields as $cf) {
                $key = '_' . $cf['name'];
                $fieldExcludes[$key] = true;
                if (isset($_POST[$key])) {
                    $optId = (int) $_POST[$key];
                    if (DatabaseAction::getOptionById($config['db_path'], $cf['name'], $optId) === null) {
                        http_response_code(400);
                        exit(ucfirst($cf['name']) . ' not found');
                    }
                    $fieldValues[$cf['name']] = $optId;
                }
            }
        }

        if ($entryId !== null && !empty($config['db_enabled'])) {
            $parent = DatabaseAction::getById($config['db_path'], $entryId);
            if ($parent === null) {
                http_response_code(404);
                exit('Parent entry not found');
            }
        } elseif ($entryId !== null) {
            http_response_code(400);
            exit('_id requires db_enabled');
        }

        // -- Capture extra POST fields as JSON body --------
        $extraFields = array_diff_key($_POST, array_merge(['file' => true, '_id' => true, '_email' => true], $fieldExcludes));
        $body = !empty($extraFields) ? json_encode($extraFields, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;

        // -- Build metadata HTML --------------------------
        $metaHtml = "<p><strong>Time:</strong> {$ctx->time}</p>"
            . "<p><strong>IP:</strong> {$ctx->ip}</p>"
            . "<p><strong>User-Agent:</strong> {$ctx->ua}</p>"
            . "<p><strong>Filename:</strong> " . htmlspecialchars($filename) . "</p>"
            . "<p><strong>Size:</strong> " . number_format($file['size']) . " bytes</p>";

        foreach ($extraFields as $key => $value) {
            $metaHtml .= '<p><strong>' . htmlspecialchars($key) . ':</strong> '
                . htmlspecialchars($value) . '</p>';
        }

        $subject = "File: " . mb_substr($filename, 0, 80);

        // -- Storage action --------------------------------
        $filePath = null;
        if ($config['storage_enabled']) {
            $filePath = StorageAction::saveFile(
                $config['storage_path'],
                $filename,
                $fileData
            );
        }

        // -- Database action -------------------------------
        $insertId = null;
        if (!empty($config['db_enabled'])) {
            if ($entryId !== null) {
                // Append mode: add as attachment to existing entry
                $attachmentId = DatabaseAction::appendFile(
                    $config['db_path'],
                    $entryId,
                    $subject,
                    $filename,
                    $file['size'],
                    $filePath,
                    $body,
                    $ctx
                );
                // Write custom field pivot rows for attachment
                foreach ($fieldValues as $fn => $optId) {
                    DatabaseAction::setAttachmentFieldValue($config['db_path'], $attachmentId, $fn, $optId);
                }
                $insertId = $entryId;
            } else {
                // Normal mode: create new entry
                $insertId = DatabaseAction::saveFile(
                    $config['db_path'],
                    $subject,
                    $filename,
                    $file['size'],
                    $filePath,
                    $ctx,
                    $body
                );
                // Write custom field pivot rows for entry
                foreach ($fieldValues as $fn => $optId) {
                    DatabaseAction::setEntryFieldValue($config['db_path'], $insertId, $fn, $optId);
                }
            }
        }

        // -- Email action ----------------------------------
        $shouldEmail = $config['email_enabled'] && $emailOverride !== false;
        if ($shouldEmail) {
            EmailAction::sendFileEmail(
                $config['email_to'],
                $subject,
                $fileData,
                $filename,
                $metaHtml,
                $ctx
            );
        }

        return $insertId;
    }
}
