# Media File Explorer Share

Server-side companion for [Media File Explorer](https://play.google.com/store/apps/details?id=de.xida.folder_gallery), an Android media gallery app.

This PHP endpoint receives shared text and files from the app's **API Integration** feature and dispatches them to email and/or disk storage on your server.

## Features

- **Email notifications** — receive shared content as HTML-formatted emails (Markdown and Logarte debug exports supported)
- **File storage** — save uploaded files and text payloads to a server directory
- **Basic Auth** — optional username/password authentication for incoming requests
- **Configurable limits** — set max file and text payload sizes
- **Zero dependencies** — plain PHP, no frameworks or Composer packages required

## Requirements

- PHP 7.4+
- `mail()` function enabled (if using email notifications)
- `php.ini` settings for file uploads:
  - `upload_max_filesize` >= 10M
  - `post_max_size` >= 10M

## Quick Start

1. **Upload** the project folder to your web server
2. **Edit** `share.php` — set your `email_to` address and enable/disable features:
   ```php
   $config = [
     'email_enabled'  => true,
     'email_to'       => 'you@example.com',
     'storage_enabled' => false,
     'auth_enabled'   => false,
   ];
   ```
3. **Configure the app** — open Media File Explorer, go to **Settings > API Integration**, and set the endpoint URL to `https://yourdomain.com/path/to/share.php`
4. **Test** — share a file or text from the app and check your inbox

## Configuration Reference

Edit the `$config` array in `share.php`:

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `email_enabled` | bool | `true` | Send payloads via email |
| `email_to` | string | `'youremail@domain.com'` | Recipient email address |
| `storage_enabled` | bool | `false` | Save payloads to disk |
| `storage_path` | string | `__DIR__ . '/uploads'` | Directory for stored files/texts |
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
├── share.php                           # Config + bootstrap (edit this)
├── README.md
├── LICENSE
└── inc/
    ├── WebhookHandler.php              # Orchestrator — validates & dispatches
    ├── AuthValidator.php               # Basic Auth credential check
    ├── RequestContext.php              # DTO: ip, user-agent, time, domain
    ├── handlers/
    │   ├── FileHandler.php            # $_FILES processing & dispatch
    │   └── TextHandler.php            # php://input processing & dispatch
    ├── actions/
    │   ├── EmailAction.php            # Email sending (plain, HTML, MIME)
    │   └── StorageAction.php          # Save files/text to disk
    └── formatters/
        ├── LogarteFormatter.php       # Logarte debug export → HTML
        └── MarkdownFormatter.php      # Markdown → HTML for emails
```

## How It Works

1. `share.php` defines the config and bootstraps `WebhookHandler`
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
