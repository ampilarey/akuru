<?php

return [
    'card_fonts' => [
        'latin' => env('DAILY_CARD_FONT_LATIN', '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf'),
        'arabic' => env('DAILY_CARD_FONT_ARABIC', resource_path('fonts/share-cards/NotoNaskhArabic-Regular.ttf')),
        'thaana' => env('DAILY_CARD_FONT_THAANA', resource_path('fonts/share-cards/NotoSansThaana-Regular.ttf')),
    ],
];
