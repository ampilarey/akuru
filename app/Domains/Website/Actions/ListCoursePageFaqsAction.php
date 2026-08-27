<?php

namespace App\Domains\Website\Actions;

class ListCoursePageFaqsAction
{
    /**
     * FAQs rendered on the public course page. JSON-LD FAQPage must match this list.
     *
     * @return list<array{q: string, a: string}>
     */
    public function execute(): array
    {
        return [
            [
                'q' => 'Who is this course for?',
                'a' => 'This course is open to anyone interested in Islamic education — students, adults, and professionals. No prior knowledge is required for beginner levels.',
            ],
            [
                'q' => 'How do I enroll?',
                'a' => 'Click the "Enroll in this course" button above to start the enrollment process. You will need to verify your mobile number via OTP and complete a short registration form.',
            ],
            [
                'q' => 'What is the payment method?',
                'a' => 'We accept payments online via BML Internet Banking and debit/credit cards. All payments are processed securely through the Bank of Maldives payment portal.',
            ],
            [
                'q' => 'Can I get a refund if I cannot attend?',
                'a' => 'Please review our refund policy on the website or contact us directly for guidance specific to your situation.',
            ],
            [
                'q' => 'Will I receive a certificate?',
                'a' => 'Yes, students who successfully complete the course requirements will receive a certificate of completion from Akuru Institute.',
            ],
            [
                'q' => 'Are classes online or in-person?',
                'a' => 'We offer both online and in-person sessions depending on the course. Check the schedule section above or contact us for details on this course.',
            ],
        ];
    }
}
