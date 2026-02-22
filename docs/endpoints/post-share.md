# POST `/share.php`

Webhook receiver for text and file uploads from the Media File Explorer Android app.

## URL

```
POST /share.php
```

## Authentication

Optional Basic Auth, controlled by `auth_enabled` in config. When enabled, uses `auth_username` and `auth_password`.

## Modes

The handler dispatches based on whether a `file` field is present in `$_FILES`:

| Condition | Handler | Content-Type |
|-----------|---------|--------------|
| `$_FILES['file']` present | `FileHandler` | `multipart/form-data` |
| No file | `TextHandler` | `application/json` or plain text |

## Text / JSON Mode

Send a JSON body or raw text via `php://input`.

### JSON with `text_or_url`

The primary format. The `text_or_url` field is formatted (Logarte or Markdown) for email. Extra fields are included in the email body.

```bash
curl -X POST https://example.com/share.php \
  -H "Content-Type: application/json" \
  -d '{"text_or_url": "Hello world", "source": "my-app"}'
```

### JSON with custom fields only

A JSON object without `text_or_url` is treated as a fields-only payload. The subject is derived from the first field value.

```bash
curl -X POST https://example.com/share.php \
  -H "Content-Type: application/json" \
  -d '{"title": "Build failed", "branch": "main"}'
```

### Plain text

Non-JSON bodies are sent as plain-text email.

```bash
curl -X POST https://example.com/share.php \
  -H "Content-Type: text/plain" \
  -d "Simple text payload"
```

### Size limit

Controlled by `max_text_size` (default: 1 MB). Returns **413** if exceeded.

## File Upload Mode

Send a file as `multipart/form-data` with the field name `file`.

```bash
curl -X POST https://example.com/share.php \
  -F "file=@document.pdf"
```

### Extra form fields

Additional POST fields sent alongside the file are captured as JSON in the database `body` column and included in the email metadata.

```bash
curl -X POST https://example.com/share.php \
  -F "file=@screenshot.png" \
  -F "description=Login page bug" \
  -F "priority=high"
```

### Size limit

Controlled by `max_file_size` (default: 10 MB). Returns **413** if exceeded. PHP's `upload_max_filesize` INI directive also applies.

## Reserved Fields

See [Reserved Fields Reference](../reserved-fields.md) for full details.

| Field | Type | Effect |
|-------|------|--------|
| `_id` | int | Append to existing entry instead of creating new |
| `_email` | bool | `false` suppresses email for this request |

### Append mode example

```bash
curl -X POST https://example.com/share.php \
  -H "Content-Type: application/json" \
  -d '{"_id": 1, "text_or_url": "Follow-up note"}'
```

## Response

| Condition | Status | Body |
|-----------|--------|------|
| Success with `db_enabled` | 200 | Entry ID (integer) |
| Success, append mode | 200 | Parent `_id` (integer) |
| Success without `db_enabled` | 200 | `response_message` config value |

## Error Codes

| Status | Condition |
|--------|-----------|
| 400 | Empty body (text mode) |
| 400 | File upload error (bad upload) |
| 400 | `_id` used but `db_enabled` is off |
| 401 | Basic Auth failed (when `auth_enabled`) |
| 404 | `_id` references a non-existent parent entry |
| 405 | Request method is not POST |
| 413 | Text payload exceeds `max_text_size` |
| 413 | File exceeds `max_file_size` |
| 500 | Autoloader or config file missing |
| 500 | Failed to read uploaded file |
