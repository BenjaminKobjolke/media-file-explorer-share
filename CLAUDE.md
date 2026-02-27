# Media File Explorer Share

PHP webhook receiver for the Media File Explorer Android app. Accepts text/file POST requests and dispatches them to email and/or disk storage.

## Architecture

PHP app with two entry points using PSR-4 autoloading via Composer:

- `share.php` — POST webhook receiver (accepts text/file uploads)
- `api.php` — JSON API (Slim 4, CRUD operations on the SQLite database)

### Namespace Map

| Namespace | Directory | Classes |
|---|---|---|
| `App` | `inc/` | `WebhookHandler`, `RequestContext`, `AuthValidator` |
| `App\Handlers` | `inc/Handlers/` | `FileHandler`, `TextHandler` |
| `App\Actions` | `inc/Actions/` | `DatabaseAction`, `EmailAction`, `StorageAction` |
| `App\Middleware` | `inc/Middleware/` | `CorsMiddleware` |
| `App\Formatters` | `inc/Formatters/` | `LogarteFormatter`, `MarkdownFormatter` |

### Request Flow

1. `share.php` loads config + Composer autoloader, creates `WebhookHandler`
2. `WebhookHandler` validates POST method and optional Basic Auth
3. Routes to `FileHandler` (multipart file upload) or `TextHandler` (JSON/text)
4. Handlers check for `_id` — if present, validates parent entry exists and appends as attachment; otherwise creates a new entry
5. Handlers call `StorageAction`, `DatabaseAction`, and/or `EmailAction` based on config
6. Returns the parent entry ID (append mode) or the new entry ID (when `db_enabled`) or the configured response message with HTTP 200

#### Reserved Fields

Fields prefixed with `_` are internal control fields stripped before storage:

| Field | Type | Effect |
|---|---|---|
| `_id` | int | Append mode — attach to existing entry instead of creating new one |
| `_email` | bool | `false` suppresses email for this request even when `email_enabled` is on |

#### Append Mode

Send `_id` with your POST to attach text/files to an existing entry:
- **Text**: include `"_id": 1` in the JSON body alongside other fields
- **File**: include `_id=1` as a form field alongside the file upload
- Extra form fields sent with file uploads are captured as JSON in the attachment's `body` column
- Returns the parent `_id` (not a new ID) so IDs remain sequential

#### Read API (`api.php`)

1. `api.php` loads config + Composer autoloader, gates on `api_enabled`
2. Creates a Slim 4 app with JSON error handling
3. `GET /entries` — requires `db_enabled`, optional Basic Auth, paginated entry list with attachment counts (query params: `page`, `per_page`)
4. `GET /entries/{id}` — requires `db_enabled`, optional Basic Auth, then `DatabaseAction::getByIdWithAttachments()` returns entry with nested attachments array and `file_url` links
5. `POST /entries` with `{"id": 1}` body — requires `db_enabled`, alternative to `GET /entries/{id}`, same auth and response; returns 400 if `id` is missing
6. `PUT /entries/{id}` — requires `db_enabled`, optional Basic Auth, updates subject and/or body, returns full entry
7. `DELETE /entries/{id}` — requires `db_enabled`, optional Basic Auth, deletes entry + attachments + files from disk
8. `DELETE /attachments/{id}` — requires `db_enabled`, optional Basic Auth, deletes single attachment + file from disk
9. `GET /entries/{id}/file` — requires `db_enabled`, optional Basic Auth, serves entry's main file
10. `GET /files/{id}` — requires `db_enabled`, optional Basic Auth, serves attachment file from disk with `realpath()` traversal protection
11. `GET /fields` — no auth, no `db_enabled` required; returns reserved field metadata
12. `GET /auth` — no auth, no `db_enabled` required; returns `{"method": "none"|"basic"}` indicating required auth method
13. CORS middleware handles preflight OPTIONS requests for configured origins (`cors_origins` in config)
14. All JSON responses include `_version` (from `VERSION` file) and optionally `_deploy_id` (from `deploy.ver`) as top-level keys; array responses are wrapped in a `{"_version": ..., "data": [...]}` envelope

##### Clean URLs

A root `.htaccess` rewrites `api/...` to `api.php/...`, so both forms work:
- `api.php/entries/1` (direct)
- `api/entries/1` (clean URL via rewrite)

## Build / Test

```bash
composer install          # generate autoloader
tools\tests.bat           # run tests (placeholder)
```

## Configuration

```bash
cp config/app.php.example config/app.php   # then edit with your values
```

`config/app.php` is gitignored. The example template is committed.

## Conventions

- PHP 7.4+ with `declare(strict_types=1)` in every file
- PSR-4 autoloading: `App\` maps to `inc/`
- Static handler/action/formatter methods (no DI container)
- Config passed as plain array from `config/app.php`
- Every endpoint must have a corresponding Hoppscotch request in `hoppscotch/` and a documentation file in `docs/endpoints/`
