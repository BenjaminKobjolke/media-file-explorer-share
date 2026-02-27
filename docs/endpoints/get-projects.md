# GET `/projects`

List all projects with entry counts.

## URL

```
GET /projects
```

- **Clean URL:** `api/projects`
- **Direct:** `api.php/projects`

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
  "data": [
    {
      "id": 1,
      "name": "My Project",
      "created_at": "2025-01-15T10:30:00+00:00",
      "entry_count": 5
    }
  ]
}
```

### Response Notes

- Projects are ordered alphabetically by `name`
- `entry_count` is the number of entries tagged with this project
- The response is wrapped in a `{"data": [...]}` envelope by the version middleware

## Error Codes

| Status | Response |
|--------|----------|
| 401 | `{"error": "Unauthorized"}` — Basic Auth failed |

## Example

```bash
curl https://example.com/api/projects \
  -u user:pass
```
