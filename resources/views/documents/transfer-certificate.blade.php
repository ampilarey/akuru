<!DOCTYPE html>
<html lang="{{ $locale ?? 'en' }}" dir="{{ $dir ?? 'ltr' }}">
<head>
    <meta charset="utf-8">
    <title>Transfer certificate</title>
    <style>
        body { font-family: "Noto Sans", sans-serif; margin: 24px; color: #1f1f1f; }
        h1 { color: #7C2D37; }
    </style>
</head>
<body>
    <h1>Transfer / leaving certificate</h1>
    <p>This is to certify that <strong>{{ $student['name'] }}</strong> ({{ $student['number'] ?? $student['id'] }}) is recorded with status <strong>{{ $student['status'] }}</strong>.</p>
    <h2>Status history</h2>
    <ul>
        @forelse ($history as $row)
            <li>{{ $row['effective_date'] }}: {{ $row['from'] }} → {{ $row['to'] }} {{ $row['reason'] ? '('.$row['reason'].')' : '' }}</li>
        @empty
            <li>No status changes recorded.</li>
        @endforelse
    </ul>
</body>
</html>
