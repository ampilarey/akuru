# Authentication Guide - Akuru Institute LMS

## Overview
Comprehensive guide to the authentication system in Akuru Institute LMS, including traditional email/password login and modern SMS-based OTP authentication.

---

## 🔐 Authentication Methods

### 1. Email/Password Authentication (Traditional)

#### Login Process
1. User enters email and password
2. System validates credentials
3. Laravel Breeze handles session creation
4. User redirected to appropriate dashboard based on role

#### Registration Process
1. User provides email, name, and password
2. Email verification sent (optional)
3. Account created with default permissions
4. User can log in after email verification

### 2. OTP-Based Authentication (Primary Method)

#### SMS OTP Login Process
1. User enters phone number on login page
2. System sends 6-digit OTP via SMS
3. User enters OTP code
4. System validates OTP and creates session
5. User redirected to dashboard

#### OTP Password Reset
1. User requests password reset via phone number
2. System sends OTP via SMS
3. User enters OTP for verification
4. User can set new password
5. System updates password hash

---

## 📱 SMS Integration Details

### OTP Generation
```php
// OTP Configuration
- Length: 6 digits
- Expiry: 10 minutes
- Rate limiting: 3 attempts per 10 minutes
- Character set: 0-9 only
```

### SMS Gateway Integration
- **Gateway**: Local SMS provider integration
- **Format**: Maldives phone number formatting (+960)
- **Delivery**: Real-time SMS delivery
- **Cost tracking**: Per-SMS cost monitoring

### OTP Security Features
- **Rate Limiting**: Prevents OTP spam
- **Expiry**: Automatic OTP expiration
- **One-time use**: Each OTP can only be used once
- **IP tracking**: Logs IP addresses for security

---

## 👥 User Roles & Access Control

### Role Hierarchy
```
Super Admin > Admin > Headmaster > Supervisor > Teacher > Student/Parent
```

### Permission System
- **Spatie Laravel Permission** package
- **Role-based access control** (RBAC)
- **Middleware protection** on routes
- **Policy-based authorization** for models

### 7 User Roles

#### 1. Super Admin
- **Access**: Full system access
- **Functions**: System management, user creation, role assignment
- **Restrictions**: None

#### 2. Admin
- **Access**: School operations management
- **Functions**: Student/teacher management, fees, admissions
- **Restrictions**: Cannot access system settings

#### 3. Headmaster
- **Access**: Academic leadership and oversight
- **Functions**: Academic management, approvals, reports
- **Restrictions**: No financial operations

#### 4. Supervisor
- **Access**: Academic monitoring and supervision
- **Functions**: Monitoring, reports, substitution management
- **Restrictions**: Cannot add/edit users directly

#### 5. Teacher
- **Access**: Teaching functions and class management
- **Functions**: Attendance, grades, assignments, Quran progress
- **Restrictions**: Own classes only

#### 6. Student
- **Access**: Personal academic data
- **Functions**: View grades, submit assignments, track progress
- **Restrictions**: Own data only

#### 7. Parent
- **Access**: Children's academic data
- **Functions**: Monitor progress, submit absence notes, pay fees
- **Restrictions**: Linked children only

---

## 🔧 Authentication Implementation

### Models & Database

#### `users` Table
```sql
- id (primary key)
- name, email, password (standard Laravel)
- phone (OTP authentication)
- email_verified_at, remember_token
- created_at, updated_at
```

#### `otps` Table
```sql
- id (primary key)
- phone (varchar) - Phone number
- otp_code (varchar) - 6-digit OTP
- expires_at (timestamp)
- is_used (boolean)
- created_at, updated_at
```

### Controllers

#### `OtpLoginController`
- Handles phone number submission
- OTP generation and SMS sending
- OTP validation and login

#### `OtpPasswordResetController`
- Handles password reset requests
- OTP verification for password reset
- New password setting

#### `AuthenticatedSessionController` (Laravel Breeze)
- Traditional email/password login
- Session management
- Logout functionality

### Middleware

#### Authentication Middleware
- `auth` - Requires authenticated user
- `guest` - Redirects authenticated users
- `verified` - Requires verified email

#### Authorization Middleware
- `role:admin` - Requires specific role
- `permission:manage-users` - Requires specific permission

---

## 🚀 Session Management

### Session Configuration
```php
// config/session.php
'driver' => 'database',
'lifetime' => 120, // 2 hours
'expire_on_close' => false,
'encrypt' => false,
'secure' => true, // HTTPS only in production
'http_only' => true,
'same_site' => 'lax'
```

### Session Security
- **Database sessions** for scalability
- **CSRF protection** on all forms
- **Session regeneration** on login
- **IP address tracking** in sessions

---

## 📊 Security Features

