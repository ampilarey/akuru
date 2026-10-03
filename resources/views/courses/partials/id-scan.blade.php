{{--
    C17 slice R2 (STATUS §5od): "Fill in from your ID card". The photo is read
    in the browser by the self-hosted reader (resources/js/id-scan); nothing
    here posts it anywhere.

    $mode = 'photo'   — its own file input, with no name, so the photo is
                        never submitted (the checkout form).
    $mode = 'uploads' — no input of its own; it reads the form's ID card
                        uploads marked data-id-scan-source (the details form).

    Off when config('registration.id_scan') is false: the forms work as before.
--}}
@if(config('registration.id_scan'))
    @php
        $idScanFields = ['first_name', 'last_name', 'dob', 'gender', 'national_id', 'passport'];
    @endphp
    <div data-id-scan
         data-testid="id-scan"
         data-ocr-base="{{ asset('vendor/tesseract/'.config('registration.ocr_version')) }}"
         data-messages="{{ json_encode([
             'reading' => __('account.id_scan_reading'),
             'filled' => __('account.id_scan_filled'),
             'differs' => __('account.id_scan_differs'),
             'nothing' => __('account.id_scan_nothing'),
             'already' => __('account.id_scan_already'),
             'failed' => __('account.id_scan_failed'),
             'pdf' => __('account.id_scan_pdf'),
         ]) }}"
         data-labels="{{ json_encode(collect($idScanFields)->mapWithKeys(fn ($f) => [$f => __('account.id_scan_field_'.$f)])->all()) }}"
         class="{{ ($mode ?? 'photo') === 'photo' ? 'mb-4 rounded-lg border border-dashed border-brandMaroon-300 bg-brandMaroon-50/40 p-3' : 'mt-2' }}">
        @if(($mode ?? 'photo') === 'photo')
            <p class="text-sm font-semibold text-gray-800">📷 {{ __('account.id_scan_title') }}</p>
            <p class="mb-2 text-xs text-gray-600">{{ __('account.id_scan_hint') }}</p>
            <label class="btn-secondary inline-flex cursor-pointer items-center gap-2 text-sm">
                <input type="file" accept="image/*" class="sr-only" data-id-scan-source data-testid="id-scan-photo">
                {{ __('account.id_scan_choose') }}
            </label>
        @else
            <p class="text-xs text-gray-500">📷 {{ __('account.id_scan_upload_hint') }}</p>
        @endif
        <p data-id-scan-status data-testid="id-scan-status" role="status" aria-live="polite" class="mt-2 text-sm text-gray-700" hidden></p>
        <button type="button" data-id-scan-use data-testid="id-scan-use" class="mt-1 text-sm font-semibold text-brandMaroon-700 underline" hidden>{{ __('account.id_scan_use') }}</button>
    </div>
@endif
