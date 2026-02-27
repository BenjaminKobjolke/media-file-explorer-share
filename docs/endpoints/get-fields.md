# GET `/fields`

List all reserved fields (underscore-prefixed control fields) accepted by the webhook.

## URL

```
GET /fields
```

- **Clean URL:** `api/fields`
- **Direct:** `api.php/fields`

## Authentication

None required. This endpoint serves public schema metadata.

## Prerequisites

Requires `api_enabled` to be `true` in config. Does **not** require `db_enabled`.

| Condition | Status | Response |
|-----------|--------|----------|
| `api_enabled` is off | 403 | `{"error": "API is disabled"}` |

## Success Response

**Status:** 200

```json
{
  "_version": "1.1.0",
  "_deploy_id": "e74993c",
  "data": [
    {
      "name": "_id",
      "type": "int",
      "description": "Append mode — attach to an existing entry instead of creating a new one",
      "accepted_values": [
        {"value": 1, "description": "ID of the existing entry to append to"}
      ]
    },
    {
      "name": "_email",
      "type": "bool",
      "description": "Set to false to suppress email notification for this request",
      "accepted_values": [
        {"value": false, "description": "Suppress email (also accepts string \"false\", \"0\", or empty string)"},
        {"value": true, "description": "Send email (default when omitted, if email_enabled is on)"}
      ]
    },
    {
      "name": "_project",
      "type": "int",
      "description": "Tag the entry with a project ID from the projects table",
      "accepted_values": [
        {"value": 1, "description": "ID of the project (must exist in projects table)"}
      ]
    }
  ]
}
```

### Response Notes

- The `data` array contains field descriptor objects, each with `name`, `type`, `description`, and `accepted_values`
- `accepted_values` is an array of example/allowed values, each with a `value` and `description`
- The list reflects all `_`-prefixed reserved fields stripped before storage
- `_version` is read from the `VERSION` file; `_deploy_id` is read from `deploy.ver` (omitted when the file is absent or empty)

## Example

```bash
curl https://example.com/api/fields
```
