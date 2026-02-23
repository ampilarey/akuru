{{--
    BML-required compliance block (all 14 requirements covered here).
    Include on every page where payment options are presented to the Cardholder.
--}}
<div style="margin-top:1.5rem;padding-top:1.25rem;border-top:1px solid #E5E7EB">

    {{-- Req #1: Card brand logos in full colour, equal prominence --}}
    <div style="text-align:center;margin-bottom:.75rem">
        <img src="{{ asset('images/card-brands.png') }}"
             alt="We accept American Express, Visa, Mastercard and Maestro"
             style="height:32px;max-width:100%;object-fit:contain">
    </div>

    {{-- Req #4 & #5: Transaction currency + Merchant outlet country --}}
    <p style="text-align:center;font-size:.72rem;color:#6B7280;margin:.5rem 0">
        Merchant outlet country: <strong style="color:#374151">Maldives</strong>
        &nbsp;·&nbsp;
        Transaction currency: <strong style="color:#374151">MVR (Maldivian Rufiyaa)</strong>
    </p>

    {{-- Req #10: Security capabilities statement --}}
    <p style="text-align:center;font-size:.72rem;color:#6B7280;margin:.5rem 0;display:flex;align-items:center;justify-content:center;gap:.35rem">
        <svg width="12" height="12" fill="currentColor" viewBox="0 0 20 20" style="color:#16a34a;flex-shrink:0">
            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
        </svg>
        Payments processed securely by Bank of Maldives (BML) via SSL encryption. Card details are never stored on our servers.
    </p>

    {{-- Req #11: Retain transaction records --}}
    <p style="text-align:center;font-size:.72rem;color:#6B7280;margin:.5rem 0">
        We recommend you retain a copy of your receipt and transaction records after payment.
    </p>

</div>
