@extends('layouts.app')

@section('title', __('elearning.quran'))

@section('content')
{{-- OB3 (STATUS §5tf): one area's subjects, in the page's language only. --}}
@php
    $locale = app()->getLocale();
    $name = fn ($s) => match ($locale) { 'ar' => $s->name_arabic ?: $s->name, 'dv' => $s->name_dhivehi ?: $s->name, default => $s->name };
    $about = fn ($s) => match ($locale) { 'ar' => $s->description_arabic ?: $s->description, 'dv' => $s->description_dhivehi ?: $s->description, default => $s->description };
@endphp
<div class="p-4">
    <a href="{{ route('e-learning.index') }}" class="mb-2 inline-block text-sm text-brandMaroon-700 underline"><span class="rtl-flip" aria-hidden="true">←</span> {{ __('elearning.back') }}</a>
    <h1 class="mb-4 text-2xl font-bold text-brandMaroon-900">{{ __('elearning.quran') }}</h1>
    @if($quranSubjects->isEmpty())
        <p class="rounded-lg border bg-white p-4 text-sm text-gray-600" data-phone-main>{{ __('elearning.none_quran') }}</p>
    @else
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3" data-phone-main>
            @foreach($quranSubjects as $subject)
                <div class="flex flex-col gap-2 rounded-lg border bg-white p-4" data-testid="el-subject">
                    <i class="fas fa-quran text-2xl text-brandMaroon-700" aria-hidden="true"></i>
                    <h2 class="font-semibold text-gray-900">{{ $name($subject) }}</h2>
                    <p class="text-sm text-gray-600">{{ \Illuminate\Support\Str::limit((string) $about($subject), 100) }}</p>
                    <div class="mt-auto flex items-center justify-between gap-2">
                        <span class="rounded bg-gray-100 px-2 py-0.5 text-xs" dir="ltr">{{ $subject->code }}</span>
                        <a href="{{ route('e-learning.show', $subject) }}" class="text-sm text-brandMaroon-700 underline">{{ __('elearning.view') }}</a>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
