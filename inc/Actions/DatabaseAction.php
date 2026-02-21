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
     * @return int Insert ID.
     */
    public static function saveFile(
        string $dbPath,
        string $subject,
        string $filename,
        int $fileSize,
        ?string $filePath,
        RequestContext $ctx
    ): int {
        $pdo = self::getConnection($dbPath);

        $stmt = $pdo->prepare(
            'INSERT INTO entries (type, subject, filename, file_path, file_size, ip, ua, created_at)
             VALUES (:type, :subject, :filename, :file_path, :file_size, :ip, :ua, :created_at)'
        );
        $stmt->execute([
            ':type'       => 'file',
            ':subject'    => $subject,
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
}
