<?php

namespace App\Domains\Bookshop\Actions\Print;

use App\Domains\Bookshop\Models\PrintImage;
use App\Domains\Bookshop\Models\PrintLayout;
use App\Domains\Bookshop\Models\PrintTemplate;
use App\Domains\Bookshop\Support\PrintDesign;
use App\Domains\Media\Actions\ResolvePublicImageVariantAction;
use App\Domains\Media\Actions\ResolvePublicMediaUrlAction;
use App\Support\Services\PrintFontFaces;

/** NAME_TAGS_PLAN §4.7 (NT2): the office's templates, and what the designer needs to draw one. */
class ListPrintTemplatesAction
{
    /** @return list<array<string, mixed>> */
    public function execute(): array
    {
        return PrintTemplate::query()->with('layout')->orderBy('sort_order')->orderBy('id')->get()
            ->map(fn (PrintTemplate $t) => $this->present($t))->values()->all();
    }

    /** @return array<string, mixed> */
    public function present(PrintTemplate $t): array
    {
        return [
            'id' => $t->id, 'title' => $t->title, 'title_dv' => $t->title_dv, 'title_ar' => $t->title_ar, 'kind' => $t->kind,
            'print_layout_id' => $t->print_layout_id, 'layout' => $t->layout?->name, 'status' => $t->status, 'uses_count' => $t->uses_count,
            'sort_order' => $t->sort_order, 'fields' => PrintDesign::fieldKeys((array) $t->design), 'design' => $t->design,
            'updated_at' => $t->updated_at?->toDateString(),
        ];
    }

    /** What the designer page needs besides the template: layouts, fonts, the image library, the font CSS. @return array<string, mixed> */
    public function designerProps(): array
    {
        $thumb = app(ResolvePublicImageVariantAction::class);
        $full = app(ResolvePublicMediaUrlAction::class);

        return [
            'layouts' => PrintLayout::query()->where('is_active', true)->orderBy('sort_order')->get()
                ->map(fn (PrintLayout $l) => ['id' => $l->id, 'name' => $l->name, 'tag_w_mm' => $l->tag_w_mm, 'tag_h_mm' => $l->tag_h_mm, 'tags' => $l->tagsPerSheet()])->values()->all(),
            'fonts' => PrintFontFaces::faces(),
            'font_css' => PrintFontFaces::forBrowser(),
            'images' => PrintImage::query()->orderBy('sort_order')->orderBy('id')->get()->map(fn (PrintImage $i) => [
                'id' => $i->id, 'media' => (int) $i->media_file_id, 'alt' => $i->alt,
                'thumb' => $thumb->execute((int) $i->media_file_id, 240), 'url' => $full->execute((int) $i->media_file_id),
            ])->values()->all(),
            'field_keys' => PrintDesign::FIELD_KEYS,
            'kinds' => PrintTemplate::KINDS,
            'max_elements' => PrintDesign::MAX_ELEMENTS,
        ];
    }

    /** A sample row to draw a template with: every field filled with its label. @return array<string, string> */
    public static function sampleRow(string $locale): array
    {
        $row = [];
        foreach (PrintDesign::FIELD_KEYS as $key) {
            $row[$key] = (string) __('print.sample_'.$key, [], $locale);
        }

        return $row;
    }
}
