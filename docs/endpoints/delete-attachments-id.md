# DELETE `/attachments/{id}`

Delete a single attachment. The associated file is removed from disk.

## URL

```
DELETE /attachments/{id}
```

- **Clean URL:** `api/attachments/1`
- **Direct:** `api.php/attachments/1`

The `{id}` parameter must be a positive integer (regex: `[0-9]+`).

## Authentication

Optional Basic Auth, controlled by `auth_enabled` in config. Reuses `auth_username` and `auth_password`.

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
  "message": "Attachment deleted"
}
```

### Behavior

- The attachment row is deleted from the `attachments` table
- The associated file on disk (if any) is deleted with path traversal protection
- File deletion failure is silently ignored (the database record is still removed)

## Error Codes

| Status | Response |
|--------|----------|
| 401 | `{"error": "Unauthorized"}` — Basic Auth failed |
| 404 | `{"error": "Attachment not found"}` |

## Example

```bash
curl -X DELETE https://example.com/api/attachments/1 \
  -u user:pass
```
