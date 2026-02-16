@extends('public.layouts.public')

@section('title', 'Checkout – ' . $course->title)

@section('content')
<section class="py-12">
    <div class="container mx-auto px-4 max-w-2xl">
        <h1 class="text-2xl font-bold text-brandMaroon-900 mb-6">Checkout – {{ $course->title }}</h1>

        @if(session('error'))
            <div class="mb-4 p-3 bg-red-100 text-red-800 rounded">{{ session('error') }}</div>
        @endif
        @if($errors->any())
            <div class="mb-4 p-3 bg-red-100 text-red-800 rounded">
                <ul class="list-disc list-inside">{{ implode('', $errors->all('<li>:message</li>')) }}</ul>
            </div>
        @endif

        {{-- Compliance: description of goods/services --}}
        <div class="card p-6 mb-6">
            <h2 class="font-semibold text-brandMaroon-700 mb-2">Course registration</h2>
            <p class="text-gray-600 text-sm mb-4">{{ $course->short_desc ?? $course->title }}</p>
            <p class="text-lg font-semibold">Registration fee: {{ number_format($fee, 2) }} {{ $currency }}</p>
            <p class="text-sm text-gray-500 mt-1">Merchant outlet country: {{ $merchant_outlet_country }}</p>
        </div>

        {{-- Compliance: All card brand marks in full colour and equal prominence (Req 1) --}}
        <div class="border border-gray-200 rounded-lg p-4 mb-6 bg-gray-50">
            <p class="text-center text-sm text-gray-600 mb-3">We accept</p>
            <div class="flex flex-wrap items-center justify-center gap-6">
                {{-- Visa brand mark (full colour) --}}
                <div class="flex items-center justify-center h-10" aria-hidden="true">
                    <svg viewBox="0 0 48 16" class="h-8 w-auto" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Visa">
                        <rect width="48" height="16" rx="2" fill="#1A1F71"/>
                        <text x="24" y="11" text-anchor="middle" fill="#FFF" font-family="Arial,sans-serif" font-size="10" font-weight="bold">VISA</text>
                    </svg>
                </div>
                {{-- Mastercard brand mark (full colour: red and orange circles) --}}
                <div class="flex items-center justify-center h-10" aria-hidden="true">
                    <svg viewBox="0 0 24 16" class="h-8 w-auto" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Mastercard">
                        <circle cx="9" cy="8" r="6" fill="#EB001B"/>
                        <circle cx="15" cy="8" r="6" fill="#F79E1B" fill-opacity="0.9"/>
                        <path d="M12 4.5a6 6 0 0 1 0 7 6 6 0 0 1 0-7" fill="#FF5F00"/>
                    </svg>
                </div>
                <span class="text-sm text-gray-600">Other card brands as accepted by Bank of Maldives</span>
            </div>
        </div>

        <form method="POST" action="{{ route('payments.course.start', $course) }}" class="card p-6" id="checkout-form">
            @csrf

            {{-- Compliance: terms and policies before checkout --}}
            <div class="mb-6 space-y-3 text-sm">
                <p>Before completing your purchase, please read:</p>
                <ul class="list-disc list-inside text-gray-600">
                    <li><a href="{{ route('public.terms') }}" class="text-brandMaroon-600 hover:underline" target="_blank" rel="noopener">Terms &amp; Conditions</a></li>
                    <li><a href="{{ route('public.refunds') }}" class="text-brandMaroon-600 hover:underline" target="_blank" rel="noopener">Refund &amp; Cancellation Policy</a></li>
                    <li><a href="{{ route('public.privacy') }}" class="text-brandMaroon-600 hover:underline" target="_blank" rel="noopener">Privacy Policy</a></li>
                </ul>
                <p class="text-gray-600">No import/export or custom duties apply to our services.</p>
            </div>

            {{-- Required acceptance checkbox --}}
            <div class="mb-6">
                <label class="inline-flex items-start gap-2 cursor-pointer">
                    <input type="checkbox" name="accept_terms" value="1" required
                        class="rounded border-gray-300 text-brandMaroon-600 focus:ring-brandMaroon-500 mt-0.5"
                        {{ old('accept_terms') ? 'checked' : '' }}>
                    <span class="text-gray-700">I agree to the <a href="{{ route('public.terms') }}" class="text-brandMaroon-600 hover:underline">Terms &amp; Conditions</a>, <a href="{{ route('public.refunds') }}" class="text-brandMaroon-600 hover:underline">Refund Policy</a>, and <a href="{{ route('public.privacy') }}" class="text-brandMaroon-600 hover:underline">Privacy Policy</a>.</span>
                </label>
            </div>

            <p class="text-xs text-gray-500 mb-4">Payment is processed securely by Bank of Maldives. We do not store your card details.</p>

            <button type="submit" class="w-full bg-brandMaroon-600 text-white py-3 px-6 rounded-lg font-semibold hover:bg-brandMaroon-700">
                Pay {{ number_format($fee, 2) }} {{ $currency }}
            </button>
        </form>

        {{-- Corporate / contact info (compliance) --}}
        <div class="mt-8 text-sm text-gray-600">
            <p><strong>Akuru Institute</strong></p>
            <p>M. Guldhastha Aage, Muniya Magu, Malé 20026, Maldives</p>
            <p>Tel: +960 797 2434</p>
            <p>Email: info@akuru.edu.mv</p>
        </div>
    </div>
</section>
@endsection
