<!DOCTYPE html>
@php
    // Every heading in the card's own language (STATUS §5pz). The Dhivehi card
    // printed placeholders, each English heading tagged DV, and Arabic fell
    // back to English, as did the attendance line's five counts and each
    // behaviour record's type, which was printed as its code.
    $locale = $locale ?? 'en';
    $say = fn (string $key, array $replace = []) => __("documents.report_card.{$key}", $replace, $locale);
    $behaviorType = fn (?string $type) => trans()->has("documents.report_card.behavior_types.{$type}", $locale)
        ? __("documents.report_card.behavior_types.{$type}", [], $locale)
        : (string) $type;
@endphp
<html lang="{{ $locale }}" dir="{{ $dir ?? 'ltr' }}">
<head>
    <meta charset="utf-8">
    <title>{{ $template['header'] ?? $say('title') }}</title>
    <style>
        body { font-family: "Noto Sans", "Noto Sans Thaana", "Noto Naskh Arabic", sans-serif; margin: 24px; color: #1f1f1f; }
        h1, h2 { color: #7C2D37; }
        table { width: 100%; border-collapse: collapse; margin: 12px 0; }
        th, td { border: 1px solid #d6cfc4; padding: 6px 8px; text-align: start; }
        th { background: #F3EBE0; }
        .meta { margin-bottom: 16px; }
    </style>
</head>
<body>
    <h1>{{ $template['header'] ?? $say('title') }}</h1>
    <div class="meta">
        <div><strong>{{ $say('student') }}:</strong> {{ $student['name'] }} ({{ $student['number'] ?? $student['id'] }})</div>
        <div><strong>{{ $say('class') }}:</strong> {{ $class['name'] }}</div>
        <div><strong>{{ $say('term') }}:</strong> {{ $term['name'] }} — {{ $term['year'] }}</div>
    </div>

    @if (in_array('grades_table', $template['sections'] ?? [], true))
        <h2>{{ $say('grades') }}</h2>
        <table>
            <thead>
                <tr>
                    <th>{{ $say('subject') }}</th>
                    <th>%</th>
                    <th>{{ $say('grade') }}</th>
                    <th>{{ $say('gpa') }}</th>
                    <th>{{ $say('rank') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($grades as $row)
                    <tr>
                        <td>{{ $row['subject'] }}</td>
                        <td>{{ $row['percent'] }}</td>
                        <td>{{ $row['grade'] }}</td>
                        <td>{{ $row['point'] }}</td>
                        <td>{{ $row['rank'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5">—</td></tr>
                @endforelse
            </tbody>
        </table>
    @endif

    @if (in_array('attendance_summary', $template['sections'] ?? [], true))
        <h2>{{ $say('attendance') }}</h2>
        <p>
            {{ $say('percent') }}: {{ $attendance['percent'] }}%
            ({{ $say('attendance_counts', [
                'present' => $attendance['present'],
                'late' => $attendance['late'],
                'absent' => $attendance['absent'],
                'excused' => $attendance['excused'],
                'total' => $attendance['total'],
            ]) }})
        </p>
    @endif

    @if (in_array('behavior_summary', $template['sections'] ?? [], true))
        <h2>{{ $say('behavior') }}</h2>
        <p>{{ $say('count') }}: {{ $behavior['total'] }}</p>
        <ul>
            @foreach ($behavior['items'] as $item)
                <li>{{ $item['date'] }} — {{ $behaviorType($item['type']) }} — {{ $item['description'] }}</li>
            @endforeach
        </ul>
    @endif

    @if (in_array('competencies', $template['sections'] ?? [], true) && count($competencies) > 0)
        <h2>{{ $say('competencies') }}</h2>
        <ul>
            @foreach ($competencies as $item)
                <li>{{ $item['name'] }}: {{ $item['level'] }}</li>
            @endforeach
        </ul>
    @endif

    @if (in_array('teacher_comment', $template['sections'] ?? [], true) && ($comments['class_teacher'] ?? null))
        <h2>{{ $say('class_teacher') }}</h2>
        <p>{{ $comments['class_teacher'] }}</p>
    @endif

    @if (in_array('head_comment', $template['sections'] ?? [], true) && ($comments['head'] ?? null))
        <h2>{{ $say('head') }}</h2>
        <p>{{ $comments['head'] }}</p>
    @endif

    @if (in_array('awards', $template['sections'] ?? [], true) && count($awards) > 0)
        <h2>{{ $say('awards') }}</h2>
        <ul>
            @foreach ($awards as $award)
                <li>{{ is_array($award) ? ($award['title'] ?? '') : $award }}</li>
            @endforeach
        </ul>
    @endif

    <footer>{{ $template['footer'] ?? '' }}</footer>
</body>
</html>
