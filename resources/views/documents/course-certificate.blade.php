<!DOCTYPE html>
@php
    // Written in the certificate's own language (slice DOC2). What the office
    // wrote on its template, the course's name and the learner's stay as written.
    $locale = $locale ?? 'en';
    $say = fn (string $key) => __("documents.course_certificate.{$key}", [], $locale);
    $day = fn (?string $date) => $date ? \Illuminate\Support\Carbon::parse($date)->locale($locale)->translatedFormat('j F Y') : '';
@endphp
<html lang="{{ $locale }}" dir="{{ $dir ?? 'ltr' }}">
<head>
    <meta charset="utf-8">
    <title>{{ $say('title') }}</title>
    <style>
        body { font-family: "Noto Sans", "Noto Sans Thaana", "Noto Naskh Arabic", sans-serif; margin: 48px; text-align: center; color: #1f1f1f; }
        h1 { color: #7C2D37; }
        .face { margin: 24px auto; max-width: 640px; }
        .qr { margin-top: 32px; }
        .meta { color: #555; font-size: 0.9rem; }
    </style>
</head>
<body>
    <p>{{ $say('institute') }}</p>
    <h1>{{ $face['template_name'] ?? $say('certificate') }}</h1>
    <div class="face">
        {!! $body_html !!}
        <p class="meta">{{ $say('number') }}: <span dir="ltr">{{ $face['certificate_number'] ?? '' }}</span></p>
        <p class="meta">{{ $say('date') }}: {{ $day($face['completion_date'] ?? null) }}</p>
        @if (! empty($face['grade']))
            <p class="meta">{{ $say('grade') }}: {{ $face['grade'] }}</p>
        @endif
        @if (! empty($face['offering_name']))
            <p class="meta">{{ $say('offering') }}: {{ $face['offering_name'] }}</p>
        @endif
        @if (! empty($face['attendance_percent']))
            <p class="meta">{{ $say('attendance') }}: {{ $face['attendance_percent'] }}%</p>
        @endif
    </div>
    <div class="qr">{!! $qr !!}</div>
    <p class="meta">{{ $say('verify_at') }} <span dir="ltr">{{ $verify_url }}</span></p>
    <p class="meta">{{ $say('signature') }}</p>
</body>
</html>
