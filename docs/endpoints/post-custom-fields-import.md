# POST `/custom-fields/import`

Import custom fields with their options in merge mode (INSERT OR IGNORE).

## URL

```
POST /custom-fields/import
```

- **Clean URL:** `api/custom-fields/import`
- **Direct:** `api.php/custom-fields/import`

## Authentication

Optional Basic Auth, controlled by `auth_enabled` in config.

## Prerequisites

Requires both `api_enabled` and `db_enabled` to be `true` in config.

| Condition | Status | Response |
|-----------|--------|----------|
| `api_enabled` is off | 403 | `{"error": "API is disabled"}` |
| `db_enabled` is off | 503 | `{"error": "Database is disabled"}` |

## Request Body

JSON object with a `fields` array. Each field object contains:

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `name` | string | Yes | Field name (lowercase letters and underscores) |
| `description` | string | No | Field description |
| `sort_order` | int | No | Display sort order (default: 0) |
| `options` | string[] | No | Array of option names to create |

```json
{
  "fields": [
    {
      "name": "priority",
      "description": "Issue priority level",
      "sort_order": 2,
      "options": ["low", "medium", "high", "critical"]
    }
  ]
}
```

## Merge Behavior

- Uses `INSERT OR IGNORE` — existing fields and options are skipped, not overwritten
- Only new fields and new options are created
- Existing field descriptions and sort orders are **not** updated
- Safe to re-import the same data multiple times (idempotent)

## Success Response

**Status:** 200

```json
{
  "_version": "1.1.0",
  "fields_created": 1,
  "options_created": 4
}
```

### Response Notes

- `fields_created` — number of new fields inserted (0 if all existed)
- `options_created` — number of new options inserted (0 if all existed)

## Error Codes

| Status | Response |
|--------|----------|
| 400 | `{"error": "Request body must contain a \"fields\" array"}` |
| 401 | `{"error": "Unauthorized"}` — Basic Auth failed |

## Example

### Import from exported JSON

```bash
curl -X POST https://example.com/api/custom-fields/import \
  -u user:pass \
  -H "Content-Type: application/json" \
  -d @fields.json
```

### Import inline

```bash
curl -X POST https://example.com/api/custom-fields/import \
  -u user:pass \
  -H "Content-Type: application/json" \
  -d '{"fields": [{"name": "severity", "description": "Bug severity", "options": ["low", "medium", "high"]}]}'
```
