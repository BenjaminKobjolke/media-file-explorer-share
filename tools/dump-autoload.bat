@echo off
REM Regenerate Composer autoloader
cd /d "%~dp0.."
composer dump-autoload
