{{-- L1/L5: one book on the lending shelf or the Free items page. A give-away says Free to keep, then
     Reserved once its giver has promised it to someone, then Taken (with the day) once handed over. --}}
@php($state = $book['status'] === 'given' ? 'taken' : ($book['reserved'] ? 'reserved' : $book['status']))
<a href="{{ $book['url'] }}" class="group flex h-full flex-col overflow-hidden rounded-xl border border-gray-200 bg-white transition hover:border-brandMaroon-300 hover:shadow-md {{ $state === 'taken' ? 'opacity-75' : '' }}" data-lending-book="{{ $book['slug'] }}" data-status="{{ $book['status'] }}" data-state="{{ $state }}">
    <div class="relative aspect-square overflow-hidden bg-brandBeige-50">
        @if($book['photo'])
            <img src="{{ $book['photo'] }}" alt="{{ $book['title'] }}" class="h-full w-full object-cover {{ $state === 'taken' ? 'grayscale' : '' }}" loading="lazy">
        @else
            <span class="flex h-full w-full items-center justify-center text-brandBeige-300" aria-hidden="true">
                <svg class="h-12 w-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.4" d="M5 4h11a3 3 0 013 3v13H8a3 3 0 01-3-3V4zm0 13a3 3 0 013-3h11"/></svg>
            </span>
        @endif
        <span class="absolute top-2 start-2 flex flex-col items-start gap-1">
            @if($state === 'taken')
                <span class="rounded bg-gray-700 px-1.5 py-0.5 text-[11px] font-semibold text-white" data-badge="taken">{{ __('lending.taken_badge') }}</span>
            @elseif($state === 'reserved')
                <span class="rounded bg-amber-600 px-1.5 py-0.5 text-[11px] font-semibold text-white" data-badge="reserved">{{ __('lending.reserved_badge') }}</span>
            @else
                <span class="rounded px-1.5 py-0.5 text-[11px] font-semibold text-white {{ $book['status'] === 'on_loan' ? 'bg-gray-600' : 'bg-green-700' }}" data-badge="status">{{ $book['status'] === 'on_loan' ? __('lending.on_loan_badge') : __('lending.available_badge') }}</span>
            @endif
            <span class="rounded bg-amber-700 px-1.5 py-0.5 text-[11px] font-semibold text-white" data-badge="condition">{{ $book['condition_label'] }}</span>
            @if($book['offer'] === 'give')<span class="rounded bg-brandMaroon-700 px-1.5 py-0.5 text-[11px] font-semibold text-white" data-badge="give">{{ __('lending.give_badge') }}</span>@endif
        </span>
    </div>
    <div class="flex flex-1 flex-col p-3">
        <p class="truncate text-[11px] text-gray-500">{{ $book['offer'] === 'give' ? __('lending.given_by') : __('lending.lent_by') }} <span class="font-medium text-gray-700" dir="auto">{{ $book['lender']['name'] }}</span>@if($book['lender']['island']) · {{ $book['lender']['island'] }}@endif</p>
        <h3 class="mt-0.5 line-clamp-2 min-h-[2.5rem] text-sm font-semibold leading-snug text-brandMaroon-900 group-hover:underline" dir="auto">{{ $book['title'] }}</h3>
        @if($book['author'])<p class="text-xs text-gray-600" dir="auto">{{ $book['author'] }}</p>@endif
        @if($book['lender']['rating']['count'] > 0)<p class="text-xs text-amber-700" data-testid="card-rating" data-avg="{{ $book['lender']['rating']['avg'] }}">★ {{ __('lending.rating_summary', ['avg' => $book['lender']['rating']['avg'], 'count' => $book['lender']['rating']['count']]) }}</p>@endif
        <p class="mt-auto pt-2 text-xs text-gray-500">
            @if($state === 'taken')
                <span data-testid="taken-on">{{ __('lending.taken_on', ['date' => $book['taken_on']]) }}</span>
            @else
                {{ $book['offer'] === 'give' ? __('lending.offer_give') : __('lending.max_days', ['days' => $book['max_days']]) }}@if($book['grade']) · {{ __('lending.filter_grade') }} {{ $book['grade'] }}@endif
            @endif
        </p>
    </div>
</a>
