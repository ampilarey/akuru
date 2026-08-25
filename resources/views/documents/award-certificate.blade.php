<!DOCTYPE html>
<html lang="{{ $locale ?? 'en' }}" dir="{{ $dir ?? 'ltr' }}">
<head>
    <meta charset="utf-8">
    <title>Award certificate</title>
    <style>
        body { font-family: "Noto Sans", sans-serif; margin: 48px; text-align: center; color: #1f1f1f; }
        h1 { color: #7C2D37; }
    </style>
</head>
<body>
    <h1>Certificate of achievement</h1>
    <p>This certifies that <strong>{{ $student['name'] }}</strong></p>
    <p>has been awarded</p>
    <h2>{{ $award['title'] }}</h2>
    <p>{{ $award['description'] }}</p>
    <p>Date: {{ $awarded_date }}</p>
    @if ($notes)
        <p>{{ $notes }}</p>
    @endif
</body>
</html>
