<!DOCTYPE html>
@php
    // Written in the certificate's own language (slice DOC2); a status by the
    // transcript's name for it, as the report card and the transcript say it.
    $locale = $locale ?? 'en';
    $say = fn (string $key, array $replace = []) => __("documents.transfer_certificate.{$key}", $replace, $locale);
    $status = fn (?string $code) => $code !== null && trans()->has("documents.transcript.statuses.{$code}", $locale)
        ? __("documents.transcript.statuses.{$code}", [], $locale)
        : (string) $code;
    $day = fn (?string $date) => $date ? \Illuminate\Support\Carbon::parse($date)->locale($locale)->translatedFormat('j F Y') : '';
@endphp
<html lang="{{ $locale }}" dir="{{ $dir ?? 'ltr' }}">
<head>
    <meta charset="utf-8">
    <title>{{ $say('title') }}</title>
    <style>
        body { font-family: "Noto Sans", "Noto Sans Thaana", "Noto Naskh Arabic", sans-serif; margin: 24px; color: #1f1f1f; }
        h1 { color: #7C2D37; }
    </style>
</head>
<body>
    <h1>{{ $say('heading') }}</h1>
    <p>{{ $say('certifies', ['name' => $student['name'], 'number' => $student['number'] ?? $student['id'], 'status' => $status($student['status'])]) }}</p>
    <h2>{{ $say('history') }}</h2>
    <ul>
        @forelse ($history as $row)
            <li>{{ $day($row['effective_date']) }}: {{ $status($row['from']) }} → {{ $status($row['to']) }} {{ $row['reason'] ? '('.$row['reason'].')' : '' }}</li>
        @empty
            <li>{{ $say('no_history') }}</li>
        @endforelse
    </ul>
</body>
</html>
