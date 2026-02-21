<?php
declare(strict_types=1);

namespace App\Actions;

use App\RequestContext;

/**
 * Save uploaded files or text payloads to disk.
 */
class StorageAction
{
    /**
     * Save an uploaded file to {path}/files/{timestamp}_{filename}.
     *
     * @param string $basePath  Root storage directory from config.
     * @param string $filename  Original filename.
     * @param string $fileData  Raw file contents.
     * @return void Exits on failure.
     */
    public static function saveFile(string $basePath, string $filename, string $fileData): void
    {
        $dir = rtrim($basePath, '/') . '/files';

        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            http_response_code(500);
            exit("Failed to create storage directory: {$dir}");
        }

        // Sanitize filename — keep only safe characters
        $safe = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        $dest = $dir . '/' . date('Ymd_His') . '_' . $safe;

        if (file_put_contents($dest, $fileData) === false) {
            http_response_code(500);
            exit("Failed to write file to: {$dest}");
        }
    }

    /**
     * Append a text payload to {path}/texts/YYYY-MM-DD.log.
     *
     * @param string         $basePath  Root storage directory from config.
     * @param string         $body      Raw text payload.
     * @param RequestContext  $ctx       Request metadata.
     * @return void Exits on failure.
     */
    public static function saveText(string $basePath, string $body, RequestContext $ctx): void
    {
        $dir = rtrim($basePath, '/') . '/texts';

        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            http_response_code(500);
            exit("Failed to create storage directory: {$dir}");
        }

        $logFile = $dir . '/' . date('Y-m-d') . '.log';

        $entry = "--- {$ctx->time} | IP: {$ctx->ip} | UA: {$ctx->ua} ---\n"
            . $body . "\n\n";

        if (file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX) === false) {
            http_response_code(500);
            exit("Failed to write to log: {$logFile}");
        }
    }
}
