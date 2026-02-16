@extends('public.layouts.public')

@section('title', 'Privacy Policy')

@section('content')
<div class="container mx-auto px-4 py-8 max-w-4xl prose prose-brandMaroon">
    <h1 class="text-3xl font-bold text-brandMaroon-900">Privacy Policy</h1>
    <p class="text-gray-600">How we collect, use, and protect your data.</p>
    <div class="mt-6 space-y-4 text-gray-700">
        <p>We collect information you provide when registering (name, contact details, date of birth where applicable) for the purpose of course enrollment and communication. Payment card details are not stored by us; they are handled securely by Bank of Maldives on their payment page.</p>
        <p>We do not share your data with third parties except as required for payment processing. We use appropriate measures to prevent unauthorized access to your data.</p>
    </div>
    <p class="mt-8 text-sm text-gray-500">Last updated: {{ now()->format('F j, Y') }}</p>
</div>
@endsection
