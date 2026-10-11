{{-- The layout says the waiting list's thanks and its refusals, once: this form sits twice on the course page (slice WB1). --}}
@if(($course->conversion['seats_tone'] ?? null) === 'full')
    <form method="POST" action="{{ route('public.courses.waitlist', $course) }}" class="mt-3 space-y-2 text-sm">
        @csrf
        <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off" aria-hidden="true">
        <p class="font-medium text-red-700">{{ __('public.Full — join waiting list') }}</p>
        <input class="form-input w-full" type="text" name="name" required maxlength="255" placeholder="{{ __('public.Name') }}" aria-label="{{ __('public.Name') }}" value="{{ old('name') }}">
        <input class="form-input w-full" type="text" name="phone" required maxlength="30" placeholder="{{ __('public.Mobile') }}" aria-label="{{ __('public.Mobile') }}" value="{{ old('phone') }}">
        <input class="form-input w-full" type="email" name="email" maxlength="255" placeholder="{{ __('public.Email (optional)') }}" aria-label="{{ __('public.Email (optional)') }}" value="{{ old('email') }}">
        <button type="submit" class="btn-primary w-full">{{ __('public.Join waiting list') }}</button>
    </form>
@endif
