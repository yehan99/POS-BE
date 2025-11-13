# Test API Endpoint - Backend connectivity test for Frontend# Test API Endpoint

# This script tests if the backend API is accessible from frontend

Write-Host "========================================"  -ForegroundColor Cyan

Write-Host "Testing Backend API Endpoints" -ForegroundColor CyanWrite-Host "========================================" -ForegroundColor Cyan

Write-Host "========================================" -ForegroundColor CyanWrite-Host "Testing Backend API Endpoints" -ForegroundColor Cyan

Write-Host ""Write-Host "========================================" -ForegroundColor Cyan

Write-Host ""

$baseUrl = "http://127.0.0.1:8000"

$baseUrl = "http://127.0.0.1:8000"

# Test 1: Check if server is running

Write-Host "Test 1: Checking if server is running..." -ForegroundColor Yellow# Test 1: Check if server is running

try {Write-Host "Test 1: Checking if server is running..." -ForegroundColor Yellow

    $health = Invoke-RestMethod -Uri "$baseUrl/up" -Method Get -TimeoutSec 5 -ErrorAction Stoptry {

    Write-Host "[OK] Server is running!" -ForegroundColor Green    $health = Invoke-RestMethod -Uri "$baseUrl/up" -Method Get -TimeoutSec 5

} catch {    Write-Host "✓ Server is running!" -ForegroundColor Green

    Write-Host "[FAIL] Server is not running!" -ForegroundColor Red} catch {

    Write-Host "Please start the server with: php artisan serve" -ForegroundColor Yellow    Write-Host "✗ Server is not running!" -ForegroundColor Red

    exit 1    Write-Host "Please start the server with: php artisan serve" -ForegroundColor Yellow

}    exit 1

}

Write-Host ""

Write-Host ""

# Test 2: Test CORS headers

Write-Host "Test 2: Checking CORS configuration..." -ForegroundColor Yellow# Test 2: Test CORS headers

try {Write-Host "Test 2: Checking CORS headers..." -ForegroundColor Yellow

    $cors Headers = @{try {

        "Origin" = "http://localhost:4200"    $headers = @{

        "Access-Control-Request-Method" = "POST"        "Origin" = "http://localhost:4200"

        "Access-Control-Request-Headers" = "content-type"        "Access-Control-Request-Method" = "POST"

    }        "Access-Control-Request-Headers" = "content-type"

        }

    $response = Invoke-WebRequest -Uri "$baseUrl/api/auth/register" `

        -Method Options `    $response = Invoke-WebRequest -Uri "$baseUrl/api/auth/register" -Method Options -Headers $headers -UseBasicParsing -ErrorAction Stop

        -Headers $corsHeaders `

        -UseBasicParsing `    if ($response.Headers.'Access-Control-Allow-Origin') {

        -ErrorAction Stop        Write-Host "✓ CORS is properly configured!" -ForegroundColor Green

            Write-Host "  Allowed Origin: $($response.Headers.'Access-Control-Allow-Origin')" -ForegroundColor White

    if ($response.Headers['Access-Control-Allow-Origin']) {    } else {

        Write-Host "[OK] CORS is properly configured!" -ForegroundColor Green        Write-Host "✗ CORS headers not found!" -ForegroundColor Red

        Write-Host "  Allowed Origin: $($response.Headers['Access-Control-Allow-Origin'])" -ForegroundColor White    }

    } else {} catch {

        Write-Host "[WARN] CORS headers not found" -ForegroundColor Yellow    Write-Host "⚠ CORS check failed (this might be normal)" -ForegroundColor Yellow

    }}

} catch {

    Write-Host "[WARN] CORS preflight check skipped" -ForegroundColor YellowWrite-Host ""

}

# Test 3: Test register endpoint validation

Write-Host ""Write-Host "Test 3: Testing register endpoint (validation)..." -ForegroundColor Yellow

