# PUT `/entries/{id}`

Update an entry's subject and/or body.

## URL

```
PUT /entries/{id}
```

- **Clean URL:** `api/entries/1`
- **Direct:** `api.php/entries/1`

The `{id}` parameter must be a positive integer (regex: `[0-9]+`).

## Request Body

JSON object with at least one of:

| Field | Type | Description |
|-------|------|-------------|
| `subject` | string | New subject line |
| `body` | string | New body text |

```json
{
  "subject": "Updated subject",
  "body": "Updated body text"
}
```

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

Returns the full entry with attachments (same format as GET `/entries/{id}`).

```json
{
  "_version": "1.1.0",
  "id": 1,
  "type": "text",
  "subject": "Updated subject",
  "body": "Updated body text",
  "filename": null,
  "file_size": null,
  "ip": "192.168.1.1",
  "ua": "MediaFileExplorer/1.0",
  "created_at": "2025-01-15T10:30:00+00:00",
  "attachments": []
}
```

### Behavior

- Only provided fields are updated; omitted fields remain unchanged
- Returns the full entry via the same lookup used by GET `/entries/{id}`

## Error Codes

| Status | Response |
|--------|----------|
| 400 | `{"error": "No fields to update"}` — neither subject nor body provided |
| 401 | `{"error": "Unauthorized"}` — Basic Auth failed |
| 404 | `{"error": "Entry not found"}` |

## Example

```bash
curl -X PUT https://example.com/api/entries/1 \
  -u user:pass \
  -H "Content-Type: application/json" \
  -d '{"subject": "New subject"}'
```
