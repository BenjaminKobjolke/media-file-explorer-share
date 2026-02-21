# Media File Explorer Share

Server-side companion for [Media File Explorer](https://play.google.com/store/apps/details?id=de.xida.folder_gallery), an Android media gallery app.

This PHP endpoint receives shared text and files from the app's **API Integration** feature and dispatches them to email and/or disk storage on your server.

## Features

- **Email notifications** — receive shared content as HTML-formatted emails (Markdown and Logarte debug exports supported)
- **File storage** — save uploaded files and text payloads to a server directory
- **Basic Auth** — optional username/password authentication for incoming requests
- **Configurable limits** — set max file and text payload sizes

## Requirements

- PHP 8.4+
- [Composer](https://getcomposer.org/)
- `mail()` function enabled (if using email notifications)
- `php.ini` settings for file uploads:
  - `upload_max_filesize` >= 10M
  - `post_max_size` >= 10M

## Quick Start

1. **Clone** the repository and install dependencies:
   ```bash
   git clone <repo-url>
   cd media-file-explorer-share
   composer install
   ```
2. **Copy** the config template and edit it:
   ```bash
   cp config/app.php.example config/app.php
   ```
   Set your `email_to` address and enable/disable features in `config/app.php`.
3. **Configure the app** — open Media File Explorer, go to **Settings > API Integration**, and set the endpoint URL to `https://yourdomain.com/path/to/share.php`
4. **Test** — share a file or text from the app and check your inbox

## Configuration Reference

Edit `config/app.php` (copy from `config/app.php.example`):

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `email_enabled` | bool | `true` | Send payloads via email |
| `email_to` | string | `'you@example.com'` | Recipient email address |
| `storage_enabled` | bool | `false` | Save payloads to disk |
| `storage_path` | string | `__DIR__ . '/../uploads'` | Directory for stored files/texts |
| `auth_enabled` | bool | `false` | Require Basic Auth on requests |
| `auth_username` | string | `'user'` | Expected Basic Auth username |
| `auth_password` | string | `'pass'` | Expected Basic Auth password |
| `max_file_size` | int | `10485760` (10 MB) | Max file upload size in bytes |
| `max_text_size` | int | `1048576` (1 MB) | Max text payload size in bytes |
| `response_message` | string | `'OK'` | Response body sent back to the app |

If using storage, ensure the `storage_path` directory is writable by the web server (`chmod 755`).

## Directory Structure

```
media-file-explorer-share/
├── share.php                           # Bootstrap (loads config + autoloader)
├── composer.json                       # Composer config with PSR-4 autoloading
├── config/
│   ├── app.php.example                # Config template (committed)
│   └── app.php                        # Local config (gitignored)
├── inc/
│   ├── WebhookHandler.php              # Orchestrator — validates & dispatches
│   ├── AuthValidator.php               # Basic Auth credential check
│   ├── RequestContext.php              # DTO: ip, user-agent, time, domain
│   ├── handlers/
│   │   ├── FileHandler.php            # $_FILES processing & dispatch
│   │   └── TextHandler.php            # php://input processing & dispatch
│   ├── actions/
│   │   ├── EmailAction.php            # Email sending (plain, HTML, MIME)
│   │   └── StorageAction.php          # Save files/text to disk
│   └── formatters/
│       ├── LogarteFormatter.php       # Logarte debug export → HTML
│       └── MarkdownFormatter.php      # Markdown → HTML for emails
├── tools/
│   └── tests.bat                      # Test runner placeholder
├── README.md
├── CLAUDE.md
└── LICENSE
```

## How It Works

1. `share.php` loads the config and bootstraps `WebhookHandler` via Composer autoloading
2. `WebhookHandler` validates the request method (POST only) and auth credentials
3. File uploads are routed to `FileHandler`, text/JSON payloads to `TextHandler`
4. Each handler calls `StorageAction` and/or `EmailAction` based on config
5. The configured `response_message` is returned with HTTP 200

## App Configuration

In Media File Explorer, go to **Settings > API Integration** and configure:

| Setting | Value |
|---------|-------|
| **Endpoint URL** | `https://yourdomain.com/path/to/share.php` |
| **Authentication** | Basic Auth with the username/password from your config (if `auth_enabled` is `true`) |

The app sends text payloads as JSON (`text_or_url` field) and file uploads as multipart form data.

## License

MIT — Benjamin Kobjolke
