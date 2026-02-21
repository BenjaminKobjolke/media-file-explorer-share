# Hoppscotch Collections

## Authentication Patterns

### Basic API Key Auth
For endpoints that only require API key authentication (no user context):

```json
{
    "key": "<<apiKey>>"
}
```

Used by:
- `user/settings/all-keys`

### User Auth
For endpoints that require user authentication:

```json
{
    "user_id": <<userId>>,
    "api_token": "<<apiToken>>",
    "software": "<<software>>"
}
```

Used by:
- `user/settings/keys`
- `user/settings/create`
- `user/settings/get`
- `user/settings/all`
- `user/settings/delete`
- `user/settings/deleteall`
- `spamai/spamcheck`
- `spamai/info`

### Machine Auth
For machine-to-machine authentication:

```json
{
    "key": "<<apiKey>>",
    "machine_id": <<machineId>>,
    "password": "<<password>>",
    "email": "<<email>>",
    "software": "<<software>>"
}
```

Used by:
- `spamai/user-email-infos`
- `spamai/machine-spamcheck`
- `spamai/machine-info/{id}`

## Environment Variables

Configure these in Hoppscotch environment:

| Variable | Description |
|----------|-------------|
| `baseURLApi` | Base URL for API (e.g., `http://localhost/ai-chat-api/`) |
| `apiKey` | API key for basic auth |
| `userId` | Numeric user ID |
| `apiToken` | User API token hash |
| `software` | App identifier (e.g., `spam.ai`, `de.xida.ai`) |
| `appVersion` | App version string |
