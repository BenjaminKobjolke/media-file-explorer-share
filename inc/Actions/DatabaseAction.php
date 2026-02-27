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
    /** @var PDO[] Cached connections keyed by database path. */
    private static array $connections = [];

    /**
     * Open (and initialise) the SQLite database.
     *
     * @param string $dbPath Absolute path to the .sqlite file.
     * @return PDO
     */
    private static function getConnection(string $dbPath): PDO
    {
        if (isset(self::$connections[$dbPath])) {
            return self::$connections[$dbPath];
        }

        $dir = dirname($dbPath);
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            throw new \RuntimeException("Failed to create database directory: {$dir}");
        }

        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA busy_timeout = 5000');
        $pdo->exec('PRAGMA journal_mode = WAL');

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

        // Custom fields tables
        $pdo->exec('CREATE TABLE IF NOT EXISTS custom_fields (
            name        TEXT PRIMARY KEY,
            description TEXT,
            sort_order  INTEGER DEFAULT 0,
            created_at  TEXT NOT NULL
        )');

        $pdo->exec('CREATE TABLE IF NOT EXISTS field_options (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            field_name  TEXT NOT NULL REFERENCES custom_fields(name),
            name        TEXT NOT NULL,
            created_at  TEXT NOT NULL,
            UNIQUE(field_name, name)
        )');

        $pdo->exec('CREATE TABLE IF NOT EXISTS entry_field_values (
            entry_id    INTEGER NOT NULL REFERENCES entries(id),
            field_name  TEXT NOT NULL,
            option_id   INTEGER NOT NULL REFERENCES field_options(id),
            PRIMARY KEY (entry_id, field_name)
        )');

        $pdo->exec('CREATE TABLE IF NOT EXISTS attachment_field_values (
            attachment_id INTEGER NOT NULL REFERENCES attachments(id),
            field_name    TEXT NOT NULL,
            option_id     INTEGER NOT NULL REFERENCES field_options(id),
            PRIMARY KEY (attachment_id, field_name)
        )');

        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_fo_field ON field_options(field_name)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_efv_lookup ON entry_field_values(field_name, option_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_afv_lookup ON attachment_field_values(field_name, option_id)');

        // Seed default custom fields (idempotent)
        $defaults = [
            'project' => ['description' => 'Tag entry with a project', 'sort_order' => -1, 'options' => []],
            'status' => ['description' => 'Entry lifecycle status', 'sort_order' => 0, 'options' => ['open', 'in progress', 'closed']],
            'resolution' => ['description' => 'Resolution reason for the entry', 'sort_order' => 1, 'options' => ['Fixed', 'Duplicate', "Won't Fix", 'Not a Bug']],
        ];
        foreach ($defaults as $fieldName => $def) {
            $pdo->exec("INSERT OR IGNORE INTO custom_fields (name, description, sort_order, created_at) VALUES ("
                . $pdo->quote($fieldName) . ", "
                . $pdo->quote($def['description']) . ", "
                . $def['sort_order'] . ", "
                . $pdo->quote(date('c')) . ")");
            foreach ($def['options'] as $optName) {
                $pdo->exec("INSERT OR IGNORE INTO field_options (field_name, name, created_at) VALUES ("
                    . $pdo->quote($fieldName) . ", "
                    . $pdo->quote($optName) . ", "
                    . $pdo->quote(date('c')) . ")");
            }
        }

        self::$connections[$dbPath] = $pdo;
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

        // Add custom field values
        $fieldValues = self::getEntryFieldValues($dbPath, (int) $entry['id']);
        foreach ($fieldValues as $fn => $optId) {
            $entry[$fn . '_id'] = $optId;
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

            // Add custom field values
            $attFieldValues = self::getAttachmentFieldValues($dbPath, (int) $att['id']);
            foreach ($attFieldValues as $fn => $optId) {
                $att[$fn . '_id'] = $optId;
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
    public static function getAllPaginated(string $dbPath, int $page, int $perPage, array $fieldFilters = []): array
    {
        $pdo = self::getConnection($dbPath);

        $joins = '';
        $countParams = [];

        // Dynamic field filter joins
        $joinIdx = 0;
        foreach ($fieldFilters as $fn => $optId) {
            $alias = 'efv' . $joinIdx;
            $joins .= " INNER JOIN entry_field_values {$alias}"
                    . " ON {$alias}.entry_id = e.id"
                    . " AND {$alias}.field_name = :fn{$joinIdx}"
                    . " AND {$alias}.option_id = :fv{$joinIdx}";
            $countParams[":fn{$joinIdx}"] = $fn;
            $countParams[":fv{$joinIdx}"] = $optId;
            $joinIdx++;
        }

        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM entries e' . $joins);
        $countStmt->execute($countParams);
        $total = (int) $countStmt->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $stmt = $pdo->prepare(
            'SELECT e.*, COUNT(a.id) AS attachment_count
             FROM entries e
             LEFT JOIN attachments a ON a.entry_id = e.id'
             . $joins .
            ' GROUP BY e.id
             ORDER BY e.created_at DESC
             LIMIT :limit OFFSET :offset'
        );
        $joinIdx = 0;
        foreach ($fieldFilters as $fn => $optId) {
            $stmt->bindValue(":fn{$joinIdx}", $fn, PDO::PARAM_STR);
            $stmt->bindValue(":fv{$joinIdx}", $optId, PDO::PARAM_INT);
            $joinIdx++;
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($entries as &$entry) {
            $entry['id'] = (int) $entry['id'];
            $entry['file_size'] = $entry['file_size'] !== null ? (int) $entry['file_size'] : null;
            $entry['attachment_count'] = (int) $entry['attachment_count'];
            unset($entry['file_path']);

            // Add custom field values
            $fv = self::getEntryFieldValues($dbPath, $entry['id']);
            foreach ($fv as $fn => $optId) {
                $entry[$fn . '_id'] = $optId;
            }
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

        // Clean up pivot rows for attachments
        foreach ($attachments as $att) {
            $stmt = $pdo->prepare('DELETE FROM attachment_field_values WHERE attachment_id = :id');
            $stmt->execute([':id' => $att['id']]);
        }

        $stmt = $pdo->prepare('DELETE FROM attachments WHERE entry_id = :entry_id');
        $stmt->execute([':entry_id' => $id]);

        // Clean up entry pivot rows
        $stmt = $pdo->prepare('DELETE FROM entry_field_values WHERE entry_id = :id');
        $stmt->execute([':id' => $id]);

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
    public static function updateEntry(string $dbPath, int $id, ?string $subject, ?string $body, array $fieldValues = []): ?array
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

        foreach ($fieldValues as $fn => $optId) {
            self::setEntryFieldValue($dbPath, $id, $fn, $optId);
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
        $stmt = $pdo->prepare('DELETE FROM attachment_field_values WHERE attachment_id = :id');
        $stmt->execute([':id' => $id]);
        $stmt = $pdo->prepare('DELETE FROM attachments WHERE id = :id');
        $stmt->execute([':id' => $id]);

        return $attachment;
    }

    // -- Custom field CRUD ---------------------------------------------------

    /**
     * Fetch all custom fields with option counts.
     *
     * @param string $dbPath Absolute path to the .sqlite file.
     * @return array List of custom field rows with option_count.
     */
    public static function getAllCustomFields(string $dbPath): array
    {
        $pdo = self::getConnection($dbPath);
        $stmt = $pdo->query(
            'SELECT cf.*, COUNT(fo.id) AS option_count
             FROM custom_fields cf
             LEFT JOIN field_options fo ON fo.field_name = cf.name
             GROUP BY cf.name
             ORDER BY cf.sort_order ASC, cf.name ASC'
        );
        $fields = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($fields as &$field) {
            $field['sort_order'] = (int) $field['sort_order'];
            $field['option_count'] = (int) $field['option_count'];
        }
        unset($field);
        return $fields;
    }

    /**
     * Fetch a single custom field by name.
     *
     * @param string $dbPath Absolute path to the .sqlite file.
     * @param string $name   Field name.
     * @return array|null Row as associative array, or null if not found.
     */
    public static function getCustomFieldByName(string $dbPath, string $name): ?array
    {
        $pdo = self::getConnection($dbPath);
        $stmt = $pdo->prepare('SELECT * FROM custom_fields WHERE name = :name');
        $stmt->execute([':name' => $name]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }
        $row['sort_order'] = (int) $row['sort_order'];
        return $row;
    }

    /**
     * Create a new custom field.
     *
     * @param string $dbPath      Absolute path to the .sqlite file.
     * @param string $name        Field name (must be unique).
     * @param string $description Field description.
     * @param int    $sortOrder   Display sort order.
     */
    public static function createCustomField(string $dbPath, string $name, string $description, int $sortOrder = 0): void
    {
        $pdo = self::getConnection($dbPath);
        $stmt = $pdo->prepare(
            'INSERT INTO custom_fields (name, description, sort_order, created_at)
             VALUES (:name, :description, :sort_order, :created_at)'
        );
        $stmt->execute([
            ':name'        => $name,
            ':description' => $description,
            ':sort_order'  => $sortOrder,
            ':created_at'  => date('c'),
        ]);
    }

    /**
     * Update a custom field's description and/or sort_order.
     *
     * @param string   $dbPath      Absolute path to the .sqlite file.
     * @param string   $name        Field name.
     * @param string|null $description New description, or null to leave unchanged.
     * @param int|null $sortOrder   New sort order, or null to leave unchanged.
     * @return array|null Updated row, or null if not found.
     */
    public static function updateCustomField(string $dbPath, string $name, ?string $description, ?int $sortOrder): ?array
    {
        $field = self::getCustomFieldByName($dbPath, $name);
        if ($field === null) {
            return null;
        }

        $fields = [];
        $params = [':name' => $name];

        if ($description !== null) {
            $fields[] = 'description = :description';
            $params[':description'] = $description;
        }
        if ($sortOrder !== null) {
            $fields[] = 'sort_order = :sort_order';
            $params[':sort_order'] = $sortOrder;
        }

        if (!empty($fields)) {
            $pdo = self::getConnection($dbPath);
            $sql = 'UPDATE custom_fields SET ' . implode(', ', $fields) . ' WHERE name = :name';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        }

        return self::getCustomFieldByName($dbPath, $name);
    }

    /**
     * Delete a custom field and all associated options + pivot rows.
     *
     * @param string $dbPath Absolute path to the .sqlite file.
     * @param string $name   Field name.
     * @return bool True if deleted, false if not found.
     */
    public static function deleteCustomField(string $dbPath, string $name): bool
    {
        $field = self::getCustomFieldByName($dbPath, $name);
        if ($field === null) {
            return false;
        }

        $pdo = self::getConnection($dbPath);
        $stmt = $pdo->prepare('DELETE FROM entry_field_values WHERE field_name = :name');
        $stmt->execute([':name' => $name]);
        $stmt = $pdo->prepare('DELETE FROM attachment_field_values WHERE field_name = :name');
        $stmt->execute([':name' => $name]);
        $stmt = $pdo->prepare('DELETE FROM field_options WHERE field_name = :name');
        $stmt->execute([':name' => $name]);
        $stmt = $pdo->prepare('DELETE FROM custom_fields WHERE name = :name');
        $stmt->execute([':name' => $name]);

        return true;
    }

    // -- Field option CRUD ---------------------------------------------------

    /**
     * Fetch all options for a given custom field with entry counts.
     *
     * @param string $dbPath    Absolute path to the .sqlite file.
     * @param string $fieldName Custom field name.
     * @return array List of option rows with entry_count.
     */
    public static function getAllOptions(string $dbPath, string $fieldName): array
    {
        $pdo = self::getConnection($dbPath);
        $stmt = $pdo->prepare(
            'SELECT fo.*, COUNT(efv.entry_id) AS entry_count
             FROM field_options fo
             LEFT JOIN entry_field_values efv ON efv.option_id = fo.id AND efv.field_name = fo.field_name
             WHERE fo.field_name = :field_name
             GROUP BY fo.id
             ORDER BY fo.id ASC'
        );
        $stmt->execute([':field_name' => $fieldName]);
        $options = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($options as &$opt) {
            $opt['id'] = (int) $opt['id'];
            $opt['entry_count'] = (int) $opt['entry_count'];
        }
        unset($opt);
        return $options;
    }

    /**
     * Fetch a single option by ID, scoped to a field.
     *
     * @param string $dbPath    Absolute path to the .sqlite file.
     * @param string $fieldName Custom field name.
     * @param int    $id        Option ID.
     * @return array|null Row as associative array, or null if not found.
     */
    public static function getOptionById(string $dbPath, string $fieldName, int $id): ?array
    {
        $pdo = self::getConnection($dbPath);
        $stmt = $pdo->prepare('SELECT * FROM field_options WHERE id = :id AND field_name = :field_name');
        $stmt->execute([':id' => $id, ':field_name' => $fieldName]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }
        $row['id'] = (int) $row['id'];
        return $row;
    }

    /**
     * Create a new option for a custom field.
     *
     * @param string $dbPath    Absolute path to the .sqlite file.
     * @param string $fieldName Custom field name.
     * @param string $name      Option name (must be unique within field).
     * @return int Insert ID.
     */
    public static function createOption(string $dbPath, string $fieldName, string $name): int
    {
        $pdo = self::getConnection($dbPath);
        $stmt = $pdo->prepare(
            'INSERT INTO field_options (field_name, name, created_at)
             VALUES (:field_name, :name, :created_at)'
        );
        $stmt->execute([
            ':field_name' => $fieldName,
            ':name'       => $name,
            ':created_at' => date('c'),
        ]);
        return (int) $pdo->lastInsertId();
    }

    /**
     * Rename an option within a custom field.
     *
     * @param string $dbPath    Absolute path to the .sqlite file.
     * @param string $fieldName Custom field name.
     * @param int    $id        Option ID.
     * @param string $name      New option name.
     * @return array|null Updated row, or null if not found.
     */
    public static function updateOption(string $dbPath, string $fieldName, int $id, string $name): ?array
    {
        $option = self::getOptionById($dbPath, $fieldName, $id);
        if ($option === null) {
            return null;
        }

        $pdo = self::getConnection($dbPath);
        $stmt = $pdo->prepare('UPDATE field_options SET name = :name WHERE id = :id AND field_name = :field_name');
        $stmt->execute([':name' => $name, ':id' => $id, ':field_name' => $fieldName]);

        return self::getOptionById($dbPath, $fieldName, $id);
    }

    /**
     * Delete an option and its pivot rows.
     *
     * @param string $dbPath    Absolute path to the .sqlite file.
     * @param string $fieldName Custom field name.
     * @param int    $id        Option ID.
     * @return bool True if deleted, false if not found.
     */
    public static function deleteOption(string $dbPath, string $fieldName, int $id): bool
    {
        $option = self::getOptionById($dbPath, $fieldName, $id);
        if ($option === null) {
            return false;
        }

        $pdo = self::getConnection($dbPath);
        $stmt = $pdo->prepare('DELETE FROM entry_field_values WHERE option_id = :id AND field_name = :field_name');
        $stmt->execute([':id' => $id, ':field_name' => $fieldName]);
        $stmt = $pdo->prepare('DELETE FROM attachment_field_values WHERE option_id = :id AND field_name = :field_name');
        $stmt->execute([':id' => $id, ':field_name' => $fieldName]);
        $stmt = $pdo->prepare('DELETE FROM field_options WHERE id = :id AND field_name = :field_name');
        $stmt->execute([':id' => $id, ':field_name' => $fieldName]);

        return true;
    }

    // -- Pivot methods -------------------------------------------------------

    /**
     * Set a field value on an entry (upsert).
     *
     * @param string $dbPath    Absolute path to the .sqlite file.
     * @param int    $entryId   Entry ID.
     * @param string $fieldName Custom field name.
     * @param int    $optionId  Option ID.
     */
    public static function setEntryFieldValue(string $dbPath, int $entryId, string $fieldName, int $optionId): void
    {
        $pdo = self::getConnection($dbPath);
        $stmt = $pdo->prepare(
            'INSERT OR REPLACE INTO entry_field_values (entry_id, field_name, option_id)
             VALUES (:entry_id, :field_name, :option_id)'
        );
        $stmt->execute([
            ':entry_id'   => $entryId,
            ':field_name' => $fieldName,
            ':option_id'  => $optionId,
        ]);
    }

    /**
     * Set a field value on an attachment (upsert).
     *
     * @param string $dbPath       Absolute path to the .sqlite file.
     * @param int    $attachmentId Attachment ID.
     * @param string $fieldName    Custom field name.
     * @param int    $optionId     Option ID.
     */
    public static function setAttachmentFieldValue(string $dbPath, int $attachmentId, string $fieldName, int $optionId): void
    {
        $pdo = self::getConnection($dbPath);
        $stmt = $pdo->prepare(
            'INSERT OR REPLACE INTO attachment_field_values (attachment_id, field_name, option_id)
             VALUES (:attachment_id, :field_name, :option_id)'
        );
        $stmt->execute([
            ':attachment_id' => $attachmentId,
            ':field_name'    => $fieldName,
            ':option_id'     => $optionId,
        ]);
    }

    /**
     * Get all field values for an entry.
     *
     * @param string $dbPath  Absolute path to the .sqlite file.
     * @param int    $entryId Entry ID.
     * @return array Associative array: ['status' => 1, 'resolution' => 3]
     */
    public static function getEntryFieldValues(string $dbPath, int $entryId): array
    {
        $pdo = self::getConnection($dbPath);
        $stmt = $pdo->prepare('SELECT field_name, option_id FROM entry_field_values WHERE entry_id = :entry_id');
        $stmt->execute([':entry_id' => $entryId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $result = [];
        foreach ($rows as $row) {
            $result[$row['field_name']] = (int) $row['option_id'];
        }
        return $result;
    }

    /**
     * Get all field values for an attachment.
     *
     * @param string $dbPath       Absolute path to the .sqlite file.
     * @param int    $attachmentId Attachment ID.
     * @return array Associative array: ['status' => 1, 'resolution' => 3]
     */
    public static function getAttachmentFieldValues(string $dbPath, int $attachmentId): array
    {
        $pdo = self::getConnection($dbPath);
        $stmt = $pdo->prepare('SELECT field_name, option_id FROM attachment_field_values WHERE attachment_id = :attachment_id');
        $stmt->execute([':attachment_id' => $attachmentId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $result = [];
        foreach ($rows as $row) {
            $result[$row['field_name']] = (int) $row['option_id'];
        }
        return $result;
    }

    // -- Export / Import -----------------------------------------------------

    /**
     * Export all custom fields with their options as nested JSON.
     *
     * @param string $dbPath Absolute path to the .sqlite file.
     * @return array ['fields' => [['name' => ..., 'description' => ..., 'sort_order' => ..., 'options' => [...]], ...]]
     */
    public static function exportCustomFields(string $dbPath): array
    {
        $pdo = self::getConnection($dbPath);
        $fields = $pdo->query(
            'SELECT name, description, sort_order FROM custom_fields ORDER BY sort_order ASC, name ASC'
        )->fetchAll(PDO::FETCH_ASSOC);

        foreach ($fields as &$field) {
            $field['sort_order'] = (int) $field['sort_order'];
            $stmt = $pdo->prepare(
                'SELECT name FROM field_options WHERE field_name = :field_name ORDER BY id ASC'
            );
            $stmt->execute([':field_name' => $field['name']]);
            $field['options'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
        unset($field);

        return ['fields' => $fields];
    }

    /**
     * Import custom fields with their options (merge mode — INSERT OR IGNORE).
     *
     * @param string $dbPath Absolute path to the .sqlite file.
     * @param array  $fields Array of field definitions with options.
     * @return array ['fields_created' => int, 'options_created' => int]
     */
    public static function importCustomFields(string $dbPath, array $fields): array
    {
        $pdo = self::getConnection($dbPath);
        $fieldsCreated = 0;
        $optionsCreated = 0;

        foreach ($fields as $def) {
            $name = $def['name'] ?? '';
            if ($name === '') {
                continue;
            }

            $stmt = $pdo->prepare(
                'INSERT OR IGNORE INTO custom_fields (name, description, sort_order, created_at)
                 VALUES (:name, :description, :sort_order, :created_at)'
            );
            $stmt->execute([
                ':name'        => $name,
                ':description' => $def['description'] ?? '',
                ':sort_order'  => (int) ($def['sort_order'] ?? 0),
                ':created_at'  => date('c'),
            ]);
            $fieldsCreated += $stmt->rowCount();

            foreach ($def['options'] ?? [] as $optName) {
                $stmt = $pdo->prepare(
                    'INSERT OR IGNORE INTO field_options (field_name, name, created_at)
                     VALUES (:field_name, :name, :created_at)'
                );
                $stmt->execute([
                    ':field_name' => $name,
                    ':name'       => $optName,
                    ':created_at' => date('c'),
                ]);
                $optionsCreated += $stmt->rowCount();
            }
        }

        return ['fields_created' => $fieldsCreated, 'options_created' => $optionsCreated];
    }
}
