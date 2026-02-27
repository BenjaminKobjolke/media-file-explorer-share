# DELETE `/entries/{id}`

Delete an entry and all its attachments. Associated files are removed from disk.

## URL

```
DELETE /entries/{id}
```

- **Clean URL:** `api/entries/1`
- **Direct:** `api.php/entries/1`

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
  "message": "Entry deleted"
}
```

### Behavior

- The entry row is deleted from the `entries` table
- All attachment rows for this entry are deleted from the `attachments` table
- Any files on disk (entry file + attachment files) are deleted with path traversal protection
- File deletion failures are silently ignored (the database records are still removed)

## Error Codes

| Status | Response |
|--------|----------|
| 401 | `{"error": "Unauthorized"}` — Basic Auth failed |
| 404 | `{"error": "Entry not found"}` |

## Example

```bash
curl -X DELETE https://example.com/api/entries/1 \
  -u user:pass
```
