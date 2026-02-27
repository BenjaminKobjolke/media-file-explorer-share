# DELETE `/projects/{id}`

Delete a project. Associated entries and attachments have their `project_id` set to NULL (they are not deleted).

## URL

```
DELETE /projects/{id}
```

- **Clean URL:** `api/projects/1`
- **Direct:** `api.php/projects/1`

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
  "message": "Project deleted"
}
```

### Behavior

- The project row is deleted from the `projects` table
- All entries and attachments with this `project_id` have their `project_id` set to `NULL`
- No entries or attachments are deleted — only the project association is removed

## Error Codes

| Status | Response |
|--------|----------|
| 401 | `{"error": "Unauthorized"}` — Basic Auth failed |
| 404 | `{"error": "Project not found"}` |

## Example

```bash
curl -X DELETE https://example.com/api/projects/1 \
  -u user:pass
```
