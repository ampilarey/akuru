{{-- BOOKSHOP_PLAN §6.1 (slice B4): the vendor's own head of the page inside the Akuru frame —
     banner, logo, name and tagline in the visitor's language, the office's badges, always the
     "at Akuru Bookstore" line (decision 11), then the story and the contact block. --}}
@php($sf = $vendor['storefront'])
<header class="sf-band" data-testid="storefront-head">
    @if($sf['banner'])
        <div class="sf-banner w-full overflow-hidden"><img src="{{ $sf['banner'] }}" alt="" class="h-full w-full object-cover" data-testid="storefront-banner"></div>
    @endif
    <div class="container mx-auto flex flex-wrap items-center gap-5 px-4 py-8">
        @if($sf['logo'])
            <img src="{{ $sf['logo'] }}" alt="{{ $sf['name'] }}" class="sf-logo-light h-24 w-24 rounded-lg object-cover bg-white" data-testid="storefront-logo">
            @if($sf['logo_dark'])<img src="{{ $sf['logo_dark'] }}" alt="{{ $sf['name'] }}" class="sf-logo-dark h-24 w-24 rounded-lg object-cover">@endif
        @endif
        <div class="min-w-0 flex-1">
            <nav class="mb-1 text-sm opacity-80"><a href="{{ route('public.shop.index') }}" class="hover:underline">{{ __('shop.bookshop_title') }}</a> ›</nav>
            <h1 class="text-3xl md:text-4xl font-bold" dir="auto" data-testid="shop-heading">{{ $sf['name'] }}</h1>
            @if($sf['tagline'])<p class="sf-tagline mt-1 text-lg" dir="auto" data-testid="storefront-tagline">{{ $sf['tagline'] }}</p>@endif
            <p class="mt-1 flex flex-wrap items-center gap-2 text-sm">
                <span data-testid="at-akuru">{{ __('shop.at_akuru') }}</span>
                @foreach($sf['badges'] as $badge)
                    <span class="sf-badge px-2 py-0.5 text-xs font-semibold" data-testid="badge-{{ $badge }}">{{ __('shop.badge_'.$badge) }}</span>
                @endforeach
            </p>
            @if($vendor['holiday'] ?? null)
                <div class="mt-3 inline-block rounded-lg border border-amber-300 bg-amber-50 px-4 py-2 text-sm text-amber-900" data-testid="holiday-notice">
                    <p class="font-semibold">{{ __('shop.back_on', ['date' => $vendor['holiday']['back_on']]) }}</p>
                    @if($vendor['holiday']['notice'])<p dir="auto">{{ $vendor['holiday']['notice'] }}</p>@endif
                </div>
            @endif
        </div>
    </div>
</header>

@php($contact = array_filter($sf['contact']))
@if($sf['story'] || $contact !== [] || $sf['hours'] || $sf['socials'] !== [])
    <section class="container mx-auto grid gap-6 px-4 py-8 md:grid-cols-3" data-testid="storefront-about">
        @if($sf['story'])
            <div class="prose max-w-none md:col-span-2" dir="auto" data-testid="storefront-story">{!! $sf['story'] !!}</div>
        @endif
        @if($contact !== [] || $sf['hours'] || $sf['socials'] !== [])
            <aside class="sf-card p-4 text-sm {{ $sf['story'] ? '' : 'md:col-span-3' }}" data-testid="storefront-contact">
                @if($contact !== [])
                    <h2 class="mb-2 text-base font-semibold">{{ __('shop.contact_heading') }}</h2>
                    <ul class="space-y-1">
                        @if($contact['phone'] ?? null)<li><a class="sf-link" href="tel:{{ preg_replace('/\s+/', '', $contact['phone']) }}">{{ $contact['phone'] }}</a></li>@endif
                        @if($contact['viber'] ?? null)<li>Viber: {{ $contact['viber'] }}</li>@endif
                        @if($contact['email'] ?? null)<li><a class="sf-link" href="mailto:{{ $contact['email'] }}">{{ $contact['email'] }}</a></li>@endif
                        @if($contact['address'] ?? null)<li dir="auto">{{ $contact['address'] }}@if($contact['map_url'] ?? null) · <a class="sf-link" href="{{ $contact['map_url'] }}" target="_blank" rel="noopener">{{ __('shop.map') }}</a>@endif</li>@endif
                    </ul>
                @endif
                @if($sf['hours'])
                    <h2 class="mb-1 mt-3 text-base font-semibold">{{ __('shop.opening_hours') }}</h2>
                    <p class="whitespace-pre-line" dir="auto">{{ $sf['hours'] }}</p>
                @endif
                @if($sf['socials'] !== [])
                    <ul class="mt-3 flex flex-wrap gap-3" data-testid="storefront-socials">
                        @foreach($sf['socials'] as $network => $url)
                            <li><a class="sf-link underline" href="{{ $url }}" target="_blank" rel="noopener">{{ __('shop.social_'.$network) }}</a></li>
                        @endforeach
                    </ul>
                @endif
            </aside>
        @endif
    </section>
@endif
