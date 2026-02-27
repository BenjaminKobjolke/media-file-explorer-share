# PUT `/projects/{id}`

Rename an existing project.

## URL

```
PUT /projects/{id}
```

- **Clean URL:** `api/projects/1`
- **Direct:** `api.php/projects/1`

The `{id}` parameter must be a positive integer (regex: `[0-9]+`).

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
  "name": "Renamed Project"
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `name` | string | Yes | New project name (must be unique, non-empty) |

## Success Response

**Status:** 200

```json
{
  "_version": "1.1.0",
  "id": 1,
  "name": "Renamed Project",
  "created_at": "2025-01-15T10:30:00+00:00"
}
```

## Error Codes

| Status | Response |
|--------|----------|
| 400 | `{"error": "Project name is required"}` — name is missing or empty |
| 401 | `{"error": "Unauthorized"}` — Basic Auth failed |
| 404 | `{"error": "Project not found"}` |
| 409 | `{"error": "Project name already exists"}` — UNIQUE constraint violation |

## Example

```bash
curl -X PUT https://example.com/api/projects/1 \
  -H "Content-Type: application/json" \
  -u user:pass \
  -d '{"name": "Renamed Project"}'
```
