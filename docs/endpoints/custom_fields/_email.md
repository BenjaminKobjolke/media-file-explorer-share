# `_email` — Email Suppression

Override the global `email_enabled` setting for a single request. Set to a falsy value to suppress the email notification.

| Property | Value |
|----------|-------|
| Type | `bool` |
| Handlers | `TextHandler`, `FileHandler` |
| Default | Inherits from `email_enabled` config |

## Accepted Values

| Value | Effect |
|-------|--------|
| `false` (bool) | Suppresses email |
| `"false"` (string) | Suppresses email (coerced) |
| `"0"` (string) | Suppresses email (coerced) |
| `""` (empty string) | Suppresses email (coerced) |
| Any other truthy value | Email is sent (if `email_enabled` is on) |
| Field omitted | Email is sent (if `email_enabled` is on) |

## Behavior

- When `_email` evaluates to `false`, the email action is skipped even if `email_enabled` is `true` in config
- When `_email` is omitted or truthy, normal `email_enabled` behavior applies
- The field is stripped from the stored body — it never reaches the database or disk

## Examples

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

**Combine with `_id`:**

```bash
curl -X POST https://example.com/share.php \
  -H "Content-Type: application/json" \
  -d '{"_id": 3, "_email": false, "text_or_url": "Silent attachment"}'
```