### Password Security
- **Bcrypt hashing** with configurable rounds
- **Minimum password requirements** (8+ characters)
- **Password strength validation**
- **Password history** (prevents reuse)

### Rate Limiting
```php
// OTP Rate Limiting
'otp' => [
    'max_attempts' => 3,
    'decay_minutes' => 10,
    'key' => 'otp_attempts:{phone}'
]

// Login Rate Limiting
'login' => [
    'max_attempts' => 5,
    'decay_minutes' => 15,
    'key' => 'login_attempts:{ip}'
]
```

### Security Headers
- **X-Frame-Options**: DENY
- **X-Content-Type-Options**: nosniff
- **Referrer-Policy**: strict-origin-when-cross-origin
- **Content-Security-Policy**: Configured for security

---

## 🔄 Multi-Language Authentication

### Language-Specific Forms
- **English**: Default interface
- **Arabic**: RTL layout with Arabic labels
- **Dhivehi**: Thaana script support

### Localized Error Messages
```php
// Authentication messages in multiple languages
'en' => [
    'failed' => 'These credentials do not match our records.',
    'throttle' => 'Too many login attempts. Please try again in :seconds seconds.'
],
'ar' => [
    'failed' => 'هذه البيانات غير صحيحة.',
    'throttle' => 'محاولات دخول كثيرة. حاول مرة أخرى خلال :seconds ثانية.'
],
'dv' => [
    'failed' => 'މި ޑެޓާ ހުށަހަޅައި ނުދެނެފައި.',
    'throttle' => 'ގިނަ ހުށަހަޅުވުމުގެ ފިންވަތް. :seconds ސިކުންތަކުގައި ފިނި ރަގަޅުވާށެވެ.'
]
```

---

## 🧪 Testing Authentication

### Test Cases
1. **Successful Login**: Email/password and OTP login
2. **Failed Login**: Invalid credentials handling
3. **Rate Limiting**: OTP and login attempt limits
4. **Session Management**: Login/logout functionality
5. **Role-based Access**: Dashboard redirections
6. **OTP Expiry**: Time-based OTP validation

### Test Data
```php
// Test users with different roles
'super_admin@akuru.edu.mv' => 'Super Admin',
'admin@akuru.edu.mv' => 'Admin',
'teacher@akuru.edu.mv' => 'Teacher',
'student@akuru.edu.mv' => 'Student',
'parent@akuru.edu.mv' => 'Parent'
```

---

## 🚨 Troubleshooting

### Common Issues

#### OTP Not Received
1. Check SMS gateway configuration
2. Verify phone number format (+960XXXXXXXX)
3. Check rate limiting status
4. Review SMS provider logs

#### Login Failures
1. Verify user exists and is active
2. Check password hashing
3. Review session configuration
4. Check middleware settings

#### Permission Denied
1. Verify user role assignments
2. Check route middleware
3. Review permission policies
4. Validate role hierarchy

### Debug Tools
- **Laravel Log**: Check `storage/logs/laravel.log`
- **Session Debug**: Use `Session::all()` in tests
- **Database Queries**: Enable query logging
- **SMS Testing**: Use test phone numbers

---

## 🔧 Configuration

### Environment Variables
```env
# Authentication
AUTH_OTP_EXPIRY_MINUTES=10
AUTH_OTP_MAX_ATTEMPTS=3
AUTH_OTP_RATE_LIMIT_MINUTES=10

# Session
SESSION_LIFETIME=120
SESSION_SECURE=true
SESSION_HTTP_ONLY=true

# SMS Gateway
SMS_GATEWAY_URL=
SMS_GATEWAY_API_KEY=
SMS_SENDER_ID=
```

### Laravel Configuration
```php
// config/auth.php - Multiple guards
'guards' => [
    'web' => [
        'driver' => 'session',
        'provider' => 'users',
    ],
    'api' => [
        'driver' => 'sanctum',
        'provider' => 'users',
    ],
]
```

---

## 📈 Performance Considerations

### Caching
- **OTP validation caching** for performance
- **User role caching** to reduce database queries
- **Session data optimization**

### Database Optimization
- **Indexed phone numbers** for OTP lookups
- **Optimized user queries** with proper joins
- **Session table cleanup** jobs

---

## 🎯 Future Enhancements

### Planned Features
1. **Social Login**: Google/Microsoft integration
2. **Two-Factor Authentication**: TOTP support
3. **Biometric Authentication**: Fingerprint/face
4. **SSO Integration**: Single sign-on support
5. **Passwordless Login**: Advanced OTP features

---

**Last Updated**: October 16, 2025  
**Version**: 2.0.0  
**Supported Methods**: Email/Password + SMS OTP  
**Languages**: English, Arabic, Dhivehi
