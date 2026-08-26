<!DOCTYPE html>
<html lang="{{ $locale ?? 'en' }}" dir="{{ $dir ?? 'ltr' }}">
<head>
    <meta charset="utf-8">
    <title>Course certificate</title>
    <style>
        body { font-family: "Noto Sans", "Noto Sans Thaana", sans-serif; margin: 48px; text-align: center; color: #1f1f1f; }
        h1 { color: #7C2D37; }
        .face { margin: 24px auto; max-width: 640px; }
        .qr { margin-top: 32px; }
        .meta { color: #555; font-size: 0.9rem; }
    </style>
</head>
<body>
    <p>{{ $face['institute'] ?? 'Akuru Institute' }}</p>
    <h1>{{ $face['template_name'] ?? 'Certificate' }}</h1>
    <div class="face">
        {!! $body_html !!}
        <p class="meta">Certificate number: {{ $face['certificate_number'] ?? '' }}</p>
        <p class="meta">Date: {{ $face['completion_date'] ?? '' }}</p>
        @if (! empty($face['grade']))
            <p class="meta">Grade: {{ $face['grade'] }}</p>
        @endif
        @if (! empty($face['offering_name']))
            <p class="meta">Offering: {{ $face['offering_name'] }}</p>
        @endif
        @if (! empty($face['attendance_percent']))
            <p class="meta">Attendance: {{ $face['attendance_percent'] }}%</p>
        @endif
    </div>
    <div class="qr">{!! $qr !!}</div>
    <p class="meta">Verify at {{ $verify_url }}</p>
    <p class="meta">Authorized signature — Akuru Institute</p>
</body>
</html>
