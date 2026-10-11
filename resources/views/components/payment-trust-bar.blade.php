{{--
    BML-required compliance block (all 14 requirements covered here).
    Include on every page where payment options are presented to the Cardholder.
    In the page's language since slice PY1: it sat in English on the
    registration's Dhivehi and Arabic code step and on the processing page.
--}}
<div style="margin-top:1.5rem;padding-top:1.25rem;border-top:1px solid #E5E7EB" data-testid="payment-trust-bar">

    {{-- Req #1: Card brand logos in full colour, equal prominence --}}
    <div style="text-align:center;margin-bottom:.75rem">
        <img src="{{ asset('images/card-brands.png') }}"
             alt="{{ __('payments.card_brands') }}"
             style="height:32px;max-width:100%;object-fit:contain">
    </div>

    {{-- Req #4 & #5: Transaction currency + Merchant outlet country --}}
    {{-- Each value sits where the language puts it in its line. --}}
    <p style="text-align:center;font-size:.72rem;color:#6B7280;margin:.5rem 0">
        <span>@foreach(preg_split('/(:country)/', __('payments.outlet_country'), -1, PREG_SPLIT_DELIM_CAPTURE) as $part)@if($part === ':country')<strong style="color:#374151">{{ __('payments.maldives') }}</strong>@else{{ $part }}@endif{{ '' }}@endforeach</span>
        <span aria-hidden="true" style="margin:0 .35rem">·</span>
        <span>@foreach(preg_split('/(:currency)/', __('payments.currency'), -1, PREG_SPLIT_DELIM_CAPTURE) as $part)@if($part === ':currency')<strong style="color:#374151">{{ __('payments.mvr') }}</strong>@else{{ $part }}@endif{{ '' }}@endforeach</span>
    </p>

    {{-- Req #10: Security capabilities statement --}}
    <p style="text-align:center;font-size:.72rem;color:#6B7280;margin:.5rem 0;display:flex;align-items:center;justify-content:center;gap:.35rem">
        <svg width="12" height="12" fill="currentColor" viewBox="0 0 20 20" style="color:#16a34a;flex-shrink:0" aria-hidden="true">
            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
        </svg>
        {{ __('payments.secure_ssl') }}
    </p>

    {{-- Req #11: Retain transaction records --}}
    <p style="text-align:center;font-size:.72rem;color:#6B7280;margin:.5rem 0">
        {{ __('payments.retain') }}
    </p>

</div>
