# GET `/auth`

Report the authentication method required by the API.

## URL

```
GET /auth
```

- **Clean URL:** `api/auth`
- **Direct:** `api.php/auth`

## Authentication

None required. This endpoint is used by clients to discover the auth method before authenticating.

## Prerequisites

Requires `api_enabled` to be `true` in config. Does **not** require `db_enabled`.

| Condition | Status | Response |
|-----------|--------|----------|
| `api_enabled` is off | 403 | `{"error": "API is disabled"}` |

## Success Response

**Status:** 200

When `api_auth_enabled` is `false`:

```json
{
  "_version": "1.1.0",
  "method": "none"
}
```

When `api_auth_enabled` is `true`:

```json
{
  "_version": "1.1.0",
  "method": "basic"
}
```

### Response Notes

- `method` is `"none"` when auth is disabled, `"basic"` when Basic Auth is required
- `_version` is read from the `VERSION` file; `_deploy_id` is read from `deploy.ver` (omitted when the file is absent or empty)

## Example

```bash
curl https://example.com/api/auth
```
