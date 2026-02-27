# GET `/field-options/{field}`

List all options for a custom field with entry counts.

## URL

```
GET /field-options/{field}
```

- **Clean URL:** `api/field-options/status`
- **Direct:** `api.php/field-options/status`

The `{field}` parameter must match `[a-z][a-z_]*` (lowercase letters and underscores, starting with a letter).

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
      "field_name": "status",
      "name": "open",
      "created_at": "2025-01-15T10:30:00+00:00",
      "entry_count": 5
    },
    {
      "id": 2,
      "field_name": "status",
      "name": "in progress",
      "created_at": "2025-01-15T10:30:00+00:00",
      "entry_count": 3
    },
    {
      "id": 3,
      "field_name": "status",
      "name": "closed",
      "created_at": "2025-01-15T10:30:00+00:00",
      "entry_count": 0
    }
  ]
}
```

### Response Notes

- Options are ordered by `id ASC`
- `entry_count` is the number of entries tagged with this option
- The response is wrapped in a `{"data": [...]}` envelope by the version middleware

## Error Codes

| Status | Response |
|--------|----------|
| 401 | `{"error": "Unauthorized"}` — Basic Auth failed |
| 404 | `{"error": "Custom field not found"}` — the `{field}` does not exist |

## Example

```bash
curl https://example.com/api/field-options/status \
  -u user:pass
```
