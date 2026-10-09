<!DOCTYPE html>
@php
    // Every heading in the transcript's own language (STATUS §5pz). Dhivehi
    // had its headings and Arabic fell back to English; "Point" and the status
    // history's codes were English in every language.
    $locale = $locale ?? 'en';
    $say = fn (string $key) => __("documents.transcript.{$key}", [], $locale);
    $status = fn (?string $code) => $code !== null && trans()->has("documents.transcript.statuses.{$code}", $locale)
        ? __("documents.transcript.statuses.{$code}", [], $locale)
        : (string) $code;
@endphp
<html lang="{{ $locale }}" dir="{{ $dir ?? 'ltr' }}">
<head>
    <meta charset="utf-8">
    <title>{{ $say('title') }}</title>
    <style>
        body { font-family: "Noto Sans", "Noto Sans Thaana", "Noto Naskh Arabic", sans-serif; margin: 24px; color: #1f1f1f; }
        h1 { color: #7C2D37; }
        table { width: 100%; border-collapse: collapse; margin: 12px 0; }
        th, td { border: 1px solid #d6cfc4; padding: 6px 8px; text-align: start; }
        th { background: #F3EBE0; }
    </style>
</head>
<body>
    <h1>{{ $say('title') }}</h1>
    <p><strong>{{ $say('student') }}:</strong> {{ $student['name'] }} ({{ $student['number'] ?? $student['id'] }})</p>
    @if ($gpa !== null)
        <p><strong>{{ $say('gpa') }}:</strong> {{ $gpa }}</p>
    @endif
    <table>
        <thead>
            <tr>
                <th>{{ $say('year') }}</th>
                <th>{{ $say('term') }}</th>
                <th>{{ $say('subject') }}</th>
                <th>%</th>
                <th>{{ $say('grade') }}</th>
                <th>{{ $say('point') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['year'] }}</td>
                    <td>{{ $row['term'] }}</td>
                    <td>{{ $row['subject'] }}</td>
                    <td>{{ $row['percent'] }}</td>
                    <td>{{ $row['grade'] }}</td>
                    <td>{{ $row['point'] }}</td>
                </tr>
            @empty
                <tr><td colspan="6">—</td></tr>
            @endforelse
        </tbody>
    </table>
    @if (count($history) > 0)
        <h2>{{ $say('status_history') }}</h2>
        <ul>
            @foreach ($history as $row)
                <li>{{ $row['effective_date'] }}: {{ $status($row['from']) }} → {{ $status($row['to']) }} {{ $row['reason'] ? '('.$row['reason'].')' : '' }}</li>
            @endforeach
        </ul>
    @endif
</body>
</html>
