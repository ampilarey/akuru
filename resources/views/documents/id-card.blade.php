<!DOCTYPE html>
@php
    // Written in the card's own language (slice DOC2).
    $locale = $locale ?? 'en';
    $say = fn (string $key) => __("documents.id_card.{$key}", [], $locale);
@endphp
<html lang="{{ $locale }}" dir="{{ $dir ?? 'ltr' }}">
<head>
    <meta charset="utf-8">
    <title>{{ $say('title') }}</title>
    <style>
        body { font-family: "Noto Sans", "Noto Sans Thaana", "Noto Naskh Arabic", sans-serif; margin: 24px; color: #1f1f1f; }
        .card { width: 360px; border: 2px solid #7C2D37; padding: 16px; }
        h1 { color: #7C2D37; font-size: 18px; }
        .qr { margin-top: 12px; }
    </style>
</head>
<body>
    <div class="card">
        <h1>{{ $say('heading') }}</h1>
        <p><strong>{{ $student['name'] }}</strong></p>
        <p>{{ $say('number') }}: <span class="student-number" dir="ltr">{{ $student['number'] }}</span></p>
        @if ($student['photo'] ?? null)
            <p class="photo">{{ $say('photo') }}: {{ $student['photo'] }}</p>
        @endif
        <div class="qr">{!! $qr !!}</div>
    </div>
</body>
</html>
