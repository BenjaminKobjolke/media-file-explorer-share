# Reserved Fields Reference

Fields prefixed with `_` are internal control fields. They are **stripped before storage** — they never appear in the database `body` or `subject` columns.

## Hardcoded Fields

These fields are built into the codebase:

| Field | Type | Effect | Details |
|-------|------|--------|---------|
| `_id` | int | Append mode — attach to existing entry instead of creating new one | [docs](endpoints/custom_fields/_id.md) |
| `_email` | bool | `false` suppresses email for this request even when `email_enabled` is on | [docs](endpoints/custom_fields/_email.md) |

## Dynamic Custom Fields

Custom fields are auto-discovered from the `custom_fields` database table. They follow the same `_`-prefix convention and are validated against their options. Values are stored in pivot tables (`entry_field_values`, `attachment_field_values`), not in entry columns.

### Default Fields (seeded automatically)

| Field | Type | Effect | Details |
|-------|------|--------|---------|
| `_project` | int | Tag entry with a project option ID; returns 400 if not found | [docs](endpoints/custom_fields/_project.md) |
| `_status` | int | Tag entry with a status option ID; returns 400 if not found | [docs](endpoints/custom_fields/_status.md) |
| `_resolution` | int | Tag entry with a resolution option ID; returns 400 if not found | [docs](endpoints/custom_fields/_resolution.md) |

### Creating New Custom Fields

New fields can be added entirely via the API — no code changes required:

1. `POST /custom-fields` with `{"name": "priority", "description": "Issue priority"}` — creates the field
2. `POST /field-options/priority` with `{"name": "high"}` — adds options
3. The field is immediately usable as `_priority` in webhook requests
4. `GET /fields` automatically includes the new field with `resource` metadata
5. `GET /entries?priority_id=1` — filter support is automatic
6. `PUT /entries/{id}` with `{"priority_id": 1}` — update support is automatic

See [custom fields CRUD endpoints](endpoints/get-custom-fields.md) and [field options CRUD endpoints](endpoints/get-field-options.md) for full documentation.

See individual field documentation in [`docs/endpoints/custom_fields/`](endpoints/custom_fields/) for behavior, examples, and related resources.
