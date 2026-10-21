<?php

namespace App\Domains\Bookshop\Actions\Print;

use App\Domains\Academics\Actions\ResolveAcademicYearForDateAction;
use App\Domains\Bookshop\Enums\PrintJobStatus;
use App\Domains\Bookshop\Models\PrintImage;
use App\Domains\Bookshop\Models\PrintJob;
use App\Domains\Bookshop\Models\PrintLayout;
use App\Domains\Bookshop\Models\PrintPaperOption;
use App\Domains\Bookshop\Models\PrintSubjectSet;
use App\Domains\Bookshop\Models\PrintTemplate;
use App\Domains\Bookshop\Support\PrintDesign;
use App\Domains\Bookshop\Support\PrintDesk;
use App\Domains\Bookshop\Support\PrintJobEntry;
use App\Domains\Bookshop\Support\PrintProducts;
use App\Domains\Bookshop\Support\PrintUpload;
use Illuminate\Validation\ValidationException;

/**
 * NAME_TAGS_PLAN §3.2, §3.5, §3.6, §6.1 (NT3): a customer's job — started
 * from a template, changed as they type (autosave), copied, renamed, put
 * away. A job is changed only while it is a draft or proofed; a change to a
 * proofed job sends it back to draft (the proof no longer shows it); an
 * approved job is never changed — `print.error_locked`, and *Change* makes a
 * copy.
 */
class SavePrintJobAction
{
    public function __construct(private readonly QuotePrintJobAction $quote) {}

    /** A new draft from an active template, with the desk's first priced paper and its minimum sheets. */
    public function start(PrintTemplate $template, ?int $userId, ?int $cartId, string $locale, ?string $grade = null): PrintJob
    {
        if ($template->status !== 'active') {
            throw ValidationException::withMessages(['template' => __('print.error_job_template')]);
        }
        // PP2: the template's product, and that product's layouts.
        $kind = PrintProducts::valid($template->print_kind) ? $template->print_kind : PrintProducts::DEFAULT;
        $layout = ($template->layout?->is_active && $template->layout->print_kind === $kind ? $template->layout : null)
            ?? self::firstLayout($kind);
        if ($layout === null) {
            throw ValidationException::withMessages(['template' => __('print.error_job_layout')]);
        }
        $paper = PrintPaperOption::firstFor($layout->sheet_size);
        $year = app(ResolveAcademicYearForDateAction::class)->execute();

        $job = PrintJob::query()->create([
            'user_id' => $userId,
            'cart_id' => $userId === null ? $cartId : null,
            'product_id' => self::productId($kind),
            'print_template_id' => $template->id,
            'print_layout_id' => $layout->id,
            'print_paper_option_id' => $paper?->id,
            'colour' => $paper === null || (float) $paper->price_colour > 0,
            'finishing' => [],
            'sheets' => PrintDesk::settings()['min_sheets'],
            'entry' => ['mode' => 'same', 'row' => [], 'list' => [], 'subjects' => ['set_id' => null, 'items' => [], 'language' => $locale === 'en' ? 'en' : $locale]],
            'design' => PrintDesign::fit((array) $template->design, $layout->tag_w_mm, $layout->tag_h_mm),
            'source' => 'template',
            'status' => PrintJobStatus::Draft,
            'name' => $template->titleIn($locale),
            'academic_year_id' => $year === null ? null : (int) $year['id'],
        ]);
        // NT7: from a school's book list, the grade's subject set is chosen already.
        $set = $grade === null ? null : self::subjectSetFor($grade);
        if ($set !== null && in_array('subject', PrintDesign::fieldKeys((array) $job->design), true)) {
            $entry = (array) $job->entry;
            $entry['subjects'] = ['set_id' => $set->id, 'items' => (array) $set->subjects, 'language' => $entry['subjects']['language'] ?? 'en'];
            $job->forceFill(['entry' => $entry, 'print_subject_set_id' => $set->id]);
        }
        $this->price($job, $locale, quietly: true);

        return $job;
    }

