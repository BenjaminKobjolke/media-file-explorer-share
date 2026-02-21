@echo off
setlocal enabledelayedexpansion

REM ============================================================
REM GitHub Release Script for media-file-explorer-share
REM Usage: tools\github-release.bat [version]
REM   version argument is optional; defaults to VERSION file
REM Example: tools\github-release.bat
REM Example: tools\github-release.bat 2.0.0
REM ============================================================

cd /d "%~dp0.."

REM --- Determine version ---
if not "%~1"=="" (
    set "VERSION=%~1"
) else (
    if not exist "VERSION" (
        echo ERROR: No version argument and no VERSION file found.
        echo Usage: tools\github-release.bat [version]
        exit /b 1
    )
    set /p VERSION=<VERSION
)

if "!VERSION!"=="" (
    echo ERROR: VERSION file is empty.
    exit /b 1
)
set "PROJECT_NAME=media-file-explorer-share"
set "ZIP_NAME=%PROJECT_NAME%-v%VERSION%.zip"
set "STAGING_DIR=%TEMP%\%PROJECT_NAME%-release-staging"

echo.
echo === Creating GitHub release v%VERSION% ===
echo.

REM --- Check prerequisites ---
where gh >nul 2>&1
if errorlevel 1 (
    echo ERROR: gh CLI not found. Install from https://cli.github.com/
    exit /b 1
)

where composer >nul 2>&1
if errorlevel 1 (
    echo ERROR: Composer not found. Install from https://getcomposer.org/
    exit /b 1
)

REM --- Clean up any previous staging dir ---
if exist "%STAGING_DIR%" (
    echo Cleaning previous staging directory...
    rmdir /s /q "%STAGING_DIR%"
)

REM --- Create staging directory ---
echo [1/6] Creating staging directory...
mkdir "%STAGING_DIR%"

REM --- Copy runtime files ---
echo [2/6] Copying runtime files...
copy "share.php" "%STAGING_DIR%\" >nul
copy "api.php" "%STAGING_DIR%\" >nul
copy ".htaccess" "%STAGING_DIR%\" >nul
copy "composer.json" "%STAGING_DIR%\" >nul
copy "composer.lock" "%STAGING_DIR%\" >nul
copy "README.md" "%STAGING_DIR%\" >nul
copy "LICENSE" "%STAGING_DIR%\" >nul

xcopy "inc" "%STAGING_DIR%\inc\" /e /i /q >nul
xcopy "config\app.php.example" "%STAGING_DIR%\config\" /i /q >nul

REM --- Install production dependencies ---
echo [3/6] Installing production dependencies (no-dev)...
pushd "%STAGING_DIR%"
call composer install --no-dev --optimize-autoloader --no-interaction --quiet
if errorlevel 1 (
    popd
    echo ERROR: Composer install failed.
    rmdir /s /q "%STAGING_DIR%"
    exit /b 1
)
popd

REM --- Create zip ---
echo [4/6] Creating %ZIP_NAME%...
if exist "%ZIP_NAME%" del "%ZIP_NAME%"
pushd "%STAGING_DIR%"
call tar -a -cf "%~dp0..\%ZIP_NAME%" *
if errorlevel 1 (
    popd
    echo ERROR: Failed to create zip archive.
    rmdir /s /q "%STAGING_DIR%"
    exit /b 1
)
popd

REM --- Create GitHub release ---
echo [5/6] Creating GitHub release v%VERSION%...
call gh release create "v%VERSION%" "%ZIP_NAME%" --title "v%VERSION%" --generate-notes
if errorlevel 1 (
    echo ERROR: Failed to create GitHub release.
    del "%ZIP_NAME%" 2>nul
    rmdir /s /q "%STAGING_DIR%"
    exit /b 1
)

REM --- Clean up ---
echo [6/6] Cleaning up...
del "%ZIP_NAME%" 2>nul
rmdir /s /q "%STAGING_DIR%"

echo.
echo === Release v%VERSION% created successfully! ===
echo.

endlocal
