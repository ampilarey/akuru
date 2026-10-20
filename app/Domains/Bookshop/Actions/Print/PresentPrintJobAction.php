<?php

namespace App\Domains\Bookshop\Actions\Print;

use App\Domains\Bookshop\Enums\PrintJobStatus;
use App\Domains\Bookshop\Models\OrderItem;
use App\Domains\Bookshop\Models\PrintFinishingOption;
use App\Domains\Bookshop\Models\PrintJob;
use App\Domains\Bookshop\Models\PrintLayout;
use App\Domains\Bookshop\Models\PrintPaperOption;
use App\Domains\Bookshop\Models\PrintSubjectSet;
use App\Domains\Bookshop\Models\PrintTemplate;
use App\Domains\Bookshop\Support\PrintDesign;
use App\Domains\Bookshop\Support\PrintDesk;
use App\Domains\Bookshop\Support\PrintJobEntry;
use App\Domains\Bookshop\Support\PrintLine;
use App\Domains\Bookshop\Support\PrintProducts;
use App\Domains\Bookshop\Support\PrintSheet;
use App\Domains\Media\Actions\ResolvePublicMediaUrlAction;
use App\Support\Contracts\PdfRasterizerInterface;
use App\Support\Services\PrintFontFaces;
use Illuminate\Support\Facades\URL;

/**
 * NAME_TAGS_PLAN §3 (NT3): what the customer's pages show — the door's
 * gallery, one job with everything its page needs to choose from, and
 * *My designs*. Proof pictures are reached only by signed links that last
 * minutes (decision D6); the print file has no customer link at all.
 */
class PresentPrintJobAction
{
    /** Minutes a proof picture's link works for. */
    public const PROOF_LINK_MINUTES = 15;

    /** @return list<array<string, mixed>> the active templates, for the door */
    public function gallery(string $locale, string $kind = PrintProducts::DEFAULT): array
    {
        // PP2: the templates of one print product.
        return PrintTemplate::query()->with('layout')->where('status', 'active')->where('print_kind', $kind)->orderBy('sort_order')->orderBy('id')->get()
            ->map(fn (PrintTemplate $t) => [
                'id' => $t->id, 'title' => $t->titleIn($locale), 'kind' => $t->kind, 'shape' => $t->layout?->shape ?? 'rect', 'design' => $t->design,
                'fields' => PrintDesign::fieldKeys((array) $t->design), 'layout' => $t->layout?->name,
            ])->values()->all();
    }

    /** @return array<string, mixed> one job, with what its page offers */
    /** @return array{number: string, status: string}|null */
    private function order(PrintJob $job): ?array
    {
        $line = OrderItem::query()->where('print_job_id', $job->id)->with('order:id,number,status')->latest('id')->first();
        if ($line?->order === null) {
            return null;
        }

        return ['number' => (string) $line->order->number, 'status' => $line->order->status instanceof \BackedEnum ? $line->order->status->value : (string) $line->order->status];
    }

