# Media File Explorer Share

PHP webhook receiver for the Media File Explorer Android app. Accepts text/file POST requests and dispatches them to email and/or disk storage.

## Architecture

Single-endpoint PHP app (`share.php`) using PSR-4 autoloading via Composer.

### Namespace Map

| Namespace | Directory | Classes |
|---|---|---|
| `App` | `inc/` | `WebhookHandler`, `RequestContext`, `AuthValidator` |
| `App\Handlers` | `inc/handlers/` | `FileHandler`, `TextHandler` |
| `App\Actions` | `inc/actions/` | `EmailAction`, `StorageAction` |
| `App\Formatters` | `inc/formatters/` | `LogarteFormatter`, `MarkdownFormatter` |

### Request Flow

1. `share.php` loads config + Composer autoloader, creates `WebhookHandler`
2. `WebhookHandler` validates POST method and optional Basic Auth
3. Routes to `FileHandler` (multipart file upload) or `TextHandler` (JSON/text)
4. Handlers call `StorageAction` and/or `EmailAction` based on config
5. Returns configured response message with HTTP 200

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
