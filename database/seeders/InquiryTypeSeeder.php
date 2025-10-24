<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\InquiryType;

class InquiryTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $inquiryTypes = [
            [
                'name' => 'General Inquiry',
                'slug' => 'general-inquiry',
                'description' => 'General questions about Akuru Institute, programs, or services',
                'email_to' => 'info@akuru.edu.mv',
                'auto_response_template' => 'Dear {{name}},

Thank you for contacting Akuru Institute. We have received your inquiry regarding "{{subject}}" and will respond within 24 hours.

Your inquiry is important to us, and we appreciate your interest in our Islamic education programs.

Best regards,
Akuru Institute Team',
                'requires_phone' => false,
                'requires_subject' => true,
                'custom_fields' => null,
                'is_active' => true,
                'sort_order' => 1,
                'response_time_hours' => 24,
            ],
            [
                'name' => 'Admissions Inquiry',
                'slug' => 'admissions-inquiry',
                'description' => 'Questions about admissions, enrollment, and course requirements',
                'email_to' => 'admissions@akuru.edu.mv',
                'auto_response_template' => 'Dear {{name}},

Thank you for your interest in joining Akuru Institute. We have received your admissions inquiry and our admissions team will contact you within 48 hours to discuss your educational goals and program options.

We look forward to welcoming you to our community of learners.

Best regards,
Admissions Team
Akuru Institute',
                'requires_phone' => true,
                'requires_subject' => true,
                'custom_fields' => [
                    [
                        'name' => 'interested_program',
                        'type' => 'select',
                        'label' => 'Program of Interest',
                        'required' => true,
                        'placeholder' => 'Select a program',
                        'options' => [
                            'Quran Memorization (Hifz)',
                            'Arabic Language',
                            'Islamic Studies',
                            'Tajweed Classes',
                            'Adult Education',
                            'Other'
                        ]
                    ],
                    [
                        'name' => 'age_group',
                        'type' => 'select',
                        'label' => 'Age Group',
                        'required' => true,
                        'placeholder' => 'Select age group',
                        'options' => [
                            'Kids (6-12 years)',
                            'Youth (13-18 years)',
                            'Adult (19+ years)'
                        ]
                    ],
                    [
                        'name' => 'previous_experience',
                        'type' => 'textarea',
                        'label' => 'Previous Islamic Education Experience',
                        'required' => false,
                        'placeholder' => 'Please describe any previous Islamic education or Quran study experience'
                    ]
                ],
                'is_active' => true,
                'sort_order' => 2,
                'response_time_hours' => 48,
            ],
            [
                'name' => 'Course Information',
                'slug' => 'course-information',
                'description' => 'Questions about specific courses, schedules, and curriculum',
                'email_to' => 'courses@akuru.edu.mv',
                'auto_response_template' => 'Dear {{name}},

Thank you for your interest in our courses. We have received your inquiry about "{{subject}}" and our academic team will provide detailed information within 24 hours.

We offer a comprehensive range of Islamic education programs designed to meet various learning needs and goals.

Best regards,
Academic Team
Akuru Institute',
                'requires_phone' => false,
                'requires_subject' => true,
                'custom_fields' => [
                    [
                        'name' => 'course_name',
                        'type' => 'text',
                        'label' => 'Course Name',
                        'required' => true,
                        'placeholder' => 'Enter the course name you\'re interested in'
                    ],
                    [
                        'name' => 'preferred_schedule',
                        'type' => 'select',
                        'label' => 'Preferred Schedule',
                        'required' => false,
                        'placeholder' => 'Select preferred time',
                        'options' => [
                            'Morning (9:00 AM - 12:00 PM)',
                            'Afternoon (2:00 PM - 5:00 PM)',
                            'Evening (6:00 PM - 9:00 PM)',
                            'Weekend',
                            'Flexible'
                        ]
                    ]
                ],
                'is_active' => true,
                'sort_order' => 3,
                'response_time_hours' => 24,
            ],
            [
                'name' => 'Event Information',
                'slug' => 'event-information',
                'description' => 'Questions about events, workshops, and special programs',
                'email_to' => 'events@akuru.edu.mv',
                'auto_response_template' => 'Dear {{name}},

Thank you for your interest in our events and programs. We have received your inquiry and will provide you with detailed information about upcoming events and registration procedures.

Stay connected with us for the latest updates on our community activities.

Best regards,
Events Team
Akuru Institute',
                'requires_phone' => false,
                'requires_subject' => true,
                'custom_fields' => [
                    [
                        'name' => 'event_name',
                        'type' => 'text',
                        'label' => 'Event Name',
                        'required' => false,
                        'placeholder' => 'Enter the event name you\'re interested in'
                    ],
                    [
                        'name' => 'registration_interest',
                        'type' => 'checkbox',
                        'label' => 'Interested in Registration',
                        'required' => false,
                        'options' => ['Yes, I would like to register for this event']
                    ]
                ],
                'is_active' => true,
                'sort_order' => 4,
                'response_time_hours' => 24,
            ],
            [
                'name' => 'Technical Support',
                'slug' => 'technical-support',
                'description' => 'Technical issues with website, online learning, or digital services',
                'email_to' => 'support@akuru.edu.mv',
                'auto_response_template' => 'Dear {{name}},

Thank you for reporting this technical issue. We have received your support request and our technical team will investigate and resolve the issue as soon as possible.

We apologize for any inconvenience and appreciate your patience.

Best regards,
Technical Support Team
Akuru Institute',
                'requires_phone' => false,
                'requires_subject' => true,
                'custom_fields' => [
                    [
                        'name' => 'issue_type',
                        'type' => 'select',
                        'label' => 'Issue Type',
                        'required' => true,
                        'placeholder' => 'Select the type of issue',
                        'options' => [
                            'Website not loading',
                            'Login problems',
                            'Online course access',
                            'Payment issues',
                            'Mobile app problems',
                            'Other'
                        ]
                    ],
                    [
                        'name' => 'browser_info',
                        'type' => 'text',
                        'label' => 'Browser and Device Information',
                        'required' => false,
                        'placeholder' => 'e.g., Chrome on Windows 10, Safari on iPhone'
                    ]
                ],
                'is_active' => true,
                'sort_order' => 5,
                'response_time_hours' => 12,
            ],
            [
                'name' => 'Partnership Inquiry',
                'slug' => 'partnership-inquiry',
                'description' => 'Business partnerships, collaborations, and community outreach',
                'email_to' => 'partnerships@akuru.edu.mv',
                'auto_response_template' => 'Dear {{name}},

Thank you for your interest in partnering with Akuru Institute. We have received your inquiry and our partnerships team will review your proposal and contact you within 72 hours.

We value community partnerships and look forward to exploring opportunities for collaboration.

Best regards,
Partnerships Team
Akuru Institute',
                'requires_phone' => true,
                'requires_subject' => true,
                'custom_fields' => [
                    [
                        'name' => 'organization_name',
                        'type' => 'text',
                        'label' => 'Organization Name',
                        'required' => true,
                        'placeholder' => 'Enter your organization name'
                    ],
                    [
                        'name' => 'partnership_type',
                        'type' => 'select',
                        'label' => 'Partnership Type',
                        'required' => true,
                        'placeholder' => 'Select partnership type',
                        'options' => [
                            'Educational Collaboration',
                            'Community Outreach',
                            'Event Sponsorship',
                            'Resource Sharing',
                            'Other'
                        ]
                    ],
                    [
                        'name' => 'proposal_details',
                        'type' => 'textarea',
                        'label' => 'Partnership Proposal Details',
                        'required' => true,
                        'placeholder' => 'Please provide details about your partnership proposal'
                    ]
                ],
                'is_active' => true,
                'sort_order' => 6,
                'response_time_hours' => 72,
            ],
            [
                'name' => 'Feedback & Suggestions',
                'slug' => 'feedback-suggestions',
                'description' => 'Feedback, suggestions, and improvement recommendations',
                'email_to' => 'feedback@akuru.edu.mv',
                'auto_response_template' => 'Dear {{name}},

Thank you for taking the time to share your feedback with us. We greatly value your input and suggestions as they help us improve our services and programs.

Your feedback has been forwarded to the relevant department for review and consideration.

Best regards,
Akuru Institute Team',
                'requires_phone' => false,
                'requires_subject' => true,
                'custom_fields' => [
                    [
                        'name' => 'feedback_category',
                        'type' => 'select',
                        'label' => 'Feedback Category',
                        'required' => true,
                        'placeholder' => 'Select feedback category',
                        'options' => [
                            'Academic Programs',
                            'Teaching Quality',
                            'Facilities',
                            'Administration',
                            'Events',
                            'Website/Online Services',
                            'Other'
                        ]
                    ],
                    [
                        'name' => 'rating',
                        'type' => 'select',
                        'label' => 'Overall Rating',
                        'required' => false,
                        'placeholder' => 'Rate your experience',
                        'options' => [
                            'Excellent',
                            'Good',
                            'Average',
                            'Poor',
                            'Very Poor'
                        ]
                    ]
                ],
                'is_active' => true,
                'sort_order' => 7,
                'response_time_hours' => 48,
            ],
            [
                'name' => 'Complaint',
                'slug' => 'complaint',
                'description' => 'Formal complaints and concerns requiring immediate attention',
                'email_to' => 'complaints@akuru.edu.mv',
                'auto_response_template' => 'Dear {{name}},

We have received your complaint and take all concerns seriously. Your complaint has been forwarded to our management team for immediate review and appropriate action.

We will investigate the matter thoroughly and provide you with a detailed response within 48 hours.

Thank you for bringing this to our attention.

Best regards,
Management Team
Akuru Institute',
                'requires_phone' => true,
                'requires_subject' => true,
                'custom_fields' => [
                    [
                        'name' => 'complaint_category',
                        'type' => 'select',
                        'label' => 'Complaint Category',
                        'required' => true,
                        'placeholder' => 'Select complaint category',
                        'options' => [
                            'Academic Issues',
                            'Administrative Problems',
                            'Staff Behavior',
                            'Facility Issues',
                            'Safety Concerns',
                            'Other'
                        ]
                    ],
                    [
                        'name' => 'incident_date',
                        'type' => 'date',
                        'label' => 'Date of Incident',
                        'required' => false,
                        'placeholder' => 'Select date if applicable'
                    ],
                    [
                        'name' => 'witnesses',
                        'type' => 'text',
                        'label' => 'Witnesses (if any)',
                        'required' => false,
                        'placeholder' => 'Names of any witnesses to the incident'
                    ]
                ],
                'is_active' => true,
                'sort_order' => 8,
                'response_time_hours' => 48,
            ],
        ];

        foreach ($inquiryTypes as $type) {
            InquiryType::create($type);
        }
    }
}