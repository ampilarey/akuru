<?php

namespace App\Domains\Bookshop\Actions\Print;

use App\Domains\Bookshop\Models\PrintLayout;
use App\Domains\Media\Actions\ReadPublicMediaAction;
use App\Domains\Media\Actions\ResolvePublicMediaUrlAction;
use App\Support\Contracts\DocumentRendererInterface;
use App\Support\Services\PrintFontFaces;

/**
 * NAME_TAGS_PLAN §5.2 (NT2): one render for every output. A design laid
 * out on a layout, once per row of the list (or once per tag for the same
 * tag × N), becomes the A4 sheets' HTML through the one document renderer.
 * Images are read into `data:` URIs so nothing is fetched over a network;
 * a palette token becomes its scheme's colour; a `{field}` becomes the
 * row's value.
 */
class RenderPrintSheetAction
{
    /** NT5: the customer's own artwork, as a `data:` URI, for an element marked `upload`. */
    private ?string $upload = null;

    public function __construct(private readonly DocumentRendererInterface $renderer, private readonly ReadPublicMediaAction $read, private readonly ResolvePublicMediaUrlAction $url) {}

    /**
     * @param  array<string, mixed>  $design  a validated design (PrintDesign::validate)
     * @param  list<array<string, string>>  $rows  one per tag: field key => value
     * @param  array{watermark?: ?string, crop_marks?: bool, lang?: string, title?: string, browser?: bool, grayscale?: bool, upload?: string}  $options
     * @return list<string> one HTML document per sheet
     */
    public function execute(array $design, PrintLayout $layout, array $rows, array $options = []): array
    {
        return array_map(fn (array $tags) => $this->render([$tags], $layout, $options), $this->sheets($design, $layout, $rows, $options));
    }

    /**
     * Every sheet in one HTML document, a page each — the print file Chrome
     * turns into one PDF (NT3).
     *
     * @param  array<string, mixed>  $design
     * @param  list<array<string, string>>  $rows
     * @param  array{watermark?: ?string, crop_marks?: bool, lang?: string, title?: string, browser?: bool, grayscale?: bool}  $options
     */
    public function document(array $design, PrintLayout $layout, array $rows, array $options = []): string
    {
        return $this->render($this->sheets($design, $layout, $rows, $options), $layout, $options);
    }

    /** @return list<list<array<string, mixed>>> each sheet's tags, placed on the layout */
    private function sheets(array $design, PrintLayout $layout, array $rows, array $options): array
    {
        $this->upload = isset($options['upload']) && is_string($options['upload']) ? $options['upload'] : null;
        $perSheet = max(1, $layout->tagsPerSheet());
        // PP3: with a bleed, each tag is drawn that much larger on every side,
        // and what touches the trim runs on into the bleed, so the cut shows no white edge.
        $bleed = max(0.0, (float) $layout->bleed_mm);
        $tagW = (float) ($design['tag']['w'] ?? $layout->tag_w_mm);
        $tagH = (float) ($design['tag']['h'] ?? $layout->tag_h_mm);
        $sheets = [];
        $images = [];
        foreach (array_chunk($rows === [] ? [[]] : array_values($rows), $perSheet) as $chunk) {
            $tags = [];
            foreach ($chunk as $i => $row) {
                $col = $i % $layout->columns;
                $line = intdiv($i, $layout->columns);
                $tags[] = [
                    'x' => round($layout->margin_left_mm + $col * ($layout->tag_w_mm + $layout->gutter_x_mm) - $bleed, 2),
                    'y' => round($layout->margin_top_mm + $line * ($layout->tag_h_mm + $layout->gutter_y_mm) - $bleed, 2),
                    'w' => round($layout->tag_w_mm + 2 * $bleed, 2),
                    'h' => round($layout->tag_h_mm + 2 * $bleed, 2),
                    // PP4: a round sticker prints as its circle, its bleed included.
                    'round' => $layout->shape === 'circle',
                    'background' => $this->colour($design['background']['color'] ?? '#FFFFFF', $design),
                    'elements' => self::bleed($this->elements($design, $row, $images, (bool) ($options['browser'] ?? false)), $tagW, $tagH, $bleed),
                ];
            }
            $sheets[] = $tags;
        }

        return $sheets;
    }

