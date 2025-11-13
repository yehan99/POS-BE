# 🚀 Server Management Scripts

Quick reference for managing your Laravel development server.

## 📋 Available Scripts

### `start-server.bat` or `start-server.ps1`
**Starts the Laravel server with auto-restart**

- Automatically restarts if the server stops
- Shows restart count
- Clears config cache before each start
- Press `Ctrl+C` to stop permanently

**Usage:**
```bash
# Windows Command Prompt / PowerShell
start-server.bat

# PowerShell only
.\start-server.ps1
```

### `check-server.ps1`
**Checks if the server is running**

- Shows server status
- Displays process details and memory usage
- Checks database connection
- Lists all PHP processes

**Usage:**
```powershell
.\check-server.ps1
```

## 🔧 Quick Commands

### Start Server (Standard)
```bash
php artisan serve
```

### Start Server (With Auto-Restart)
```bash
.\start-server.ps1
```

### Check Server Status
```powershell
.\check-server.ps1
```

### Stop Server
```powershell
# Press Ctrl+C in the terminal where server is running
# OR find and kill the process:
netstat -ano | findstr :8000
taskkill /PID <process_id> /F
```

### View Logs
```powershell
# Real-time logs
Get-Content storage/logs/laravel.log -Wait -Tail 20

# Last 50 lines
Get-Content storage/logs/laravel.log -Tail 50
```

## 🐛 Troubleshooting

### Server stops after a few minutes?

**Possible causes:**
1. Remote database connection timeout
2. PowerShell idle timeout
3. Memory issues
4. PHP configuration

**Solutions:**
1. Use the auto-restart scripts: `.\start-server.ps1`
2. Keep the terminal window active
3. Check database connection: `php artisan db:show`
4. View detailed troubleshooting: See `SERVER-TROUBLESHOOTING.md`

### Can't access http://127.0.0.1:8000?

```powershell
# Check if port is in use
netstat -ano | findstr :8000

# Check firewall
# Allow PHP through Windows Firewall if prompted
```

### Database connection issues?

```bash
# Test connection
php artisan db:show

# Or use Tinker
php artisan tinker
>>> DB::connection()->getPdo();
```

## 📚 More Help

- See `SERVER-TROUBLESHOOTING.md` for detailed troubleshooting
- Laravel Docs: https://laravel.com/docs
- Check logs: `storage/logs/laravel.log`

## 🎯 Recommended Setup

For a more stable development environment, consider:

1. **Laravel Herd** (Recommended): https://herd.laravel.com/windows
2. **Laragon**: https://laragon.org
3. **Docker**: Use containerized setup

---

**Need immediate help?**
Run `.\check-server.ps1` to diagnose issues.
