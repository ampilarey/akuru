# OTP Authentication Guide

Complete guide for using OTP (One-Time Password) authentication in Akuru LMS.

---

## 🎯 Features

- ✅ **OTP Login** - Login using phone number + OTP code
- ✅ **OTP Password Reset** - Reset password using phone number + OTP
- ✅ **SMS Integration** - OTP sent via SMS Gateway
- ✅ **Rate Limiting** - Prevents abuse
- ✅ **Security** - 10-minute expiry, 5 attempt limit
- ✅ **User-Friendly** - Clean UI with auto-submit

---

## 🚀 Setup Instructions

### Step 1: Run Migrations

```bash
php artisan migrate
```

This will create:
- `otps` table - Stores OTP codes
- Phone fields in `users` table

### Step 2: Configure SMS Gateway

Make sure your SMS Gateway is configured in `.env`:

```env
SMS_GATEWAY_URL=https://sms.akuru.edu.mv/api/v2
SMS_GATEWAY_API_KEY=your_api_key_here
SMS_GATEWAY_ENABLED=true
```

### Step 3: Update User Phone Numbers

Make sure users have phone numbers in their profiles:

```php
// Via database seeder or manually
User::where('email', 'admin@akuru.edu.mv')->update([
    'phone' => '7972434'  // or '9607972434'
]);
```

---

## 📱 How It Works

### OTP Login Flow

```
1. User enters phone number
   ↓
2. System generates 6-digit OTP
   ↓
3. OTP sent via SMS
   ↓
4. User enters OTP code
   ↓
5. System verifies code
   ↓
6. User logged in
```

### OTP Password Reset Flow

```
1. User enters phone number
   ↓
2. System generates 6-digit OTP
   ↓
3. OTP sent via SMS
   ↓
4. User enters OTP code
   ↓
5. System verifies code
   ↓
6. User sets new password
   ↓
7. Password updated
```

---

## 🔒 Security Features

### Rate Limiting

- **OTP Generation**: Max 3 OTPs per 10 minutes per phone number
- **OTP Verification**: Max 5 verification attempts per 5 minutes
- Prevents brute force attacks

### OTP Expiry

- **Default**: 10 minutes
- After expiry, user must request new OTP
- Old OTPs are invalidated when new one is generated

### Attempt Tracking

- **Max Attempts**: 5 attempts per OTP
- After 5 failed attempts, OTP becomes invalid
- User must request new OTP

### Session Security

- OTP verification uses secure session storage
- Sessions expire after successful verification
- Prevents replay attacks

---

## 🎨 User Interface

### Login with OTP

**URL**: `/otp/login`

Users can:
- Enter phone number
- Receive OTP via SMS
- Enter 6-digit code
- Login automatically

### Reset Password with OTP

**URL**: `/password/otp/request`

Users can:
- Enter phone number
- Receive OTP via SMS
- Verify OTP code
- Set new password

---

## 💻 Usage for Developers

### Sending OTP Manually

```php
use App\Services\OtpService;

$otpService = app(OtpService::class);

// Generate and send OTP
$result = $otpService->generate(
    '7972434',     // Phone number
    'login',       // Type: login, password_reset, verification, 2fa
    [
        'expires_in' => 10,  // Minutes (optional, default: 10)
        'length' => 6,       // Code length (optional, default: 6)
    ]
);

if ($result['success']) {
    echo "OTP sent! Expires at: " . $result['expires_at'];
} else {
    echo "Error: " . $result['error'];
}
```

### Verifying OTP

```php
$result = $otpService->verify(
    '7972434',     // Phone number
    '123456',      // OTP code
    'login'        // Type
);

if ($result['success']) {
    // OTP is valid
    $otpId = $result['otp_id'];
    // Proceed with login/password reset
} else {
    // OTP is invalid
    echo "Error: " . $result['error'];
}
```

### Check OTP Statistics

```php
$stats = $otpService->getStats('7972434');

echo "Today: " . $stats['today'];
echo "This week: " . $stats['this_week'];
echo "Failed: " . $stats['failed'];
```

---

## 🔧 Customization

### Change OTP Expiry Time

Edit `OtpService::generate()` method:

```php
'expires_at' => now()->addMinutes($options['expires_in'] ?? 15), // Change to 15 minutes
```

### Change OTP Length

Edit `OtpService::generate()` method:

```php
$code = $this->generateCode($options['length'] ?? 8); // Change to 8 digits
```

### Change Rate Limits

Edit `OtpService` class:

```php
// OTP Generation
if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) { // Change from 3 to 5
    
// OTP Verification
if (RateLimiter::tooManyAttempts($rateLimitKey, 10)) { // Change from 5 to 10
```

### Customize SMS Messages

