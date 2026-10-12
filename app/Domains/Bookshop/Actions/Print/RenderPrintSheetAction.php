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
    public function __construct(private readonly DocumentRendererInterface $renderer, private readonly ReadPublicMediaAction $read, private readonly ResolvePublicMediaUrlAction $url) {}

    /**
     * @param  array<string, mixed>  $design  a validated design (PrintDesign::validate)
     * @param  list<array<string, string>>  $rows  one per tag: field key => value
     * @param  array{watermark?: ?string, crop_marks?: bool, lang?: string, title?: string, browser?: bool}  $options
     * @return list<string> one HTML document per sheet
     */
    public function execute(array $design, PrintLayout $layout, array $rows, array $options = []): array
    {
        $perSheet = max(1, $layout->tagsPerSheet());
        $sheets = [];
        $images = [];
        foreach (array_chunk($rows === [] ? [[]] : array_values($rows), $perSheet) as $chunk) {
            $tags = [];
            foreach ($chunk as $i => $row) {
                $col = $i % $layout->columns;
                $line = intdiv($i, $layout->columns);
                $tags[] = [
                    'x' => round($layout->margin_left_mm + $col * ($layout->tag_w_mm + $layout->gutter_x_mm), 2),
                    'y' => round($layout->margin_top_mm + $line * ($layout->tag_h_mm + $layout->gutter_y_mm), 2),
                    'w' => $layout->tag_w_mm,
                    'h' => $layout->tag_h_mm,
                    'background' => $this->colour($design['background']['color'] ?? '#FFFFFF', $design),
                    'elements' => $this->elements($design, $row, $images, (bool) ($options['browser'] ?? false)),
                ];
            }
            $sheets[] = $this->renderer->render('print-sheet', [
                'title' => (string) ($options['title'] ?? 'Print sheet'),
                'lang' => (string) ($options['lang'] ?? 'en'),
                'fontCss' => ! empty($options['browser']) ? PrintFontFaces::forBrowser() : PrintFontFaces::forFile(),
                'page' => ['w' => PrintLayout::PAGE_W, 'h' => PrintLayout::PAGE_H],
                'cropMarks' => (bool) ($options['crop_marks'] ?? ($layout->sheet_code === null)),
                'watermark' => $options['watermark'] ?? null,
                'tags' => $tags,
            ]);
        }

        return $sheets;
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
                        'size' => (int) $el['size'],
                        'weight' => (int) ($el['weight'] ?? 400),
                        'color' => $this->colour($el['color'] ?? '#111111', $design),
                        'align' => $el['align'] ?? 'center',
                        'dir' => $el['dir'] ?? 'auto',
                    ];
                    break;
                case 'image':
                    $src = $this->image((int) $el['media'], $images, $browser);
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
