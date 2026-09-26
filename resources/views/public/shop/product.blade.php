@extends('public.layouts.public')

{{-- BOOKSHOP_PLAN §4 "Product page" (slice B1b); add to cart since B2. --}}
@section('title', $product['title'] . ' - ' . __('shop.bookshop_title'))
@section('description', \Illuminate\Support\Str::limit($product['summary'] ?? strip_tags((string) $product['description']), 155))
@if(isset($product['gallery'][0]))
    @section('og_image', $product['gallery'][0]['large'])
@endif
{{-- B5 (§6.7): structured data — name, price, availability — so search engines list the product. --}}
@push('head_meta')
    @include('public.partials.json_ld', ['payload' => $product['json_ld'] ?? []])
    <style>
        .shop-badge { background: #7a1f2b; color: #fff; }
        .shop-badge-new { background: #0f4c81; }
        .shop-badge-bestseller { background: #8a5a0b; }
        .shop-badge-custom { background: #1f5f3f; }
    </style>
@endpush

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
                {{-- B11 (§10 "zoom"): each photo opens full-size in a lightbox; without JS the link still opens it in a tab. --}}
                <a href="{{ $product['gallery'][0]['large'] }}" target="_blank" rel="noopener" data-zoom="0" title="{{ __('shop.zoom_photo') }}">
                    <img src="{{ $product['gallery'][0]['large'] }}" alt="{{ $product['gallery'][0]['alt'] }}" class="w-full cursor-zoom-in rounded-lg border object-contain bg-white" data-main-image>
                </a>
                @if(count($product['gallery']) > 1)
                    <div class="mt-3 grid grid-cols-4 gap-2">
                        @foreach($product['gallery'] as $index => $image)
                            <a href="{{ $image['large'] }}" target="_blank" rel="noopener" data-zoom="{{ $index }}" aria-label="{{ __('shop.photo_n', ['n' => $index + 1]) }}">
                                <img src="{{ $image['card'] }}" alt="{{ $image['alt'] }}" class="aspect-square w-full cursor-zoom-in rounded border object-cover" loading="lazy">
                            </a>
                        @endforeach
                    </div>
                @endif
                <dialog id="shop-zoom" class="m-auto w-[min(96vw,1100px)] rounded-lg bg-white p-2 shadow-xl backdrop:bg-black/70" aria-label="{{ __('shop.zoom_photo') }}" data-testid="zoom-dialog">
                    <div class="flex items-center justify-between gap-2 px-1 pb-2 text-sm">
                        <span data-zoom-caption dir="auto"></span>
                        <span class="flex items-center gap-2">
                            <button type="button" class="btn-secondary px-2 py-1" data-zoom-prev aria-label="{{ __('shop.zoom_previous') }}">‹</button>
                            <button type="button" class="btn-secondary px-2 py-1" data-zoom-next aria-label="{{ __('shop.zoom_next') }}">›</button>
                            <button type="button" class="btn-secondary px-2 py-1" data-zoom-close data-testid="zoom-close">{{ __('shop.zoom_close') }}</button>
                        </span>
                    </div>
                    <div class="max-h-[80vh] overflow-auto">
                        <img src="" alt="" class="mx-auto max-h-[80vh] cursor-zoom-in object-contain" data-zoom-image data-testid="zoom-image">
                    </div>
                </dialog>
            @else
                <div class="aspect-square w-full rounded-lg border bg-brandBeige-50"></div>
            @endif
        </div>

        <div>
            @if(session('success'))
                <p class="mb-3 rounded bg-green-50 p-2 text-sm text-green-800" data-testid="flash-success">{{ session('success') }}</p>
            @endif
            @if(count($product['badges']) > 0)
                <p class="mb-2 flex flex-wrap gap-1" data-testid="product-badges">
                    @foreach($product['badges'] as $badge)
                        <span class="shop-badge shop-badge-{{ $badge['kind'] }} rounded px-2 py-0.5 text-xs font-semibold" dir="auto" data-badge="{{ $badge['kind'] }}">{{ $badge['label'] }}</span>
                    @endforeach
                </p>
            @endif
            <h1 class="text-2xl md:text-3xl font-bold text-brandMaroon-900" dir="auto" data-testid="product-title">{{ $product['title'] }}</h1>
            @if($product['rating'])
                <a href="#reviews" class="mt-1 inline-block text-sm text-amber-700" data-testid="product-rating">
                    <span aria-hidden="true">{{ str_repeat('★', (int) round((float) $product['rating']['avg'])) }}{{ str_repeat('☆', 5 - (int) round((float) $product['rating']['avg'])) }}</span>
                    {{ __('shop.rating_summary', ['avg' => $product['rating']['avg'], 'count' => $product['rating']['count']]) }}
                </a>
            @endif
            <p class="mt-2 text-sm text-gray-600" data-testid="product-vendor">
                {{ __('shop.sold_by') }}
                <a href="{{ route('public.shop.vendor', $product['vendor']['slug']) }}" class="font-semibold text-brandMaroon-700 hover:underline">{{ $product['vendor']['name'] }}</a>
                · {{ __('shop.at_akuru') }}
            </p>

            <p class="mt-4 text-2xl font-semibold" data-testid="product-price">
                {{ $product['currency'] }} {{ $product['price'] }}
                @if($product['on_sale'])
                    <span class="ms-2 text-lg font-normal text-gray-500 line-through">{{ $product['compare_at_price'] }}</span>
                @endif
            </p>
            <p class="text-xs text-gray-500">{{ __('shop.prices_include_tax') }}</p>
            @if($product['vendor']['free_delivery_over'])
                <p class="text-sm text-green-800" data-testid="free-delivery-line">{{ __('shop.free_delivery_over_line', ['amount' => $product['currency'].' '.$product['vendor']['free_delivery_over'], 'vendor' => $product['vendor']['name']]) }}</p>
            @endif
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
            {{-- B7 (§4): out of stock — notify me; the wishlist. --}}
            <div class="mt-4 flex flex-wrap items-center gap-3">
                @auth
                    @unless($product['available'])
                        <form method="POST" action="{{ route('public.shop.stock-alert', $product['slug']) }}">
                            @csrf
                            <button type="submit" class="btn-secondary" data-testid="notify-me">{{ $product['has_alert'] ? __('shop.alert_cancel') : __('shop.notify_me') }}</button>
                        </form>
                        @if($product['has_alert'])<span class="text-sm text-gray-600" data-testid="alert-on">{{ __('shop.alert_waiting') }}</span>@endif
                    @endunless
                    <form method="POST" action="{{ route('public.shop.wishlist.toggle', $product['slug']) }}">
                        @csrf
                        <button type="submit" class="btn-secondary" aria-pressed="{{ $product['in_wishlist'] ? 'true' : 'false' }}" data-testid="wishlist-toggle">{{ $product['in_wishlist'] ? '♥ '.__('shop.in_wishlist') : '♡ '.__('shop.add_to_wishlist') }}</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="text-sm text-brandMaroon-700 underline" data-testid="sign-in-to-save">{{ $product['available'] ? __('shop.sign_in_to_save') : __('shop.sign_in_to_be_told') }}</a>
                @endauth
            </div>
            <p class="mt-3"><a href="{{ route('public.shop.vendor', $product['vendor']['slug']) }}" class="text-sm text-brandMaroon-700 hover:underline">{{ __('shop.visit_shop') }} →</a></p>

            @if($product['ebook'])
                {{-- B11 (§4): the printed book's Digital Library edition, one click away. --}}
                <p class="mt-6 rounded-lg border border-brandMaroon-200 bg-brandBeige-50 p-3 text-sm" data-testid="ebook-link">
                    <span class="text-gray-700">{{ __('shop.ebook_available') }}</span>
                    <a href="{{ $product['ebook']['url'] }}" class="ms-1 font-semibold text-brandMaroon-800 underline" dir="auto">{{ __('shop.read_ebook', ['title' => $product['ebook']['title']]) }}</a>
                </p>
            @endif

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

    {{-- B7 (§4 "Trust"): reviews from customers who received it; the shop's replies. --}}
    @php($rv = $product['reviews'])
    <section id="reviews" class="mt-12" data-testid="reviews">
        <h2 class="mb-3 text-xl font-semibold text-brandMaroon-900">{{ __('shop.reviews_heading') }}
            @if($rv['count'] > 0)<span class="text-base font-normal text-gray-600">· {{ __('shop.rating_summary', ['avg' => $rv['avg'], 'count' => $rv['count']]) }}</span>@endif
        </h2>
        @if($rv['count'] > 0)
            <ul class="mb-4 max-w-sm space-y-1 text-sm" aria-label="{{ __('shop.rating_breakdown') }}">
                @foreach($rv['distribution'] as $stars => $n)
                    <li class="flex items-center gap-2"><span class="w-8">{{ $stars }}★</span><span class="h-2 flex-1 rounded bg-gray-200"><span class="block h-2 rounded bg-amber-500" style="width: {{ $rv['count'] > 0 ? round($n * 100 / $rv['count']) : 0 }}%"></span></span><span class="w-6 text-end text-gray-500">{{ $n }}</span></li>
                @endforeach
            </ul>
        @endif
        @if($rv['can_review'])
            <form method="POST" action="{{ route('public.shop.review', $product['slug']) }}" class="mb-6 max-w-xl rounded-lg border bg-brandBeige-50 p-4" data-testid="review-form">
                @csrf
                <p class="mb-2 text-sm font-semibold">{{ __('shop.write_review') }}</p>
                <fieldset class="mb-2 flex gap-3 text-sm">
                    <legend class="sr-only">{{ __('shop.your_rating') }}</legend>
                    @foreach([5, 4, 3, 2, 1] as $stars)
                        <label class="flex items-center gap-1"><input type="radio" name="rating" value="{{ $stars }}" required @checked(old('rating') == $stars) data-testid="rating-{{ $stars }}"> {{ $stars }}★</label>
                    @endforeach
                </fieldset>
                <textarea name="body" rows="3" maxlength="{{ config('bookshop.reviews.max_body', 2000) }}" class="form-input w-full" placeholder="{{ __('shop.review_placeholder') }}" data-testid="review-body">{{ old('body') }}</textarea>
                @error('rating')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
                <button type="submit" class="btn-primary mt-2" data-testid="submit-review">{{ __('shop.submit_review') }}</button>
            </form>
        @elseif($rv['pending_mine'])
            <p class="mb-4 text-sm text-gray-600" data-testid="review-pending">{{ __('shop.review_pending_flash') }}</p>
        @endif
        @if($rv['count'] === 0)
            <p class="text-sm text-gray-500" data-testid="no-reviews">{{ __('shop.no_reviews') }}</p>
        @endif
        <ul class="space-y-4">
            @foreach($rv['reviews'] as $review)
                <li class="border-b pb-3" data-testid="review-{{ $review['id'] }}">
                    <p class="text-sm"><span class="text-amber-700" aria-label="{{ __('shop.rated_out_of', ['avg' => $review['rating']]) }}">{{ str_repeat('★', $review['rating']) }}{{ str_repeat('☆', 5 - $review['rating']) }}</span>
                        <span class="font-semibold">{{ $review['name'] }}</span> · <span class="text-gray-500">{{ $review['date'] }}</span> · <span class="text-xs text-green-700">{{ __('shop.verified_purchase') }}</span></p>
                    @if($review['body'])<p class="mt-1 whitespace-pre-line text-sm" dir="auto">{{ $review['body'] }}</p>@endif
                    @if($review['reply'])
                        <div class="mt-2 ms-4 rounded bg-gray-50 p-2 text-sm" data-testid="review-reply">
                            <p class="text-xs font-semibold text-gray-600">{{ __('shop.reply_from', ['vendor' => $product['vendor']['name']]) }}</p>
                            <p class="whitespace-pre-line" dir="auto">{{ $review['reply'] }}</p>
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    </section>

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

@if(count($product['recently_viewed']) > 0)
    <section class="container mx-auto max-w-6xl px-4 pb-10" data-testid="recently-viewed">
        <h2 class="mb-3 text-xl font-semibold text-brandMaroon-900">{{ __('shop.recently_viewed') }}</h2>
        <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
            @foreach(array_slice($product['recently_viewed'], 0, 4) as $card)
                @include('public.shop._card', ['card' => $card])
            @endforeach
        </div>
    </section>
@endif

@include('public.shop._bottom-bar')
@endsection

@push('scripts')
<script>
(() => {
    // B11 (§10 "zoom"): the lightbox. Photos come from the page's own gallery links; nothing is fetched.
    const dialog = document.getElementById('shop-zoom');
    if (!dialog || typeof dialog.showModal !== 'function') return;
    const links = Array.from(document.querySelectorAll('[data-testid="product-gallery"] a[data-zoom]'));
    const photos = [];
    for (const link of links) {
        const index = Number(link.dataset.zoom);
        const img = link.querySelector('img');
        photos[index] = { src: link.getAttribute('href'), alt: img ? img.getAttribute('alt') : '' };
    }
    const image = dialog.querySelector('[data-zoom-image]');
    const caption = dialog.querySelector('[data-zoom-caption]');
    let current = 0;
    const show = (index) => {
        current = (index + photos.length) % photos.length;
        image.src = photos[current].src;
        image.alt = photos[current].alt;
        image.classList.remove('zoomed');
        caption.textContent = photos.length > 1 ? `${photos[current].alt} (${current + 1}/${photos.length})` : photos[current].alt;
        if (!dialog.open) dialog.showModal();
    };
    for (const link of links) {
        link.addEventListener('click', (e) => { e.preventDefault(); show(Number(link.dataset.zoom)); });
    }
    dialog.querySelector('[data-zoom-prev]').addEventListener('click', () => show(current - 1));
    dialog.querySelector('[data-zoom-next]').addEventListener('click', () => show(current + 1));
    dialog.querySelector('[data-zoom-close]').addEventListener('click', () => dialog.close());
    image.addEventListener('click', () => image.classList.toggle('zoomed'));
    dialog.addEventListener('click', (e) => { if (e.target === dialog) dialog.close(); });
    dialog.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowLeft') show(current - 1);
        if (e.key === 'ArrowRight') show(current + 1);
    });
})();
</script>
<style>
#shop-zoom img.zoomed { max-height: none; max-width: none; width: auto; cursor: zoom-out; }
</style>
@endpush