Edit `OtpService::sendOtp()` method:

```php
$messages = [
    'login' => "Your Akuru code is: {$code}. Valid for 10 minutes.",
    'password_reset' => "Your password reset code is: {$code}.",
    // Add more custom messages
];
```

---

## 🧹 Maintenance

### Clean Up Expired OTPs

Run this command daily (add to scheduler):

```php
// In app/Console/Kernel.php

protected function schedule(Schedule $schedule)
{
    $schedule->call(function () {
        app(\App\Services\OtpService::class)->cleanupExpired();
    })->daily();
}
```

Or manually:

```bash
php artisan tinker
```

```php
app(\App\Services\OtpService::class)->cleanupExpired();
```

---

## 📊 Database Structure

### `otps` Table

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| identifier | string | Phone number or email |
| code | string(6) | OTP code |
| type | enum | Type (login, password_reset, etc) |
| expires_at | timestamp | Expiry time |
| verified_at | timestamp | When verified (nullable) |
| ip_address | string | Request IP |
| user_agent | text | Browser info |
| attempts | integer | Verification attempts |
| is_used | boolean | Whether OTP was used |

### `users` Table (New Fields)

| Column | Type | Description |
|--------|------|-------------|
| phone | string(20) | Phone number |
| phone_verified_at | timestamp | Phone verification time |
| otp_enabled | boolean | OTP login preference |
| two_factor_enabled | boolean | 2FA preference |

---

## 🐛 Troubleshooting

### OTP Not Sending

1. Check SMS Gateway configuration in `.env`
2. Verify API key is valid
3. Check `storage/logs/laravel.log` for errors
4. Test SMS Gateway connection:

```php
$sms = app(\App\Services\SmsGatewayService::class);
$health = $sms->checkHealth();
dd($health); // Should return true
```

### Rate Limit Errors

Clear rate limiter:

```php
use Illuminate\Support\Facades\RateLimiter;

RateLimiter::clear('otp_generate:7972434');
RateLimiter::clear('otp_verify:7972434');
```

### Session Expired Errors

Increase session lifetime in `config/session.php`:

```php
'lifetime' => 120, // Change to higher value (minutes)
```

### Phone Number Format Issues

The system auto-formats phone numbers:
- `7972434` → `9607972434`
- `+960 797 2434` → `9607972434`
- `797-2434` → `9607972434`

---

## 🔐 Best Practices

1. **Always use HTTPS** - OTP codes should never be sent over HTTP
2. **Educate Users** - Tell users not to share OTP codes
3. **Monitor Usage** - Check logs for suspicious activity
4. **Set Reasonable Limits** - Balance security and usability
5. **Clean Up Regularly** - Remove expired OTPs to keep database clean
6. **Log Everything** - Keep audit trail of OTP usage
7. **Test in Demo Mode** - Test without sending real SMS first

---

## 🎯 User Instructions

### How to Login with OTP

1. Go to login page
2. Click "**Login with Phone OTP**"
3. Enter your phone number
4. Click "**Send OTP**"
5. Check your phone for SMS
6. Enter the 6-digit code
7. Click "**Verify & Login**"

### How to Reset Password with OTP

1. Go to login page
2. Click "**Forgot your password?**"
3. Click "**Reset via Phone OTP**"
4. Enter your phone number
5. Click "**Send Reset OTP**"
6. Check your phone for SMS
7. Enter the 6-digit code
8. Click "**Verify OTP**"
9. Enter new password
10. Click "**Reset Password**"

---

## 📝 Routes

### OTP Login

- `GET /otp/login` - Show phone number form
- `POST /otp/request` - Request OTP
- `GET /otp/verify` - Show OTP verification form
- `POST /otp/verify` - Verify OTP and login
- `POST /otp/resend` - Resend OTP

### OTP Password Reset

- `GET /password/otp/request` - Show phone number form
- `POST /password/otp/send` - Request OTP
- `GET /password/otp/verify` - Show OTP verification form
- `POST /password/otp/verify` - Verify OTP
- `GET /password/otp/reset` - Show new password form
- `POST /password/otp/reset` - Update password
- `POST /password/otp/resend` - Resend OTP

---

## 🚀 Future Enhancements

Potential improvements:
- [ ] Email OTP support
- [ ] Biometric authentication
- [ ] Remember device feature
- [ ] OTP via voice call
- [ ] Multi-language SMS templates
- [ ] Admin dashboard for OTP analytics
- [ ] Export OTP usage reports

---

## 📧 Support

For issues or questions:
- Check logs in `storage/logs/laravel.log`
- Review this documentation
- Test SMS Gateway connection
- Contact system administrator

---

**Last Updated**: October 15, 2025  
**Version**: 1.0.0

