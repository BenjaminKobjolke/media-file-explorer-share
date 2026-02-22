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
      "description": "Append mode — attach to an existing entry instead of creating a new one"
    },
    {
      "name": "_email",
      "type": "bool",
      "description": "Set to false to suppress email notification for this request"
    }
  ]
}
```

### Response Notes

- The `data` array contains field descriptor objects, each with `name`, `type`, and `description`
- The list reflects all `_`-prefixed reserved fields stripped before storage
- `_version` is read from the `VERSION` file; `_deploy_id` is read from `deploy.ver` (omitted when the file is absent or empty)

## Example

```bash
curl https://example.com/api/fields
```
