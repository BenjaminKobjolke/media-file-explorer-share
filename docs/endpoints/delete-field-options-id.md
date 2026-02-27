# DELETE `/field-options/{field}/{id}`

Delete an option from a custom field and remove all associated pivot rows.

## URL

```
DELETE /field-options/{field}/{id}
```

- **Clean URL:** `api/field-options/status/1`
- **Direct:** `api.php/field-options/status/1`

The `{field}` parameter must match `[a-z][a-z_]*`. The `{id}` must be a positive integer.

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
  "message": "Option deleted"
}
```

### Cascade

Deleting an option also removes:
- All pivot rows in `entry_field_values` referencing this option
- All pivot rows in `attachment_field_values` referencing this option

## Error Codes

| Status | Response |
|--------|----------|
| 401 | `{"error": "Unauthorized"}` — Basic Auth failed |
| 404 | `{"error": "Custom field not found"}` — the `{field}` does not exist |
| 404 | `{"error": "Option not found"}` — the `{id}` does not exist for this field |

## Example

```bash
curl -X DELETE https://example.com/api/field-options/status/1 \
  -u user:pass
```
