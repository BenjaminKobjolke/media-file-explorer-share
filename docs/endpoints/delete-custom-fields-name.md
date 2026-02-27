# DELETE `/custom-fields/{name}`

Delete a custom field and all associated options and pivot rows.

## URL

```
DELETE /custom-fields/{name}
```

- **Clean URL:** `api/custom-fields/priority`
- **Direct:** `api.php/custom-fields/priority`

The `{name}` parameter must match `[a-z][a-z_]*` (lowercase letters and underscores, starting with a letter).

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
  "message": "Custom field deleted"
}
```

### Cascade

Deleting a custom field also removes:
- All options in `field_options` for this field
- All pivot rows in `entry_field_values` for this field
- All pivot rows in `attachment_field_values` for this field
- The field disappears from `GET /fields`

## Error Codes

| Status | Response |
|--------|----------|
| 401 | `{"error": "Unauthorized"}` — Basic Auth failed |
| 404 | `{"error": "Custom field not found"}` |

## Example

```bash
curl -X DELETE https://example.com/api/custom-fields/priority \
  -u user:pass
```
