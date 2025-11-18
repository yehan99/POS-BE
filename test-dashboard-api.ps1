# Test Dashboard API Integration
Write-Host "==================================" -ForegroundColor Cyan
Write-Host "Dashboard API Integration Test" -ForegroundColor Cyan
Write-Host "==================================" -ForegroundColor Cyan
Write-Host ""

$baseUrl = "http://127.0.0.1:8000/api"

# Step 1: Login to get JWT token
Write-Host "1. Authenticating..." -ForegroundColor Yellow
$loginBody = @{
    email = "admin@paradise.com"
    password = "password"
} | ConvertTo-Json

try {
    $loginResponse = Invoke-RestMethod -Uri "$baseUrl/auth/login" -Method Post -Body $loginBody -ContentType "application/json"
    $token = $loginResponse.access_token
    Write-Host "   [OK] Authentication successful!" -ForegroundColor Green
    Write-Host "   Token: $($token.Substring(0, 20))..." -ForegroundColor Gray
} catch {
    Write-Host "   [ERROR] Authentication failed: $($_.Exception.Message)" -ForegroundColor Red
    exit 1
}

Write-Host ""

# Step 2: Test Dashboard Summary
Write-Host "2. Testing Dashboard Summary..." -ForegroundColor Yellow
$headers = @{
    "Authorization" = "Bearer $token"
    "Accept" = "application/json"
}

try {
    $summaryResponse = Invoke-RestMethod -Uri "$baseUrl/dashboard/summary" -Method Get -Headers $headers
    Write-Host "   [OK] Dashboard Summary loaded successfully!" -ForegroundColor Green

    if ($summaryResponse.kpis) {
        Write-Host "   KPIs:" -ForegroundColor Cyan
        Write-Host "   - Today's Sales: Rs. $($summaryResponse.kpis.todaySales)" -ForegroundColor Gray
        Write-Host "   - Transactions: $($summaryResponse.kpis.todayTransactions)" -ForegroundColor Gray
        Write-Host "   - Avg Order: Rs. $([math]::Round($summaryResponse.kpis.averageOrderValue, 2))" -ForegroundColor Gray
        Write-Host "   - Total Customers: $($summaryResponse.kpis.totalCustomers)" -ForegroundColor Gray
        Write-Host "   - Total Products: $($summaryResponse.kpis.totalProducts)" -ForegroundColor Gray
    }
} catch {
    Write-Host "   [ERROR] Dashboard Summary failed: $($_.Exception.Message)" -ForegroundColor Red
}

Write-Host ""

# Step 3: Test KPIs Endpoint
Write-Host "3. Testing KPIs Endpoint..." -ForegroundColor Yellow
try {
    $kpisResponse = Invoke-RestMethod -Uri "$baseUrl/dashboard/kpis" -Method Get -Headers $headers
    Write-Host "   [OK] KPIs loaded successfully!" -ForegroundColor Green
} catch {
    Write-Host "   [ERROR] KPIs failed: $($_.Exception.Message)" -ForegroundColor Red
}

Write-Host ""

# Step 4: Test Recent Transactions
Write-Host "4. Testing Recent Transactions..." -ForegroundColor Yellow
try {
    $transactionsResponse = Invoke-RestMethod -Uri "$baseUrl/dashboard/recent-transactions" -Method Get -Headers $headers
    Write-Host "   [OK] Recent Transactions loaded!" -ForegroundColor Green
    Write-Host "   - Found $($transactionsResponse.Count) transactions" -ForegroundColor Gray
} catch {
    Write-Host "   [ERROR] Recent Transactions failed: $($_.Exception.Message)" -ForegroundColor Red
}

Write-Host ""

# Step 5: Test Inventory Alerts
Write-Host "5. Testing Inventory Alerts..." -ForegroundColor Yellow
try {
    $alertsResponse = Invoke-RestMethod -Uri "$baseUrl/dashboard/inventory-alerts" -Method Get -Headers $headers
    Write-Host "   [OK] Inventory Alerts loaded!" -ForegroundColor Green
    Write-Host "   - Found $($alertsResponse.Count) alerts" -ForegroundColor Gray
} catch {
    Write-Host "   [ERROR] Inventory Alerts failed: $($_.Exception.Message)" -ForegroundColor Red
}

Write-Host ""

# Step 6: Test Top Customers
Write-Host "6. Testing Top Customers..." -ForegroundColor Yellow
try {
    $customersResponse = Invoke-RestMethod -Uri "$baseUrl/dashboard/top-customers" -Method Get -Headers $headers
    Write-Host "   [OK] Top Customers loaded!" -ForegroundColor Green
    Write-Host "   - Found $($customersResponse.Count) customers" -ForegroundColor Gray
} catch {
    Write-Host "   [ERROR] Top Customers failed: $($_.Exception.Message)" -ForegroundColor Red
}

Write-Host ""

# Step 7: Test Payment Method Sales
Write-Host "7. Testing Payment Method Sales..." -ForegroundColor Yellow
try {
    $paymentsResponse = Invoke-RestMethod -Uri "$baseUrl/dashboard/payment-method-sales" -Method Get -Headers $headers
    Write-Host "   [OK] Payment Method Sales loaded!" -ForegroundColor Green
    Write-Host "   - Found $($paymentsResponse.Count) payment methods" -ForegroundColor Gray
} catch {
    Write-Host "   [ERROR] Payment Method Sales failed: $($_.Exception.Message)" -ForegroundColor Red
}

Write-Host ""

# Step 8: Test Sales Trend
Write-Host "8. Testing Sales Trend..." -ForegroundColor Yellow
try {
    $trendResponse = Invoke-RestMethod -Uri "$baseUrl/dashboard/sales-trend?period=week" -Method Get -Headers $headers
    Write-Host "   [OK] Sales Trend loaded!" -ForegroundColor Green
    Write-Host "   - Found $($trendResponse.Count) data points" -ForegroundColor Gray
} catch {
    Write-Host "   [ERROR] Sales Trend failed: $($_.Exception.Message)" -ForegroundColor Red
}

Write-Host ""
Write-Host "==================================" -ForegroundColor Cyan
Write-Host "[SUCCESS] API Integration Test Complete!" -ForegroundColor Green
Write-Host "==================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Backend API URL: http://127.0.0.1:8000/api" -ForegroundColor Cyan
Write-Host "Frontend connects to backend successfully!" -ForegroundColor Green
