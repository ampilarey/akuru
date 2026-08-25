<!DOCTYPE html>
<html lang="{{ $locale ?? 'en' }}" dir="{{ $dir ?? 'ltr' }}">
<head>
    <meta charset="utf-8">
    <title>Student ID card</title>
    <style>
        body { font-family: "Noto Sans", sans-serif; margin: 24px; color: #1f1f1f; }
        .card { width: 360px; border: 2px solid #7C2D37; padding: 16px; }
        h1 { color: #7C2D37; font-size: 18px; }
        .qr { margin-top: 12px; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Akuru Institute — Student ID</h1>
        <p><strong>{{ $student['name'] }}</strong></p>
        <p>Student number: <span class="student-number">{{ $student['number'] }}</span></p>
        @if ($student['photo'] ?? null)
            <p class="photo">Photo: {{ $student['photo'] }}</p>
        @endif
        <div class="qr">{!! $qr !!}</div>
    </div>
</body>
</html>
