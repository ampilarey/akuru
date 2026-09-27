@extends('public.layouts.public')

@section('title', __('public.Offers') . ' - ' . __('public.Digital Library') . ' - ' . config('app.name'))
@section('description', __('public.Current offers'))

@section('content')
<div class="container mx-auto max-w-5xl px-4 py-10">
    <nav class="mb-6 flex flex-wrap items-center gap-2 text-sm text-gray-500">
        <a href="{{ route('public.library.index') }}" class="hover:text-brandMaroon-600">{{ __('public.Digital Library') }}</a>
        <span>›</span>
        <span class="text-gray-700">{{ __('public.Offers') }}</span>
    </nav>
    <h1 class="mb-6 text-3xl font-bold text-brandMaroon-900">{{ __('public.Current offers') }}</h1>

    @if(count($promotions) === 0)
        <p class="text-gray-500" data-testid="no-offers">{{ __('public.No offers are running right now.') }}</p>
    @endif

    {{-- B4 (§8.5, §18): one card per live campaign, with what it covers. --}}
    @foreach($promotions as $promotion)
        <section class="mb-8 rounded-lg border bg-white p-5" data-campaign="{{ $promotion['slug'] }}">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="text-xl font-semibold text-brandMaroon-900">{{ $promotion['name'] }}</h2>
                @php($figure = $promotion['discount_type'] === 'percentage' ? rtrim(rtrim(number_format($promotion['discount_value'], 2), '0'), '.').'%' : 'MVR '.number_format($promotion['discount_value'], 2))
                <p class="text-sm text-red-800">
                    {{ $promotion['is_gift_card_bonus'] ? __('public.:bonus bonus on gift cards', ['bonus' => $figure]) : $figure.' '.__('public.off') }}
                    @if($promotion['ends_on'])
                        · {{ __('public.Offer ends :date', ['date' => $promotion['ends_on']]) }}
                    @endif
                </p>
            </div>
            @if($promotion['description'])
                <p class="mt-2 whitespace-pre-line text-gray-700">{{ $promotion['description'] }}</p>
            @endif
            @if($promotion['is_gift_card_bonus'])
                {{-- B4b: the bonus is on the gift card page, where the card is bought. --}}
                <p class="mt-2 text-sm text-gray-600">
                    @if($promotion['minimum_amount'])
                        {{ __('public.Buy MVR :min or more and get :bonus extra on the card', ['min' => number_format($promotion['minimum_amount']), 'bonus' => $figure]) }}
                    @else
                        {{ __('public.Get :bonus extra on every card', ['bonus' => $figure]) }}
                    @endif
                </p>
                <a href="{{ route('public.gift-cards.index') }}" class="mt-4 inline-block text-sm text-brandMaroon-700 underline">{{ __('public.Buy a gift card') }}</a>
                @continue
            @endif
            @if(collect($promotion['targets'])->contains(fn ($target) => $target['type'] === 'all'))
                <p class="mt-2 text-sm text-gray-500">{{ __('public.Everything in the library') }}</p>
            @endif
            @if(count($promotion['items']) > 0)
                <div class="mt-4 grid gap-3 md:grid-cols-2 lg:grid-cols-4">
                    @foreach($promotion['items'] as $item)
                        <a href="{{ route('public.library.show', $item['slug']) }}" class="flex gap-3 rounded border p-3 hover:shadow-md" data-item="{{ $item['slug'] }}">
                            @if($item['cover_url'])
                                <img src="{{ $item['cover_url'] }}" alt="" class="h-20 w-14 shrink-0 rounded object-cover" loading="lazy">
                            @endif
                            <div class="min-w-0 text-sm">
                                <p class="font-semibold leading-tight text-brandMaroon-900">{{ $item['title'] }}</p>
                                @if($item['promotion'])
                                    <p class="mt-1 text-red-800"><s class="text-gray-400">{{ $item['price'] }}</s> {{ $item['currency'] }} {{ number_format($item['promotion']['price'], 2) }}</p>
                                @endif
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
            <a href="{{ route('public.library.index', ['campaign' => $promotion['slug']]) }}" class="mt-4 inline-block text-sm text-brandMaroon-700 underline">{{ __('public.Browse this offer') }}</a>
        </section>
    @endforeach
</div>
@endsection
