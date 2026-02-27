# GET `/custom-fields/export`

Export all custom fields with their options as nested JSON.

## URL

```
GET /custom-fields/export
```

- **Clean URL:** `api/custom-fields/export`
- **Direct:** `api.php/custom-fields/export`

## Authentication

Optional Basic Auth, controlled by `api_auth_enabled` in config.

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
  "fields": [
    {
      "name": "project",
      "description": "Tag entry with a project",
      "sort_order": -1,
      "options": []
    },
    {
      "name": "status",
      "description": "Entry lifecycle status",
      "sort_order": 0,
      "options": ["open", "in progress", "closed"]
    },
    {
      "name": "resolution",
      "description": "Resolution reason for the entry",
      "sort_order": 1,
      "options": ["Fixed", "Duplicate", "Won't Fix", "Not a Bug"]
    }
  ]
}
```

### Response Notes

- Fields are ordered by `sort_order ASC`, then `name ASC`
- Options are ordered by their creation order (ID ASC)
- The output can be fed directly into `POST /custom-fields/import` for replication

## Example

```bash
curl https://example.com/api/custom-fields/export \
  -u user:pass
```

### Export and save to file

```bash
curl https://example.com/api/custom-fields/export \
  -u user:pass -o fields.json
```
