# `_status` — Entry Status

Tag the entry with a status option from the `status` custom field.

| Property | Value |
|----------|-------|
| Type | `int` |
| Handlers | `TextHandler`, `FileHandler` |
| Default | None (field is optional) |
| Requires | `db_enabled = true` for validation |

## Behavior

- When present, the value is cast to `int` and validated against the `field_options` table for the `status` field
- Returns **400** `"Status not found"` if the option ID does not exist
- The value is stored in the `entry_field_values` pivot table (not in the entry row itself)
- The field is stripped from the stored body — it never appears in the `body` or `subject` columns
- Available options are discoverable via `GET /fields` (which includes a `resource` object pointing to `/field-options/status`)

## Default Options

Seeded automatically on first database access:

| ID | Name |
|----|------|
| 1 | open |
| 2 | in progress |
| 3 | closed |

## Resource

This field is backed by the **status** custom field with full CRUD endpoints:

| Operation | Endpoint | Description |
|-----------|----------|-------------|
| List options | `GET /field-options/status` | List all status options with entry counts |
| Create option | `POST /field-options/status` | Add a new status option |
| Rename option | `PUT /field-options/status/{id}` | Rename an existing status option |
| Delete option | `DELETE /field-options/status/{id}` | Delete a status option (removes pivot rows) |

The field itself is managed via the [custom fields CRUD](../get-custom-fields.md) endpoints.

## Examples

**Tag a text entry with a status:**

```bash
curl -X POST https://example.com/share.php \
  -H "Content-Type: application/json" \
  -d '{"_status": 1, "text_or_url": "New bug report"}'
```

**Tag a file upload with a status:**

```bash
curl -X POST https://example.com/share.php \
  -F "file=@screenshot.png" \
  -F "_status=1"
```

**Combine with other reserved fields:**

```bash
curl -X POST https://example.com/share.php \
  -H "Content-Type: application/json" \
  -d '{"_project": 1, "_status": 1, "_email": false, "text_or_url": "Silent bug report"}'
```

**Update an entry's status via the API:**

```bash
curl -X PUT https://example.com/api/entries/1 \
  -H "Content-Type: application/json" \
  -d '{"status_id": 3}'
```

**Filter entries by status:**

```bash
curl https://example.com/api/entries?status_id=1
```
