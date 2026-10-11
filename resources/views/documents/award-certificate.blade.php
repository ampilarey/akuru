<!DOCTYPE html>
@php
    // Written in the certificate's own language (slice DOC2): every word of it was English.
    $locale = $locale ?? 'en';
    $say = fn (string $key) => __("documents.award_certificate.{$key}", [], $locale);
    $day = fn (?string $date) => $date ? \Illuminate\Support\Carbon::parse($date)->locale($locale)->translatedFormat('j F Y') : '';
@endphp
<html lang="{{ $locale }}" dir="{{ $dir ?? 'ltr' }}">
<head>
    <meta charset="utf-8">
    <title>{{ $say('title') }}</title>
    <style>
        body { font-family: "Noto Sans", "Noto Sans Thaana", "Noto Naskh Arabic", sans-serif; margin: 48px; text-align: center; color: #1f1f1f; }
        h1 { color: #7C2D37; }
    </style>
</head>
<body>
    <h1>{{ $say('heading') }}</h1>
    <p>{{ $say('certifies') }} <strong>{{ $student['name'] }}</strong></p>
    <p>{{ $say('awarded') }}</p>
    <h2>{{ $award['title'] }}</h2>
    <p>{{ $award['description'] }}</p>
    <p>{{ $say('date') }}: {{ $day($awarded_date) }}</p>
    @if ($notes)
        <p>{{ $notes }}</p>
    @endif
</body>
</html>
