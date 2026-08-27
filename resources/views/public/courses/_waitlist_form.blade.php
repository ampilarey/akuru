@if(session('success'))
    <p class="text-sm text-green-700">{{ session('success') }}</p>
@endif
@if(($course->conversion['seats_tone'] ?? null) === 'full')
    <form method="POST" action="{{ route('public.courses.waitlist', $course) }}" class="mt-3 space-y-2 text-sm">
        @csrf
        <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off">
        <p class="font-medium text-red-700">Full — join waiting list</p>
        <input class="form-input w-full" type="text" name="name" required maxlength="255" placeholder="Name" value="{{ old('name') }}">
        <input class="form-input w-full" type="text" name="phone" required maxlength="30" placeholder="Mobile" value="{{ old('phone') }}">
        <input class="form-input w-full" type="email" name="email" maxlength="255" placeholder="Email (optional)" value="{{ old('email') }}">
        @error('course')
            <p class="text-red-600">{{ $message }}</p>
        @enderror
        <button type="submit" class="btn-primary w-full">Join waiting list</button>
    </form>
@endif
