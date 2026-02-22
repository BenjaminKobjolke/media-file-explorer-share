# POST `/entries`

Alternative to [GET `/entries/{id}`](get-entries-id.md) — retrieves a single entry by passing the ID in a JSON body.

## URL

```
POST /entries
```

- **Clean URL:** `api/entries`
- **Direct:** `api.php/entries`

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
  "id": 1
}
```

The `id` field is required. The body must be valid JSON.

## Success Response

**Status:** 200

Same structure as [GET `/entries/{id}`](get-entries-id.md#success-response).

## Error Codes

| Status | Response |
|--------|----------|
| 400 | `{"error": "Missing id in request body"}` |
| 401 | `{"error": "Unauthorized"}` — Basic Auth failed |
| 404 | `{"error": "Entry not found"}` |

> All JSON responses (success and error) include `_version` and optionally `_deploy_id` top-level fields. See [GET `/entries/{id}` Response Notes](get-entries-id.md#response-notes).

## Example

```bash
curl -X POST https://example.com/api/entries \
  -H "Content-Type: application/json" \
  -u user:pass \
  -d '{"id": 1}'
```
