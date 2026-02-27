# POST `/field-options/{field}`

Create a new option for a custom field.

## URL

```
POST /field-options/{field}
```

- **Clean URL:** `api/field-options/status`
- **Direct:** `api.php/field-options/status`

The `{field}` parameter must match `[a-z][a-z_]*` (lowercase letters and underscores, starting with a letter).

## Authentication

Optional Basic Auth, controlled by `auth_enabled` in config. Reuses `auth_username` and `auth_password`.

## Prerequisites

Requires both `api_enabled` and `db_enabled` to be `true` in config.

| Condition | Status | Response |
|-----------|--------|----------|
| `api_enabled` is off | 403 | `{"error": "API is disabled"}` |
| `db_enabled` is off | 503 | `{"error": "Database is disabled"}` |

## Request Body

```json
{
  "name": "blocked"
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `name` | string | Yes | Option name (must be unique within the field) |

## Success Response

**Status:** 201

```json
{
  "_version": "1.1.0",
  "id": 8,
  "field_name": "status",
  "name": "blocked",
  "created_at": "2025-01-15T10:30:00+00:00"
}
```

## Error Codes

| Status | Response |
|--------|----------|
| 400 | `{"error": "Option name is required"}` — name is missing or empty |
| 401 | `{"error": "Unauthorized"}` — Basic Auth failed |
| 404 | `{"error": "Custom field not found"}` — the `{field}` does not exist |
| 409 | `{"error": "Option name already exists for this field"}` — duplicate name |

## Example

```bash
curl -X POST https://example.com/api/field-options/status \
  -H "Content-Type: application/json" \
  -u user:pass \
  -d '{"name": "blocked"}'
```
