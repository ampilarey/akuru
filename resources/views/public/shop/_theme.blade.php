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
    </style>
@endpush
