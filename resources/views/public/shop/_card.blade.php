{{-- One product in a grid (BOOKSHOP_PLAN §1: the vendor's name is on every card). B7: badges and stars.
     STATUS §5lu, after iruali's card: the discount as a percentage, two lines of title, the price first. --}}
@php($percentOff = ($card['on_sale'] ?? false) && (float) $card['compare_at_price'] > 0 ? (int) round((1 - (float) $card['price'] / (float) $card['compare_at_price']) * 100) : 0)
<a href="{{ route('public.shop.product', $card['slug']) }}" class="group flex h-full flex-col overflow-hidden rounded-xl border border-gray-200 bg-white transition hover:border-brandMaroon-300 hover:shadow-md" data-product="{{ $card['slug'] }}">
    <div class="relative aspect-square overflow-hidden bg-brandBeige-50">
        @if($card['image'])
            <img src="{{ $card['image'] }}" alt="{{ $card['image_alt'] }}" class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-[1.03]" loading="lazy" data-card-image>
        @else
            <span class="flex h-full w-full items-center justify-center text-brandBeige-300" aria-hidden="true">
                <svg class="h-12 w-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.4" d="M5 4h11a3 3 0 013 3v13H8a3 3 0 01-3-3V4zm0 13a3 3 0 013-3h11"/></svg>
            </span>
        @endif
        @if($percentOff > 0 || count($card['badges'] ?? []) > 0)
            <span class="absolute top-2 start-2 flex flex-col items-start gap-1">
                @if($percentOff > 0)<span dir="ltr" class="rounded bg-brandMaroon-600 px-1.5 py-0.5 text-[11px] font-bold text-white" data-badge="percent-off">&minus;{{ $percentOff }}%</span>@endif
                @foreach($card['badges'] ?? [] as $badge)
                    <span class="shop-badge shop-badge-{{ $badge['kind'] }} rounded px-1.5 py-0.5 text-[11px] font-semibold" dir="auto" data-badge="{{ $badge['kind'] }}">{{ $badge['label'] }}</span>
                @endforeach
            </span>
        @endif
    </div>
    <div class="flex flex-1 flex-col p-3">
        <p class="truncate text-[11px] text-gray-500">{{ __('shop.sold_by') }} <span class="font-medium text-gray-700">{{ $card['vendor']['name'] }}</span></p>
        <h3 class="mt-0.5 line-clamp-2 min-h-[2.5rem] text-sm font-semibold leading-snug text-brandMaroon-900 group-hover:underline" dir="auto">{{ $card['title'] }}</h3>
        @if($card['rating'] ?? null)
            <p class="mt-1 text-xs text-amber-700" aria-label="{{ __('shop.rated_out_of', ['avg' => $card['rating']['avg']]) }}" data-rating="{{ $card['rating']['avg'] }}">
                <span aria-hidden="true">{{ str_repeat('★', (int) round((float) $card['rating']['avg'])) }}{{ str_repeat('☆', 5 - (int) round((float) $card['rating']['avg'])) }}</span>
                <span class="text-gray-500">({{ $card['rating']['count'] }})</span>
            </p>
        @endif
        <p class="mt-auto pt-2">
            <span class="text-base font-bold text-brandMaroon-900">{{ $card['currency'] }} {{ $card['price'] }}</span>
            @if($card['on_sale'])
                <span class="ms-1 text-xs text-gray-500 line-through">{{ $card['compare_at_price'] }}</span>
            @endif
        </p>
        @if($card['sale'] ?? null)
            {{-- §5lb: a timed sale counts down to its end (the layout's clock); without script, the end date. --}}
            <p class="text-xs font-medium text-brandMaroon-700" data-sale-ends="{{ $card['sale']['ends_at'] }}" data-template="{{ __('shop.sale_ends_in', ['time' => '__TIME__']) }}" data-days="{{ __('shop.days_short') }}" data-ended="{{ __('shop.sale_ended') }}" data-testid="card-sale-ends">{{ __('shop.sale_ends_on', ['date' => \Illuminate\Support\Carbon::parse($card['sale']['ends_at'])->translatedFormat('j M, H:i')]) }}</p>
        @endif
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
