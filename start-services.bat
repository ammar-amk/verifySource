@echo off
REM ================================================================
REM VerifySource - Start All Background Services (Windows)
REM ================================================================
REM This script starts all required background services:
REM - Laravel Scheduler (for automated crawling)
REM - Queue Worker (for processing crawl jobs)
REM ================================================================

echo.
echo ================================================================
echo   VerifySource - Starting Background Services
echo ================================================================
echo.

cd /d "%~dp0"

REM Check if PHP is available
php --version >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo ERROR: PHP is not installed or not in PATH
    pause
    exit /b 1
)

echo [1/3] Starting Laravel Scheduler...
start "VerifySource Scheduler" cmd /k "php artisan schedule:work"
timeout /t 2 /nobreak >nul

echo [2/3] Starting Queue Worker (crawling queue)...
start "VerifySource Queue - Crawling" cmd /k "php artisan queue:work --queue=crawling --tries=3 --timeout=600"
timeout /t 2 /nobreak >nul

echo [3/3] Starting Queue Worker (default queue)...
start "VerifySource Queue - Default" cmd /k "php artisan queue:work --queue=default --tries=3 --timeout=300"
timeout /t 2 /nobreak >nul

echo.
echo ================================================================
echo   All services started successfully!
echo ================================================================
echo.
echo Running services:
echo   - Laravel Scheduler (automated crawling every 10 min)
echo   - Queue Worker - Crawling (processes crawl jobs)
echo   - Queue Worker - Default (processes verification jobs)
echo.
echo To stop services: Close the opened terminal windows
echo.
pause
