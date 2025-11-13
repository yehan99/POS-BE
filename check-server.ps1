# Check Laravel Server Status

Write-Host "Checking Laravel Server Status..." -ForegroundColor Cyan
Write-Host ""

# Check if port 8000 is in use
$port = 8000
$connection = Get-NetTCPConnection -LocalPort $port -ErrorAction SilentlyContinue

if ($connection) {
    Write-Host "✓ Server is RUNNING on port $port" -ForegroundColor Green
    Write-Host ""
    Write-Host "Process Details:" -ForegroundColor Yellow
    $processId = $connection.OwningProcess
    $process = Get-Process -Id $processId
    Write-Host "  Process: $($process.Name)" -ForegroundColor White
    Write-Host "  PID: $processId" -ForegroundColor White
    Write-Host "  Memory: $([math]::Round($process.WorkingSet64 / 1MB, 2)) MB" -ForegroundColor White
    Write-Host ""
    Write-Host "To stop the server:" -ForegroundColor Yellow
    Write-Host "  taskkill /PID $processId /F" -ForegroundColor White
} else {
    Write-Host "✗ Server is NOT running on port $port" -ForegroundColor Red
    Write-Host ""
    Write-Host "To start the server, run:" -ForegroundColor Yellow
    Write-Host "  php artisan serve" -ForegroundColor White
    Write-Host "  OR" -ForegroundColor Yellow
    Write-Host "  .\start-server.ps1 (with auto-restart)" -ForegroundColor White
}

Write-Host ""

# Check for any PHP processes
$phpProcesses = Get-Process php -ErrorAction SilentlyContinue
if ($phpProcesses) {
    Write-Host "PHP Processes Running:" -ForegroundColor Cyan
    foreach ($proc in $phpProcesses) {
        Write-Host "  PID: $($proc.Id) | Memory: $([math]::Round($proc.WorkingSet64 / 1MB, 2)) MB" -ForegroundColor White
    }
} else {
    Write-Host "No PHP processes found" -ForegroundColor Gray
}

Write-Host ""

# Check database connection
Write-Host "Checking Database Connection..." -ForegroundColor Cyan
$dbCheck = php artisan tinker --execute="echo 'Connected: ' . (DB::connection()->getPdo() ? 'Yes' : 'No');" 2>&1

if ($LASTEXITCODE -eq 0) {
    Write-Host "✓ Database connection OK" -ForegroundColor Green
} else {
    Write-Host "✗ Database connection issue" -ForegroundColor Red
    Write-Host $dbCheck -ForegroundColor Yellow
}

Write-Host ""
