# Laravel Development Server Auto-Restart Script
# This script will automatically restart the server if it stops

$Host.UI.RawUI.WindowTitle = "Laravel Development Server"

Write-Host "=======================================" -ForegroundColor Cyan
Write-Host "Laravel Development Server Auto-Restart" -ForegroundColor Cyan
Write-Host "=======================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Server will auto-restart if it stops." -ForegroundColor Yellow
Write-Host "Press Ctrl+C to stop permanently." -ForegroundColor Yellow
Write-Host ""

$restartCount = 0

while ($true) {
    if ($restartCount -gt 0) {
        Write-Host "Restart count: $restartCount" -ForegroundColor Magenta
    }

    Write-Host "Starting Laravel server..." -ForegroundColor Green

    # Clear config cache before starting
    php artisan config:clear | Out-Null

    # Start the server
    php artisan serve --host=127.0.0.1 --port=8000

    # If we get here, the server stopped
    $restartCount++

    Write-Host ""
    Write-Host "Server stopped! Restarting in 3 seconds..." -ForegroundColor Red
    Start-Sleep -Seconds 3
}