    public function job(PrintJob $job, string $locale): array
    {
        $design = (array) $job->design;
        $warnings = [];
        foreach ((array) ($job->preflight['warnings'] ?? []) as $w) {
            $vars = (array) ($w['vars'] ?? []);
            if (isset($vars['field'])) {
                $vars['field'] = __('print.field_'.$vars['field']) === 'print.field_'.$vars['field'] ? (string) $vars['field'] : __('print.field_'.$vars['field']);
            }
            $warnings[] = __((string) $w['key'], $vars);
        }
        $layout = PrintLayout::query()->find($job->print_layout_id);

        return [
            'id' => $job->id,
            'name' => $job->name,
            'status' => $job->status->value,
            'open' => $job->isOpen(),
            'template' => $job->template?->titleIn($locale),
            'design' => $design,
            'fields' => array_map(fn (array $f) => ['key' => $f['key'], 'required' => (bool) $f['required'], 'label' => $this->fieldLabel($f['key'])], (array) ($design['fields'] ?? [])),
            'schemes' => (array) ($design['palette']['schemes'] ?? []),
            'scheme' => $design['palette']['scheme'] ?? null,
            'font' => PrintDesign::fieldFont($design),
            'entry' => $job->entry,
            'layout_id' => $job->print_layout_id,
            'paper_id' => $job->print_paper_option_id,
            'colour' => (bool) $job->colour,
            'finishing' => array_map('intval', (array) $job->finishing),
            'sheets' => (int) $job->sheets,
            'tags' => $layout === null ? 0 : count(PrintJobEntry::rows((array) $job->entry, $layout, (int) $job->sheets)),
            'per_sheet_tags' => $layout?->tagsPerSheet() ?? 0,
            'price' => $job->price_breakdown,
            'held' => $job->approved_price_snapshot,
            // NT4: where it stands with the basket and the order.
            'buyable' => PrintLine::blocker($job, (int) $job->sheets) === null && ! AddPrintJobToCartAction::inOpenOrder((int) $job->id),
            'lapsed' => $job->status === PrintJobStatus::Approved && ! PrintLine::held($job),
            'order' => $this->order($job),
            'warnings' => $warnings,
            // NT5: a job from the customer's own artwork — what was read from it, and what stops it.
            'source' => (string) $job->source,
            'upload' => $job->isUpload() ? [
                'has_file' => $job->upload_document_id !== null,
                'kind' => $job->upload_kind,
                'type' => $job->upload_meta['type'] ?? null,
                'w' => $job->upload_meta['w'] ?? null,
                'h' => $job->upload_meta['h'] ?? null,
                'pages' => $job->upload_meta['pages'] ?? null,
                'dpi' => $job->preflight['dpi'] ?? null,
                'refusals' => array_map(fn ($r) => __((string) $r['key'], (array) ($r['vars'] ?? [])), (array) ($job->preflight['refusals'] ?? [])),
                'declared_at' => $job->declared_at?->toIso8601String(),
                'pdf' => app(PdfRasterizerInterface::class)->enabled(),
                'max_mb' => (int) round(PrintDesk::settings()['max_upload_kb'] / 1024),
            ] : null,
            'proof_images' => $this->proofLinks($job),
            'more_sheets' => max(0, (int) $job->sheets - count((array) $job->proof_document_ids)),
            'proofed_at' => $job->proofed_at?->toIso8601String(),
            'approved_at' => $job->approved_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> the lists a job's page chooses from */
    public function choices(string $locale, array $design, string $kind = PrintProducts::DEFAULT): array
    {
        $media = [];
        foreach ((array) ($design['elements'] ?? []) as $el) {
            if (($el['type'] ?? null) === 'image' && ! empty($el['media'])) {
                $media[(int) $el['media']] = app(ResolvePublicMediaUrlAction::class)->execute((int) $el['media']);
            }
        }
        $settings = PrintDesk::settings();

        return [
            // PP2: the layouts of the job's print product.
            'layouts' => PrintLayout::query()->where('is_active', true)->where('print_kind', $kind)->orderBy('sort_order')->orderBy('id')->get()
                ->map(fn (PrintLayout $l) => ['id' => $l->id, 'name' => $l->name, 'shape' => $l->shape, 'sheet_size' => $l->sheet_size, 'sheet_label' => PrintSheet::label($l->sheet_size), 'tags' => $l->tagsPerSheet(), 'tag_w_mm' => $l->tag_w_mm, 'tag_h_mm' => $l->tag_h_mm])->values()->all(),
            // PP1: each paper names its sheet size; the page offers those of the chosen layout's size.
            'papers' => PrintPaperOption::query()->where('is_active', true)->where(fn ($q) => $q->where('price_bw', '>', 0)->orWhere('price_colour', '>', 0))->orderBy('sort_order')->orderBy('id')->get()
                ->map(fn (PrintPaperOption $p) => ['id' => $p->id, 'name' => $p->nameIn($locale), 'sheet_size' => $p->sheet_size, 'gsm' => $p->gsm, 'price_bw' => (float) $p->price_bw, 'price_colour' => (float) $p->price_colour])->values()->all(),
            'finishing_options' => PrintFinishingOption::query()->where('is_active', true)->where('price', '>', 0)->orderBy('sort_order')->orderBy('id')->get()
                ->map(fn (PrintFinishingOption $f) => ['id' => $f->id, 'name' => $f->nameIn($locale), 'price' => (float) $f->price, 'cuts_sheet' => (bool) $f->cuts_sheet])->values()->all(),
            'subject_sets' => PrintSubjectSet::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get()
                ->map(fn (PrintSubjectSet $s) => ['id' => $s->id, 'label' => $s->labelIn($locale), 'subjects' => $s->subjects])->values()->all(),
            'fonts' => PrintFontFaces::faces(),
            'font_css' => PrintFontFaces::forBrowser(),
            'images' => array_filter($media),
            'settings' => ['min_sheets' => $settings['min_sheets'], 'max_rows' => $settings['max_rows'], 'lead_days' => $settings['lead_days'], 'tiers' => $settings['tiers']],
        ];
    }

    /** @return list<array<string, mixed>> the customer's designs, newest first */
    public function mine(int $userId, string $locale): array
    {
        return PrintJob::query()->with(['template', 'layout'])->where('user_id', $userId)->latest('updated_at')->limit(200)->get()
            ->map(fn (PrintJob $j) => [
                'id' => $j->id, 'name' => $j->name, 'shape' => $j->layout?->shape ?? 'rect', 'status' => $j->status->value, 'template' => $j->template?->titleIn($locale), 'source' => (string) $j->source,
                'design' => $j->design, 'entry' => $j->entry, 'sheets' => (int) $j->sheets, 'total' => (float) $j->price_total,
                'updated_at' => $j->updated_at?->toIso8601String(),
                'removable' => ! in_array($j->status, [PrintJobStatus::Ordered, PrintJobStatus::Printing, PrintJobStatus::Finished], true),
            ])->values()->all();
    }

    /** @return list<string> */
    private function proofLinks(PrintJob $job): array
    {
        $links = [];
        foreach (array_values((array) $job->proof_document_ids) as $i => $id) {
            $links[] = URL::temporarySignedRoute('public.shop.print.proof', now()->addMinutes(self::PROOF_LINK_MINUTES), ['job' => $job->id, 'sheet' => $i + 1]);
        }

        return $links;
    }

    private function fieldLabel(string $key): string
    {
        $label = __('print.field_'.$key);

        return $label === 'print.field_'.$key ? $key : (string) $label;
    }
}
