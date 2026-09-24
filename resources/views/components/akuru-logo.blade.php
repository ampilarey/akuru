@props(['size' => 'h-8', 'class' => '', 'variant' => 'default'])

@php
    // The Akuru Institute logo in the brand palette (docs/BRAND.md): a vector
    // per background, traced from the original artwork on 2026-09-24.
    //   default  — wine wordmark, gold mark: on white or the site beige
    //   on-dark  — white wordmark, brand-gold mark: on the wine gradients
    //   white    — one colour: any dark background where gold would clash
    // The file names are lowercase and exact; the old `akuru-logo.PNG` was
    // found only because someone copied a lowercase file onto each server.
    $file = match ($variant) {
        'on-dark' => 'akuru-logo-on-dark.svg',
        'white' => 'akuru-logo-white.svg',
        default => 'akuru-logo.svg',
    };
    $logoExists = file_exists(public_path('images/logos/'.$file));
    $logoPath = asset('images/logos/'.$file).'?v=3';
@endphp

<div {{ $attributes->merge(['class' => "flex items-center {$class}"]) }}>
    @if($logoExists)
        <img src="{{ $logoPath }}"
             alt="Akuru Institute"
             class="{{ $size }} w-auto object-contain">
    @else
        <div class="flex items-center gap-2">
            <div class="flex flex-col">
                <span class="font-bold {{ $variant === 'default' ? 'text-brandMaroon-600' : 'text-white' }} text-lg leading-tight">AKURU</span>
                <span class="font-semibold {{ $variant === 'default' ? 'text-brandGold-700' : 'text-brandGold-600' }} text-sm leading-tight">INSTITUTE</span>
            </div>
        </div>
    @endif
</div>
