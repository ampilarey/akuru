<?php

/**
 * NAME_TAGS_PLAN slice NT0: a sample sheet for the print path — two name
 * tags on A4, one Thaana with fili, one Arabic, one Latin, each with a logo
 * as a data: URI — and the fakes that stand in for Chrome where there is
 * none (CI).
 */

use App\Support\Contracts\PageImageInterface;
use App\Support\Contracts\PdfConverterInterface;
use App\Support\Services\PrintFontFaces;

/** A 24×24 maroon square as a PNG data: URI, made with GD so no file is needed. */
function nt0LogoDataUri(): string
{
    $im = imagecreatetruecolor(24, 24);
    imagefill($im, 0, 0, imagecolorallocate($im, 0x7A, 0x1F, 0x2B));
    imagefilledellipse($im, 12, 12, 14, 14, imagecolorallocate($im, 0xE9, 0xD8, 0xB4));
    ob_start();
    imagepng($im);
    $png = (string) ob_get_clean();
    imagedestroy($im);

    return 'data:image/png;base64,'.base64_encode($png);
}

/** @return array<string, mixed> the data `documents.print-sheet` takes */
function nt0SampleSheet(string $fontCss, ?string $watermark = null): array
{
    $logo = nt0LogoDataUri();
    $tag = fn (float $x, float $y, array $elements) => ['x' => $x, 'y' => $y, 'w' => 90, 'h' => 50, 'background' => '#FFFFFF', 'elements' => [
        ['type' => 'rect', 'x' => 0, 'y' => 0, 'w' => 90, 'h' => 50, 'fill' => '#FFFFFF', 'stroke' => '#7A1F2B', 'stroke_mm' => 0.5, 'radius' => 3],
        ['type' => 'rect', 'x' => 0, 'y' => 40, 'w' => 90, 'h' => 10, 'fill' => '#E9D8B4'],
        ['type' => 'image', 'x' => 4, 'y' => 4, 'w' => 12, 'h' => 12, 'src' => $logo],
        ...$elements,
    ]];

    return [
        'title' => 'NT0 sample',
        'lang' => 'en',
        'fontCss' => $fontCss,
        'page' => ['w' => 210, 'h' => 297],
        'cropMarks' => true,
        'watermark' => $watermark,
        'tags' => [
            $tag(15, 15, [
                ['type' => 'text', 'x' => 5, 'y' => 18, 'w' => 80, 'h' => 16, 'text' => 'ޢާއިޝަތު ޝިފާ', 'family' => 'Noto Sans Thaana', 'size' => 22, 'weight' => 700, 'color' => '#7A1F2B', 'align' => 'center', 'dir' => 'auto'],
                ['type' => 'text', 'x' => 5, 'y' => 41, 'w' => 80, 'h' => 8, 'text' => 'ގްރޭޑް 3 · ދިވެހި', 'family' => 'Faruma', 'size' => 11, 'color' => '#2B1B1E', 'align' => 'center', 'dir' => 'auto'],
            ]),
            $tag(110, 15, [
                ['type' => 'text', 'x' => 5, 'y' => 18, 'w' => 80, 'h' => 16, 'text' => 'عائشة شفاء', 'family' => 'Amiri', 'size' => 22, 'weight' => 700, 'color' => '#7A1F2B', 'align' => 'center', 'dir' => 'auto'],
                ['type' => 'text', 'x' => 5, 'y' => 41, 'w' => 80, 'h' => 8, 'text' => 'الصف الثالث · اللغة العربية', 'family' => 'Cairo', 'size' => 11, 'color' => '#2B1B1E', 'align' => 'center', 'dir' => 'auto'],
            ]),
            $tag(15, 75, [
                ['type' => 'text', 'x' => 5, 'y' => 18, 'w' => 80, 'h' => 16, 'text' => 'Aishath Shifa', 'family' => 'Fredoka', 'size' => 22, 'weight' => 700, 'color' => '#7A1F2B', 'align' => 'center', 'dir' => 'auto'],
                ['type' => 'text', 'x' => 5, 'y' => 41, 'w' => 80, 'h' => 8, 'text' => 'Grade 3 · English', 'family' => 'Poppins', 'size' => 11, 'color' => '#2B1B1E', 'align' => 'center', 'dir' => 'auto'],
            ]),
        ],
    ];
}

function nt0FakePdf(): PdfConverterInterface
{
    return new class implements PdfConverterInterface
    {
        public array $seen = [];

        public function enabled(): bool
        {
            return true;
        }

        public function fromHtml(string $html): string
        {
            $this->seen[] = $html;

            return "%PDF-1.4 fake\n%%EOF";
        }
    };
}

function nt0FakePageImage(): PageImageInterface
{
    return new class implements PageImageInterface
    {
        public array $seen = [];

        public function enabled(): bool
        {
            return true;
        }

        public function fromHtml(string $html, int $widthPx, int $heightPx): string
        {
            $this->seen[] = [$html, $widthPx, $heightPx];

            return "\x89PNG\r\n\x1a\nfake";
        }
    };
}

/** The real Chrome on this machine, or null where there is none. */
function nt0Chrome(): ?string
{
    $chrome = getenv('DOCUMENTS_CHROME_PATH') ?: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome';

    return is_executable($chrome) ? $chrome : null;
}

/** The sample sheet as the HTML Chrome prints. */
function nt0SampleHtml(?string $watermark = null): string
{
    return app(\App\Support\Contracts\DocumentRendererInterface::class)->render('print-sheet', nt0SampleSheet(PrintFontFaces::forFile(), $watermark));
}

/** The Bookstore manager who runs the print desk (NT1). */
function printOffice(): \App\Domains\Identity\Models\User
{
    \Spatie\Permission\Models\Role::findOrCreate('super_admin', 'web');
    \Spatie\Permission\Models\Permission::findOrCreate('bookshop.manage', 'web');
    $office = \App\Domains\Identity\Models\User::factory()->create();
    $office->assignRole('super_admin');
    $office->givePermissionTo('bookshop.manage');

    return $office;
}

function printWeb()
{
    return test()->withoutLocalizationMiddleware();
}

/** Pretend this host can print: the two Chrome contracts answer with fakes. */
function printHostCanPrint(): void
{
    app()->instance(PdfConverterInterface::class, nt0FakePdf());
    app()->instance(PageImageInterface::class, nt0FakePageImage());
}
