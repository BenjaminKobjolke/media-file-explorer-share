# Custom Fields System

Custom fields provide a dynamic, extensible way to tag entries and attachments with structured metadata. All custom fields — including the built-in defaults — are managed through the same API and stored in the same tables.

## How It Works

### Storage Model

Custom fields use pivot tables rather than adding columns to the entries/attachments tables:

| Table | Purpose |
|-------|---------|
| `custom_fields` | Field registry (name, description, sort_order) |
| `field_options` | Options per field (id, field_name, name) |
| `entry_field_values` | Pivot: which option is set on each entry |
| `attachment_field_values` | Pivot: which option is set on each attachment |

### Default Fields

Three fields are seeded automatically on first database access:

| Field | Description | Default Options |
|-------|-------------|-----------------|
| `project` | Tag entry with a project | *(none — add via API)* |
| `status` | Entry lifecycle status | open, in progress, closed |
| `resolution` | Resolution reason for the entry | Fixed, Duplicate, Won't Fix, Not a Bug |

See individual field docs: [`_project`](_project.md), [`_status`](_status.md), [`_resolution`](_resolution.md)

## Creating and Managing Fields

### 1. Create a field

```bash
POST /custom-fields
{"name": "priority", "description": "Issue priority level", "sort_order": 2}
```

Field names must be lowercase letters and underscores only, starting with a letter.

### 2. Add options

```bash
POST /field-options/priority
{"name": "high"}
```

### 3. Use in webhook requests

The field is immediately usable as `_priority` in POST requests to `share.php`:

```bash
curl -X POST https://example.com/share.php \
  -H "Content-Type: application/json" \
  -d '{"_priority": 1, "text_or_url": "Urgent bug"}'
```

### 4. Filter entries

```bash
GET /entries?priority_id=1
```

### 5. Update entry field values

```bash
PUT /entries/{id}
{"priority_id": 1}
```

## Auto-Discovery

`GET /fields` automatically includes all custom fields. Each custom field entry includes a `resource` object with `name` and `path` for CRUD endpoint discovery:

```json
{
  "name": "_priority",
  "type": "int",
  "description": "Issue priority level",
  "accepted_values": [{"value": 1, "description": "ID of the priority option (must exist)"}],
  "resource": {"name": "priority", "path": "/field-options/priority"}
}
```

## API Endpoints

| Operation | Endpoint | Description |
|-----------|----------|-------------|
| List fields | [`GET /custom-fields`](../get-custom-fields.md) | List all custom fields with option counts |
| Create field | [`POST /custom-fields`](../post-custom-fields.md) | Create a new custom field |
| Update field | [`PUT /custom-fields/{name}`](../put-custom-fields-name.md) | Update description/sort order |
| Delete field | [`DELETE /custom-fields/{name}`](../delete-custom-fields-name.md) | Delete field + options + pivot rows |
| Export fields | [`GET /custom-fields/export`](../get-custom-fields-export.md) | Export all fields with options as JSON |
| Import fields | [`POST /custom-fields/import`](../post-custom-fields-import.md) | Import fields (merge mode) |
| List options | [`GET /field-options/{field}`](../get-field-options.md) | List options with entry counts |
| Create option | [`POST /field-options/{field}`](../post-field-options.md) | Add a new option |
| Rename option | [`PUT /field-options/{field}/{id}`](../put-field-options-id.md) | Rename an option |
| Delete option | [`DELETE /field-options/{field}/{id}`](../delete-field-options-id.md) | Delete option + pivot rows |
| Discover fields | [`GET /fields`](../get-fields.md) | Schema metadata with resource links |
