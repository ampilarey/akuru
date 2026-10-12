<?php

use App\Support\Contracts\DocumentRendererInterface;
use App\Support\Contracts\PageImageInterface;
use App\Support\Contracts\PdfConverterInterface;
use App\Support\Services\ChromePageImage;
use App\Support\Services\ChromePdfConverter;
use App\Support\Services\PrintFontFaces;

/**
 * The print path works on this host (NAME_TAGS_PLAN §11 NT0, STATUS §5rn).
 *
 * Before any name tag is designed, sold or printed: the fonts a tag may be
 * set in are on disk with their licences; one Blade sheet lays tags out in
 * millimetres from the same markup the browser previews, Chrome prints and
 * Chrome photographs; and — where this machine has a Chrome — a Thaana name
 * with fili, an Arabic name and a Latin one come out of the real converter
 * in their own faces, loaded by `file://` path.
 */
it('ships every print font with its licence, and names what each can set', function () {
    $faces = PrintFontFaces::faces();
    expect($faces)->toHaveKeys(['faruma', 'noto-sans-thaana', 'amiri', 'cairo', 'figtree', 'poppins', 'nunito', 'fredoka', 'baloo-2', 'lora', 'merriweather', 'pacifico']);

    foreach (PrintFontFaces::files() as $path) {
        expect(is_file(public_path($path)))->toBeTrue($path);
    }
    foreach (config('print.fonts') as $id => $font) {
        if ($font['licence'] !== null) {
            expect((string) file_get_contents(public_path(($font['dir'] ?? config('print.fonts_dir')).'/'.$font['licence'])))->toContain('SIL OPEN FONT LICENSE');
        }
        expect($faces[$id]['scripts'])->not->toBeEmpty($id)
            ->and(in_array(400, $faces[$id]['weights'], true))->toBeTrue($id);
    }
    // Two faces can set Thaana; none of them is MV Boli.
    $thaana = array_keys(array_filter($faces, fn (array $f) => in_array('thaana', $f['scripts'], true)));
    expect($thaana)->toBe(['faruma', 'noto-sans-thaana'])
        ->and(json_encode($faces))->not->toContain('MV Boli');
});

it('writes the same @font-face rules for the browser and for a page opened from disk', function () {
    $browser = PrintFontFaces::forBrowser();
    $file = PrintFontFaces::forFile();

    expect($browser)->toContain("font-family: 'Noto Sans Thaana'; src: url('/fonts/print/noto-sans-thaana-thaana-700-normal.woff2')")
        ->toContain("url('/fonts/Faruma.woff2')")
        ->and($file)->toContain("src: url('file://".public_path('fonts/print/noto-sans-thaana-thaana-700-normal.woff2')."')")
        ->toContain("url('file://".public_path('fonts/Faruma.woff2')."')")
        ->and(substr_count($browser, '@font-face'))->toBe(substr_count($file, '@font-face'))->toBe(22);
});

it('lays the sample sheet out in millimetres, each text in its own direction, nothing fetched over a network', function () {
    $html = app(DocumentRendererInterface::class)->render('print-sheet', nt0SampleSheet(PrintFontFaces::forFile(), 'PROOF'));

    expect($html)->toContain('@page { size: 210mm 297mm; margin: 0; }')
        ->toContain('ޢާއިޝަތު ޝިފާ')->toContain('عائشة شفاء')->toContain('Aishath Shifa')
        ->toContain('dir="auto"')
        ->toContain("font-family: 'Noto Sans Thaana'; font-size: 22pt; font-weight: 700;")
        ->toContain('left: 110mm; top: 15mm; width: 90mm; height: 50mm;')
        ->toContain('src="data:image/png;base64,')
        ->toContain('class="watermark"')->toContain('PROOF')
        ->and(preg_match('#src="https?://#', $html))->toBe(0);
    // A crop mark at each tag corner, four per tag, two strokes each.
    expect(substr_count($html, 'class="crop"'))->toBe(3 * 4 * 2);
});

it('is disabled where no Chrome is configured, and the fakes stand in', function () {
    expect(app(PageImageInterface::class))->toBeInstanceOf(ChromePageImage::class)
        ->and((new ChromePageImage(null))->enabled())->toBeFalse()
        ->and((new ChromePageImage('/no/such/chrome'))->enabled())->toBeFalse()
        ->and(fn () => (new ChromePageImage(null))->fromHtml('<html></html>', 10, 10))->toThrow(RuntimeException::class);

    app()->instance(PdfConverterInterface::class, $pdf = nt0FakePdf());
    app()->instance(PageImageInterface::class, $png = nt0FakePageImage());
    $html = nt0SampleHtml();
    expect(app(PdfConverterInterface::class)->fromHtml($html))->toStartWith('%PDF')
        ->and(app(PageImageInterface::class)->fromHtml($html, 794, 1123))->toStartWith("\x89PNG")
        ->and($pdf->seen)->toHaveCount(1)->and($png->seen[0][1])->toBe(794);
});

it('prints the Thaana, Arabic and Latin tags with headless Chrome from a file:// page, where one is installed', function () {
    $chrome = nt0Chrome();
    if ($chrome === null) {
        $this->markTestSkipped('No Chrome on this host; the converter and the page image stay disabled, as the other tests show.');
    }

    $html = nt0SampleHtml('PROOF');
    $pdf = (new ChromePdfConverter($chrome, 120))->fromHtml($html);
    $png = (new ChromePageImage($chrome, 120))->fromHtml($html, 794, 1123);

    expect($pdf)->toStartWith('%PDF')->and(strlen($pdf))->toBeGreaterThan(5000)
        ->and(glob(storage_path('app/tmp/pdf/*')) ?: [])->toBe([]);
    // The faces are embedded by name: the fonts loaded from their file:// paths.
    foreach (['NotoSansThaana', 'Faruma', 'Amiri', 'Cairo', 'Fredoka', 'Poppins'] as $face) {
        expect($pdf)->toContain($face);
    }

    // A4 at 96 dpi, and not a blank page.
    expect($png)->toStartWith("\x89PNG");
    [$w, $h] = array_values(unpack('Nw/Nh', substr($png, 16, 8)));
    expect([$w, $h])->toBe([794, 1123]);
    $image = imagecreatefromstring($png);
    $colours = [];
    for ($x = 0; $x < 794; $x += 7) {
        for ($y = 0; $y < 1123; $y += 7) {
            $colours[imagecolorat($image, $x, $y)] = true;
        }
    }
    expect(count($colours))->toBeGreaterThan(20);
});
