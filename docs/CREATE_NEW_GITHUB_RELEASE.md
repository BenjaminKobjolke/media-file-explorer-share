# Creating a New GitHub Release

## Prerequisites

- `gh` CLI installed and authenticated (`gh auth status`)
- `composer` installed
- Clean git working tree (no uncommitted changes)

## Runtime Files (included in release zip)

- `share.php` - application entry point
- `composer.json` - dependency manifest
- `composer.lock` - locked dependency versions
- `inc/` - application source code (all subdirectories)
- `config/app.php.example` - configuration template
- `vendor/` - production dependencies (rebuilt with `--no-dev`)
- `README.md` - project documentation
- `LICENSE` - license file

## Excluded Files (not in release zip)

- `vendor/` (from source - rebuilt fresh with `--no-dev`)
- `tools/` - development scripts
- `docs/` - internal documentation
- `hoppscotch/` - API collection
- `config/app.php` - local config (user must create from example)
- `.git/`, `.gitignore` - version control
- `CLAUDE.md` - AI assistant instructions

## Versioning

The current version is stored in the `VERSION` file in the project root (plain text, e.g. `1.0.0`). Update this file before creating a new release. Versions follow semver. Each release creates a git tag prefixed with `v` (e.g. `v1.0.0`).

## Release Script

**Location:** `tools/github-release.bat`

**Usage:**
```
tools\github-release.bat
```

The script reads the version from the `VERSION` file. You can optionally override it with an argument:
```
tools\github-release.bat 2.0.0
```

## What the Script Does

1. Reads version from `VERSION` file (or uses the CLI argument if provided)
2. Checks that `gh` and `composer` are available
3. Creates a temporary staging directory
4. Copies runtime files (`share.php`, `composer.json`, `composer.lock`, `README.md`, `LICENSE`, `inc/`, `config/app.php.example`)
5. Runs `call composer install --no-dev --optimize-autoloader` in the staging directory
6. Creates a zip archive using `call tar -a -cf` (Windows 10+ built-in)
7. Creates a GitHub release with `call gh release create v<VERSION>` and uploads the zip
8. Cleans up the staging directory and zip file

## Important: Batch Script Convention

External commands (`composer`, `tar`, `gh`) must be invoked with the `call` keyword in `.bat` files. Without `call`, the batch script transfers control to the external command and never returns to execute subsequent steps.
