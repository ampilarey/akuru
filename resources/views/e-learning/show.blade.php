@extends('layouts.app')

@section('title', $subject->name)

@section('content')
{{-- OB3 (STATUS §5tf): a subject in the page's language — it printed its name and
     description in all three languages at once, and its labels likewise. --}}
@php
    $locale = app()->getLocale();
    $name = match ($locale) { 'ar' => $subject->name_arabic ?: $subject->name, 'dv' => $subject->name_dhivehi ?: $subject->name, default => $subject->name };
    $about = match ($locale) { 'ar' => $subject->description_arabic ?: $subject->description, 'dv' => $subject->description_dhivehi ?: $subject->description, default => $subject->description };
    $typeKey = 'elearning.type_'.\Illuminate\Support\Str::slug((string) $subject->type, '_');
    $typeName = __($typeKey) === $typeKey ? $subject->type : __($typeKey);
@endphp
<div class="p-4">
    <a href="{{ route('e-learning.index') }}" class="mb-2 inline-block text-sm text-brandMaroon-700 underline"><span class="rtl-flip" aria-hidden="true">←</span> {{ __('elearning.back') }}</a>
    <div class="grid gap-4 md:grid-cols-3">
        <section class="rounded-lg border bg-white p-4 md:col-span-2" data-phone-main>
            <h1 class="mb-3 text-2xl font-bold text-brandMaroon-900">{{ $name }}</h1>
            @if($about)
                <p class="mb-4 text-gray-700">{{ $about }}</p>
            @endif
            <dl class="grid gap-2 text-sm sm:grid-cols-2">
                <div><dt class="font-semibold">{{ __('elearning.subject_type') }}</dt><dd>{{ $typeName }}</dd></div>
                <div><dt class="font-semibold">{{ __('elearning.credits') }}</dt><dd>{{ $subject->credits ?? '—' }}</dd></div>
            </dl>
            @if($subject->is_quran_subject)
                <p class="mt-4 rounded bg-blue-50 p-3 text-sm text-blue-900"><i class="fas fa-quran me-2" aria-hidden="true"></i>{{ __('elearning.quran_subject') }}</p>
            @endif
        </section>
        <aside class="rounded-lg border bg-white p-4 text-sm">
            <h2 class="mb-2 font-semibold">{{ __('elearning.information') }}</h2>
            <dl class="grid gap-2">
                <div><dt class="font-semibold">{{ __('elearning.code') }}</dt><dd dir="ltr" class="text-start">{{ $subject->code }}</dd></div>
                <div><dt class="font-semibold">{{ __('elearning.status') }}</dt><dd>{{ $subject->is_active ? __('elearning.active') : __('elearning.inactive') }}</dd></div>
            </dl>
            @if($subject->is_quran_subject)
                <h3 class="mb-1 mt-3 font-semibold">{{ __('elearning.quran_features') }}</h3>
                <ul class="grid gap-1">
                    <li><i class="fas fa-check me-2 text-green-700" aria-hidden="true"></i>{{ __('elearning.memorisation') }}</li>
                    <li><i class="fas fa-check me-2 text-green-700" aria-hidden="true"></i>{{ __('elearning.recitation') }}</li>
                </ul>
            @endif
        </aside>
    </div>
</div>
@endsection
