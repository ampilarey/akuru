{{-- One product in a grid (BOOKSHOP_PLAN §1: the vendor's name is on every card). --}}
<a href="{{ route('public.shop.product', $card['slug']) }}" class="group flex flex-col rounded-lg border bg-white overflow-hidden hover:shadow-md transition" data-product="{{ $card['slug'] }}">
    <div class="relative aspect-square bg-brandBeige-50">
        @if($card['image'])
            <img src="{{ $card['image'] }}" alt="{{ $card['image_alt'] }}" class="h-full w-full object-cover" loading="lazy" data-card-image>
        @endif
        @if($card['on_sale'])
            <span class="absolute top-2 start-2 rounded bg-brandMaroon-600 px-2 py-0.5 text-xs font-semibold text-white">{{ __('shop.on_sale') }}</span>
        @endif
    </div>
    <div class="flex flex-1 flex-col p-3">
        <p class="text-xs text-gray-500">{{ __('shop.sold_by') }} <span class="font-medium text-gray-700">{{ $card['vendor']['name'] }}</span></p>
        <h3 class="mt-1 font-semibold leading-snug text-brandMaroon-900 group-hover:underline" dir="auto">{{ $card['title'] }}</h3>
        <p class="mt-auto pt-2">
            <span class="font-semibold">{{ $card['currency'] }} {{ $card['price'] }}</span>
            @if($card['on_sale'])
                <span class="ms-1 text-sm text-gray-500 line-through">{{ $card['compare_at_price'] }}</span>
            @endif
        </p>
        @include('public.shop._stock', ['stock' => $card['stock'], 'compact' => true])
    </div>
</a>
