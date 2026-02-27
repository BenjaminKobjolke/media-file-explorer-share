# `_id` — Append Mode

Attach text or files to an existing entry instead of creating a new one.

| Property | Value |
|----------|-------|
| Type | `int` |
| Handlers | `TextHandler`, `FileHandler` |
| Requires | `db_enabled = true` |

## Behavior

1. The handler casts the value to `int`
2. Looks up the parent entry in the database — returns **404** if not found
3. Returns **400** if `db_enabled` is off
4. Inserts an attachment row linked to the parent entry
5. The response returns the **parent `_id`** (not a new ID), keeping IDs sequential

## Examples

**Text (JSON body):**

```bash
curl -X POST https://example.com/share.php \
  -H "Content-Type: application/json" \
  -d '{"_id": 1, "text_or_url": "Additional note for entry 1"}'
```

**File (multipart form-data):**

```bash
curl -X POST https://example.com/share.php \
  -F "file=@photo.jpg" \
  -F "_id=1"
```
