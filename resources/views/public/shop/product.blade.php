@extends('public.layouts.public')

{{-- BOOKSHOP_PLAN §4 "Product page" (slice B1b); add to cart since B2. --}}
@section('title', $product['title'] . ' - ' . __('shop.bookshop_title'))
@section('description', \Illuminate\Support\Str::limit($product['summary'] ?? strip_tags((string) $product['description']), 155))
@if(isset($product['gallery'][0]))
    @section('og_image', $product['gallery'][0]['large'])
@endif

@section('content')
<div class="container mx-auto max-w-6xl px-4 py-8">
    <nav class="mb-6 flex flex-wrap items-center gap-2 text-sm text-gray-500">
        <a href="{{ route('public.shop.index') }}" class="hover:text-brandMaroon-600">{{ __('shop.bookshop_title') }}</a>
        @if($product['category_slug'])
            <span>›</span>
            <a href="{{ route('public.shop.category', $product['category_slug']) }}" class="hover:text-brandMaroon-600">{{ $product['category_name'] }}</a>
        @endif
        <span>›</span>
        <span class="max-w-xs truncate text-gray-700">{{ $product['title'] }}</span>
    </nav>

    <div class="grid gap-8 md:grid-cols-2">
        <div data-testid="product-gallery">
            @if(count($product['gallery']) > 0)
                <a href="{{ $product['gallery'][0]['large'] }}" target="_blank" rel="noopener">
                    <img src="{{ $product['gallery'][0]['large'] }}" alt="{{ $product['gallery'][0]['alt'] }}" class="w-full rounded-lg border object-contain bg-white" data-main-image>
                </a>
                @if(count($product['gallery']) > 1)
                    <div class="mt-3 grid grid-cols-4 gap-2">
                        @foreach($product['gallery'] as $index => $image)
                            <a href="{{ $image['large'] }}" target="_blank" rel="noopener" aria-label="{{ __('shop.photo_n', ['n' => $index + 1]) }}">
                                <img src="{{ $image['card'] }}" alt="{{ $image['alt'] }}" class="aspect-square w-full rounded border object-cover" loading="lazy">
                            </a>
                        @endforeach
                    </div>
                @endif
            @else
                <div class="aspect-square w-full rounded-lg border bg-brandBeige-50"></div>
            @endif
        </div>

        <div>
            <h1 class="text-2xl md:text-3xl font-bold text-brandMaroon-900" dir="auto" data-testid="product-title">{{ $product['title'] }}</h1>
            <p class="mt-2 text-sm text-gray-600" data-testid="product-vendor">
                {{ __('shop.sold_by') }}
                <a href="{{ route('public.shop.vendor', $product['vendor']['slug']) }}" class="font-semibold text-brandMaroon-700 hover:underline">{{ $product['vendor']['name'] }}</a>
                · {{ __('shop.at_akuru') }}
            </p>

            <p class="mt-4 text-2xl font-semibold" data-testid="product-price">
                {{ $product['currency'] }} {{ $product['price'] }}
                @if($product['on_sale'])
                    <span class="ms-2 text-lg font-normal text-gray-500 line-through">{{ $product['compare_at_price'] }}</span>
                    <span class="ms-2 rounded bg-brandMaroon-600 px-2 py-0.5 text-xs font-semibold text-white align-middle">{{ __('shop.on_sale') }}</span>
                @endif
            </p>
            <p class="text-xs text-gray-500">{{ __('shop.prices_include_tax') }}</p>
            <div class="mt-2">@include('public.shop._stock', ['stock' => $product['stock']])</div>

            @if($product['summary'])
                <p class="mt-4 text-brandGray-700" dir="auto">{{ $product['summary'] }}</p>
            @endif

            @if(count($product['variants']) > 0)
                <div class="mt-5" data-testid="product-variants">
                    <h2 class="mb-2 text-sm font-semibold text-gray-700">{{ __('shop.options_heading') }}</h2>
                    <ul class="flex flex-wrap gap-2">
                        @foreach($product['variants'] as $variant)
                            <li class="rounded border px-3 py-2 text-sm {{ $variant['in_stock'] ? 'bg-white' : 'bg-gray-50 text-gray-400' }}">
                                {{ $variant['name'] }} · {{ $product['currency'] }} {{ $variant['price'] }}
                                @unless($variant['in_stock'])<span class="block text-xs">{{ __('shop.unavailable') }}</span>@endunless
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @php($buyable = count($product['variants']) > 0
                ? collect($product['variants'])->contains('in_stock', true)
                : $product['stock']['state'] !== 'out_of_stock')
            @if($product['vendor']['holiday'])
                {{-- B3 holiday mode: still visible, not in the cart until the shop is back. --}}
                <div class="mt-6 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900" data-testid="holiday-notice">
                    <p class="font-semibold">{{ __('shop.back_on', ['date' => $product['vendor']['holiday']['back_on']]) }}</p>
                    @if($product['vendor']['holiday']['notice'])<p dir="auto">{{ $product['vendor']['holiday']['notice'] }}</p>@endif
                </div>
            @elseif($buyable)
                <form method="POST" action="{{ route('public.shop.cart.add') }}" class="mt-6 flex flex-wrap items-end gap-3 rounded-lg bg-brandBeige-50 p-4" data-testid="add-to-cart-form">
                    @csrf
                    <input type="hidden" name="product" value="{{ $product['slug'] }}">
                    @if(count($product['variants']) > 0)
                        <label class="text-sm">{{ __('shop.options_heading') }}
                            <select name="variant_id" class="form-input block" required data-testid="variant-select">
                                <option value="">{{ __('shop.choose_option') }}</option>
                                @foreach($product['variants'] as $variant)
                                    <option value="{{ $variant['id'] }}" @disabled(! $variant['in_stock'])>{{ $variant['name'] }} · {{ $product['currency'] }} {{ $variant['price'] }}@unless($variant['in_stock']) — {{ __('shop.unavailable') }}@endunless</option>
                                @endforeach
                            </select>
                        </label>
                    @endif
                    <label class="text-sm">{{ __('shop.quantity') }}
                        <input type="number" name="quantity" value="1" min="1" max="{{ config('bookshop.checkout.max_quantity_per_line', 50) }}" class="form-input block w-20" data-testid="quantity">
                    </label>
                    <button type="submit" class="btn-primary" data-testid="add-to-cart">{{ __('shop.add_to_cart') }}</button>
                    @if($errors->any())
                        <p class="w-full text-sm text-red-700" data-testid="add-error">{{ $errors->first() }}</p>
                    @endif
                </form>
            @endif
            <p class="mt-3"><a href="{{ route('public.shop.vendor', $product['vendor']['slug']) }}" class="text-sm text-brandMaroon-700 hover:underline">{{ __('shop.visit_shop') }} →</a></p>

            @php($facts = array_filter([
                'author' => $product['details']['author'] ?? null,
                'publisher' => $product['details']['publisher'] ?? null,
                'year' => $product['details']['year'] ?? null,
                'pages' => $product['details']['pages'] ?? null,
                'language' => $product['details']['language'] ?? null,
                'age_range' => $product['details']['age_range'] ?? null,
                'grade' => $product['details']['grade'] ?? null,
                'subject' => $product['details']['subject'] ?? null,
                'brand' => $product['brand'],
                'barcode' => $product['barcode'],
                'weight_grams' => $product['weight_grams'],
                'dimensions' => $product['dimensions'],
            ], fn ($value) => $value !== null && $value !== ''))
            @if(count($facts) > 0)
                <div class="mt-6" data-testid="product-details">
                    <h2 class="mb-2 text-sm font-semibold text-gray-700">{{ __('shop.details_heading') }}</h2>
                    <dl class="grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
                        @foreach($facts as $key => $value)
                            <dt class="text-gray-500">{{ __('shop.'.$key) }}</dt>
                            <dd class="text-gray-800" dir="auto">{{ $value }}</dd>
                        @endforeach
                    </dl>
                </div>
            @endif
        </div>
    </div>

    @if($product['description'])
        <div class="prose mt-10 max-w-none" dir="auto" data-testid="product-description">{!! $product['description'] !!}</div>
    @endif

    @if(count($product['related']) > 0)
        <section class="mt-12">
            <h2 class="mb-3 text-xl font-semibold text-brandMaroon-900">{{ __('shop.related') }}</h2>
            <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                @foreach($product['related'] as $card)
                    @include('public.shop._card', ['card' => $card])
                @endforeach
            </div>
        </section>
    @endif
</div>

@include('public.shop._bottom-bar')
@endsection
