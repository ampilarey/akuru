@extends('public.layouts.public')

@section('title', 'Terms & Conditions')

@section('content')
<div class="container mx-auto px-4 py-8 max-w-4xl prose prose-brandMaroon">
    <h1 class="text-3xl font-bold text-brandMaroon-900">Terms & Conditions</h1>
    <p class="text-gray-600">Please read these terms before completing your purchase.</p>
    <div class="mt-6 space-y-4 text-gray-700">
        <p>By registering for a course at Akuru Institute, you agree to these terms. Course fees are as stated at checkout. Payment is processed in MVR (Maldivian Rufiyaa) via Bank of Maldives. Your use of our payment page is subject to the bank's terms and our refund policy.</p>
        <p><strong>Import/export and legal restrictions:</strong> Our services are delivered in the Maldives (and/or online). No import, export, or custom duties apply to the educational services we offer. You are responsible for complying with any local laws that apply to you.</p>
        <p>We recommend you retain a copy of this policy and your transaction records.</p>
    </div>
    <p class="mt-8 text-sm text-gray-500">Last updated: {{ now()->format('F j, Y') }}</p>
</div>
@endsection
