# GET `/custom-fields`

List all custom fields with option counts.

## URL

```
GET /custom-fields
```

- **Clean URL:** `api/custom-fields`
- **Direct:** `api.php/custom-fields`

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
      "name": "status",
      "description": "Entry lifecycle status",
      "sort_order": 0,
      "created_at": "2025-01-15T10:30:00+00:00",
      "option_count": 3
    },
    {
      "name": "resolution",
      "description": "Resolution reason for the entry",
      "sort_order": 1,
      "created_at": "2025-01-15T10:30:00+00:00",
      "option_count": 4
    }
  ]
}
```

### Response Notes

- Fields are ordered by `sort_order ASC`, then `name ASC`
- `option_count` is the number of options defined for each field
- The response is wrapped in a `{"data": [...]}` envelope by the version middleware
- Default fields (`status`, `resolution`) are seeded automatically on first database access

## Error Codes

| Status | Response |
|--------|----------|
| 401 | `{"error": "Unauthorized"}` — Basic Auth failed |

## Example

```bash
curl https://example.com/api/custom-fields \
  -u user:pass
```
