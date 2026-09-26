{{-- BOOKSHOP_PLAN §6.4 (slice B5): the storefront's own menu inside the Akuru frame — the whole
     catalogue, the shop's collections and pages, never a bare URL (SectionTypes::normalizeNavigation). --}}
@php($sf = $vendor['storefront'])
@if(count($sf['navigation']) > 0)
    <nav class="sf-nav border-b" aria-label="{{ __('shop.storefront_menu') }}" data-testid="storefront-nav">
        <div class="container mx-auto flex flex-wrap gap-1 overflow-x-auto px-4 text-sm">
            <a href="{{ $sf['home_url'] }}" class="px-3 py-2 {{ url()->current() === $sf['home_url'] ? 'sf-nav-active' : '' }}">{{ __('shop.home') }}</a>
            @foreach($sf['navigation'] as $entry)
                <a href="{{ $entry['url'] }}" class="px-3 py-2 {{ url()->current() === $entry['url'] ? 'sf-nav-active' : '' }}" dir="auto">{{ $entry['label'] }}</a>
            @endforeach
        </div>
    </nav>
@endif
