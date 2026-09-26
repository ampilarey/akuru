{{-- BOOKSHOP_PLAN §6.2 / §10 (slice B4): the vendor's theme as CSS custom properties on the
     storefront root. Every value is a normalised hex colour or a word from a fixed list
     (Theme::cssVariables), never vendor-written CSS; the fonts come from the approved list. --}}
@php($sf = $vendor['storefront'])
@push('head_meta')
    @if($sf['fonts_url'])
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link rel="stylesheet" href="{{ $sf['fonts_url'] }}">
    @endif
    <style>
        .storefront {
            @foreach($sf['css'] as $name => $value){{ $name }}: {!! $value !!};
            @endforeach
            background: var(--sf-page);
            color: var(--sf-text);
            font-family: var(--sf-font-body);
            font-size: calc(1rem * var(--sf-scale));
        }
        @if($sf['dark_css'] !== [])
        @media (prefers-color-scheme: dark) {
            .storefront {
                @foreach($sf['dark_css'] as $name => $value){{ $name }}: {!! $value !!};
                @endforeach
            }
            .storefront .sf-logo-light { display: none; }
            .storefront .sf-logo-dark { display: block; }
        }
        @endif
        .storefront .sf-logo-dark { display: none; }
        .storefront h1, .storefront h2, .storefront h3 { font-family: var(--sf-font-heading); color: var(--sf-text); }
        .storefront .sf-band { background: var(--sf-primary); color: var(--sf-on-primary); }
        .storefront .sf-band h1, .storefront .sf-band p, .storefront .sf-band a { color: var(--sf-on-primary); }
        .storefront .sf-tagline { font-family: var(--sf-font-accent); }
        .storefront .sf-banner { height: var(--sf-banner-h); }
        .storefront .sf-card, .storefront [data-product] { background: var(--sf-card); border: var(--sf-card-border); box-shadow: var(--sf-card-shadow); border-radius: var(--sf-radius); }
        .storefront [data-product] h3, .storefront [data-product] .font-semibold { color: var(--sf-text); }
        .storefront [data-product] .aspect-square { aspect-ratio: var(--sf-image-ratio); }
        .storefront .sf-badge { background: var(--sf-secondary); color: var(--sf-text); border-radius: var(--sf-radius); }
        .storefront .sf-link, .storefront .prose a { color: var(--sf-accent); }
        .storefront .btn-primary { background: var(--sf-accent); color: var(--sf-on-accent); border: 1px solid var(--sf-accent); border-radius: var(--sf-radius); }
        .storefront.sf-outlined .btn-primary { background: transparent; color: var(--sf-accent); }
        .storefront .btn-secondary, .storefront .form-input { border-radius: var(--sf-radius); }
        .storefront .prose { color: var(--sf-text); }
        /* B5 (§6.3): sections. The mobile order is a CSS `order`, never a second copy of the markup. */
        .storefront .sf-sections { display: flex; flex-direction: column; }
        @media (max-width: 767px) { .storefront .sf-section { order: var(--sf-mobile-order, 0); } }
        .storefront .sf-hero { background: var(--sf-secondary); min-height: 14rem; }
        .storefront .sf-hero .sf-slide { position: absolute; inset: 0; height: 100%; width: 100%; object-fit: cover; opacity: 0; animation: sf-slide calc(var(--n) * 6s) infinite; animation-delay: calc(var(--i) * 6s); }
        .storefront .sf-hero .sf-slide:first-child { opacity: 1; }
        .storefront .sf-hero.sf-has-image .sf-hero-copy { color: #fff; text-shadow: 0 1px 6px rgba(0, 0, 0, .6); }
        .storefront .sf-hero.sf-has-image .sf-hero-copy h2 { color: #fff; }
        .storefront .sf-hero.sf-has-image::after { content: ''; position: absolute; inset: 0; background: linear-gradient(to top, rgba(0, 0, 0, .55), rgba(0, 0, 0, .15)); }
        .storefront .sf-hero.sf-has-image .sf-hero-copy { z-index: 1; }
        @keyframes sf-slide { 0% { opacity: 0; } 4% { opacity: 1; } 30% { opacity: 1; } 36% { opacity: 0; } 100% { opacity: 0; } }
        @media (prefers-reduced-motion: reduce) { .storefront .sf-hero .sf-slide { animation: none; } }
        .storefront .sf-announce { background: var(--sf-accent); color: var(--sf-on-accent); }
        .storefront .sf-announce a { color: var(--sf-on-accent); }
        .storefront .sf-nav { background: var(--sf-card); border-color: var(--sf-secondary); }
        .storefront .sf-nav a { color: var(--sf-text); border-bottom: 2px solid transparent; white-space: nowrap; }
        .storefront .sf-nav a:hover, .storefront .sf-nav .sf-nav-active { border-color: var(--sf-accent); }
        .storefront .sf-tile { background: var(--sf-secondary); color: var(--sf-text); border-radius: var(--sf-radius); }
        .storefront .sf-carousel { display: flex; gap: 1rem; overflow-x: auto; scroll-snap-type: x mandatory; padding-bottom: .5rem; }
        .storefront .sf-carousel > * { flex: 0 0 14rem; scroll-snap-align: start; }
        .storefront .sf-draft-hidden { outline: 2px dashed #b45309; opacity: .65; }
        .storefront .sf-faq summary { cursor: pointer; font-weight: 600; }
    </style>
    {{-- B10c (ADR-039): the shop's own CSS — cleaned, every selector under .storefront, approved by the
         office (the preview shows the one waiting). The shop's part of the page contains its own
         painting and positioning, so nothing it draws can cover the Akuru frame. --}}
    @if(! empty($sf['custom_css']))
        <style data-testid="shop-custom-css">
            .storefront { position: relative; isolation: isolate; contain: paint; }
            {!! $sf['custom_css'] !!}
        </style>
    @endif
@endpush
