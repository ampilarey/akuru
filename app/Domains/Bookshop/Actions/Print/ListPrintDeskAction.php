<?php

namespace App\Domains\Bookshop\Actions\Print;

use App\Domains\Bookshop\Models\PrintFinishingOption;
use App\Domains\Bookshop\Models\PrintLayout;
use App\Domains\Bookshop\Models\PrintPaperOption;
use App\Domains\Bookshop\Models\PrintSubjectSet;
use App\Domains\Bookshop\Models\PrintTemplate;
use App\Domains\Bookshop\Support\PrintDesk;
use App\Domains\Bookshop\Support\PrintProducts;
use App\Domains\Bookshop\Support\PrintSheet;

/** NAME_TAGS_PLAN §4 (NT1): everything the print desk's office page shows, and its CSVs. */
class ListPrintDeskAction
{
    /** @return array<string, mixed> */
    public function execute(): array
    {
        [, $product] = PrintDesk::ensure();
        $why = PrintDesk::cannotEnableBecause();

        return [
            'settings' => PrintDesk::settings(),
            'can_enable' => $why === null,
            'cannot_enable_because' => $why === null ? null : __($why),
            'product' => $product === null ? null : ['slug' => $product->slug, 'title' => $product->title, 'status' => $product->status->value],
            'vendor' => ($v = PrintDesk::vendor()) === null ? null : ['name' => $v->name, 'slug' => $v->slug],
            'papers' => PrintPaperOption::query()->orderBy('sort_order')->orderBy('id')->get()->map(fn (PrintPaperOption $p) => [
                'id' => $p->id, 'name' => $p->name, 'name_dv' => $p->name_dv, 'name_ar' => $p->name_ar, 'gsm' => $p->gsm, 'kind' => $p->kind, 'sheet_size' => $p->sheet_size,
                'price_bw' => (string) $p->price_bw, 'price_colour' => (string) $p->price_colour, 'is_active' => $p->is_active, 'sort_order' => $p->sort_order,
            ])->values()->all(),
            'finishing' => PrintFinishingOption::query()->orderBy('sort_order')->orderBy('id')->get()->map(fn (PrintFinishingOption $f) => [
                'id' => $f->id, 'name' => $f->name, 'name_dv' => $f->name_dv, 'name_ar' => $f->name_ar, 'price' => (string) $f->price,
                'cuts_sheet' => $f->cuts_sheet, 'is_active' => $f->is_active, 'sort_order' => $f->sort_order,
            ])->values()->all(),
            'layouts' => PrintLayout::query()->orderBy('sort_order')->orderBy('id')->get()->map(fn (PrintLayout $l) => [
                'id' => $l->id, 'name' => $l->name, 'print_kind' => $l->print_kind, 'shape' => $l->shape, 'sheet_size' => $l->sheet_size, 'columns' => $l->columns, 'rows' => $l->rows, 'tags' => $l->tagsPerSheet(),
                'tag_w_mm' => $l->tag_w_mm, 'tag_h_mm' => $l->tag_h_mm, 'margin_top_mm' => $l->margin_top_mm, 'margin_left_mm' => $l->margin_left_mm,
                'gutter_x_mm' => $l->gutter_x_mm, 'gutter_y_mm' => $l->gutter_y_mm, 'bleed_mm' => $l->bleed_mm, 'sheet_code' => $l->sheet_code, 'is_active' => $l->is_active, 'sort_order' => $l->sort_order,
            ])->values()->all(),
            'subject_sets' => PrintSubjectSet::query()->orderBy('sort_order')->orderBy('id')->get()->map(fn (PrintSubjectSet $s) => [
                'id' => $s->id, 'label' => $s->label, 'label_dv' => $s->label_dv, 'label_ar' => $s->label_ar,
                'subjects' => $s->subjects, 'count' => count((array) $s->subjects), 'is_active' => $s->is_active, 'sort_order' => $s->sort_order,
                'subjects_en' => implode("\n", array_column((array) $s->subjects, 'en')),
                'subjects_dv' => implode("\n", array_column((array) $s->subjects, 'dv')),
                'subjects_ar' => implode("\n", array_column((array) $s->subjects, 'ar')),
            ])->values()->all(),
            'kinds' => PrintPaperOption::KINDS,
            // PP1: the sheet sizes a paper comes in and a layout is laid out on.
            'sheet_sizes' => PrintSheet::codes(),
            // PP2: every print product, on sale or not, with what it has to print on.
            'products' => collect(PrintProducts::kinds())->map(fn (string $k) => [
                'kind' => $k, 'title' => PrintProducts::title($k, app()->getLocale()), 'on_sale' => PrintProducts::open($k),
                'layouts' => PrintLayout::query()->where('is_active', true)->where('print_kind', $k)->count(),
                'templates' => PrintTemplate::query()->where('status', 'active')->where('print_kind', $k)->count(),
            ])->values()->all(),
        ];
    }

    /** @return array{0: list<string>, 1: list<list<mixed>>} a CSV's header and rows */
    public function csv(string $list): array
    {
        $data = $this->execute();

        return match ($list) {
            'papers' => [['name', 'name_dv', 'name_ar', 'gsm', 'kind', 'sheet_size', 'price_bw', 'price_colour', 'active'], array_map(fn ($p) => [$p['name'], $p['name_dv'], $p['name_ar'], $p['gsm'], $p['kind'], $p['sheet_size'], $p['price_bw'], $p['price_colour'], $p['is_active'] ? 'yes' : 'no'], $data['papers'])],
            'finishing' => [['name', 'name_dv', 'name_ar', 'price', 'cuts_sheet', 'active'], array_map(fn ($f) => [$f['name'], $f['name_dv'], $f['name_ar'], $f['price'], $f['cuts_sheet'] ? 'yes' : 'no', $f['is_active'] ? 'yes' : 'no'], $data['finishing'])],
            'layouts' => [['name', 'product', 'shape', 'sheet_size', 'columns', 'rows', 'tags', 'tag_w_mm', 'tag_h_mm', 'margin_top_mm', 'margin_left_mm', 'gutter_x_mm', 'gutter_y_mm', 'bleed_mm', 'sheet_code', 'active'], array_map(fn ($l) => [$l['name'], $l['print_kind'], $l['shape'], $l['sheet_size'], $l['columns'], $l['rows'], $l['tags'], $l['tag_w_mm'], $l['tag_h_mm'], $l['margin_top_mm'], $l['margin_left_mm'], $l['gutter_x_mm'], $l['gutter_y_mm'], $l['bleed_mm'], $l['sheet_code'], $l['is_active'] ? 'yes' : 'no'], $data['layouts'])],
            default => [['label', 'label_dv', 'label_ar', 'subjects', 'active'], array_map(fn ($s) => [$s['label'], $s['label_dv'], $s['label_ar'], implode('; ', array_column($s['subjects'], 'en')), $s['is_active'] ? 'yes' : 'no'], $data['subject_sets'])],
        };
    }
}
