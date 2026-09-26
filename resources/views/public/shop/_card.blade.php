{{-- One product in a grid (BOOKSHOP_PLAN §1: the vendor's name is on every card). B7: badges and stars. --}}
<a href="{{ route('public.shop.product', $card['slug']) }}" class="group flex flex-col rounded-lg border bg-white overflow-hidden hover:shadow-md transition" data-product="{{ $card['slug'] }}">
    <div class="relative aspect-square bg-brandBeige-50">
        @if($card['image'])
            <img src="{{ $card['image'] }}" alt="{{ $card['image_alt'] }}" class="h-full w-full object-cover" loading="lazy" data-card-image>
        @endif
        @if(count($card['badges'] ?? []) > 0)
            <span class="absolute top-2 start-2 flex flex-col items-start gap-1">
                @foreach($card['badges'] as $badge)
                    <span class="shop-badge shop-badge-{{ $badge['kind'] }} rounded px-2 py-0.5 text-xs font-semibold" dir="auto" data-badge="{{ $badge['kind'] }}">{{ $badge['label'] }}</span>
                @endforeach
            </span>
        @endif
    </div>
    <div class="flex flex-1 flex-col p-3">
        <p class="text-xs text-gray-500">{{ __('shop.sold_by') }} <span class="font-medium text-gray-700">{{ $card['vendor']['name'] }}</span></p>
        <h3 class="mt-1 font-semibold leading-snug text-brandMaroon-900 group-hover:underline" dir="auto">{{ $card['title'] }}</h3>
        @if($card['rating'] ?? null)
            <p class="mt-1 text-xs text-amber-700" aria-label="{{ __('shop.rated_out_of', ['avg' => $card['rating']['avg']]) }}" data-rating="{{ $card['rating']['avg'] }}">
                <span aria-hidden="true">{{ str_repeat('★', (int) round((float) $card['rating']['avg'])) }}{{ str_repeat('☆', 5 - (int) round((float) $card['rating']['avg'])) }}</span>
                <span class="text-gray-500">({{ $card['rating']['count'] }})</span>
            </p>
        @endif
        <p class="mt-auto pt-2">
            <span class="font-semibold">{{ $card['currency'] }} {{ $card['price'] }}</span>
            @php($usd = \App\Domains\Bookshop\Support\Usd::line($card['price']))
            @if($usd !== '')<span class="block text-xs text-gray-500" data-testid="card-usd">{{ $usd }}</span>@endif
            @if($card['on_sale'])
                <span class="ms-1 text-sm text-gray-500 line-through">{{ $card['compare_at_price'] }}</span>
            @endif
        </p>
        @include('public.shop._stock', ['stock' => $card['stock'], 'compact' => true])
    </div>
</a>
@once
    @push('head_meta')
        <style>
            .shop-badge { background: #7a1f2b; color: #fff; }
            .shop-badge-new { background: #0f4c81; }
            .shop-badge-bestseller { background: #8a5a0b; }
            .shop-badge-custom { background: #1f5f3f; }
        </style>
    @endpush
@endonce
