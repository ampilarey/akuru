{{-- A stock state a customer can read (BOOKSHOP_PLAN §4): "3 left", "out of stock", "made to order, 5 days". --}}
@php($tone = match ($stock['state']) {
    'out_of_stock' => 'text-red-700',
    'few_left' => 'text-amber-700',
    default => 'text-green-700',
})
<p class="{{ $compact ?? false ? 'text-xs' : 'text-sm font-medium' }} {{ $tone }}" data-stock="{{ $stock['state'] }}">
    @switch($stock['state'])
        @case('few_left'){{ __('shop.stock_few_left', ['count' => $stock['count']]) }}@break
        @case('out_of_stock'){{ __('shop.stock_out_of_stock') }}@break
        @case('made_to_order'){{ __('shop.stock_made_to_order', ['days' => $stock['days']]) }}@break
        @case('available'){{ __('shop.stock_available') }}@break
        @default{{ __('shop.stock_in_stock') }}
    @endswitch
</p>
