<?php

use App\Support\Pdf\PdfEncodings;
use App\Support\Pdf\PdfFilters;
use App\Support\Pdf\PdfTextExtractor;

require_once __DIR__.'/../../Fixtures/pdf/make.php';

/**
 * `PdfTextExtractor` is the Library's page source for PDF originals
 * (LIBRARY_PLAN §36). No fixture, no database: bytes in, text out.
 *
 * Two of the fixtures are real producer output — a browser's print-to-PDF
 * (Type0 fonts, Identity-H, ToUnicode CMaps, Flate) and a PDF 1.6 file whose
 * objects live in object streams behind a cross-reference stream — and the
 * rest are built by hand to pin one parser behaviour each.
 */
$extract = fn (string $pdf): array => (new PdfTextExtractor)->pages($pdf);

it('reads a browser-printed PDF page by page, in three scripts', function () use ($extract) {
    $pages = $extract((string) file_get_contents(__DIR__.'/../../Fixtures/pdf/three-pages-chromium.pdf'));

    expect($pages)->toHaveCount(3)
        ->and($pages[0])->toStartWith("Chapter One\n\nThe quick brown fox jumps over the lazy dog. Ahlan wa sahlan.")
        // The ﬁ ligature and the curly quotes come out as their letters.
        ->and($pages[0])->toContain('Second paragraph with “quotes” and dashes – and filigrees.')
        ->and($pages[0])->not->toContain('Chapter Two')
        ->and($pages[1])->toContain('Chapter Two begins here on page two.')
        // Right-to-left lines read in logical order, with the Thaana vowel
        // signs on their consonants and the colon on its word.
        ->and($pages[1])->toContain('Arabic: بسم الله الرحمن الرحيم')
        ->and($pages[1])->toContain('Dhivehi: ދިވެހި ބަސް')
        ->and($pages[2])->toBe('Page three is short.');
});

it('reads a PDF whose objects sit in object streams behind a cross-reference stream', function () use ($extract) {
    $pages = $extract((string) file_get_contents(__DIR__.'/../../Fixtures/pdf/object-streams.pdf'));

    expect($pages)->toHaveCount(1)
        ->and($pages[0])->toStartWith('Lorem ipsum dolor sit amet, consectetur adipiscing elit.')
        // Wrapped lines of one paragraph are joined; paragraphs stay apart.
        ->and(substr_count($pages[0], "\n\n"))->toBeGreaterThanOrEqual(4)
        ->and($pages[0])->toContain('Donec ut magna magna.');
});

it('handles simple fonts: WinAnsi, Differences, TJ kerning, a nested page tree and inherited resources', function () use ($extract) {
    $pages = $extract(pdfSimpleFontTwoPages());

    expect($pages)->toHaveCount(2)
        // -20 is a kern, -400 is a space.
        ->and($pages[0])->toContain('Hello there')
        // \351 is é in WinAnsi; \256 is remapped to fi by /Differences, and
        // the ligature comes out as its two letters.
        ->and($pages[0])->toContain('café fi fin')
        // A 24pt line followed by a 12pt line is a heading and a paragraph.
        ->and($pages[0])->toContain("Heading\n\nBody after heading")
        ->and($pages[1])->toBe('Second page');
});

it('follows text into form XObjects and inflates compressed streams', function () use ($extract) {
    $pages = $extract(pdfFormXObject());

    expect($pages)->toHaveCount(1)
        ->and($pages[0])->toContain('on the page')
        ->and($pages[0])->toContain('inside the form');
});

it('returns an empty string for a page without text, and keeps the page in its place', function () use ($extract) {
    expect($extract(pdfNoText()))->toBe([''])
        ->and($extract(pdfTextThenBlank()))->toBe(['Words on one', '']);
});

it('steps over a wrong stream length and an inline image', function () use ($extract) {
    $pages = $extract(pdfBrokenLengthAndInlineImage());

    expect($pages)->toHaveCount(1)
        ->and($pages[0])->toContain('before image')
        ->and($pages[0])->toContain('after image')
        ->and($pages[0])->not->toContain('EI');
});

it('yields no pages, and no exception, for bytes that are not a PDF', function () use ($extract) {
    expect($extract(''))->toBe([])
        ->and($extract('not a pdf at all'))->toBe([])
        ->and($extract(random_bytes(2048)))->toBe([]);
});

it('decodes the lossless filters', function () {
    expect(PdfFilters::asciiHex('48656C6C6F>'))->toBe('Hello')
        ->and(PdfFilters::ascii85('87cURD_*#4DfTZ)~>'))->toBe('Hello, World')
        ->and(PdfFilters::runLength("\x02abc\xFEz\x80"))->toBe('abczzz')
        ->and(PdfFilters::lzw("\x80\x0B\x60\x50\x22\x0C\x0C\x85\x01"))->toBe('-----A---B');
});

it('knows glyph names, including composed accents and uniXXXX', function () {
    expect(PdfEncodings::glyph('eacute'))->toBe('é')
        ->and(PdfEncodings::glyph('Ntilde'))->toBe('Ñ')
        ->and(PdfEncodings::glyph('quotedblleft'))->toBe('“')
        ->and(PdfEncodings::glyph('uni078B'))->toBe('ދ')
        ->and(PdfEncodings::glyph('a.sc'))->toBe('a')
        ->and(PdfEncodings::glyph('g123'))->toBeNull();
});