try {

# Test 3: Test register endpoint    $body = @{

Write-Host "Test 3: Testing register endpoint..." -ForegroundColor Yellow        firstName = ""

try {    } | ConvertTo-Json

    $testBody = @{

        firstName = ""    $response = Invoke-RestMethod -Uri "$baseUrl/api/auth/register" `

    } | ConvertTo-Json        -Method Post `

        -Body $body `

    $result = Invoke-RestMethod -Uri "$baseUrl/api/auth/register" `        -ContentType "application/json" `

        -Method Post `        -Headers @{

        -Body $testBody `            "Accept" = "application/json"

        -ContentType "application/json" `            "Origin" = "http://localhost:4200"

        -Headers @{ "Accept" = "application/json"; "Origin" = "http://localhost:4200" } `        } -ErrorAction Stop

        -ErrorAction Stop

            Write-Host "✗ Unexpected success response" -ForegroundColor Red

    Write-Host "[WARN] Unexpected success response" -ForegroundColor Yellow} catch {

} catch {    $statusCode = $_.Exception.Response.StatusCode.value__

    if ($_.Exception.Response -and $_.Exception.Response.StatusCode.value__ -eq 422) {    if ($statusCode -eq 422) {

        Write-Host "[OK] Endpoint is working! (Validation error is expected)" -ForegroundColor Green        Write-Host "✓ Endpoint is working! (Validation error expected)" -ForegroundColor Green

        Write-Host "  Status: 422 Unprocessable Entity" -ForegroundColor White        Write-Host "  Status: 422 Unprocessable Entity" -ForegroundColor White

    } else {    } else {

        Write-Host "[FAIL] Unexpected error: $($_.Exception.Message)" -ForegroundColor Red        Write-Host "✗ Unexpected error: $($_.Exception.Message)" -ForegroundColor Red

    }    }

}}



Write-Host ""Write-Host ""



# Test 4: List available routes# Test 4: List available routes

Write-Host "Test 4: Available auth routes:" -ForegroundColor YellowWrite-Host "Test 4: Available auth routes:" -ForegroundColor Yellow

Write-Host "  POST   $baseUrl/api/auth/register" -ForegroundColor WhiteWrite-Host "  POST   $baseUrl/api/auth/register" -ForegroundColor White

Write-Host "  POST   $baseUrl/api/auth/login" -ForegroundColor WhiteWrite-Host "  POST   $baseUrl/api/auth/login" -ForegroundColor White

Write-Host "  POST   $baseUrl/api/auth/refresh" -ForegroundColor WhiteWrite-Host "  POST   $baseUrl/api/auth/refresh" -ForegroundColor White

Write-Host "  GET    $baseUrl/api/auth/me" -ForegroundColor WhiteWrite-Host "  GET    $baseUrl/api/auth/me" -ForegroundColor White

Write-Host "  POST   $baseUrl/api/auth/logout" -ForegroundColor WhiteWrite-Host "  POST   $baseUrl/api/auth/logout" -ForegroundColor White



Write-Host ""Write-Host ""

Write-Host "========================================" -ForegroundColor CyanWrite-Host "========================================" -ForegroundColor Cyan

Write-Host "Test Summary" -ForegroundColor CyanWrite-Host "Test Summary" -ForegroundColor Cyan

Write-Host "========================================" -ForegroundColor CyanWrite-Host "========================================" -ForegroundColor Cyan

Write-Host ""Write-Host ""

Write-Host "Backend URL:  $baseUrl" -ForegroundColor WhiteWrite-Host "Backend URL: $baseUrl" -ForegroundColor White

Write-Host "Frontend URL: http://localhost:4200" -ForegroundColor WhiteWrite-Host "Frontend URL: http://localhost:4200" -ForegroundColor White

Write-Host ""Write-Host ""

Write-Host "Angular Configuration:" -ForegroundColor YellowWrite-Host "Your Angular app should use:" -ForegroundColor Yellow

Write-Host "  API_URL = '$baseUrl/api'" -ForegroundColor CyanWrite-Host "  API_URL = '$baseUrl/api'" -ForegroundColor Cyan

Write-Host ""Write-Host ""

Write-Host "For detailed integration guide, see: FRONTEND-INTEGRATION.md" -ForegroundColor YellowWrite-Host "Example Angular service code:" -ForegroundColor Yellow

Write-Host ""Write-Host "  See FRONTEND-INTEGRATION.md for complete examples" -ForegroundColor Cyan

Write-Host ""
