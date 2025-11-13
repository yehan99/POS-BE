@echo off
title Laravel Development Server
echo Starting Laravel Development Server...
echo This will auto-restart if the server stops.
echo Press Ctrl+C to stop permanently.
echo.

:start
php artisan serve --host=127.0.0.1 --port=8000
echo.
echo Server stopped! Restarting in 3 seconds...
timeout /t 3 /nobreak > nul
goto start
