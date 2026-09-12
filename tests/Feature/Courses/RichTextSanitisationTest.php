<?php

use App\Domains\Courses\Actions\ValidateContentBlockDataAction;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * SPEC §14: "The `data` JSON must be validated per block type … Do not allow
 * unvalidated arbitrary JSON."
 *
 * Rich-text blocks were sanitised with
 * `strip_tags($html, '<p><br><strong><em><ul><ol><li><h2><h3><a>')`, which
 * removes disallowed **tags** and keeps every **attribute** on the ones it
 * allows. All of these survived intact:
 *
 *     <a href="javascript:alert(1)">click</a>
 *     <p onclick="alert(1)">text</p>
 *     <a href="#" onmouseover="alert(1)">hover</a>
 *
 * The stored value is rendered with `dangerouslySetInnerHTML` in the lesson
 * player (`Courses/Player/Show.jsx`), so it ran in the browser of every student
 * and teacher who opened the lesson.
 *
 * Block authoring is gated to `super_admin|admin|headmaster`, so this was not
 * reachable by a low-privileged account. It still matters: §34 plans for Course
 * Creators to author blocks, and content pasted in from elsewhere should not be
 * able to execute either way.
 */
uses(RefreshDatabase::class);

function sanitisedHtml(string $html): string
{
    return app(ValidateContentBlockDataAction::class)
        ->execute('rich_text', ['html' => $html])['data']['html'];
}

it('keeps the formatting a lesson actually needs', function () {
    $clean = sanitisedHtml('<p>Recite <strong>slowly</strong> and <em>clearly</em>.</p><ul><li>One</li></ul>');

    expect($clean)->toContain('<strong>slowly</strong>')
        ->and($clean)->toContain('<em>clearly</em>')
        ->and($clean)->toContain('<li>One</li>');
});

it('strips event handlers from allowed tags', function () {
    $clean = sanitisedHtml('<p onclick="alert(1)">text</p>');

    // The paragraph survives; the handler does not.
    expect($clean)->toContain('text')
        ->and($clean)->not->toContain('onclick');
})->with([
    'onclick', 'onmouseover', 'onerror', 'onload', 'onfocus',
]);

it('refuses a javascript: link', function () {
    $clean = sanitisedHtml('<a href="javascript:alert(1)">click</a>');

    // The text stays — an author wrote it — but the link cannot execute.
    expect($clean)->toContain('click')
        ->and($clean)->not->toContain('javascript');
});

it('refuses obfuscated javascript: links', function (string $href) {
    $clean = sanitisedHtml('<a href="'.$href.'">click</a>');

    expect(strtolower($clean))->not->toContain('javascript')
        ->and(strtolower($clean))->not->toContain('vbscript')
        ->and($clean)->not->toContain('href=');
})->with([
    'whitespace' => ' javascript:alert(1)',
    'tab entity' => 'java&#09;script:alert(1)',
    'newline' => "java\nscript:alert(1)",
    'uppercase' => 'JaVaScRiPt:alert(1)',
    'vbscript' => 'vbscript:msgbox(1)',
    'data uri' => 'data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==',
]);

it('keeps ordinary links', function (string $href) {
    $clean = sanitisedHtml('<a href="'.$href.'">read more</a>');

    expect($clean)->toContain('href="'.$href.'"');
})->with([
    'https' => 'https://example.test/guide',
    'relative' => '/learn/courses/1',
    'anchor' => '#tajweed',
    'mailto' => 'mailto:office@example.test',
]);

it('unwraps disallowed tags but keeps the words inside them', function () {
    $clean = sanitisedHtml('<div><span>Important</span> note</div>');

    // Deleting the node would silently lose the author's text.
    expect($clean)->toContain('Important')
        ->and($clean)->toContain('note')
        ->and($clean)->not->toContain('<div')
        ->and($clean)->not->toContain('<span');
});

it('drops a script tag and its contents', function () {
    $clean = sanitisedHtml('<p>Before</p><script>alert(1)</script><p>After</p>');

    expect($clean)->toContain('Before')
        ->and($clean)->toContain('After')
        ->and($clean)->not->toContain('<script')
        // `strip_tags` used to leave `alert(1)` behind as loose text.
        ->and($clean)->not->toContain('alert(1)');
});

it('drops an iframe even though it carries no text', function () {
    $clean = sanitisedHtml('<p>Watch</p><iframe src="https://evil.test"></iframe>');

    expect($clean)->toContain('Watch')
        ->and($clean)->not->toContain('iframe')
        ->and($clean)->not->toContain('evil.test');
});

it('keeps Dhivehi and Arabic text intact', function () {
    // The sanitiser round-trips through DOMDocument, which mangles multibyte
    // content without an encoding hint.
    $clean = sanitisedHtml('<p>އަކުރު</p><p>بِسْمِ ٱللَّٰهِ</p>');

    expect($clean)->toContain('އަކުރު')
        ->and($clean)->toContain('بِسْمِ');
});

it('strips a style attribute', function () {
    $clean = sanitisedHtml('<p style="position:fixed;top:0;width:100%">overlay</p>');

    // An absolutely-positioned block over the page is a defacement even
    // without script.
    expect($clean)->toContain('overlay')
        ->and($clean)->not->toContain('style');
});
