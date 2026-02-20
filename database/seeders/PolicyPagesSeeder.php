<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

class PolicyPagesSeeder extends Seeder
{
    public function run(): void
    {
        $pages = [
            [
                'slug'         => 'terms',
                'title'        => 'Terms and Conditions',
                'excerpt'      => 'Terms and conditions for enrolling in courses at Akuru Institute.',
                'is_published' => true,
                'published_at' => now(),
                'body'         => $this->terms(),
            ],
            [
                'slug'         => 'refund-policy',
                'title'        => 'Refund & Cancellation Policy',
                'excerpt'      => 'Our policy on refunds, cancellations and course changes.',
                'is_published' => true,
                'published_at' => now(),
                'body'         => $this->refundPolicy(),
            ],
            [
                'slug'         => 'privacy-policy',
                'title'        => 'Privacy Policy',
                'excerpt'      => 'How Akuru Institute collects, uses and protects your personal information.',
                'is_published' => true,
                'published_at' => now(),
                'body'         => $this->privacyPolicy(),
            ],
        ];

        foreach ($pages as $data) {
            Page::updateOrCreate(
                ['slug' => $data['slug']],
                $data
            );
        }
    }

    private function terms(): string
    {
        return <<<'HTML'
<h2>Terms and Conditions</h2>
<p><em>Last updated: February 2026</em></p>

<p>These Terms and Conditions govern your enrollment in courses offered by <strong>Akuru Institute</strong>, a registered educational institution in the Maldives. By completing the enrollment process and accepting these terms, you agree to be bound by the following conditions.</p>

<h3>1. Enrollment</h3>
<p>Enrollment in a course is subject to availability. Akuru Institute reserves the right to accept or decline any enrollment application. Enrollment is confirmed only upon receipt of full payment (where applicable) and written confirmation from the Institute.</p>

<h3>2. Fees and Payment</h3>
<p>All course fees are displayed in Maldivian Rufiyaa (MVR) unless otherwise stated. Payment must be completed in full at the time of enrollment. We accept payment via Bank of Maldives (BML) payment gateway using Visa, Mastercard, Maestro, and American Express cards.</p>

<h3>3. Refunds and Cancellations</h3>
<p>Please refer to our <a href="/en/pages/refund-policy">Refund &amp; Cancellation Policy</a> for full details. In summary, enrollment fees are non-refundable unless the course is cancelled by Akuru Institute.</p>

<h3>4. Student Conduct</h3>
<p>All students are expected to conduct themselves respectfully and in accordance with the values of Akuru Institute. The Institute reserves the right to remove any student from a course without refund if their behaviour is deemed disruptive or harmful.</p>

<h3>5. Course Changes</h3>
<p>Akuru Institute reserves the right to modify course schedules, content, instructors, or delivery methods. Students will be notified of any significant changes. If a course is cancelled, enrolled students will receive a full refund.</p>

<h3>6. Intellectual Property</h3>
<p>All course materials, content, and resources provided by Akuru Institute are the intellectual property of the Institute and may not be reproduced, distributed, or shared without written permission.</p>

<h3>7. Privacy</h3>
<p>We are committed to protecting your personal information. Please review our <a href="/en/pages/privacy-policy">Privacy Policy</a> for details on how we collect, use, and protect your data.</p>

<h3>8. Limitation of Liability</h3>
<p>Akuru Institute's liability to any student shall not exceed the total fees paid by that student for the relevant course. The Institute is not liable for any indirect, incidental, or consequential losses.</p>

<h3>9. Governing Law</h3>
<p>These terms are governed by the laws of the Republic of Maldives.</p>

<h3>10. Contact</h3>
<p>For questions about these terms, please contact us at <a href="mailto:info@akuru.edu.mv">info@akuru.edu.mv</a> or call <a href="tel:+9607972434">+960 797 2434</a>.</p>
HTML;
    }

