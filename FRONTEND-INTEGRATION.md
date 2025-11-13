# 🔌 Frontend Integration Guide

## Backend API Configuration

**Backend URL:** `http://127.0.0.1:8000`
**API Prefix:** `/api`

## CORS Configuration ✅

CORS has been configured to allow requests from:
- `http://localhost:4200` (Your Angular frontend)
- `http://127.0.0.1:4200`

## Available Auth Endpoints

### 1. Register User
```
POST http://127.0.0.1:8000/api/auth/register
```

**Request Headers:**
```json
{
  "Content-Type": "application/json",
  "Accept": "application/json"
}
```

**Request Body (camelCase - Angular style):**
```json
{
  "firstName": "John",
  "lastName": "Doe",
  "email": "john@example.com",
  "phone": "+94771234567",
  "password": "SecurePass123!",
  "deviceName": "web",
  "roleSlug": "admin",
  "tenant": {
    "name": "My Business",
    "businessType": "retail",
    "country": "LK",
    "phone": "+94771234567",
    "settings": {
      "currency": "LKR",
      "timezone": "Asia/Colombo",
      "language": "en"
    }
  }
}
```

**Success Response (201):**
```json
{
  "tokenType": "Bearer",
  "accessToken": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "refreshToken": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "expiresIn": 900,
  "refreshExpiresIn": 1209600,
  "user": {
    "id": "01k9...",
    "firstName": "John",
    "lastName": "Doe",
    "email": "john@example.com",
    "phone": "+94771234567",
    "isActive": true,
    "role": {
      "id": "01k9...",
      "name": "Administrator",
      "slug": "admin"
    },
    "tenant": {
      "id": "01k9...",
      "name": "My Business",
      "businessType": "retail"
    }
  }
}
```

### 2. Login
```
POST http://127.0.0.1:8000/api/auth/login
```

**Request Body:**
```json
{
  "email": "john@example.com",
  "password": "SecurePass123!",
  "deviceName": "web"
}
```

**Success Response (200):**
```json
{
  "tokenType": "Bearer",
  "accessToken": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "refreshToken": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "expiresIn": 900,
  "refreshExpiresIn": 1209600,
  "user": { ... }
}
```

### 3. Get Current User
```
GET http://127.0.0.1:8000/api/auth/me
```

**Request Headers:**
```json
{
  "Authorization": "Bearer {accessToken}",
  "Accept": "application/json"
}
```

### 4. Refresh Token
```
POST http://127.0.0.1:8000/api/auth/refresh
```

**Request Body:**
```json
{
  "refreshToken": "eyJ0eXAiOiJKV1QiLCJhbGc..."
}
```

### 5. Logout
```
POST http://127.0.0.1:8000/api/auth/logout
```

**Request Headers:**
```json
{
  "Authorization": "Bearer {accessToken}",
  "Accept": "application/json"
}
```

**Request Body (Optional):**
```json
{
  "allDevices": false
}
```

## Angular HTTP Client Example

```typescript
import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';

@Injectable({
  providedIn: 'root'
})
export class AuthService {
  private apiUrl = 'http://127.0.0.1:8000/api/auth';

  constructor(private http: HttpClient) {}

  register(data: any): Observable<any> {
    return this.http.post(`${this.apiUrl}/register`, data, {
      headers: new HttpHeaders({
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      })
    });
  }

  login(email: string, password: string): Observable<any> {
    return this.http.post(`${this.apiUrl}/login`, {
      email,
      password,
      deviceName: 'web'
    });
  }

  getMe(token: string): Observable<any> {
    return this.http.get(`${this.apiUrl}/me`, {
      headers: new HttpHeaders({
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json'
      })
    });
  }
}
```

## Common Issues & Solutions

### ❌ CORS Error
**Error:** `Access to XMLHttpRequest at 'http://127.0.0.1:8000/api/auth/register' from origin 'http://localhost:4200' has been blocked by CORS policy`

**Solution:**
1. Make sure the Laravel server is running: `php artisan serve`
2. Clear Laravel config cache: `php artisan config:clear`
3. Restart the Laravel server
4. Check that `config/cors.php` includes your frontend URL

### ❌ 404 Not Found
**Error:** `404 | Not Found`

**Solution:**
- Ensure you're using the `/api` prefix: `http://127.0.0.1:8000/api/auth/register`
- Check routes: `php artisan route:list --path=auth`

### ❌ 422 Validation Error
**Error:** `422 | Unprocessable Entity`

**Solution:**
- Check the response body for validation errors
- Ensure all required fields are sent
- Password must be at least 8 characters with uppercase, lowercase, numbers, and symbols

### ❌ 500 Internal Server Error
**Error:** `500 | Internal Server Error`

**Solution:**
1. Check Laravel logs: `storage/logs/laravel.log`
2. Run migrations: `php artisan migrate`
3. Run seeders (for roles): `php artisan db:seed`

## Testing the API

### Using cURL:
```bash
curl -X POST http://127.0.0.1:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "firstName": "Test",
    "lastName": "User",
    "email": "test@example.com",
    "password": "SecurePass123!",
    "phone": "+94771234567",
    "roleSlug": "admin",
    "tenant": {
      "name": "Test Business",
      "businessType": "retail",
      "country": "LK",
      "phone": "+94771234567"
    }
  }'
```

### Using PowerShell:
```powershell
$body = @{
    firstName = "Test"
    lastName = "User"
    email = "test@example.com"
    password = "SecurePass123!"
    phone = "+94771234567"
    roleSlug = "admin"
    tenant = @{
        name = "Test Business"
        businessType = "retail"
        country = "LK"
        phone = "+94771234567"
    }
} | ConvertTo-Json

Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/auth/register" -Method Post -Body $body -ContentType "application/json"
```

## Password Requirements
- Minimum 8 characters
- At least 1 uppercase letter
- At least 1 lowercase letter
- At least 1 number
- At least 1 symbol

## Available Business Types
- `retail`
- `restaurant`
- `services`
- `wholesale`
- etc.

## Available Role Slugs
- `admin` - Full system access
- `manager` - Management access
- `cashier` - POS access
- `staff` - Limited access

## Need Help?

1. **Check Laravel logs:**
   ```bash
   Get-Content storage/logs/laravel.log -Tail 50
   ```

2. **Verify server is running:**
   ```bash
   netstat -ano | findstr :8000
   ```

3. **Test API directly:**
   Visit: http://127.0.0.1:8000/api/auth/register in Postman or Insomnia

4. **Check CORS config:**
   ```bash
   php artisan config:show cors
   ```
