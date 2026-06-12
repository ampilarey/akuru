# SMS Integration Guide

This guide explains how to integrate the Akuru LMS with the SMS Gateway system at `sms.akuru.edu.mv`.

---

## 📋 Overview

The Akuru LMS can send SMS notifications to students, parents, and teachers through the Dhiraagu SMS Gateway. The integration uses API-based communication between the two systems.

**Systems:**
- **Main LMS**: `akuru.edu.mv` (this system)
- **SMS Gateway**: `sms.akuru.edu.mv` (separate system)

---

## 🔧 Setup Instructions

### Step 1: Get Your API Key

1. Log into the SMS Gateway system at `https://sms.akuru.edu.mv`
2. Go to **Settings → API Settings**
3. Click **Generate API Key**
4. Copy the generated API key

### Step 2: Configure the LMS

Add these variables to your `.env` file:

```env
# SMS Gateway Configuration
SMS_GATEWAY_URL=https://sms.akuru.edu.mv/api/v2
SMS_GATEWAY_API_KEY=your_api_key_here
SMS_GATEWAY_ENABLED=true
```

### Step 3: Test the Connection

Run this command to test if the integration works:

```bash
php artisan tinker
```

Then run:

```php
$sms = app(\App\Services\SmsGatewayService::class);
$result = $sms->checkHealth();
dd($result); // Should return true if connected
```

---

## 📱 Usage Examples

### Send a Single SMS

```php
use App\Services\SmsGatewayService;

$smsService = app(SmsGatewayService::class);

$result = $smsService->sendSms(
    '7972434',  // Phone number
    'Hello from Akuru Institute!',  // Message
    [
        'sender_id' => 'AKURU',  // Optional
        'type' => 'notification'  // Optional
    ]
);

if ($result['success']) {
    echo "SMS sent! Message ID: " . $result['message_id'];
} else {
    echo "Failed: " . $result['error'];
}
```

### Send Bulk SMS

```php
$recipients = ['7972434', '7912345', '7923456'];

$result = $smsService->sendBulkSms(
    $recipients,
    'Important announcement for all parents...',
    [
        'type' => 'announcement'
    ]
);

echo "Sent: {$result['sent_count']}, Failed: {$result['failed_count']}";
```

### Send Attendance Notification

```php
$result = $smsService->sendAttendanceNotification(
    '7972434',  // Parent phone
    'Ahmed Ali',  // Student name
    'absent',  // Status: present, absent, late
    '2025-10-15'  // Date
);
```

### Send Grade Notification

```php
$result = $smsService->sendGradeNotification(
    '7972434',  // Parent phone
    'Ahmed Ali',  // Student name
    'Mathematics',  // Subject
    'A'  // Grade
);
```

### Send Announcement to All Parents

```php
$parents = \App\Models\ParentGuardian::all();
$phoneNumbers = $parents->pluck('phone')->toArray();

$result = $smsService->sendAnnouncement(
    $phoneNumbers,
    'School Closing',
    'School will be closed tomorrow due to weather conditions.'
);
```

---

## 🤖 Automated SMS Notifications

You can set up automated SMS notifications for various events:

### 1. Attendance Notifications

Add this to `App\Models\Attendance` observer or event listener:

```php
// In App\Observers\AttendanceObserver

public function created(Attendance $attendance)
{
    if ($attendance->status === 'absent') {
        $student = $attendance->student;
        $parents = $student->parentGuardians;
        
        $smsService = app(\App\Services\SmsGatewayService::class);
        
        foreach ($parents as $parent) {
            if ($parent->phone) {
                $smsService->sendAttendanceNotification(
                    $parent->phone,
                    $student->user->name,
                    $attendance->status,
                    $attendance->date->format('Y-m-d')
                );
            }
        }
    }
}
```

### 2. Grade Notifications

```php
// In App\Observers\GradeObserver

public function created(Grade $grade)
{
    $student = $grade->student;
    $parents = $student->parentGuardians;
    
    $smsService = app(\App\Services\SmsGatewayService::class);
    
    foreach ($parents as $parent) {
        if ($parent->phone) {
            $smsService->sendGradeNotification(
                $parent->phone,
                $student->user->name,
                $grade->subject->name,
                $grade->grade
            );
        }
    }
}
```

### 3. Announcement Broadcasts

```php
// In App\Observers\AnnouncementObserver

public function created(Announcement $announcement)
{
    if ($announcement->send_sms) {
        $smsService = app(\App\Services\SmsGatewayService::class);
        
        // Get recipients based on announcement type
        $recipients = [];
        
        if ($announcement->type === 'parents') {
            $recipients = \App\Models\ParentGuardian::pluck('phone')->toArray();
        } elseif ($announcement->type === 'teachers') {
            $recipients = \App\Models\Teacher::pluck('phone')->toArray();
        }
        
        if (!empty($recipients)) {
            $smsService->sendAnnouncement(
                $recipients,
                $announcement->title,
                $announcement->body
            );
        }
    }
}
```