    private function refundPolicy(): string
    {
        return <<<'HTML'
<h2>Refund &amp; Cancellation Policy</h2>
<p><em>Last updated: February 2026</em></p>

<p>This policy outlines the conditions under which refunds and cancellations are handled by <strong>Akuru Institute</strong>. Please read this policy carefully before completing your enrollment.</p>

<h3>1. No-Refund Policy</h3>
<p><strong>Enrollment fees are non-refundable once payment has been made</strong>, except in the circumstances described in Section 3 below. We encourage students to carefully review the course description, schedule, and fees before enrolling.</p>

<h3>2. Cancellation by Student</h3>
<p>If a student wishes to withdraw from a course after enrollment:</p>
<ul>
  <li>Cancellations made <strong>7 or more days</strong> before the course start date may be eligible for a credit note valid for 6 months (at the discretion of the Institute).</li>
  <li>Cancellations made <strong>less than 7 days</strong> before the course start date, or after the course has commenced, are <strong>not eligible for a refund or credit</strong>.</li>
</ul>

<h3>3. Cancellation by Akuru Institute</h3>
<p>If Akuru Institute cancels a course for any reason, enrolled students will receive a <strong>full refund</strong> of the fees paid, processed within 10 business days via the original payment method.</p>

<h3>4. Rescheduling</h3>
<p>If the Institute reschedules a course to a significantly different date, students who cannot attend the new date may request a full refund within 5 business days of being notified of the change.</p>

<h3>5. How to Request a Refund</h3>
<p>To request a refund or cancellation, contact us at:</p>
<ul>
  <li>Email: <a href="mailto:info@akuru.edu.mv">info@akuru.edu.mv</a></li>
  <li>Phone: <a href="tel:+9607972434">+960 797 2434</a></li>
</ul>
<p>Please include your full name, enrollment reference number, and the reason for your request. We will respond within 3 business days.</p>

<h3>6. Refund Processing Time</h3>
<p>Approved refunds will be processed within 10 business days. Refunds for card payments are returned to the original card used for payment. Processing times may vary depending on your bank or card provider.</p>

<h3>7. Retain Your Receipt</h3>
<p>We recommend retaining a copy of your payment receipt and enrollment confirmation for your records. These are available at any time from your enrollments page after logging in.</p>
HTML;
    }

    private function privacyPolicy(): string
    {
        return <<<'HTML'
<h2>Privacy Policy</h2>
<p><em>Last updated: February 2026</em></p>

<p><strong>Akuru Institute</strong> ("we", "us", "our") is committed to protecting the privacy of our students, parents, and website visitors. This policy explains what personal information we collect, why we collect it, how we use it, and how we keep it safe.</p>

<h3>1. Information We Collect</h3>
<p>We may collect the following types of personal information:</p>
<ul>
  <li><strong>Identity information:</strong> Full name, date of birth, gender, national ID number or passport number.</li>
  <li><strong>Contact information:</strong> Mobile number, email address.</li>
  <li><strong>Guardian/parent information:</strong> Name and contact details of parents or guardians enrolling children.</li>
  <li><strong>Payment information:</strong> Transaction references and payment status. We do <em>not</em> store card numbers — all card data is handled securely by Bank of Maldives (BML).</li>
  <li><strong>Usage information:</strong> Browser type, IP address, and pages visited, collected automatically via server logs.</li>
</ul>

<h3>2. Why We Collect This Information</h3>
<p>We collect personal information for the following purposes:</p>
<ul>
  <li>To process and manage course enrollments.</li>
  <li>To communicate with you about your enrollment, course schedule, and updates.</li>
  <li>To send payment confirmations and receipts.</li>
  <li>To verify identity for enrollment eligibility.</li>
  <li>To comply with legal and regulatory requirements.</li>
  <li>To improve our website and services.</li>
</ul>

<h3>3. How We Use Your Information</h3>
<p>Your personal information is used only for the purposes stated above. We do not sell, rent, or trade your personal information to third parties. We may share your information with:</p>
<ul>
  <li><strong>Bank of Maldives (BML):</strong> To process payments securely.</li>
  <li><strong>Dhiraagu:</strong> To send SMS verification codes and enrollment confirmations.</li>
  <li><strong>Email service providers:</strong> To send confirmation emails.</li>
</ul>

<h3>4. Security of Your Information</h3>
<p>We take the security of your personal information seriously:</p>
<ul>
  <li>All data is transmitted over <strong>SSL/TLS encrypted connections</strong> (HTTPS).</li>
  <li>Sensitive identity documents (national ID, passport numbers) are <strong>encrypted at rest</strong> in our database.</li>
  <li>Card payment details are never stored on our servers — they are processed directly by BML's secure payment gateway.</li>
  <li>Access to personal data is restricted to authorised staff only.</li>
</ul>

<h3>5. Data Retention</h3>
<p>We retain personal information for as long as necessary to provide our services and meet legal obligations. Enrollment records are retained for a minimum of 5 years. You may request deletion of your personal data by contacting us (see Section 7).</p>

<h3>6. Your Rights</h3>
<p>You have the right to:</p>
<ul>
  <li>Access the personal information we hold about you.</li>
  <li>Request correction of inaccurate information.</li>
  <li>Request deletion of your personal data (subject to legal retention requirements).</li>
  <li>Withdraw consent for communications at any time.</li>
</ul>

<h3>7. Contact Us</h3>
<p>For any privacy-related requests or questions, please contact us at:</p>
<ul>
  <li>Email: <a href="mailto:info@akuru.edu.mv">info@akuru.edu.mv</a></li>
  <li>Phone: <a href="tel:+9607972434">+960 797 2434</a></li>
  <li>Address: Akuru Institute, Malé, Republic of Maldives</li>
</ul>

<h3>8. Changes to This Policy</h3>
<p>We may update this Privacy Policy from time to time. The latest version will always be available on this page with the date of last update shown at the top.</p>
HTML;
    }
}
