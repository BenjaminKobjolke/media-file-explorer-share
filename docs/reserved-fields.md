# Reserved Fields Reference

Fields prefixed with `_` are internal control fields. They are **stripped before storage** — they never appear in the database or in API responses.

## `_id` — Append Mode

Attach text or files to an existing entry instead of creating a new one.

| Property | Value |
|----------|-------|
| Type | `int` |
| Handlers | `TextHandler`, `FileHandler` |
| Requires | `db_enabled = true` |

### Behavior

1. The handler casts the value to `int`
2. Looks up the parent entry in the database — returns **404** if not found
3. Returns **400** if `db_enabled` is off
4. Inserts an attachment row linked to the parent entry
5. The response returns the **parent `_id`** (not a new ID), keeping IDs sequential

### Examples

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

---

## `_email` — Email Suppression

Override the global `email_enabled` setting for a single request. Set to a falsy value to suppress the email notification.

| Property | Value |
|----------|-------|
| Type | `bool` |
| Handlers | `TextHandler`, `FileHandler` |
| Default | Inherits from `email_enabled` config |

### Accepted Values

| Value | Effect |
|-------|--------|
| `false` (bool) | Suppresses email |
| `"false"` (string) | Suppresses email (coerced) |
| `"0"` (string) | Suppresses email (coerced) |
| `""` (empty string) | Suppresses email (coerced) |
| Any other truthy value | Email is sent (if `email_enabled` is on) |
| Field omitted | Email is sent (if `email_enabled` is on) |

### Behavior

- When `_email` evaluates to `false`, the email action is skipped even if `email_enabled` is `true` in config
- When `_email` is omitted or truthy, normal `email_enabled` behavior applies
- The field is stripped from the stored body — it never reaches the database or disk

### Examples

**Suppress email on a text payload:**

```bash
curl -X POST https://example.com/share.php \
  -H "Content-Type: application/json" \
  -d '{"_email": false, "text_or_url": "Log this but do not email"}'
```

**Suppress email on a file upload:**

```bash
curl -X POST https://example.com/share.php \
  -F "file=@backup.zip" \
  -F "_email=false"
```

**Combine both reserved fields:**

```bash
curl -X POST https://example.com/share.php \
  -H "Content-Type: application/json" \
  -d '{"_id": 3, "_email": false, "text_or_url": "Silent attachment"}'
```

---

## `_project` — Project Tag

Tag the entry with a project name for grouping and filtering.

| Property | Value |
|----------|-------|
| Type | `string` |
| Handlers | `TextHandler`, `FileHandler` |
| Default | None (field is optional) |

### Behavior

- When present, the value is stored in the entry's `project` column
- The field is stripped from the stored body — it never appears in the `body` or `subject` columns
- Any non-empty string is accepted as a valid project name

### Examples

**Tag a text entry with a project:**

```bash
curl -X POST https://example.com/share.php \
  -H "Content-Type: application/json" \
  -d '{"_project": "My Project", "text_or_url": "Note for this project"}'
```

**Tag a file upload with a project:**

```bash
curl -X POST https://example.com/share.php \
  -F "file=@report.pdf" \
  -F "_project=My Project"
```

**Combine with other reserved fields:**

```bash
curl -X POST https://example.com/share.php \
  -H "Content-Type: application/json" \
  -d '{"_id": 2, "_email": false, "_project": "My Project", "text_or_url": "Silent attachment with project tag"}'
```