---

## 📊 Monitoring SMS Usage

### Check SMS Statistics

```php
$smsService = app(\App\Services\SmsGatewayService::class);

// Get today's usage
$stats = $smsService->getUsageStats('today');

// Get this week's usage
$stats = $smsService->getUsageStats('week');

// Get this month's usage
$stats = $smsService->getUsageStats('month');

echo "Sent: {$stats['sent_count']}, Cost: MVR {$stats['total_cost']}";
```

### Check Message Status

```php
$result = $smsService->sendSms('7972434', 'Test message');

if ($result['success']) {
    $messageId = $result['message_id'];
    
    // Check status later
    $status = $smsService->getSmsStatus($messageId);
    
    echo "Status: {$status['status']}";
    // Possible statuses: pending, sent, delivered, failed
}
```

---

## 🔐 Single Sign-On (SSO)

Users can log in to both systems with the same credentials:

### Option 1: Shared Database (Recommended if both on same server)

Configure both systems to use the same database for users:

```env
# In both .env files
DB_DATABASE=akuru_shared
```

### Option 2: API-Based SSO (For separate databases)

Coming soon - will allow users to log into the main LMS and automatically access the SMS system.

---

## 🚨 Error Handling

The SMS service handles errors gracefully:

```php
$result = $smsService->sendSms('7972434', 'Test');

if (!$result['success']) {
    switch ($result['error_code']) {
        case 'MISSING_API_KEY':
            // API key not configured
            break;
        case 'INVALID_API_KEY':
            // API key is invalid
            break;
        case 'QUOTA_EXCEEDED':
            // SMS quota exceeded
            break;
        case 'RATE_LIMIT_EXCEEDED':
            // Too many requests
            break;
        case 'EXCEPTION':
            // General error
            Log::error('SMS error: ' . $result['error']);
            break;
    }
}
```

---

## 📞 Phone Number Formats

The service automatically formats phone numbers:

**Supported formats:**
- `7972434` → Converted to `9607972434`
- `9607972434` → Used as is
- `+9607972434` → Cleaned to `9607972434`
- `797-2434` → Cleaned to `9607972434`

---

## 💰 Cost Information

- Each SMS segment costs **MVR 0.25**
- **GSM-7 encoding**: 160 characters per segment
- **Unicode (Dhivehi/Arabic)**: 70 characters per segment
- Messages longer than one segment are automatically split

---

## 🛠️ Troubleshooting

### SMS Not Sending

1. **Check API key**: Make sure `SMS_GATEWAY_API_KEY` is set in `.env`
2. **Check connection**: Run `$smsService->checkHealth()`
3. **Check logs**: Look in `storage/logs/laravel.log` for errors
4. **Verify phone numbers**: Make sure they're valid Maldivian numbers

### Rate Limiting

If you get "Rate limit exceeded" errors:
- Default limit: 1000 requests per day
- Contact SMS system admin to increase your limit
- Use bulk sending for multiple recipients

---

## 📝 Best Practices

1. **Use bulk sending** for multiple recipients (more efficient)
2. **Add references** to track messages (`reference => 'attendance_2025-10-15'`)
3. **Handle errors gracefully** - don't crash if SMS fails
4. **Monitor usage** - check stats regularly to avoid quota issues
5. **Test in demo mode** first before using real API
6. **Keep messages short** - under 160 characters to avoid multiple segments
7. **Use templates** for common messages to ensure consistency

---

## 🔗 API Endpoints

- **Send SMS**: `POST /api/v2/sms/send`
- **Bulk SMS**: `POST /api/v2/sms/bulk`
- **Check Status**: `GET /api/v2/sms/{id}`
- **Usage Stats**: `GET /api/v2/usage`
- **Health Check**: `GET /api/v2/health`

---

## 📧 Support

For SMS Gateway issues:
- **System**: https://sms.akuru.edu.mv
- **API Docs**: https://sms.akuru.edu.mv/api/v2/documentation

For LMS Integration issues:
- Check the logs in `storage/logs/laravel.log`
- Contact system administrator

---

## 🎯 Next Steps

After setting up the integration:

1. ✅ Configure your API key
2. ✅ Test with a single SMS
3. ✅ Set up automated notifications
4. ✅ Monitor usage and costs
5. ✅ Train staff on using the system

---

**Last Updated**: October 15, 2025
**Version**: 1.0.0

