<?php
declare(strict_types=1);

namespace App\Actions;

use App\RequestContext;
use PDO;

/**
 * Save webhook entries to a SQLite database.
 */
class DatabaseAction
{
    /**
     * Open (and initialise) the SQLite database.
     *
     * @param string $dbPath Absolute path to the .sqlite file.
     * @return PDO
     */
    private static function getConnection(string $dbPath): PDO
    {
        $dir = dirname($dbPath);
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            throw new \RuntimeException("Failed to create database directory: {$dir}");
        }

        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $pdo->exec('CREATE TABLE IF NOT EXISTS entries (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            type       TEXT    NOT NULL,
            subject    TEXT    NOT NULL,
            body       TEXT,
            filename   TEXT,
            file_path  TEXT,
            file_size  INTEGER,
            ip         TEXT    NOT NULL,
            ua         TEXT    NOT NULL,
            created_at TEXT    NOT NULL
        )');

        $pdo->exec('CREATE TABLE IF NOT EXISTS attachments (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            entry_id   INTEGER NOT NULL REFERENCES entries(id),
            type       TEXT    NOT NULL,
            subject    TEXT,
            body       TEXT,
            filename   TEXT,
            file_path  TEXT,
            file_size  INTEGER,
            ip         TEXT    NOT NULL,
            ua         TEXT    NOT NULL,
            created_at TEXT    NOT NULL
        )');

