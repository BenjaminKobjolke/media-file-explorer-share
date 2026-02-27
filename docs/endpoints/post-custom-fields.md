# POST `/custom-fields`

Create a new custom field.

## URL

```
POST /custom-fields
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

## Request Body

```json
{
  "name": "priority",
  "description": "Issue priority level",
  "sort_order": 2
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `name` | string | Yes | Field name — lowercase letters and underscores only, must start with a letter |
| `description` | string | No | Human-readable description (defaults to empty string) |
| `sort_order` | int | No | Display order in `GET /fields` (defaults to 0) |

## Success Response

**Status:** 201

```json
{
  "_version": "1.1.0",
  "name": "priority",
  "description": "Issue priority level",
  "sort_order": 2,
  "created_at": "2025-01-15T10:30:00+00:00"
}
```

## After Creation

Once created, the field is immediately usable:

1. Add options via `POST /field-options/priority`
2. The field appears in `GET /fields` as `_priority` with a `resource` pointing to `/field-options/priority`
3. Webhook requests can include `"_priority": <option_id>` to tag entries
4. `GET /entries` accepts `priority_id=<option_id>` as a filter parameter
5. `PUT /entries/{id}` accepts `priority_id` to update the value

## Error Codes

| Status | Response |
|--------|----------|
| 400 | `{"error": "Field name is required"}` — name is missing or empty |
| 400 | `{"error": "Field name must be lowercase letters and underscores only, starting with a letter"}` — invalid format |
| 401 | `{"error": "Unauthorized"}` — Basic Auth failed |
| 409 | `{"error": "Custom field already exists"}` — name already taken |

## Example

```bash
curl -X POST https://example.com/api/custom-fields \
  -H "Content-Type: application/json" \
  -u user:pass \
  -d '{"name": "priority", "description": "Issue priority level", "sort_order": 2}'
```
