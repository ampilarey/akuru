@extends('public.layouts.public')

@section('title', 'Terms & Conditions')

@section('content')
<div class="container mx-auto px-4 py-8 max-w-4xl prose prose-brandMaroon">
    <h1 class="text-3xl font-bold text-brandMaroon-900">Terms &amp; Conditions</h1>
    <p class="text-gray-600">Please read these terms before completing your purchase.</p>

    <div class="mt-6 space-y-6 text-gray-700">

        <div>
            <h2 class="text-lg font-semibold text-brandMaroon-800">1. Payment &amp; Fees</h2>
            <p>Course fees are as stated at checkout. Payment is processed in <strong>MVR (Maldivian Rufiyaa)</strong> via Bank of Maldives (BML). The merchant outlet is located in the <strong>Maldives</strong>. Your use of our payment page is subject to the bank's terms and our refund policy.</p>
        </div>

        <div>
            <h2 class="text-lg font-semibold text-brandMaroon-800">2. Delivery Policy</h2>
            <p>Akuru Institute offers educational services delivered in the following ways:</p>
            <ul class="list-disc ml-6 mt-2 space-y-1">
                <li><strong>In-person classes</strong> are held at our premises in Malé, Maldives. Details (location, schedule) will be communicated via email/SMS after enrollment is confirmed.</li>
                <li><strong>Online/remote classes</strong> (where applicable) will be accessible via a link sent to your registered email or mobile number after enrollment confirmation.</li>
                <li>Enrollment confirmation is subject to admin approval. You will be notified within 1–2 business days of payment.</li>
            </ul>
        </div>

        <div>
            <h2 class="text-lg font-semibold text-brandMaroon-800">3. Refund &amp; Cancellation</h2>
            <p>Registration fees are generally <strong>non-refundable</strong> once payment is confirmed. Please review our full <a href="{{ route('public.page.show', 'refund-policy') }}" class="text-brandMaroon-700 underline">Refund &amp; Cancellation Policy</a> before purchase.</p>
        </div>

        <div>
            <h2 class="text-lg font-semibold text-brandMaroon-800">4. Import / Export &amp; Legal Restrictions</h2>
            <p>Our services are educational in nature and are delivered in the Maldives (and/or online). No import, export, or custom duties apply to the educational services we offer. You are responsible for complying with any local laws that apply to you.</p>
        </div>

        <div>
            <h2 class="text-lg font-semibold text-brandMaroon-800">5. Security &amp; Card Data</h2>
            <p>All card payments are processed securely by Bank of Maldives (BML) using SSL encryption. Akuru Institute does not store, view, or retain any card details on our servers.</p>
        </div>

        <div>
            <h2 class="text-lg font-semibold text-brandMaroon-800">6. Transaction Records</h2>
            <p>We strongly recommend you retain a copy of your receipt and these Terms &amp; Conditions for your records. A receipt is available to download from your enrollment confirmation email and from the payment confirmation page.</p>
        </div>

        <div>
            <h2 class="text-lg font-semibold text-brandMaroon-800">7. Privacy</h2>
            <p>Your personal data is collected and used in accordance with our <a href="{{ route('public.page.show', 'privacy-policy') }}" class="text-brandMaroon-700 underline">Privacy Policy</a>.</p>
        </div>

    </div>

    <p class="mt-8 text-sm text-gray-500">Last updated: {{ now()->format('F j, Y') }}</p>
</div>
@endsection
