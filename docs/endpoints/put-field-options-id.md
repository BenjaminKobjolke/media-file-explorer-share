# PUT `/field-options/{field}/{id}`

Rename an option within a custom field.

## URL

```
PUT /field-options/{field}/{id}
```

- **Clean URL:** `api/field-options/status/1`
- **Direct:** `api.php/field-options/status/1`

The `{field}` parameter must match `[a-z][a-z_]*`. The `{id}` must be a positive integer.

## Request Body

```json
{
  "name": "active"
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `name` | string | Yes | New option name (must be unique within the field) |

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
  "id": 1,
  "field_name": "status",
  "name": "active",
  "created_at": "2025-01-15T10:30:00+00:00"
}
```

## Error Codes

| Status | Response |
|--------|----------|
| 400 | `{"error": "Option name is required"}` — name is missing or empty |
| 401 | `{"error": "Unauthorized"}` — Basic Auth failed |
| 404 | `{"error": "Custom field not found"}` — the `{field}` does not exist |
| 404 | `{"error": "Option not found"}` — the `{id}` does not exist for this field |
| 409 | `{"error": "Option name already exists for this field"}` — duplicate name |

## Example

```bash
curl -X PUT https://example.com/api/field-options/status/1 \
  -H "Content-Type: application/json" \
  -u user:pass \
  -d '{"name": "active"}'
```
