# Media File Explorer Share

PHP webhook receiver for the Media File Explorer Android app. Accepts text/file POST requests and dispatches them to email and/or disk storage.

## Architecture

PHP app with two entry points using PSR-4 autoloading via Composer:

- `share.php` — POST webhook receiver (accepts text/file uploads)
- `api.php` — GET read-only JSON API (Slim 4, queries the SQLite database)

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
4. Handlers call `StorageAction`, `DatabaseAction`, and/or `EmailAction` based on config
5. Returns the new database entry ID (when `db_enabled`) or the configured response message with HTTP 200

#### Read API (`api.php`)

1. `api.php` loads config + Composer autoloader, gates on `api_enabled` and `db_enabled`
2. Creates a Slim 4 app with JSON error handling
3. `GET /entries/{id}` — optional Basic Auth check, then `DatabaseAction::getById()`
4. Returns JSON response (200 with entry data, 401 if unauthorized, 404 if not found)

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