    /** PP2: the print product's id, made with the desk's other products if it is not yet. */
    private static function productId(string $kind): ?int
    {
        return (PrintProducts::product($kind) ?? (PrintDesk::ensure() ? PrintProducts::product($kind) : null))?->id;
    }

    /** PP2: the first active layout of a print product. */
    public static function firstLayout(string $kind): ?PrintLayout
    {
        return PrintLayout::query()->where('is_active', true)->where('print_kind', $kind)->orderBy('sort_order')->orderBy('id')->first();
    }

    /** NT7: the active subject set a book list's grade names (its label in any language, or a label containing it). */
    public static function subjectSetFor(string $grade): ?PrintSubjectSet
    {
        $grade = mb_strtolower(trim($grade));
        if ($grade === '') {
            return null;
        }
        $sets = PrintSubjectSet::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();
        $labels = fn (PrintSubjectSet $s) => array_filter(array_map(fn ($l) => mb_strtolower(trim((string) $l)), [$s->label, $s->label_dv, $s->label_ar]));

        return $sets->first(fn (PrintSubjectSet $s) => in_array($grade, $labels($s), true))
            ?? $sets->first(fn (PrintSubjectSet $s) => collect($labels($s))->contains(fn ($l) => str_contains($l, $grade)));
    }

    /**
     * NT6 (plan §3.3): a blank design for the free-form designer — the
     * desk's first layout and priced paper, one text that prints the name,
     * so a list of names works from the start.
     */
    public function startFreeform(?int $userId, ?int $cartId, string $locale, string $kind = PrintProducts::DEFAULT): PrintJob
    {
        $layout = self::firstLayout($kind);
        if ($layout === null) {
            throw ValidationException::withMessages(['template' => __('print.error_job_layout')]);
        }
        $paper = PrintPaperOption::firstFor($layout->sheet_size);
        $year = app(ResolveAcademicYearForDateAction::class)->execute();
        $w = (float) $layout->tag_w_mm;
        $h = (float) $layout->tag_h_mm;
        $font = (string) array_key_first((array) config('print.fonts', []));

        $job = PrintJob::query()->create([
            'user_id' => $userId,
            'cart_id' => $userId === null ? $cartId : null,
            'product_id' => self::productId($kind),
            'print_layout_id' => $layout->id,
            'print_paper_option_id' => $paper?->id,
            'colour' => $paper === null || (float) $paper->price_colour > 0,
            'finishing' => [],
            'sheets' => PrintDesk::settings()['min_sheets'],
            'entry' => ['mode' => 'same', 'row' => [], 'list' => [], 'subjects' => ['set_id' => null, 'items' => [], 'language' => $locale === 'en' ? 'en' : $locale]],
            'design' => PrintDesign::validate([
                'tag' => ['w' => $w, 'h' => $h],
                'background' => ['color' => '#FFFFFF'],
                'elements' => [['id' => 'name', 'type' => 'text', 'x' => round($w * 0.05, 2), 'y' => round($h * 0.35, 2), 'w' => round($w * 0.9, 2), 'h' => round($h * 0.3, 2),
                    'text' => '{name}', 'field' => 'name', 'font' => $font, 'size' => 18, 'weight' => 700, 'color' => '{primary}', 'align' => 'center', 'dir' => 'auto']],
                'palette' => ['scheme' => 'maroon', 'schemes' => ['maroon' => ['primary' => '#7A1F2B', 'secondary' => '#E9D8B4']]],
                'fields' => [['key' => 'name', 'required' => true]],
            ]),
            'source' => 'freeform',
            'status' => PrintJobStatus::Draft,
            'name' => __('print.freeform_job_name', [], $locale),
            'academic_year_id' => $year === null ? null : (int) $year['id'],
        ]);
        $this->price($job, $locale, quietly: true);

        return $job;
    }

