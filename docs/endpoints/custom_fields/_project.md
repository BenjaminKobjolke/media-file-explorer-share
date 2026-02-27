# `_project` — Project

Tag the entry with a project option from the `project` custom field.

| Property | Value |
|----------|-------|
| Type | `int` |
| Handlers | `TextHandler`, `FileHandler` |
| Default | None (field is optional) |
| Requires | `db_enabled = true` for validation |

## Behavior

- When present, the value is cast to `int` and validated against the `field_options` table for the `project` field
- Returns **400** `"Project not found"` if the option ID does not exist
- The value is stored in the `entry_field_values` pivot table (not in the entry row itself)
- The field is stripped from the stored body — it never appears in the `body` or `subject` columns
- Available options are discoverable via `GET /fields` (which includes a `resource` object pointing to `/field-options/project`)

## Default Options

The `project` field is seeded with no default options. Add projects via the API:

```bash
curl -X POST https://example.com/api/field-options/project \
  -H "Content-Type: application/json" \
  -d '{"name": "My App"}'
```

## Resource

This field is backed by the **project** custom field with full CRUD endpoints:

| Operation | Endpoint | Description |
|-----------|----------|-------------|
| List options | `GET /field-options/project` | List all project options with entry counts |
| Create option | `POST /field-options/project` | Add a new project option |
| Rename option | `PUT /field-options/project/{id}` | Rename an existing project option |
| Delete option | `DELETE /field-options/project/{id}` | Delete a project option (removes pivot rows) |

The field itself is managed via the [custom fields CRUD](../get-custom-fields.md) endpoints.

## Examples

**Tag a text entry with a project:**

```bash
curl -X POST https://example.com/share.php \
  -H "Content-Type: application/json" \
  -d '{"_project": 1, "text_or_url": "Note for this project"}'
```

**Tag a file upload with a project:**

```bash
curl -X POST https://example.com/share.php \
  -F "file=@report.pdf" \
  -F "_project=1"
```

**Combine with other reserved fields:**

```bash
curl -X POST https://example.com/share.php \
  -H "Content-Type: application/json" \
  -d '{"_project": 1, "_status": 1, "_email": false, "text_or_url": "Silent bug report"}'
```

**Update an entry's project via the API:**

```bash
curl -X PUT https://example.com/api/entries/1 \
  -H "Content-Type: application/json" \
  -d '{"project_id": 1}'
```

**Filter entries by project:**

```bash
curl https://example.com/api/entries?project_id=1
```
