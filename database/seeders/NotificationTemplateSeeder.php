<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\NotificationTemplate;

class NotificationTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = [
            // Welcome templates
            [
                'name' => 'welcome_email',
                'type' => 'email',
                'category' => 'system',
                'subject' => 'Welcome to Akuru Institute!',
                'body' => 'Dear {{name}}, welcome to Akuru Institute! We are excited to have you join our community of learners. Your account has been successfully created and you can now access all our educational resources.',
                'variables' => ['name', 'email'],
                'is_system' => true,
                'language' => 'en',
            ],
            [
                'name' => 'welcome_sms',
                'type' => 'sms',
                'category' => 'system',
                'subject' => null,
                'body' => 'Welcome to Akuru Institute! Your account has been created successfully. Visit our website to get started.',
                'variables' => ['name'],
                'is_system' => true,
                'language' => 'en',
            ],

            // Course enrollment
            [
                'name' => 'course_enrollment',
                'type' => 'email',
                'category' => 'course',
                'subject' => 'Course Enrollment Confirmation - {{course_name}}',
                'body' => 'Dear {{name}}, you have been successfully enrolled in {{course_name}}. Classes start on {{start_date}} at {{start_time}}. Please ensure you have all required materials ready.',
                'variables' => ['name', 'course_name', 'start_date', 'start_time'],
                'is_system' => true,
                'language' => 'en',
            ],
            [
                'name' => 'course_enrollment_sms',
                'type' => 'sms',
                'category' => 'course',
                'subject' => null,
                'body' => 'You are enrolled in {{course_name}}. Classes start {{start_date}}. Check your email for details.',
                'variables' => ['course_name', 'start_date'],
                'is_system' => true,
                'language' => 'en',
            ],

            // Assignment due
            [
                'name' => 'assignment_due',
                'type' => 'email',
                'category' => 'assignment',
                'subject' => 'Assignment Due Reminder - {{assignment_title}}',
                'body' => 'Dear {{name}}, your assignment "{{assignment_title}}" is due on {{due_date}} at {{due_time}}. Please submit it on time to avoid any penalties.',
                'variables' => ['name', 'assignment_title', 'due_date', 'due_time'],
                'is_system' => true,
                'language' => 'en',
            ],
            [
                'name' => 'assignment_due_sms',
                'type' => 'sms',
                'category' => 'assignment',
                'subject' => null,
                'body' => 'Assignment "{{assignment_title}}" due {{due_date}}. Submit on time!',
                'variables' => ['assignment_title', 'due_date'],
                'is_system' => true,
                'language' => 'en',
            ],

            // Event reminder
            [
                'name' => 'event_reminder',
                'type' => 'email',
                'category' => 'event',
                'subject' => 'Upcoming Event Reminder - {{event_title}}',
                'body' => 'Dear {{name}}, don\'t forget about the event "{{event_title}}" on {{event_date}} at {{event_time}} in {{event_location}}. We look forward to seeing you there!',
                'variables' => ['name', 'event_title', 'event_date', 'event_time', 'event_location'],
                'is_system' => true,
                'language' => 'en',
            ],
            [
                'name' => 'event_reminder_sms',
                'type' => 'sms',
                'category' => 'event',
                'subject' => null,
                'body' => 'Event "{{event_title}}" tomorrow at {{event_time}} in {{event_location}}. Don\'t miss it!',
                'variables' => ['event_title', 'event_time', 'event_location'],
                'is_system' => true,
                'language' => 'en',
            ],

            // Payment reminder
            [
                'name' => 'payment_reminder',
                'type' => 'email',
                'category' => 'payment',
                'subject' => 'Payment Reminder - {{amount}}',
                'body' => 'Dear {{name}}, this is a friendly reminder that your payment of {{amount}} for {{description}} is due on {{due_date}}. Please make the payment to avoid any late fees.',
                'variables' => ['name', 'amount', 'description', 'due_date'],
                'is_system' => true,
                'language' => 'en',
            ],
            [
                'name' => 'payment_reminder_sms',
                'type' => 'sms',
                'category' => 'payment',
                'subject' => null,
                'body' => 'Payment reminder: {{amount}} for {{description}} due {{due_date}}. Pay now to avoid late fees.',
                'variables' => ['amount', 'description', 'due_date'],
                'is_system' => true,
                'language' => 'en',
            ],

            // Grade notification
            [
                'name' => 'grade_notification',
                'type' => 'email',
                'category' => 'academic',
                'subject' => 'Grade Posted - {{assignment_title}}',
                'body' => 'Dear {{name}}, your grade for "{{assignment_title}}" has been posted. You received {{grade}} out of {{total_points}}. {{feedback}}',
                'variables' => ['name', 'assignment_title', 'grade', 'total_points', 'feedback'],
                'is_system' => true,
                'language' => 'en',
            ],

            // Attendance notification
            [
                'name' => 'attendance_notification',
                'type' => 'email',
                'category' => 'academic',
                'subject' => 'Attendance Update - {{date}}',
                'body' => 'Dear {{parent_name}}, this is to inform you that {{student_name}} was {{status}} on {{date}} for {{class_name}}.',
                'variables' => ['parent_name', 'student_name', 'status', 'date', 'class_name'],
                'is_system' => true,
                'language' => 'en',
            ],

            // System maintenance
            [
                'name' => 'system_maintenance',
                'type' => 'email',
                'category' => 'system',
                'subject' => 'Scheduled System Maintenance',
                'body' => 'Dear {{name}}, we will be performing scheduled maintenance on our system from {{start_time}} to {{end_time}} on {{date}}. During this time, the system may be temporarily unavailable.',
                'variables' => ['name', 'start_time', 'end_time', 'date'],
                'is_system' => true,
                'language' => 'en',
            ],

            // Password reset
            [
                'name' => 'password_reset',
                'type' => 'email',
                'category' => 'security',
                'subject' => 'Password Reset Request',
                'body' => 'Dear {{name}}, you have requested to reset your password. Click the following link to reset your password: {{reset_link}}. This link will expire in {{expiry_time}}.',
                'variables' => ['name', 'reset_link', 'expiry_time'],
                'is_system' => true,
                'language' => 'en',
            ],
        ];

        foreach ($templates as $template) {
            NotificationTemplate::updateOrCreate(
                ['name' => $template['name'], 'type' => $template['type']],
                $template
            );
        }
    }
}