        return $pdo;
    }

    /**
     * Insert a text entry and return the new row ID.
     *
     * @param string         $dbPath  Absolute path to the .sqlite file.
     * @param string         $subject Email-style subject line.
     * @param string         $body    Raw text payload.
     * @param RequestContext $ctx     Request metadata.
     * @return int Insert ID.
     */
    public static function saveText(string $dbPath, string $subject, string $body, RequestContext $ctx): int
    {
        $pdo = self::getConnection($dbPath);

        $stmt = $pdo->prepare(
            'INSERT INTO entries (type, subject, body, ip, ua, created_at)
             VALUES (:type, :subject, :body, :ip, :ua, :created_at)'
        );
        $stmt->execute([
            ':type'       => 'text',
            ':subject'    => $subject,
            ':body'       => $body,
            ':ip'         => $ctx->ip,
            ':ua'         => $ctx->ua,
            ':created_at' => $ctx->time,
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * Insert a file entry and return the new row ID.
     *
     * @param string         $dbPath   Absolute path to the .sqlite file.
     * @param string         $subject  Email-style subject line.
     * @param string         $filename Original filename.
     * @param int            $fileSize Size in bytes.
     * @param string|null    $filePath Disk path from StorageAction (null if storage disabled).
     * @param RequestContext $ctx      Request metadata.
     * @param string|null    $body     Optional JSON-encoded extra POST fields.
     * @return int Insert ID.
     */
    public static function saveFile(
        string $dbPath,
        string $subject,
        string $filename,
        int $fileSize,
        ?string $filePath,
        RequestContext $ctx,
        ?string $body = null
    ): int {
        $pdo = self::getConnection($dbPath);

        $stmt = $pdo->prepare(
            'INSERT INTO entries (type, subject, body, filename, file_path, file_size, ip, ua, created_at)
             VALUES (:type, :subject, :body, :filename, :file_path, :file_size, :ip, :ua, :created_at)'
        );
        $stmt->execute([
            ':type'       => 'file',
            ':subject'    => $subject,
            ':body'       => $body,
            ':filename'   => $filename,
            ':file_path'  => $filePath,
            ':file_size'  => $fileSize,
            ':ip'         => $ctx->ip,
            ':ua'         => $ctx->ua,
            ':created_at' => $ctx->time,
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * Fetch a single entry by its ID.
     *
     * @param string $dbPath Absolute path to the .sqlite file.
     * @param int    $id     Row ID.
     * @return array|null Row as associative array, or null if not found.
     */
    public static function getById(string $dbPath, int $id): ?array
    {
        $pdo = self::getConnection($dbPath);
        $stmt = $pdo->prepare('SELECT * FROM entries WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /**
     * Insert a text attachment for an existing entry.
     *
     * @param string         $dbPath  Absolute path to the .sqlite file.
     * @param int            $entryId Parent entry ID.
     * @param string         $subject Subject line.
     * @param string         $body    Raw text payload.
     * @param RequestContext $ctx     Request metadata.
     * @return int Attachment insert ID.
     */
    public static function appendText(string $dbPath, int $entryId, string $subject, string $body, RequestContext $ctx): int
    {
        $pdo = self::getConnection($dbPath);

        $stmt = $pdo->prepare(
            'INSERT INTO attachments (entry_id, type, subject, body, ip, ua, created_at)
             VALUES (:entry_id, :type, :subject, :body, :ip, :ua, :created_at)'
        );
        $stmt->execute([
            ':entry_id'   => $entryId,
            ':type'       => 'text',
            ':subject'    => $subject,
            ':body'       => $body,
            ':ip'         => $ctx->ip,
            ':ua'         => $ctx->ua,
            ':created_at' => $ctx->time,
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * Insert a file attachment for an existing entry.
     *
     * @param string         $dbPath   Absolute path to the .sqlite file.
     * @param int            $entryId  Parent entry ID.
     * @param string         $subject  Subject line.
     * @param string         $filename Original filename.
     * @param int            $fileSize Size in bytes.
     * @param string|null    $filePath Disk path from StorageAction (null if storage disabled).
     * @param string|null    $body     Optional JSON-encoded extra POST fields.
     * @param RequestContext $ctx      Request metadata.
     * @return int Attachment insert ID.
     */
    public static function appendFile(
        string $dbPath,
        int $entryId,
        string $subject,
        string $filename,
        int $fileSize,
        ?string $filePath,
        ?string $body,
        RequestContext $ctx
    ): int {
        $pdo = self::getConnection($dbPath);

        $stmt = $pdo->prepare(
            'INSERT INTO attachments (entry_id, type, subject, body, filename, file_path, file_size, ip, ua, created_at)
             VALUES (:entry_id, :type, :subject, :body, :filename, :file_path, :file_size, :ip, :ua, :created_at)'
        );
        $stmt->execute([
            ':entry_id'   => $entryId,
            ':type'       => 'file',
            ':subject'    => $subject,
            ':body'       => $body,
            ':filename'   => $filename,
            ':file_path'  => $filePath,
            ':file_size'  => $fileSize,
            ':ip'         => $ctx->ip,
            ':ua'         => $ctx->ua,
            ':created_at' => $ctx->time,
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * Fetch all attachments for a given entry.
     *
     * @param string $dbPath  Absolute path to the .sqlite file.
     * @param int    $entryId Parent entry ID.
     * @return array List of attachment rows.
     */
    public static function getAttachments(string $dbPath, int $entryId): array
    {
        $pdo = self::getConnection($dbPath);
        $stmt = $pdo->prepare('SELECT * FROM attachments WHERE entry_id = :entry_id ORDER BY created_at ASC');
        $stmt->execute([':entry_id' => $entryId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Fetch an entry with its attachments and file URLs for the API.
     *
     * @param string $dbPath  Absolute path to the .sqlite file.
     * @param int    $id      Entry ID.
     * @param string $baseUrl Base URL for building file download links.
     * @return array|null Entry with attachments, or null if not found.
     */
    public static function getByIdWithAttachments(string $dbPath, int $id, string $baseUrl): ?array
    {
        $entry = self::getById($dbPath, $id);
        if ($entry === null) {
            return null;
        }

        // Cast integer fields
        $entry['id'] = (int) $entry['id'];
        $entry['file_size'] = $entry['file_size'] !== null ? (int) $entry['file_size'] : null;

        // Decode body from JSON string to object
        if ($entry['body'] !== null) {
            $decoded = json_decode($entry['body'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $entry['body'] = $decoded;
            }
        }

        // Add file_url for file-type entries with stored files
        if ($entry['type'] === 'file' && $entry['file_path'] !== null) {
            $entry['file_url'] = $baseUrl . '/entries/' . $entry['id'] . '/file';
        }

        // Strip file_path from response
        unset($entry['file_path']);

        // Add attachments
        $attachments = self::getAttachments($dbPath, $id);
        foreach ($attachments as &$att) {
            $att['id'] = (int) $att['id'];
            $att['entry_id'] = (int) $att['entry_id'];
            $att['file_size'] = $att['file_size'] !== null ? (int) $att['file_size'] : null;

            // Decode body from JSON string to object
            if ($att['body'] !== null) {
                $decoded = json_decode($att['body'], true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $att['body'] = $decoded;
                }
            }

            // Add file_url for file-type attachments with stored files
            if ($att['type'] === 'file' && $att['file_path'] !== null) {
                $att['file_url'] = $baseUrl . '/files/' . $att['id'];
            }

            // Strip internal fields from response
            unset($att['file_path'], $att['ip'], $att['ua'], $att['entry_id']);
        }
        unset($att);

        $entry['attachments'] = $attachments;

        return $entry;
    }

    /**
     * Fetch a single attachment by its ID.
     *
     * @param string $dbPath Absolute path to the .sqlite file.
     * @param int    $id     Attachment row ID.
     * @return array|null Row as associative array, or null if not found.
     */
    public static function getAttachmentById(string $dbPath, int $id): ?array
    {
        $pdo = self::getConnection($dbPath);
        $stmt = $pdo->prepare('SELECT * FROM attachments WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }
}
