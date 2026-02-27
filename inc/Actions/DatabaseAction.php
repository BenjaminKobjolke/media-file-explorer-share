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

        // Schema migrations
        $cols = $pdo->query("PRAGMA table_info(entries)")->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array('project', $cols, true)) {
            $pdo->exec("ALTER TABLE entries ADD COLUMN project TEXT");
            $pdo->exec("ALTER TABLE attachments ADD COLUMN project TEXT");
        }

        // Projects table + project_id migration
        $pdo->exec('CREATE TABLE IF NOT EXISTS projects (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            name       TEXT    NOT NULL UNIQUE,
            created_at TEXT    NOT NULL
        )');

        if (!in_array('project_id', $cols, true)) {
            $pdo->exec("ALTER TABLE entries ADD COLUMN project_id INTEGER");
            $attCols = $pdo->query("PRAGMA table_info(attachments)")->fetchAll(PDO::FETCH_COLUMN, 1);
            if (!in_array('project_id', $attCols, true)) {
                $pdo->exec("ALTER TABLE attachments ADD COLUMN project_id INTEGER");
            }

            // Auto-migrate: insert distinct project names into projects table, backfill project_id
            $pdo->exec("INSERT OR IGNORE INTO projects (name, created_at)
                SELECT DISTINCT project, datetime('now') FROM entries WHERE project IS NOT NULL AND project != ''");
            $pdo->exec("INSERT OR IGNORE INTO projects (name, created_at)
                SELECT DISTINCT project, datetime('now') FROM attachments WHERE project IS NOT NULL AND project != ''
                AND project NOT IN (SELECT name FROM projects)");
            $pdo->exec("UPDATE entries SET project_id = (SELECT id FROM projects WHERE projects.name = entries.project) WHERE project IS NOT NULL");
            $pdo->exec("UPDATE attachments SET project_id = (SELECT id FROM projects WHERE projects.name = attachments.project) WHERE project IS NOT NULL");
        }

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
    public static function saveText(string $dbPath, string $subject, string $body, RequestContext $ctx, ?int $projectId = null): int
    {
        $pdo = self::getConnection($dbPath);

        $stmt = $pdo->prepare(
            'INSERT INTO entries (type, subject, body, ip, ua, created_at, project_id)
             VALUES (:type, :subject, :body, :ip, :ua, :created_at, :project_id)'
        );
        $stmt->execute([
            ':type'       => 'text',
            ':subject'    => $subject,
            ':body'       => $body,
            ':ip'         => $ctx->ip,
            ':ua'         => $ctx->ua,
            ':created_at' => $ctx->time,
            ':project_id' => $projectId,
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
        ?string $body = null,
        ?int $projectId = null
    ): int {
        $pdo = self::getConnection($dbPath);

        $stmt = $pdo->prepare(
            'INSERT INTO entries (type, subject, body, filename, file_path, file_size, ip, ua, created_at, project_id)
             VALUES (:type, :subject, :body, :filename, :file_path, :file_size, :ip, :ua, :created_at, :project_id)'
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
            ':project_id' => $projectId,
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
    public static function appendText(string $dbPath, int $entryId, string $subject, string $body, RequestContext $ctx, ?int $projectId = null): int
    {
        $pdo = self::getConnection($dbPath);

        $stmt = $pdo->prepare(
            'INSERT INTO attachments (entry_id, type, subject, body, ip, ua, created_at, project_id)
             VALUES (:entry_id, :type, :subject, :body, :ip, :ua, :created_at, :project_id)'
        );
        $stmt->execute([
            ':entry_id'   => $entryId,
            ':type'       => 'text',
            ':subject'    => $subject,
            ':body'       => $body,
            ':ip'         => $ctx->ip,
            ':ua'         => $ctx->ua,
            ':created_at' => $ctx->time,
            ':project_id' => $projectId,
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
        RequestContext $ctx,
        ?int $projectId = null
    ): int {
        $pdo = self::getConnection($dbPath);

        $stmt = $pdo->prepare(
            'INSERT INTO attachments (entry_id, type, subject, body, filename, file_path, file_size, ip, ua, created_at, project_id)
             VALUES (:entry_id, :type, :subject, :body, :filename, :file_path, :file_size, :ip, :ua, :created_at, :project_id)'
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
            ':project_id' => $projectId,
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
        $entry['project_id'] = $entry['project_id'] !== null ? (int) $entry['project_id'] : null;
        unset($entry['project']);

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
            $att['project_id'] = $att['project_id'] !== null ? (int) $att['project_id'] : null;
            unset($att['project']);

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

    /**
     * Fetch a paginated list of entries with attachment counts.
     *
     * @param string $dbPath  Absolute path to the .sqlite file.
     * @param int    $page    Page number (1-based).
     * @param int    $perPage Items per page.
     * @return array {entries: array, total: int, page: int, per_page: int}
     */
    public static function getAllPaginated(string $dbPath, int $page, int $perPage, ?int $projectId = null): array
    {
        $pdo = self::getConnection($dbPath);

        $where = '';
        $countParams = [];
        if ($projectId !== null) {
            $where = ' WHERE e.project_id = :project_id';
            $countParams[':project_id'] = $projectId;
        }

        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM entries e' . $where);
        $countStmt->execute($countParams);
        $total = (int) $countStmt->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $stmt = $pdo->prepare(
            'SELECT e.*, COUNT(a.id) AS attachment_count
             FROM entries e
             LEFT JOIN attachments a ON a.entry_id = e.id'
             . $where .
            ' GROUP BY e.id
             ORDER BY e.created_at DESC
             LIMIT :limit OFFSET :offset'
        );
        if ($projectId !== null) {
            $stmt->bindValue(':project_id', $projectId, PDO::PARAM_INT);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($entries as &$entry) {
            $entry['id'] = (int) $entry['id'];
            $entry['file_size'] = $entry['file_size'] !== null ? (int) $entry['file_size'] : null;
            $entry['project_id'] = $entry['project_id'] !== null ? (int) $entry['project_id'] : null;
            $entry['attachment_count'] = (int) $entry['attachment_count'];
            unset($entry['file_path'], $entry['project']);
        }
        unset($entry);

        return [
            'entries'  => $entries,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * Delete an entry and its attachments. Returns pre-deletion data for file cleanup.
     *
     * @param string $dbPath Absolute path to the .sqlite file.
     * @param int    $id     Entry ID.
     * @return array|null Entry + attachments data (with file_path), or null if not found.
     */
    public static function deleteEntry(string $dbPath, int $id): ?array
    {
        $pdo = self::getConnection($dbPath);

        $entry = self::getById($dbPath, $id);
        if ($entry === null) {
            return null;
        }

        $attachments = self::getAttachments($dbPath, $id);

        $stmt = $pdo->prepare('DELETE FROM attachments WHERE entry_id = :entry_id');
        $stmt->execute([':entry_id' => $id]);

        $stmt = $pdo->prepare('DELETE FROM entries WHERE id = :id');
        $stmt->execute([':id' => $id]);

        $entry['attachments'] = $attachments;
        return $entry;
    }

    /**
     * Update an entry's subject and/or body. Only non-null fields are updated.
     *
     * @param string      $dbPath  Absolute path to the .sqlite file.
     * @param int         $id      Entry ID.
     * @param string|null $subject New subject, or null to leave unchanged.
     * @param string|null $body    New body, or null to leave unchanged.
     * @return array|null Updated row, or null if not found.
     */
    public static function updateEntry(string $dbPath, int $id, ?string $subject, ?string $body): ?array
    {
        $entry = self::getById($dbPath, $id);
        if ($entry === null) {
            return null;
        }

        $fields = [];
        $params = [':id' => $id];

        if ($subject !== null) {
            $fields[] = 'subject = :subject';
            $params[':subject'] = $subject;
        }
        if ($body !== null) {
            $fields[] = 'body = :body';
            $params[':body'] = $body;
        }

        if (!empty($fields)) {
            $pdo = self::getConnection($dbPath);
            $sql = 'UPDATE entries SET ' . implode(', ', $fields) . ' WHERE id = :id';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        }

        return self::getById($dbPath, $id);
    }

    /**
     * Delete an attachment. Returns pre-deletion data for file cleanup.
     *
     * @param string $dbPath Absolute path to the .sqlite file.
     * @param int    $id     Attachment ID.
     * @return array|null Attachment data (with file_path), or null if not found.
     */
    public static function deleteAttachment(string $dbPath, int $id): ?array
    {
        $attachment = self::getAttachmentById($dbPath, $id);
        if ($attachment === null) {
            return null;
        }

        $pdo = self::getConnection($dbPath);
        $stmt = $pdo->prepare('DELETE FROM attachments WHERE id = :id');
        $stmt->execute([':id' => $id]);

        return $attachment;
    }

    // -- Project CRUD --------------------------------------------------------

    /**
     * Fetch all projects with entry counts.
     *
     * @param string $dbPath Absolute path to the .sqlite file.
     * @return array List of project rows with entry_count.
     */
    public static function getAllProjects(string $dbPath): array
    {
        $pdo = self::getConnection($dbPath);
        $stmt = $pdo->query(
            'SELECT p.*, COUNT(e.id) AS entry_count
             FROM projects p
             LEFT JOIN entries e ON e.project_id = p.id
             GROUP BY p.id
             ORDER BY p.name ASC'
        );
        $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($projects as &$project) {
            $project['id'] = (int) $project['id'];
            $project['entry_count'] = (int) $project['entry_count'];
        }
        unset($project);
        return $projects;
    }

    /**
     * Fetch a single project by its ID.
     *
     * @param string $dbPath Absolute path to the .sqlite file.
     * @param int    $id     Project ID.
     * @return array|null Row as associative array, or null if not found.
     */
    public static function getProjectById(string $dbPath, int $id): ?array
    {
        $pdo = self::getConnection($dbPath);
        $stmt = $pdo->prepare('SELECT * FROM projects WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }
        $row['id'] = (int) $row['id'];
        return $row;
    }

    /**
     * Create a new project.
     *
     * @param string $dbPath Absolute path to the .sqlite file.
     * @param string $name   Project name (must be unique).
     * @return int Insert ID.
     */
    public static function createProject(string $dbPath, string $name): int
    {
        $pdo = self::getConnection($dbPath);
        $stmt = $pdo->prepare(
            'INSERT INTO projects (name, created_at) VALUES (:name, :created_at)'
        );
        $stmt->execute([
            ':name'       => $name,
            ':created_at' => date('c'),
        ]);
        return (int) $pdo->lastInsertId();
    }

    /**
     * Rename an existing project.
     *
     * @param string $dbPath Absolute path to the .sqlite file.
     * @param int    $id     Project ID.
     * @param string $name   New project name (must be unique).
     * @return array|null Updated row, or null if not found.
     */
    public static function updateProject(string $dbPath, int $id, string $name): ?array
    {
        $project = self::getProjectById($dbPath, $id);
        if ($project === null) {
            return null;
        }

        $pdo = self::getConnection($dbPath);
        $stmt = $pdo->prepare('UPDATE projects SET name = :name WHERE id = :id');
        $stmt->execute([':name' => $name, ':id' => $id]);

        return self::getProjectById($dbPath, $id);
    }

    /**
     * Delete a project. Sets project_id to NULL on associated entries/attachments.
     *
     * @param string $dbPath Absolute path to the .sqlite file.
     * @param int    $id     Project ID.
     * @return bool True if deleted, false if not found.
     */
    public static function deleteProject(string $dbPath, int $id): bool
    {
        $project = self::getProjectById($dbPath, $id);
        if ($project === null) {
            return false;
        }

        $pdo = self::getConnection($dbPath);
        $stmt = $pdo->prepare('UPDATE entries SET project_id = NULL WHERE project_id = :id');
        $stmt->execute([':id' => $id]);
        $stmt = $pdo->prepare('UPDATE attachments SET project_id = NULL WHERE project_id = :id');
        $stmt->execute([':id' => $id]);
        $stmt = $pdo->prepare('DELETE FROM projects WHERE id = :id');
        $stmt->execute([':id' => $id]);

        return true;
    }
}
