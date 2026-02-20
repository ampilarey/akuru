{{--
    BML-required compliance block.
    Shows card brand logos, merchant country, and security statement.
    Include on any page where payment options are presented.
--}}
<div class="mt-6 pt-5 border-t border-gray-200 space-y-3">

    {{-- Card brand logos (Req #1) --}}
    <div class="flex items-center justify-center">
        <img src="{{ asset('images/card-brands.png') }}"
             alt="We accept American Express, Visa, Mastercard and Maestro"
             class="h-8 object-contain">
    </div>

    {{-- Merchant country (Req #5) --}}
    <p class="text-center text-xs text-gray-500">
        Merchant outlet country: <strong>Maldives</strong> &nbsp;·&nbsp;
        Transaction currency: <strong>MVR</strong>
    </p>

    {{-- Security statement (Req #10) --}}
    <p class="text-center text-xs text-gray-500 flex items-center justify-center gap-1">
        <svg class="w-3 h-3 text-green-600 shrink-0" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
        </svg>
        Payments are processed securely by Bank of Maldives (BML).
        Card details are transmitted using SSL encryption and are never stored on our servers.
    </p>

</div>
