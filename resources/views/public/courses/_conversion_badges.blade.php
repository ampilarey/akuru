@php
    $conversion = $conversion ?? [];
    $seatsLabel = $conversion['seats_label'] ?? null;
    $seatsTone = $conversion['seats_tone'] ?? null;
    $deadlineBadge = ! empty($conversion['deadline_badge']);
    $deadlineDays = $conversion['deadline_days'] ?? null;
    $deadline = $conversion['deadline'] ?? null;
@endphp
@if($seatsLabel || $deadlineBadge)
    <div class="flex flex-wrap items-center gap-2 {{ $class ?? '' }}">
        @if($seatsLabel)
            <span class="inline-flex items-center px-3 py-1 text-sm font-semibold rounded-full
                {{ $seatsTone === 'full' ? 'bg-gray-100 text-gray-700' : ($seatsTone === 'exact' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-800') }}">
                {{ $seatsLabel }}
            </span>
        @endif
        @if($deadlineBadge)
            <span class="inline-flex items-center px-3 py-1 text-sm font-semibold rounded-full bg-brandMaroon-50 text-brandMaroon-800">
                Closes {{ $deadline }}
                @if($deadlineDays !== null)
                    · {{ $deadlineDays }} {{ $deadlineDays === 1 ? 'day' : 'days' }} left
                @endif
            </span>
        @endif
    </div>
@endif
