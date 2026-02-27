# PUT `/custom-fields/{name}`

Update a custom field's description and/or sort order.

## URL

```
PUT /custom-fields/{name}
```

- **Clean URL:** `api/custom-fields/priority`
- **Direct:** `api.php/custom-fields/priority`

The `{name}` parameter must match `[a-z][a-z_]*` (lowercase letters and underscores, starting with a letter).

## Request Body

JSON object with at least one of:

| Field | Type | Description |
|-------|------|-------------|
| `description` | string | New description |
| `sort_order` | int | New display order |

```json
{
  "description": "Updated description",
  "sort_order": 5
}
```

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
  "name": "priority",
  "description": "Updated description",
  "sort_order": 5,
  "created_at": "2025-01-15T10:30:00+00:00"
}
```

### Behavior

- Only provided fields are updated; omitted fields remain unchanged
- The field `name` cannot be changed (it is the primary key)

## Error Codes

| Status | Response |
|--------|----------|
| 400 | `{"error": "No fields to update"}` — neither description nor sort_order provided |
| 401 | `{"error": "Unauthorized"}` — Basic Auth failed |
| 404 | `{"error": "Custom field not found"}` |

## Example

```bash
curl -X PUT https://example.com/api/custom-fields/priority \
  -H "Content-Type: application/json" \
  -u user:pass \
  -d '{"sort_order": 5}'
```
