@props(['size' => 'h-8', 'class' => ''])

@php
    $logoPng = public_path('images/logos/akuru-logo.png');
    $logoJpg = public_path('images/logos/akuru-logo.jpg');
    $logoSvg = public_path('images/logos/akuru-logo.svg');
    
    if (file_exists($logoPng)) {
        $logoPath = asset('images/logos/akuru-logo.png');
        $logoExists = true;
    } elseif (file_exists($logoJpg)) {
        $logoPath = asset('images/logos/akuru-logo.jpg');
        $logoExists = true;
    } elseif (file_exists($logoSvg)) {
        $logoPath = asset('images/logos/akuru-logo.svg');
        $logoExists = true;
    } else {
        $logoExists = false;
    }
@endphp

<div {{ $attributes->merge(['class' => "flex items-center {$class}"]) }}>
    @if($logoExists)
        <!-- Real Akuru Institute Logo -->
        <img src="{{ $logoPath }}" 
             alt="Akuru Institute" 
             class="{{ $size }} w-auto object-contain">
    @else
        <!-- Fallback: Text Logo with Islamic Design -->
        <div class="flex items-center gap-2">
            <!-- Islamic Crescent Symbol -->
            <div class="relative {{ $size }} w-8 flex items-center justify-center">
                <svg viewBox="0 0 24 24" class="w-full h-full text-brandBlue" fill="currentColor">
                    <!-- Crescent Moon -->
                    <path d="M12 2C13.11 2 14.11 2.3 15 2.82C13.5 4 12.5 6 12.5 8.5C12.5 11 13.5 13 15 14.18C14.11 14.7 13.11 15 12 15C8.13 15 5 11.87 5 8S8.13 1 12 2Z"/>
                    <!-- Star -->
                    <path d="M19 9L20.25 11.25L22.5 12.5L20.25 13.75L19 16L17.75 13.75L15.5 12.5L17.75 11.25L19 9Z"/>
                </svg>
            </div>
            
            <!-- Institute Name -->
            <div class="flex flex-col">
                <span class="font-bold text-brandBlue text-lg leading-tight">آکورو</span>
                <span class="font-semibold text-brandGray text-sm leading-tight">AKURU</span>
            </div>
        </div>
    @endif
</div>

{{-- Instructions for adding real logo --}}
@if(!$logoExists)
    @if(config('app.debug'))
        <!-- Development Notice (only shown in debug mode) -->
        <div class="hidden">
            <!-- To use the real Akuru Institute logo:
                 1. Save the official logo as: public/images/logos/akuru-logo.png
                 2. Recommended size: 200x60px or similar aspect ratio
                 3. Formats supported: PNG, JPG, SVG
                 4. The logo will automatically replace this fallback design
            -->
        </div>
    @endif
@endif
