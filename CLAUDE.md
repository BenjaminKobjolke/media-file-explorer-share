# Media File Explorer Share

PHP webhook receiver for the Media File Explorer Android app. Accepts text/file POST requests and dispatches them to email and/or disk storage.

## Architecture

PHP app with two entry points using PSR-4 autoloading via Composer:

- `share.php` — POST webhook receiver (accepts text/file uploads)
- `api.php` — GET/POST read-only JSON API (Slim 4, queries the SQLite database)

### Namespace Map

| Namespace | Directory | Classes |
|---|---|---|
| `App` | `inc/` | `WebhookHandler`, `RequestContext`, `AuthValidator` |
| `App\Handlers` | `inc/Handlers/` | `FileHandler`, `TextHandler` |
| `App\Actions` | `inc/Actions/` | `DatabaseAction`, `EmailAction`, `StorageAction` |
| `App\Formatters` | `inc/Formatters/` | `LogarteFormatter`, `MarkdownFormatter` |

### Request Flow

1. `share.php` loads config + Composer autoloader, creates `WebhookHandler`
2. `WebhookHandler` validates POST method and optional Basic Auth
3. Routes to `FileHandler` (multipart file upload) or `TextHandler` (JSON/text)
4. Handlers check for `entry_id` — if present, validates parent entry exists and appends as attachment; otherwise creates a new entry
5. Handlers call `StorageAction`, `DatabaseAction`, and/or `EmailAction` based on config
6. Returns the parent entry ID (append mode) or the new entry ID (when `db_enabled`) or the configured response message with HTTP 200

#### Append Mode

Send `entry_id` with your POST to attach text/files to an existing entry:
- **Text**: include `"entry_id": 1` in the JSON body alongside other fields
- **File**: include `entry_id=1` as a form field alongside the file upload
- Extra form fields sent with file uploads are captured as JSON in the attachment's `body` column
- Returns the parent `entry_id` (not a new ID) so IDs remain sequential

#### Read API (`api.php`)

1. `api.php` loads config + Composer autoloader, gates on `api_enabled` and `db_enabled`
2. Creates a Slim 4 app with JSON error handling
3. `GET /entries/{id}` — optional Basic Auth check, then `DatabaseAction::getByIdWithAttachments()` returns entry with nested attachments array and `file_url` links
4. `POST /entries` with `{"id": 1}` body — alternative to `GET /entries/{id}`, same auth and response; returns 400 if `id` is missing
5. `GET /files/{id}` — optional Basic Auth check, serves attachment file from disk with `realpath()` traversal protection
6. Returns JSON response (200 with entry data, 400 if missing id, 401 if unauthorized, 404 if not found)

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
