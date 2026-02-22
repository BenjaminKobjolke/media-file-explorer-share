# GET `/entries/{id}`

Retrieve a single entry with its nested attachments.

## URL

```
GET /entries/{id}
```

- **Clean URL:** `api/entries/1`
- **Direct:** `api.php/entries/1`

The `{id}` parameter must be a positive integer (regex: `[0-9]+`).

## Authentication

Optional Basic Auth, controlled by `api_auth_enabled` in config. Reuses `auth_username` and `auth_password`.

## Prerequisites

Requires both `api_enabled` and `db_enabled` to be `true` in config.

| Condition | Status | Response |
|-----------|--------|----------|
| `api_enabled` is off | 403 | `{"error": "API is disabled"}` |
| `db_enabled` is off | 503 | `{"error": "Database is disabled"}` |

## Success Response

**Status:** 200

```json
{
  "_version": "1.1.0",
  "_deploy_id": "e74993c",
  "id": 1,
  "type": "text",
  "subject": "Webhook payload 2025-01-15T10:30:00+00:00",
  "body": {"text_or_url": "Hello world"},
  "filename": null,
  "file_size": null,
  "ip": "192.168.1.1",
  "ua": "MediaFileExplorer/1.0",
  "created_at": "2025-01-15T10:30:00+00:00",
  "attachments": [
    {
      "id": 1,
      "type": "file",
      "subject": "File: screenshot.png",
      "body": null,
      "filename": "screenshot.png",
      "file_size": 204800,
      "file_url": "https://example.com/api/files/1",
      "created_at": "2025-01-15T10:35:00+00:00"
    }
  ]
}
```

### Response Notes

- `_version` is read from the `VERSION` file; `_deploy_id` is read from `deploy.ver` (omitted when the file is absent or empty)
- `body` is JSON-decoded when the stored value is valid JSON; otherwise returned as a raw string
- `file_path` is stripped from both entries and attachments (internal only)
- `ip`, `ua`, and `entry_id` are stripped from attachments
- `file_url` is included only for file-type attachments that have a stored file on disk

## Error Codes

| Status | Response |
|--------|----------|
| 401 | `{"error": "Unauthorized"}` — Basic Auth failed |
| 404 | `{"error": "Entry not found"}` |

## Example

```bash
curl https://example.com/api/entries/1 \
  -u user:pass
```