    /**
     * NT6: *Open in the designer* — a template job becomes the customer's
     * free-form design. The job's design is already its own copy; what the
     * template locked is unlocked here, and the template is untouched.
     */
    public function toFreeform(PrintJob $job, string $locale): PrintJob
    {
        $this->open($job);
        if ($job->isUpload()) {
            throw ValidationException::withMessages(['job' => __('print.freeform_error_upload')]);
        }
        $design = (array) $job->design;
        $design['elements'] = array_map(fn (array $el) => array_merge($el, ['locked' => false]), (array) ($design['elements'] ?? []));
        $job->forceFill(['design' => $design, 'source' => 'freeform']);
        if ($job->status === PrintJobStatus::Proofed) {
            $job->forceFill(['status' => PrintJobStatus::Draft, 'proof_document_ids' => null, 'print_document_id' => null, 'proofed_at' => null]);
        }
        $this->price($job, $locale);

        return $job;
    }

    /**
     * NT5: a new draft for the customer's own artwork — the desk's first
     * layout and priced paper, no file yet. The upload comes next
     * (`UploadPrintDesignAction`).
     */
    public function startUpload(int $userId, string $locale, string $kind = PrintProducts::DEFAULT): PrintJob
    {
        $layout = self::firstLayout($kind);
        if ($layout === null) {
            throw ValidationException::withMessages(['template' => __('print.error_job_layout')]);
        }
        $paper = PrintPaperOption::firstFor($layout->sheet_size);
        $year = app(ResolveAcademicYearForDateAction::class)->execute();

        $job = PrintJob::query()->create([
            'user_id' => $userId,
            'product_id' => self::productId($kind),
            'print_layout_id' => $layout->id,
            'print_paper_option_id' => $paper?->id,
            'colour' => $paper === null || (float) $paper->price_colour > 0,
            'finishing' => [],
            'sheets' => PrintDesk::settings()['min_sheets'],
            'entry' => ['mode' => 'same', 'row' => [], 'list' => [], 'subjects' => ['set_id' => null, 'items' => [], 'language' => 'en']],
            // PP5: a photo fills its print; an upload of tag artwork fits whole, as before.
            'design' => PrintUpload::design((float) $layout->tag_w_mm, (float) $layout->tag_h_mm, $kind === 'photos' ? 'cover' : 'contain'),
            'source' => 'upload',
            'upload_kind' => 'tag',
            'status' => PrintJobStatus::Draft,
            'name' => __('print.upload_job_name', [], $locale),
            'academic_year_id' => $year === null ? null : (int) $year['id'],
        ]);
        $this->price($job, $locale, quietly: true);

        return $job;
    }

