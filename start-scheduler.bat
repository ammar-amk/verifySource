@echo off
REM ================================================================
REM VerifySource - Start Laravel Scheduler (Windows)
REM ================================================================
REM This script keeps the Laravel scheduler running continuously
REM It should be run in the background or as a service
REM ================================================================

echo Starting VerifySource Scheduler...
echo Press Ctrl+C to stop
echo.

cd /d "%~dp0"

:loop
php artisan schedule:work
if %ERRORLEVEL% NEQ 0 (
    echo Scheduler stopped with error code %ERRORLEVEL%
    echo Restarting in 10 seconds...
    timeout /t 10 /nobreak >nul
)
goto loop
