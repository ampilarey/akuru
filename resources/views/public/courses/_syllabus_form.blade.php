@php
    $cta = $cta ?? ['whatsapp_url' => null, 'syllabus' => null];
@endphp
<div id="course-syllabus" class="space-y-2 text-sm">
    @if(session('syllabus_url'))
        <p class="text-sm text-green-700">{{ session('success') ?? 'Syllabus is ready.' }}</p>
        <a href="{{ session('syllabus_url') }}" target="_blank" rel="noopener" class="btn-primary w-full text-center block">
            Download syllabus
        </a>
    @elseif($cta['syllabus'] ?? null)
        <form method="POST" action="{{ route('public.courses.syllabus', $course) }}" class="space-y-2">
            @csrf
            <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off">
            <p class="font-medium text-gray-900">Get full syllabus</p>
            <p class="text-xs text-gray-500">Leave your name and mobile. We’ll send the PDF link.</p>
            <input class="form-input w-full" type="text" name="name" required maxlength="255" placeholder="Name" value="{{ old('name') }}">
            <input class="form-input w-full" type="text" name="mobile" required maxlength="30" placeholder="Mobile" value="{{ old('mobile') }}">
            <input class="form-input w-full" type="email" name="email" maxlength="255" placeholder="Email (optional)" value="{{ old('email') }}">
            @error('course')
                <p class="text-red-600">{{ $message }}</p>
            @enderror
            <button type="submit" class="btn-secondary w-full">Get full syllabus</button>
        </form>
    @endif
</div>
