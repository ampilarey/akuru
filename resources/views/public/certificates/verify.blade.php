<!DOCTYPE html>
<html lang="en" dir="auto">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Certificate verification</title>
    <style>
        body { font-family: "Noto Sans", "Noto Sans Thaana", sans-serif; margin: 0; background: #F9F4EE; color: #1f1f1f; }
        main { max-width: 40rem; margin: 3rem auto; background: #fff; border: 1px solid #E6D9C8; padding: 2rem; }
        h1 { color: #7C2D37; margin-top: 0; }
        .ok { color: #166534; font-weight: 600; }
        .revoked { color: #991b1b; font-weight: 600; }
        dt { font-size: 0.75rem; text-transform: uppercase; color: #6b5c4e; margin-top: 0.75rem; }
        dd { margin: 0.15rem 0 0; }
    </style>
</head>
<body>
    <main>
        <p>Akuru Institute</p>
        <h1>Certificate verification</h1>
        @if ($certificate['revoked'])
            <p class="revoked">This certificate has been revoked.</p>
        @else
            <p class="ok">This certificate is authentic.</p>
        @endif
        <dl>
            <dt>Certificate number</dt>
            <dd>{{ $certificate['certificate_number'] }}</dd>
            <dt>Student</dt>
            <dd>{{ $certificate['student_name'] }}</dd>
            <dt>Course</dt>
            <dd>{{ $certificate['course_name'] ?: '—' }}</dd>
            @if ($certificate['offering_name'] !== '')
                <dt>Offering</dt>
                <dd>{{ $certificate['offering_name'] }}</dd>
            @endif
            <dt>Date</dt>
            <dd>{{ $certificate['completion_date'] ?: '—' }}</dd>
            @if ($certificate['grade'])
                <dt>Grade</dt>
                <dd>{{ $certificate['grade'] }}</dd>
            @endif
            <dt>Institute</dt>
            <dd>{{ $certificate['institute'] }}</dd>
        </dl>
    </main>
</body>
</html>
