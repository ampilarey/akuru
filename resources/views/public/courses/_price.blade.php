@php
    $conversion = $conversion ?? [];
    $early = $conversion['early_bird'] ?? null;
    $fee = $course->fee ?? null;
    $size = $size ?? 'lg';
    $feeClass = $size === 'xl' ? 'text-3xl' : 'text-2xl';
@endphp
@if($early)
    <div class="{{ $class ?? '' }}">
        <div class="text-sm text-gray-500 line-through">{{ number_format($early['normal_amount'], 2) }} {{ $early['currency'] }}</div>
        <div class="{{ $feeClass }} font-bold text-brandMaroon-600">{{ number_format($early['amount'], 2) }} {{ $early['currency'] }}</div>
        <p class="text-sm text-gray-500 mt-1">Early bird until {{ $early['ends_at'] }}</p>
    </div>
@elseif($fee)
    <div class="{{ $class ?? '' }}">
        <div class="{{ $feeClass }} font-bold text-brandMaroon-600">{{ number_format($fee, 2) }} MVR</div>
    </div>
@endif