    /**
     * PP3: the elements moved in by the bleed, and a box or picture that
     * touches a trim edge stretched out to the bleed edge — a band printed to
     * the edge stays to the edge after the cut. Text, ellipses, lines and
     * anything turned stay as drawn.
     *
     * @param  list<array<string, mixed>>  $elements
     * @return list<array<string, mixed>>
     */
    public static function bleed(array $elements, float $w, float $h, float $bleed): array
    {
        if ($bleed <= 0) {
            return $elements;
        }
        $edge = 0.01;

        return array_map(function (array $el) use ($w, $h, $bleed, $edge) {
            [$x, $y, $ew, $eh] = [(float) $el['x'], (float) $el['y'], (float) $el['w'], (float) $el['h']];
            if (in_array($el['type'], ['rect', 'image'], true) && empty($el['rotate'])) {
                if ($x <= $edge) {
                    $ew += $bleed + $x;
                    $x = -$bleed;
                }
                if ($y <= $edge) {
                    $eh += $bleed + $y;
                    $y = -$bleed;
                }
                if ($x + $ew >= $w - $edge) {
                    $ew = $w + $bleed - $x;
                }
                if ($y + $eh >= $h - $edge) {
                    $eh = $h + $bleed - $y;
                }
            }

            return ['x' => round($x + $bleed, 2), 'y' => round($y + $bleed, 2), 'w' => round($ew, 2), 'h' => round($eh, 2)] + $el;
        }, $elements);
    }

    /**
     * PP3: crop marks for a layout with a bleed — at the sheet's outer edges
     * only, in line with every cut, so no mark lands on a neighbour's bleed.
     *
     * @return list<array{x: float, y: float, w: float, h: float}>
     */
    public static function outerMarks(PrintLayout $layout): array
    {
        $bleed = (float) $layout->bleed_mm;
        [$pw, $ph] = $layout->sheetMm();
        $xs = [];
        $ys = [];
        for ($c = 0; $c < $layout->columns; $c++) {
            $left = $layout->margin_left_mm + $c * ($layout->tag_w_mm + $layout->gutter_x_mm);
            array_push($xs, $left, $left + $layout->tag_w_mm);
        }
        for ($r = 0; $r < $layout->rows; $r++) {
            $top = $layout->margin_top_mm + $r * ($layout->tag_h_mm + $layout->gutter_y_mm);
            array_push($ys, $top, $top + $layout->tag_h_mm);
        }
        [$gridW, $gridH] = $layout->footprint();
        $marks = [];
        $long = 4.0;
        $gap = $bleed + 1.0;
        foreach (array_unique($xs) as $x) {
            // Above the first row and below the last, clear of the bleed.
            $top = max(0.0, $layout->margin_top_mm - $gap - $long);
            $marks[] = ['x' => round($x, 2), 'y' => round($top, 2), 'w' => 0.2, 'h' => round(max(0.0, $layout->margin_top_mm - $gap - $top), 2)];
            $bottom = $gridH + $gap;
            $marks[] = ['x' => round($x, 2), 'y' => round($bottom, 2), 'w' => 0.2, 'h' => round(max(0.0, min($long, $ph - $bottom)), 2)];
        }
        foreach (array_unique($ys) as $y) {
            $left = max(0.0, $layout->margin_left_mm - $gap - $long);
            $marks[] = ['x' => round($left, 2), 'y' => round($y, 2), 'w' => round(max(0.0, $layout->margin_left_mm - $gap - $left), 2), 'h' => 0.2];
            $right = $gridW + $gap;
            $marks[] = ['x' => round($right, 2), 'y' => round($y, 2), 'w' => round(max(0.0, min($long, $pw - $right)), 2), 'h' => 0.2];
        }

        return array_values(array_filter($marks, fn (array $m) => $m['w'] > 0 && $m['h'] > 0));
    }