    /**
     * Save what the customer changed. Returns the job with its price and
     * warnings; refuses an approved job and anything that cannot print.
     *
     * @param  array<string, mixed>  $data  layout_id, paper_id, colour, finishing, sheets, entry, scheme, font, name
     */
    public function update(PrintJob $job, array $data, string $locale): PrintJob
    {
        $this->open($job);
        $settings = PrintDesk::settings();

        if (array_key_exists('layout_id', $data) && (int) $data['layout_id'] !== (int) $job->print_layout_id) {
            $layout = PrintLayout::query()->where('is_active', true)->where('print_kind', $job->printKind())->find((int) $data['layout_id']);
            if ($layout === null) {
                throw ValidationException::withMessages(['layout_id' => __('print.error_job_layout')]);
            }
            if ($job->isUpload()) {
                // NT5: the artwork fills the new tag.
                $job->design = PrintUpload::design((float) $layout->tag_w_mm, (float) $layout->tag_h_mm, PrintUpload::fitOf((array) $job->design));
            } else {
                // From the template when there is one, so a layout switched back and forth loses nothing to rounding;
                // a free-form design (NT6) is the customer's own and scales from itself.
                $base = $job->template !== null && $job->source === 'template' ? (array) $job->template->design : (array) $job->design;
                $scheme = $job->design['palette']['scheme'] ?? null;
                $font = PrintDesign::fieldFont((array) $job->design);
                $job->design = PrintDesign::withFieldFont(PrintDesign::withScheme(PrintDesign::fit($base, $layout->tag_w_mm, $layout->tag_h_mm), $scheme), $font);
            }
            $job->print_layout_id = $layout->id;
            $job->setRelation('layout', $layout);
            // PP1: a paper is stock of one size; on a layout of another size the job moves to that size's first paper.
            $paper = $job->print_paper_option_id === null ? null : PrintPaperOption::query()->find($job->print_paper_option_id);
            if ($paper === null || $paper->sheet_size !== $layout->sheet_size) {
                $paper = PrintPaperOption::firstFor($layout->sheet_size, $job->printerId());
                $job->print_paper_option_id = $paper?->id;
                $job->colour = $paper === null || (float) $paper->price_colour > 0;
            }
        }
        if (array_key_exists('design', $data) && is_array($data['design'])) {
            // NT6: the free-form designer sends the whole design; the server's validator decides what is kept.
            if ($job->source !== 'freeform') {
                throw ValidationException::withMessages(['design' => __('print.freeform_error_not_freeform')]);
            }
            $design = PrintDesign::validate($data['design']);
            $library = PrintImage::query()->pluck('media_file_id')->map(fn ($id) => (int) $id)->all();
            foreach ($design['elements'] as $el) {
                if ($el['type'] === 'image' && ! in_array((int) $el['media'], $library, true)) {
                    throw ValidationException::withMessages(['design' => __('print.error_design_image', ['n' => $el['id']])]);
                }
            }
            $layout = $job->layout ?? PrintLayout::query()->find($job->print_layout_id);
            // The tag is the layout's: a design drawn at another size is scaled to it.
            $job->design = $layout === null ? $design : PrintDesign::fit($design, (float) $layout->tag_w_mm, (float) $layout->tag_h_mm);
        }
        if (array_key_exists('scheme', $data)) {
            $job->design = PrintDesign::withScheme((array) $job->design, is_string($data['scheme']) ? $data['scheme'] : null);
        }
        if (array_key_exists('font', $data) && is_string($data['font']) && $data['font'] !== '') {
            if (! isset(config('print.fonts')[$data['font']])) {
                throw ValidationException::withMessages(['font' => __('print.error_job_font')]);
            }
            $job->design = PrintDesign::withFieldFont((array) $job->design, $data['font']);
        }
        if (array_key_exists('paper_id', $data)) {
            $job->print_paper_option_id = $data['paper_id'] === null || $data['paper_id'] === '' ? null : (int) $data['paper_id'];
        }
        if (array_key_exists('colour', $data)) {
            $job->colour = filter_var($data['colour'], FILTER_VALIDATE_BOOLEAN);
        }
        if (array_key_exists('finishing', $data)) {
            $job->finishing = array_values(array_unique(array_map('intval', (array) $data['finishing'])));
        }
        if (array_key_exists('sheets', $data)) {
            $sheets = (int) $data['sheets'];
            if ($sheets < 1 || $sheets > 500) {
                throw ValidationException::withMessages(['sheets' => __('print.error_job_sheets')]);
            }
            $job->sheets = $sheets;
        }
        if (array_key_exists('entry', $data) && is_array($data['entry'])) {
            $job->entry = PrintJobEntry::normalise($data['entry'], PrintDesign::fieldKeys((array) $job->design), $settings['max_rows']);
            $job->print_subject_set_id = $job->entry['subjects']['set_id'] ?? null;
        }
        if (array_key_exists('fit', $data) && $job->isUpload()) {
            // PP5: fill the print (cropping) or fit the picture whole.
            if (! in_array($data['fit'], PrintUpload::FITS, true)) {
                throw ValidationException::withMessages(['fit' => __('print.upload_error_fit')]);
            }
            $layout = $job->layout ?? PrintLayout::query()->find($job->print_layout_id);
            $job->design = PrintUpload::design((float) ($layout?->tag_w_mm ?? $job->design['tag']['w']), (float) ($layout?->tag_h_mm ?? $job->design['tag']['h']), $data['fit']);
        }
        if (array_key_exists('upload_kind', $data) && $job->isUpload()) {
            if (! in_array($data['upload_kind'], PrintUpload::KINDS, true) || ($data['upload_kind'] === 'sheet' && $job->printKind() === 'photos')) {
                throw ValidationException::withMessages(['upload_kind' => __('print.upload_error_kind')]);
            }
            $job->upload_kind = $data['upload_kind'];
        }
        if (array_key_exists('name', $data) && is_string($data['name']) && trim($data['name']) !== '') {
            $job->name = mb_substr(trim($data['name']), 0, 120);
        }

        if ($job->status === PrintJobStatus::Proofed) {
            $job->status = PrintJobStatus::Draft;
            $job->proof_document_ids = null;
            $job->print_document_id = null;
            $job->proofed_at = null;
        }
        $this->price($job, $locale);

        return $job;
    }

