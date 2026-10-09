<?php

use App\Support\Contracts\DocumentRendererInterface;

/**
 * The report card and the transcript in Dhivehi and Arabic (STATUS §5pz).
 *
 * A Dhivehi report card printed placeholders where its headings belonged —
 * *Student (DV)*, *Grades (DV)*, *Attendance (DV)*, fifteen of them — and a
 * test pinned one of them as if it were the heading. An Arabic card, and an
 * Arabic transcript, fell back to English. The attendance line's five
 * counts, a behaviour record's type, the transcript's *Point* and its status
 * history were English, or a code, in every language.
 *
 * Both documents now read the `documents` book in the language they are
 * made in, which is the one the office or the family chose — not the
 * request's.
 *
 * Filesystem and the renderer only: no database.
 */
function documentsBook(string $locale): array
{
    return require base_path("resources/lang/{$locale}/documents.php");
}

/** A report card's payload, as `AssembleReportCardDataAction` builds it. */
function reportCardPayload(string $locale): array
{
    return [
        'locale' => $locale,
        'dir' => $locale === 'en' ? 'ltr' : 'rtl',
        'template' => ['sections' => ['grades_table', 'attendance_summary', 'behavior_summary', 'competencies', 'teacher_comment', 'head_comment', 'awards']],
        'student' => ['id' => 1, 'name' => 'Aishath Naseem', 'number' => 'S-001'],
        'class' => ['id' => 1, 'name' => 'Grade 1 A'],
        'term' => ['id' => 1, 'name' => 'Term 1', 'year' => '2026'],
        'grades' => [['subject' => 'Maths', 'percent' => 91, 'grade' => 'A', 'point' => 4.0, 'rank' => 1]],
        'competencies' => [['name' => 'Reading', 'level' => 'Secure', 'notes' => null]],
        'attendance' => ['total' => 20, 'present' => 17, 'late' => 1, 'absent' => 1, 'excused' => 1, 'left_early' => 0, 'percent' => 90],
        'behavior' => ['total' => 1, 'items' => [['type' => 'compliment', 'category' => 'Kindness', 'description' => 'Helped a classmate', 'date' => '2026-03-01', 'points' => 1]]],
        'comments' => ['class_teacher' => 'A good term.', 'head' => 'Well done.'],
        'awards' => [['title' => 'Star reader']],
    ];
}

function transcriptPayload(string $locale): array
{
    return [
        'locale' => $locale,
        'dir' => $locale === 'en' ? 'ltr' : 'rtl',
        'student' => ['id' => 1, 'name' => 'Aishath Naseem', 'number' => 'S-001'],
        'rows' => [['year' => '2026', 'term' => 'Term 1', 'subject' => 'Maths', 'percent' => 91, 'grade' => 'A', 'point' => 4.0]],
        'gpa' => 4.0,
        'history' => [['from' => 'prospective', 'to' => 'active', 'reason' => null, 'effective_date' => '2026-01-01']],
    ];
}

/** @return array<string, string> dotted key => phrase */
function flatDocumentsPhrases(array $book, string $prefix = ''): array
{
    $flat = [];
    foreach ($book as $key => $value) {
        $flat += is_array($value) ? flatDocumentsPhrases($value, "{$prefix}{$key}.") : ["{$prefix}{$key}" => $value];
    }

    return $flat;
}

it('says every heading of the report card and the transcript in Dhivehi and Arabic', function () {
    $en = flatDocumentsPhrases(array_intersect_key(documentsBook('en'), array_flip(['report_card', 'transcript'])));
    $dv = flatDocumentsPhrases(documentsBook('dv'));
    $ar = flatDocumentsPhrases(documentsBook('ar'));

    expect($en)->not->toBeEmpty();
    foreach ($en as $key => $english) {
        expect(array_key_exists($key, $dv))->toBeTrue("documents.{$key} is missing in Dhivehi")
            ->and(array_key_exists($key, $ar))->toBeTrue("documents.{$key} is missing in Arabic")
            ->and($dv[$key])->toMatch('/\p{Thaana}/u', "documents.{$key} in Dhivehi")
            ->and($ar[$key])->toMatch('/\p{Arabic}/u', "documents.{$key} in Arabic")
            ->and(preg_match_all('/:\w+/', $dv[$key]))->toBe(preg_match_all('/:\w+/', $english), "documents.{$key} keeps its placeholders in Dhivehi")
            ->and(preg_match_all('/:\w+/', $ar[$key]))->toBe(preg_match_all('/:\w+/', $english), "documents.{$key} keeps its placeholders in Arabic");
    }
});

it('leaves no placeholder heading and no language branch in the two templates', function () {
    foreach (['report-card', 'transcript'] as $template) {
        $source = file_get_contents(resource_path("views/documents/{$template}.blade.php"));
        expect($source)->not->toContain('(DV)')
            ->and($source)->not->toMatch("/=== 'dv'/");
    }
});

it('makes a report card in each language with that language’s headings', function () {
    $render = fn (string $locale) => app(DocumentRendererInterface::class)->render('report-card', reportCardPayload($locale));

    $en = $render('en');
    expect($en)->toContain('<h2>Grades</h2>')
        ->and($en)->toContain('Percent: 90%')
        ->and($en)->toContain('present 17, late 1, absent 1, excused 1, total 20')
        ->and($en)->toContain('2026-03-01 — Compliment — Helped a classmate');

    foreach (['dv', 'ar'] as $locale) {
        $book = documentsBook($locale)['report_card'];
        $html = $render($locale);
        expect($html)->toContain("lang=\"{$locale}\"")
            ->and($html)->toContain('dir="rtl"')
            ->and($html)->not->toContain('(DV)')
            ->and($html)->toContain("<title>{$book['title']}</title>")
            ->and($html)->toContain("<h2>{$book['grades']}</h2>")
            ->and($html)->toContain("<h2>{$book['attendance']}</h2>")
            ->and($html)->toContain("<h2>{$book['behavior']}</h2>")
            ->and($html)->toContain("<h2>{$book['class_teacher']}</h2>")
            ->and($html)->toContain("— {$book['behavior_types']['compliment']} —")
            ->and($html)->toContain(str_replace([':present', ':late', ':absent', ':excused', ':total'], ['17', '1', '1', '1', '20'], $book['attendance_counts']))
            // The author's words stay as written.
            ->and($html)->toContain('Helped a classmate');
    }
});

it('makes a transcript in each language with that language’s headings, the status history named', function () {
    $render = fn (string $locale) => app(DocumentRendererInterface::class)->render('transcript', transcriptPayload($locale));

    expect($render('en'))->toContain('<h1>Academic transcript</h1>')
        ->and($render('en'))->toContain('Prospective → Active');

    foreach (['dv', 'ar'] as $locale) {
        $book = documentsBook($locale)['transcript'];
        $html = $render($locale);
        expect($html)->toContain("<h1>{$book['title']}</h1>")
            ->and($html)->toContain("<th>{$book['point']}</th>")
            ->and($html)->toContain("<h2>{$book['status_history']}</h2>")
            ->and($html)->toContain("{$book['statuses']['prospective']} → {$book['statuses']['active']}")
            ->and($html)->not->toContain('Academic transcript')
            ->and($html)->not->toContain('<th>Point</th>');
    }
});
