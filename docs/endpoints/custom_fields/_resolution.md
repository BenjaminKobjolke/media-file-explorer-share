# `_resolution` — Resolution Reason

Tag the entry with a resolution option from the `resolution` custom field.

| Property | Value |
|----------|-------|
| Type | `int` |
| Handlers | `TextHandler`, `FileHandler` |
| Default | None (field is optional) |
| Requires | `db_enabled = true` for validation |

## Behavior

- When present, the value is cast to `int` and validated against the `field_options` table for the `resolution` field
- Returns **400** `"Resolution not found"` if the option ID does not exist
- The value is stored in the `entry_field_values` pivot table (not in the entry row itself)
- The field is stripped from the stored body — it never appears in the `body` or `subject` columns
- Available options are discoverable via `GET /fields` (which includes a `resource` object pointing to `/field-options/resolution`)

## Default Options

Seeded automatically on first database access:

| ID | Name |
|----|------|
| 4 | Fixed |
| 5 | Duplicate |
| 6 | Won't Fix |
| 7 | Not a Bug |

> Note: IDs are auto-incremented after the `status` options, so they start at 4 by default.

## Resource

This field is backed by the **resolution** custom field with full CRUD endpoints:

| Operation | Endpoint | Description |
|-----------|----------|-------------|
| List options | `GET /field-options/resolution` | List all resolution options with entry counts |
| Create option | `POST /field-options/resolution` | Add a new resolution option |
| Rename option | `PUT /field-options/resolution/{id}` | Rename an existing resolution option |
| Delete option | `DELETE /field-options/resolution/{id}` | Delete a resolution option (removes pivot rows) |

The field itself is managed via the [custom fields CRUD](../get-custom-fields.md) endpoints.

## Examples

**Tag a text entry with a resolution:**

```bash
curl -X POST https://example.com/share.php \
  -H "Content-Type: application/json" \
  -d '{"_resolution": 4, "text_or_url": "Bug has been fixed"}'
```

**Combine status and resolution:**

```bash
curl -X POST https://example.com/share.php \
  -H "Content-Type: application/json" \
  -d '{"_status": 3, "_resolution": 4, "text_or_url": "Closing as fixed"}'
```

**Update an entry's resolution via the API:**

```bash
curl -X PUT https://example.com/api/entries/1 \
  -H "Content-Type: application/json" \
  -d '{"resolution_id": 4}'
```

**Filter entries by resolution:**

```bash
curl https://example.com/api/entries?resolution_id=4
```
