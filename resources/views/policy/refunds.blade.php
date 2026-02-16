@extends('public.layouts.public')

@section('title', 'Refund & Cancellation Policy')

@section('content')
<div class="container mx-auto px-4 py-8 max-w-4xl prose prose-brandMaroon">
    <h1 class="text-3xl font-bold text-brandMaroon-900">Refund & Cancellation Policy</h1>
    <p class="text-gray-600">Please read before purchase.</p>
    <div class="mt-6 space-y-4 text-gray-700">
        <p>Registration fees paid for courses at Akuru Institute are generally <strong>non-refundable</strong> once payment has been confirmed. In exceptional circumstances (e.g. course cancellation by the Institute), we may offer a refund at our discretion.</p>
        <p>If you need to cancel your registration before the course starts, please contact us. We do not offer refunds for partial attendance or change of mind after payment.</p>
    </div>
    <p class="mt-8 text-sm text-gray-500">Last updated: {{ now()->format('F j, Y') }}</p>
</div>
@endsection