    /** A new draft carrying everything the job had — how an approved job is changed. */
    public function copy(PrintJob $job, ?int $userId, ?int $cartId, string $locale): PrintJob
    {
        $copy = $job->replicate(['proof_document_ids', 'print_document_id', 'approved_price_snapshot', 'declared_at', 'warnings_accepted_at', 'proofed_at', 'approved_at', 'ordered_at', 'printing_at', 'finished_at', 'cancelled_at', 'printed_by', 'finished_by']);
        $copy->fill([
            'user_id' => $userId, 'cart_id' => $userId === null ? $cartId : null, 'status' => PrintJobStatus::Draft,
            'name' => mb_substr(__('print.copy_of', ['name' => (string) $job->name], $locale), 0, 120),
        ]);
        $copy->save();
        $this->price($copy, $locale, quietly: true);

        return $copy;
    }

    public function rename(PrintJob $job, string $name): PrintJob
    {
        $name = trim($name);
        if ($name === '') {
            throw ValidationException::withMessages(['name' => __('print.error_job_name')]);
        }
        $job->update(['name' => mb_substr($name, 0, 120)]);

        return $job;
    }

    /** Put a design away (soft): only while no order holds it. */
    public function remove(PrintJob $job): void
    {
        if (in_array($job->status, [PrintJobStatus::Ordered, PrintJobStatus::Printing, PrintJobStatus::Finished], true)) {
            throw ValidationException::withMessages(['job' => __('print.error_job_in_order')]);
        }
        $job->delete();
    }

    public function open(PrintJob $job): void
    {
        if (! $job->isOpen()) {
            throw ValidationException::withMessages(['job' => __('print.error_locked')]);
        }
    }

    /**
     * Sheets, price and warnings for the job as it stands, saved. Quietly:
     * a job just started has no paper priced yet or nothing typed — it is
     * saved without a price rather than refused.
     */
    public function price(PrintJob $job, string $locale, bool $quietly = false): PrintJob
    {
        $layout = $job->layout ?? PrintLayout::query()->find($job->print_layout_id);
        $entry = (array) $job->entry;
        if ($layout !== null) {
            $job->sheets = PrintJobEntry::sheets($entry, $layout, (int) $job->sheets);
        }
        try {
            $quote = $this->quote->execute($job->print_paper_option_id, (bool) $job->colour, (array) $job->finishing, (int) $job->sheets, $locale, $layout?->sheet_size, $job->printerId());
            $job->price_breakdown = $quote;
            $job->price_per_sheet = $quote['unit_price'];
            $job->price_total = $quote['total'];
        } catch (ValidationException $e) {
            if (! $quietly) {
                throw $e;
            }
            $job->price_breakdown = null;
            $job->price_per_sheet = 0;
            $job->price_total = 0;
        }
        $job->preflight = $job->isUpload()
            // NT5: the artwork's preflight, worked out again for the options as they are now.
            ? ($job->upload_meta === null ? ['dpi' => null, 'warnings' => [], 'refusals' => []]
                : PrintUpload::preflight((array) $job->upload_meta, (string) $job->upload_kind, $layout, (bool) $job->colour, (int) $job->sheets, PrintUpload::fitOf((array) $job->design)))
            : ['warnings' => PrintJobEntry::warnings($entry, (array) $job->design)];
        $job->save();

        return $job;
    }
}
