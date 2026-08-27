@extends('public.layouts.public')

@section('title', __('public.Library') . ' - ' . config('app.name'))
@section('description', __('public.Books, articles and research from Akuru Institute'))

@section('content')
<section class="bg-gradient-to-br from-brandMaroon-50 to-brandBeige-100 py-12">
    <div class="container mx-auto px-4">
        <h1 class="text-4xl font-bold text-brandMaroon-900 mb-3">{{ __('public.Library') }}</h1>
        <p class="text-xl text-brandGray-700">{{ __('public.Books, articles and research from Akuru Institute') }}</p>
    </div>
</section>

<section class="bg-white border-b py-4">
    <div class="container mx-auto px-4">
        <form method="GET" action="{{ route('public.library.index') }}" class="flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-48">
                <label class="block text-xs text-gray-500 mb-1">{{ __('public.Search') }}</label>
                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="form-input w-full" placeholder="{{ __('public.Search the library') }}">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">{{ __('public.Type') }}</label>
                <select name="content_type" class="form-input">
                    <option value="">{{ __('public.All types') }}</option>
                    @foreach(['book', 'article', 'research', 'course_material'] as $type)
                        <option value="{{ $type }}" @selected(($filters['content_type'] ?? '') === $type)>{{ __('public.'.$type) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">{{ __('public.Category') }}</label>
                <select name="category" class="form-input">
                    <option value="">{{ __('public.All categories') }}</option>
                    @foreach($categories as $category)
                        <option value="{{ $category['slug'] }}" @selected(($filters['category'] ?? '') === $category['slug'])>{{ $category['name'] }} ({{ $category['published_count'] }})</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn-primary">{{ __('public.Filter') }}</button>
            <a href="{{ route('public.library.export', request()->query()) }}" class="btn-secondary">{{ __('public.Export CSV') }}</a>
        </form>
    </div>
</section>

<section class="py-10">
    <div class="container mx-auto px-4">
        @if(count($items) === 0)
            <p class="text-gray-500">{{ __('public.Nothing in the library matches your search yet.') }}</p>
        @endif
        <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            @foreach($items as $item)
                <a href="{{ route('public.library.show', $item['slug']) }}" class="block rounded-lg border bg-white p-5 hover:shadow-md transition">
                    <div class="flex items-center gap-2 text-xs text-gray-500 mb-2">
                        <span class="rounded bg-brandBeige-100 px-2 py-0.5">{{ __('public.'.$item['content_type']) }}</span>
                        @if($item['access_type'] !== 'free_public')
                            <span class="rounded bg-gray-100 px-2 py-0.5">{{ $item['access_type'] === 'free_login' ? __('public.Login to read') : __('public.Coming soon') }}</span>
                        @endif
                    </div>
                    <h2 class="text-lg font-semibold text-brandMaroon-900 mb-1">{{ $item['title'] }}</h2>
                    @if($item['subtitle'])
                        <p class="text-sm text-gray-600 mb-2">{{ $item['subtitle'] }}</p>
                    @endif
                    @if(count($item['authors']))
                        <p class="text-sm text-gray-500 mb-2">{{ implode(', ', $item['authors']) }}</p>
                    @endif
                    @if($item['abstract'])
                        <p class="text-sm text-gray-600">{{ Str::limit($item['abstract'], 140) }}</p>
                    @endif
                </a>
            @endforeach
        </div>
    </div>
</section>
@endsection
