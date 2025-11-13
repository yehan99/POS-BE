# Laravel Server Stopping Issue - Solutions

## Problem
Your Laravel development server (`php artisan serve`) stops after a few minutes.

## Root Causes
1. **Remote database timeouts** - You're connecting to `athena.webserverlive.com` which can have idle connection timeouts
2. **PowerShell idle timeout** - PowerShell may terminate idle processes
3. **PHP built-in server limitations** - Not designed for long-running processes
4. **Memory exhaustion** - The server might be running out of memory

## Solutions Applied

### 1. Database Configuration Updated ✅
Added connection options in `config/database.php`:
- Connection timeout: 5 seconds
- Sticky connections to maintain database connection
- Error mode for better error handling

### 2. Auto-Restart Scripts Created ✅

**Option A: Use the Batch Script**
```batch
start-server.bat
```
Double-click this file to start the server with auto-restart.

**Option B: Use the PowerShell Script**
```powershell
.\start-server.ps1
```
Run this from PowerShell for better error messages.

### 3. Manual Server Start (Current)
```bash
php artisan serve --host=127.0.0.1 --port=8000
```

## Recommended Long-Term Solutions

### Best Option: Use Laravel Herd (Recommended)
Laravel Herd is a native Laravel development environment for Windows:
1. Download: https://herd.laravel.com/windows
2. Install Herd
3. Add your project folder
4. Access via: http://pos-be.test

### Alternative: Use Laragon
1. Download: https://laragon.org/download/
2. Install Laragon
3. Place project in `laragon/www/`
4. Laragon will serve it automatically

### Alternative: Use Docker
Create a `docker-compose.yml`:
```yaml
version: '3'
services:
  app:
    image: php:8.2-cli
    working_dir: /app
    volumes:
      - ./:/app
    ports:
      - "8000:8000"
    command: php artisan serve --host=0.0.0.0 --port=8000
```

## Immediate Troubleshooting

### If server still stops:

1. **Check PowerShell execution policy**:
   ```powershell
   Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser
   ```

2. **Monitor server logs**:
   ```bash
   tail -f storage/logs/laravel.log
   ```

3. **Check database connection**:
   ```bash
   php artisan tinker
   DB::connection()->getPdo();
   ```

4. **Increase PHP memory limit** (if needed):
   Add to `.env`:
   ```
   PHP_MEMORY_LIMIT=512M
   ```

5. **Test database connection stability**:
   ```bash
   php artisan db:show
   ```

## Environment Variables to Add

Add these to your `.env` file:
```env
# Database connection pooling
DB_POOL_SIZE=5
DB_POOL_TIMEOUT=30

# Session management
SESSION_DRIVER=file
# Or use database: SESSION_DRIVER=database

# Increase timeouts
QUEUE_CONNECTION=sync
```

## Monitoring the Server

### Check if server is running:
```powershell
netstat -ano | findstr :8000
```

### Kill hung PHP processes (if needed):
```powershell
taskkill /F /IM php.exe
```

### View real-time logs:
```powershell
Get-Content storage/logs/laravel.log -Wait -Tail 10
```

## Notes
- The built-in PHP server (`php artisan serve`) is **not** meant for production
- Remote database connections can be unstable - consider using a local database for development
- Keep the terminal window open and don't let your computer sleep
