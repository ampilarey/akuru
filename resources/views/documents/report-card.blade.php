<!DOCTYPE html>
<html lang="{{ $locale ?? 'en' }}" dir="{{ $dir ?? 'ltr' }}">
<head>
    <meta charset="utf-8">
    <title>{{ $template['header'] ?? 'Report card' }}</title>
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
    <h1>{{ $template['header'] ?? 'Report card' }}</h1>
    <div class="meta">
        <div><strong>{{ ($locale ?? 'en') === 'dv' ? 'Student (DV)' : 'Student' }}:</strong> {{ $student['name'] }} ({{ $student['number'] ?? $student['id'] }})</div>
        <div><strong>{{ ($locale ?? 'en') === 'dv' ? 'Class (DV)' : 'Class' }}:</strong> {{ $class['name'] }}</div>
        <div><strong>{{ ($locale ?? 'en') === 'dv' ? 'Term (DV)' : 'Term' }}:</strong> {{ $term['name'] }} — {{ $term['year'] }}</div>
    </div>

    @if (in_array('grades_table', $template['sections'] ?? [], true))
        <h2>{{ ($locale ?? 'en') === 'dv' ? 'Grades (DV)' : 'Grades' }}</h2>
        <table>
            <thead>
                <tr>
                    <th>{{ ($locale ?? 'en') === 'dv' ? 'Subject (DV)' : 'Subject' }}</th>
                    <th>%</th>
                    <th>{{ ($locale ?? 'en') === 'dv' ? 'Grade (DV)' : 'Grade' }}</th>
                    <th>GPA</th>
                    <th>{{ ($locale ?? 'en') === 'dv' ? 'Rank (DV)' : 'Rank' }}</th>
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
        <h2>{{ ($locale ?? 'en') === 'dv' ? 'Attendance (DV)' : 'Attendance' }}</h2>
        <p>
            {{ ($locale ?? 'en') === 'dv' ? 'Percent (DV)' : 'Percent' }}: {{ $attendance['percent'] }}%
            (present {{ $attendance['present'] }}, late {{ $attendance['late'] }},
            absent {{ $attendance['absent'] }}, excused {{ $attendance['excused'] }},
            total {{ $attendance['total'] }})
        </p>
    @endif

    @if (in_array('behavior_summary', $template['sections'] ?? [], true))
        <h2>{{ ($locale ?? 'en') === 'dv' ? 'Behavior (DV)' : 'Behavior' }}</h2>
        <p>{{ ($locale ?? 'en') === 'dv' ? 'Count (DV)' : 'Count' }}: {{ $behavior['total'] }}</p>
        <ul>
            @foreach ($behavior['items'] as $item)
                <li>{{ $item['date'] }} — {{ $item['type'] }} — {{ $item['description'] }}</li>
            @endforeach
        </ul>
    @endif

    @if (in_array('competencies', $template['sections'] ?? [], true) && count($competencies) > 0)
        <h2>{{ ($locale ?? 'en') === 'dv' ? 'Competencies (DV)' : 'Competencies' }}</h2>
        <ul>
            @foreach ($competencies as $item)
                <li>{{ $item['name'] }}: {{ $item['level'] }}</li>
            @endforeach
        </ul>
    @endif

    @if (in_array('teacher_comment', $template['sections'] ?? [], true) && ($comments['class_teacher'] ?? null))
        <h2>{{ ($locale ?? 'en') === 'dv' ? 'Class teacher (DV)' : 'Class teacher' }}</h2>
        <p>{{ $comments['class_teacher'] }}</p>
    @endif

    @if (in_array('head_comment', $template['sections'] ?? [], true) && ($comments['head'] ?? null))
        <h2>{{ ($locale ?? 'en') === 'dv' ? 'Head (DV)' : 'Head' }}</h2>
        <p>{{ $comments['head'] }}</p>
    @endif

    @if (in_array('awards', $template['sections'] ?? [], true) && count($awards) > 0)
        <h2>{{ ($locale ?? 'en') === 'dv' ? 'Awards (DV)' : 'Awards' }}</h2>
        <ul>
            @foreach ($awards as $award)
                <li>{{ is_array($award) ? ($award['title'] ?? '') : $award }}</li>
            @endforeach
        </ul>
    @endif

    <footer>{{ $template['footer'] ?? '' }}</footer>
</body>
</html>
