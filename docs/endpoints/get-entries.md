# GET `/entries`

Retrieve a paginated list of entries with attachment counts.

## URL

```
GET /entries?page=1&per_page=20
```

- **Clean URL:** `api/entries`
- **Direct:** `api.php/entries`

## Query Parameters

| Parameter | Type | Default | Range | Description |
|-----------|------|---------|-------|-------------|
| `page` | int | 1 | 1+ | Page number |
| `per_page` | int | 20 | 1–100 | Items per page |
| `project_id` | int | — | — | Filter entries by project ID |

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
  "entries": [
    {
      "id": 5,
      "type": "text",
      "subject": "Webhook payload 2025-01-15T10:30:00+00:00",
      "body": "{\"text_or_url\": \"Hello world\"}",
      "filename": null,
      "file_size": null,
      "ip": "192.168.1.1",
      "ua": "MediaFileExplorer/1.0",
      "created_at": "2025-01-15T10:30:00+00:00",
      "attachment_count": 2
    }
  ],
  "total": 42,
  "page": 1,
  "per_page": 20
}
```

### Response Notes

- Entries are ordered by `created_at DESC` (newest first)
- `file_path` is stripped from entries (internal only)
- `attachment_count` is the number of attachments for each entry
- `body` is returned as a raw string (not decoded as in GET /entries/{id})

## Error Codes

| Status | Response |
|--------|----------|
| 401 | `{"error": "Unauthorized"}` — Basic Auth failed |

## Example

```bash
curl https://example.com/api/entries?page=1&per_page=10 \
  -u user:pass
```
