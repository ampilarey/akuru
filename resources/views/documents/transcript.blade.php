<!DOCTYPE html>
<html lang="{{ $locale ?? 'en' }}" dir="{{ $dir ?? 'ltr' }}">
<head>
    <meta charset="utf-8">
    <title>Transcript</title>
    <style>
        body { font-family: "Noto Sans", "Noto Sans Thaana", "Noto Naskh Arabic", sans-serif; margin: 24px; color: #1f1f1f; }
        h1 { color: #7C2D37; }
        table { width: 100%; border-collapse: collapse; margin: 12px 0; }
        th, td { border: 1px solid #d6cfc4; padding: 6px 8px; text-align: start; }
        th { background: #F3EBE0; }
    </style>
</head>
<body>
    <h1>{{ $locale === 'dv' ? 'ޓްރާންސްކްރިޕްޓް' : 'Academic transcript' }}</h1>
    <p><strong>{{ $locale === 'dv' ? 'ދަރިވަރު' : 'Student' }}:</strong> {{ $student['name'] }} ({{ $student['number'] ?? $student['id'] }})</p>
    @if ($gpa !== null)
        <p><strong>GPA:</strong> {{ $gpa }}</p>
    @endif
    <table>
        <thead>
            <tr>
                <th>{{ $locale === 'dv' ? 'އަހަރު' : 'Year' }}</th>
                <th>{{ $locale === 'dv' ? 'ޓާމް' : 'Term' }}</th>
                <th>{{ $locale === 'dv' ? 'ސަބްޖެކްޓް' : 'Subject' }}</th>
                <th>%</th>
                <th>{{ $locale === 'dv' ? 'ގްރޭޑް' : 'Grade' }}</th>
                <th>Point</th>
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
        <h2>{{ $locale === 'dv' ? 'ސްޓޭޓަސް' : 'Status history' }}</h2>
        <ul>
            @foreach ($history as $row)
                <li>{{ $row['effective_date'] }}: {{ $row['from'] }} → {{ $row['to'] }} {{ $row['reason'] ? '('.$row['reason'].')' : '' }}</li>
            @endforeach
        </ul>
    @endif
</body>
</html>