    /** @param  list<list<array<string, mixed>>>  $sheets */
    private function render(array $sheets, PrintLayout $layout, array $options): string
    {
        // PP4: a round sticker is kiss-cut along its own outline; marks on the sheet would only be in the way.
        $crop = (bool) ($options['crop_marks'] ?? ($layout->sheet_code === null)) && $layout->shape !== 'circle';

        return $this->renderer->render('print-sheet', [
            'title' => (string) ($options['title'] ?? 'Print sheet'),
            'lang' => (string) ($options['lang'] ?? 'en'),
            'fontCss' => ! empty($options['browser']) ? PrintFontFaces::forBrowser() : PrintFontFaces::forFile(),
            'page' => ['w' => $layout->sheetMm()[0], 'h' => $layout->sheetMm()[1]],
            'cropMarks' => $crop && (float) $layout->bleed_mm <= 0,
            'outerMarks' => $crop && (float) $layout->bleed_mm > 0 ? self::outerMarks($layout) : [],
            'watermark' => $options['watermark'] ?? null,
            'grayscale' => (bool) ($options['grayscale'] ?? false),
            'tags' => $sheets[0] ?? [],
            'sheets' => $sheets,
        ]);
    }

    /** How many tags a list makes, and how many sheets. @return array{tags: int, sheets: int} */
    public static function count(PrintLayout $layout, int $tags): array
    {
        return ['tags' => $tags, 'sheets' => (int) ceil($tags / max(1, $layout->tagsPerSheet()))];
    }

    /** @param  array<int, string>  $images  cache of data: URIs by media id */
    private function elements(array $design, array $row, array &$images, bool $browser): array
    {
        $out = [];
        foreach ((array) ($design['elements'] ?? []) as $el) {
            $scale = [];
            // The design's tag may differ from the layout's; elements scale with the tag.
            $item = ['type' => $el['type'], 'x' => $el['x'], 'y' => $el['y'], 'w' => $el['w'], 'h' => $el['h'], 'rotate' => $el['rotate'] ?? 0];
            switch ($el['type']) {
                case 'text':
                    $text = (string) $el['text'];
                    if (! empty($el['field'])) {
                        $value = $row[$el['field']] ?? null;
                        $text = $value === null || $value === '' ? str_replace('{'.$el['field'].'}', '', $text) : str_replace('{'.$el['field'].'}', (string) $value, $text);
                    }
                    $text = preg_replace_callback('/\{([a-z0-9_]+)\}/', fn ($m) => (string) ($row[$m[1]] ?? ''), $text);
                    $item += [
                        'text' => $text,
                        'family' => (string) (config('print.fonts.'.$el['font'].'.family') ?? 'sans-serif'),
                        'stack' => PrintFontFaces::stack((string) $el['font']),
                        'size' => (int) $el['size'],
                        'weight' => (int) ($el['weight'] ?? 400),
                        'color' => $this->colour($el['color'] ?? '#111111', $design),
                        'align' => $el['align'] ?? 'center',
                        'dir' => $el['dir'] ?? 'auto',
                    ];
                    break;
                case 'image':
                    $src = ! empty($el['upload']) ? $this->upload : $this->image((int) $el['media'], $images, $browser);
                    if ($src === null) {
                        continue 2;
                    }
                    $item += ['src' => $src, 'fit' => $el['fit'] ?? 'contain'];
                    break;
                case 'rect':
                case 'ellipse':
                    $item += ['fill' => $this->colour($el['fill'] ?? null, $design) ?? 'transparent', 'stroke' => $this->colour($el['stroke'] ?? null, $design), 'stroke_mm' => $el['stroke_mm'] ?? 0.3, 'radius' => $el['radius'] ?? 0];
                    break;
                case 'line':
                    $item += ['stroke' => $this->colour($el['stroke'] ?? '#111111', $design)];
                    break;
            }
            $out[] = $item;
        }

        return $out;
    }

    private function colour(?string $value, array $design): ?string
    {
        if ($value === null) {
            return null;
        }
        if ($value === '{primary}' || $value === '{secondary}') {
            $scheme = $design['palette']['schemes'][$design['palette']['scheme'] ?? ''] ?? ['primary' => '#7A1F2B', 'secondary' => '#E9D8B4'];

            return (string) $scheme[trim($value, '{}')];
        }

        return $value;
    }

    /** A public image as a data: URI (the print file) or its URL (the browser's preview); a private or missing one is left out, never fetched. */
    private function image(int $mediaId, array &$images, bool $browser): ?string
    {
        if (array_key_exists($mediaId, $images)) {
            return $images[$mediaId];
        }
        if ($browser) {
            return $images[$mediaId] = $this->url->execute($mediaId);
        }
        $file = $this->read->execute($mediaId);

        return $images[$mediaId] = $file === null ? null : 'data:'.$file['mime'].';base64,'.base64_encode($file['content']);
    }
}
