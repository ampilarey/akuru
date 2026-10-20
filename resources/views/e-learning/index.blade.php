@extends('layouts.app')

@section('title', __('elearning.title'))

@section('content')
{{-- OB3 (STATUS §5tf): in the page's language only — it printed all three side by
     side, and its title sat in a section the layout never prints. --}}
@php
    $locale = app()->getLocale();
    $name = fn ($s) => match ($locale) { 'ar' => $s->name_arabic ?: $s->name, 'dv' => $s->name_dhivehi ?: $s->name, default => $s->name };
    $about = fn ($s) => match ($locale) { 'ar' => $s->description_arabic ?: $s->description, 'dv' => $s->description_dhivehi ?: $s->description, default => $s->description };
    $typeName = function ($type) {
        $key = 'elearning.type_'.\Illuminate\Support\Str::slug((string) $type, '_');

        return __($key) === $key ? $type : __($key);
    };
    $areas = [
        ['route' => 'e-learning.quran', 'title' => __('elearning.quran'), 'line' => __('elearning.quran_line'), 'icon' => 'fa-quran'],
        ['route' => 'e-learning.arabic', 'title' => __('elearning.arabic'), 'line' => __('elearning.arabic_line'), 'icon' => 'fa-language'],
        ['route' => 'e-learning.islamic-studies', 'title' => __('elearning.islamic'), 'line' => __('elearning.islamic_line'), 'icon' => 'fa-mosque'],
    ];
@endphp
<div class="p-4">
    <h1 class="mb-1 text-2xl font-bold text-brandMaroon-900">{{ __('elearning.title') }}</h1>
    <p class="mb-4 text-sm text-gray-600">{{ __('elearning.intro') }}</p>

    <div class="mb-6 grid gap-3 sm:grid-cols-3" data-phone-main>
        @foreach($areas as $area)
            <a href="{{ route($area['route']) }}" class="flex flex-col gap-1 rounded-lg border bg-white p-4 hover:shadow-md" data-testid="el-area">
                <i class="fas {{ $area['icon'] }} text-2xl text-brandMaroon-700" aria-hidden="true"></i>
                <span class="font-semibold text-gray-900">{{ $area['title'] }}</span>
                <span class="text-sm text-gray-600">{{ $area['line'] }}</span>
                <span class="mt-1 text-sm font-semibold text-brandMaroon-700">{{ __('elearning.start') }} <span class="rtl-flip" aria-hidden="true">→</span></span>
            </a>
        @endforeach
    </div>

    <h2 class="mb-2 text-lg font-semibold">{{ __('elearning.subjects') }}</h2>
    @if($subjects->isEmpty())
        <p class="rounded-lg border bg-white p-4 text-sm text-gray-600">{{ __('elearning.none_subjects') }}</p>
    @else
        <div class="overflow-x-auto rounded-lg border bg-white">
            <table class="table-stack min-w-full text-sm">
                <thead class="bg-[#F3EBE0] text-start">
                    <tr>
                        <th class="px-3 py-2 text-start">{{ __('elearning.subject') }}</th>
                        <th class="px-3 py-2 text-start">{{ __('elearning.type') }}</th>
                        <th class="px-3 py-2 text-start">{{ __('elearning.description') }}</th>
                        <th class="px-3 py-2"><span class="sr-only">{{ __('elearning.view') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($subjects as $subject)
                        <tr class="border-t" data-testid="el-subject">
                            <td data-label="{{ __('elearning.subject') }}" class="px-3 py-2 font-semibold">{{ $name($subject) }}</td>
                            <td data-label="{{ __('elearning.type') }}" class="px-3 py-2">{{ $typeName($subject->type) }}</td>
                            <td data-label="{{ __('elearning.description') }}" class="px-3 py-2 text-gray-600">{{ \Illuminate\Support\Str::limit((string) $about($subject), 80) }}</td>
                            <td class="px-3 py-2"><a href="{{ route('e-learning.show', $subject) }}" class="text-brandMaroon-700 underline">{{ __('elearning.view') }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
