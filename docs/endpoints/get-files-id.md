# GET `/files/{id}`

Serve an attachment file from disk by its attachment ID.

## URL

```
GET /files/{id}
```

- **Clean URL:** `api/files/1`
- **Direct:** `api.php/files/1`

The `{id}` parameter must be a positive integer (regex: `[0-9]+`). This is the **attachment ID**, not the entry ID.

## Authentication

Optional Basic Auth, controlled by `api_auth_enabled` in config. Reuses `auth_username` and `auth_password`.

## Prerequisites

Requires both `api_enabled` and `db_enabled` to be `true` in config. The file must also exist on disk (`storage_enabled` must have been on when the file was uploaded).

| Condition | Status | Response |
|-----------|--------|----------|
| `api_enabled` is off | 403 | `{"error": "API is disabled"}` |
| `db_enabled` is off | 503 | `{"error": "Database is disabled"}` |

## Success Response

**Status:** 200

Returns the binary file with appropriate headers:

| Header | Value |
|--------|-------|
| `Content-Type` | Auto-detected MIME type (falls back to `application/octet-stream`) |
| `Content-Length` | File size in bytes |
| `Content-Disposition` | `inline; filename="original-name.ext"` |

## Security

Path traversal protection is enforced via `realpath()`. The resolved file path must be inside the configured `storage_path` directory. If the check fails, the endpoint returns 404.

## Error Codes

| Status | Response |
|--------|----------|
| 401 | `{"error": "Unauthorized"}` — Basic Auth failed |
| 404 | `{"error": "Attachment not found"}` — no attachment with that ID |
| 404 | `{"error": "No file stored for this attachment"}` — attachment is text-type or has no `file_path` |
| 404 | `{"error": "File not accessible"}` — file missing from disk or path traversal blocked |

> All JSON error responses include `_version` and optionally `_deploy_id` top-level fields. The binary file response (200) does not include version info.

## Example

```bash
# Download an attachment file
curl https://example.com/api/files/1 \
  -u user:pass \
  -o downloaded-file.png
```

The `file_url` field in the [GET `/entries/{id}`](get-entries-id.md) response points directly to this endpoint for file-type attachments.
