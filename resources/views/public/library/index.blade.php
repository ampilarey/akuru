@extends('public.layouts.public')

@section('title', __('public.Akuru Digital Library') . ' - ' . config('app.name'))
@section('description', __('public.Books, articles and research from Akuru Institute'))

@section('content')
<section class="bg-gradient-to-br from-brandMaroon-50 to-brandBeige-100 py-12">
    <div class="container mx-auto px-4">
        <h1 class="text-4xl font-bold text-brandMaroon-900 mb-3">{{ __('public.Akuru Digital Library') }}</h1>
        <p class="text-xl text-brandGray-700">{{ __('public.Books, articles and research from Akuru Institute') }}</p>
        <p class="mt-3 text-sm text-brandGray-600">
            <a href="{{ route('public.gift-cards.index') }}" class="text-brandMaroon-700 hover:underline">{{ __('public.Gift cards') }}</a>
            @auth
                · <a href="{{ route('public.library.my') }}" class="text-brandMaroon-700 hover:underline">{{ __('public.My Library') }}</a>
                · <a href="{{ route('public.wallet') }}" class="text-brandMaroon-700 hover:underline">{{ __('public.My Wallet') }}</a>
            @endauth
        </p>
    </div>
</section>

<section class="bg-white border-b py-4">
    <div class="container mx-auto px-4">
        <form method="GET" action="{{ route('public.library.index') }}" class="flex flex-wrap gap-3 items-end" data-testid="shelf-filters">
            @if($filters['author'] ?? null)
                <input type="hidden" name="author" value="{{ $filters['author'] }}">
            @endif
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
            {{-- §8.2 / §8.3 discovery: free or paid, language, price, and a sort. --}}
            <div>
                <label class="block text-xs text-gray-500 mb-1">{{ __('public.Access') }}</label>
                <select name="access" class="form-input">
                    <option value="">{{ __('public.Free and paid') }}</option>
                    <option value="free" @selected(($filters['access'] ?? '') === 'free')>{{ __('public.Free') }}</option>
                    <option value="paid" @selected(($filters['access'] ?? '') === 'paid')>{{ __('public.Paid') }}</option>
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">{{ __('public.Language') }}</label>
                <select name="language" class="form-input">
                    <option value="">{{ __('public.All languages') }}</option>
                    @foreach($languages as $code => $label)
                        <option value="{{ $code }}" @selected(($filters['language'] ?? '') === $code)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">{{ __('public.Price (MVR)') }}</label>
                <div class="flex gap-1">
                    <input type="number" name="price_min" min="0" step="1" value="{{ $filters['price_min'] ?? '' }}" class="form-input w-24" placeholder="{{ __('public.min') }}" aria-label="{{ __('public.Min price') }}">
                    <input type="number" name="price_max" min="0" step="1" value="{{ $filters['price_max'] ?? '' }}" class="form-input w-24" placeholder="{{ __('public.max') }}" aria-label="{{ __('public.Max price') }}">
                </div>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">{{ __('public.Sort') }}</label>
                <select name="sort" class="form-input">
                    @foreach($sorts as $sort)
                        <option value="{{ $sort }}" @selected(($filters['sort'] ?? 'newest') === $sort)>{{ __('public.sort_'.$sort) }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn-primary">{{ __('public.Filter') }}</button>
            <a href="{{ route('public.library.export', request()->query()) }}" class="btn-secondary">{{ __('public.Export CSV') }}</a>
        </form>
    </div>
</section>

@if(count($continue_reading) > 0)
    <section class="bg-brandBeige-50 py-6" data-testid="continue-reading">
        <div class="container mx-auto px-4">
            <h2 class="text-lg font-semibold text-brandMaroon-900 mb-3">{{ __('public.Continue reading') }}</h2>
            <div class="flex flex-wrap gap-3">
                @foreach($continue_reading as $row)
                    <a href="{{ route('public.library.read', ['slug' => $row['slug'], 'page' => $row['current_page']]) }}" class="rounded-lg border bg-white px-4 py-3 hover:shadow-sm">
                        <span class="font-medium">{{ $row['title'] }}</span>
                        <span class="text-sm text-gray-500"> — {{ __('public.Page') }} {{ $row['current_page'] }} · {{ $row['progress_percent'] }}%</span>
                    </a>
                @endforeach
            </div>
        </div>
    </section>
@endif

@if(count($featured) > 0)
    <section class="py-8" data-testid="featured">
        <div class="container mx-auto px-4">
            <h2 class="text-lg font-semibold text-brandMaroon-900 mb-3">{{ __('public.Featured') }}</h2>
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                @foreach($featured as $item)
                    <a href="{{ route('public.library.show', $item['slug']) }}" class="flex gap-3 rounded-lg border bg-white p-3 hover:shadow-md transition">
                        @if($item['cover_url'])
                            <img src="{{ $item['cover_url'] }}" alt="" class="h-24 w-16 shrink-0 rounded object-cover" loading="lazy">
                        @endif
                        <div class="min-w-0">
                            <h3 class="font-semibold text-brandMaroon-900 leading-tight">{{ $item['title'] }}</h3>
                            <p class="text-xs text-gray-500 mt-1">{{ __('public.'.$item['content_type']) }}{{ $item['writer'] ? ' · '.$item['writer']['display_name'] : '' }}</p>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    </section>
@endif

<section class="py-10">
    <div class="container mx-auto px-4">
        @if($filters['author'] ?? null)
            <p class="mb-4 text-sm text-gray-600">
                {{ __('public.Showing works by one author.') }}
                <a href="{{ route('public.library.author', $filters['author']) }}" class="text-brandMaroon-700 hover:underline">{{ __('public.Author page') }}</a>
                · <a href="{{ route('public.library.index', array_diff_key($filters, ['author' => 1])) }}" class="hover:underline">{{ __('public.Show everyone') }}</a>
            </p>
        @endif
        @if(count($items) === 0)
            <p class="text-gray-500">{{ __('public.Nothing in the library matches your search yet.') }}</p>
        @endif
        <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3" data-testid="shelf">
            @foreach($items as $item)
                <a href="{{ route('public.library.show', $item['slug']) }}" class="block rounded-lg border bg-white p-5 hover:shadow-md transition" data-item="{{ $item['slug'] }}">
                    @if($item['cover_url'])
                        <img src="{{ $item['cover_url'] }}" alt="" class="mb-3 aspect-[3/4] w-full rounded object-cover" data-cover="{{ $item['slug'] }}" loading="lazy">
                    @endif
                    <div class="flex items-center gap-2 text-xs text-gray-500 mb-2">
                        <span class="rounded bg-brandBeige-100 px-2 py-0.5">{{ __('public.'.$item['content_type']) }}</span>
                        @if($item['access_type'] === 'paid')
                            <span class="rounded bg-gray-100 px-2 py-0.5">{{ $item['currency'] }} {{ $item['price'] }}</span>
                        @elseif($item['access_type'] !== 'free_public')
                            <span class="rounded bg-gray-100 px-2 py-0.5">{{ $item['access_type'] === 'free_login' ? __('public.Login to read') : __('public.Coming soon') }}</span>
                        @else
                            <span class="rounded bg-green-50 px-2 py-0.5 text-green-800">{{ __('public.Free') }}</span>
                        @endif
                        @if($item['featured'])
                            <span class="rounded bg-amber-50 px-2 py-0.5 text-amber-800">★ {{ __('public.Featured') }}</span>
                        @endif
                    </div>
                    <h2 class="text-lg font-semibold text-brandMaroon-900 mb-1">{{ $item['title'] }}</h2>
                    @if($item['subtitle'])
                        <p class="text-sm text-gray-600 mb-2">{{ $item['subtitle'] }}</p>
                    @endif
                    @if($item['writer'])
                        <p class="text-sm text-gray-500 mb-2">{{ $item['writer']['display_name'] }}</p>
                    @elseif(count($item['authors']))
                        <p class="text-sm text-gray-500 mb-2">{{ implode(', ', $item['authors']) }}</p>
                    @endif
                    @if($item['abstract'])
                        <p class="text-sm text-gray-600">{{ Str::limit($item['abstract'], 140) }}</p>
                    @endif
                </a>
            @endforeach
        </div>

        {{-- The plan's required pages (LIBRARY_PLAN "Required pages"), where a reader looks for them. --}}
        <p class="mt-10 text-xs text-gray-500" data-testid="library-policies">
            <a href="{{ route('public.page.show', 'reader-terms') }}" class="hover:underline">{{ __('public.Reader Terms') }}</a>
            · <a href="{{ route('public.page.show', 'copyright-policy') }}" class="hover:underline">{{ __('public.Copyright Policy') }}</a>
            · <a href="{{ route('public.page.show', 'gift-card-terms') }}" class="hover:underline">{{ __('public.Gift Card Terms') }}</a>
            · <a href="{{ route('public.page.show', 'wallet-terms') }}" class="hover:underline">{{ __('public.Wallet Terms') }}</a>
            · <a href="{{ route('public.refunds') }}" class="hover:underline">{{ __('public.Refund Policy') }}</a>
            · <a href="{{ route('public.page.show', 'publishing-terms') }}" class="hover:underline">{{ __('public.Publishing Terms') }}</a>
        </p>
    </div>
</section>
@endsection
