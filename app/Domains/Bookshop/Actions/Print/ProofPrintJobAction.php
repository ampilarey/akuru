<?php

namespace App\Domains\Bookshop\Actions\Print;

use App\Domains\Bookshop\Enums\PrintJobStatus;
use App\Domains\Bookshop\Models\PrintJob;
use App\Domains\Bookshop\Models\PrintLayout;
use App\Domains\Bookshop\Models\PrintTemplate;
use App\Domains\Bookshop\Support\PrintDesign;
use App\Domains\Bookshop\Support\PrintDesk;
use App\Domains\Bookshop\Support\PrintJobEntry;
use App\Domains\Bookshop\Support\PrintSheet;
use App\Domains\Bookshop\Support\PrintUpload;
use App\Domains\Media\Actions\ReadGeneratedDocumentAction;
use App\Domains\Media\Actions\StoreGeneratedDocumentAction;
use App\Support\Contracts\PageImageInterface;
use App\Support\Contracts\PdfConverterInterface;
use App\Support\Contracts\PdfRasterizerInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * NAME_TAGS_PLAN §3.7, §5.2 (NT3): the proof and the approval.
 *
 * *Proof* renders the job once through the one renderer: the print file —
 * every sheet in one PDF, no watermark, the office's — and a watermarked
 * picture of each sheet at screen resolution, the customer's. Both come
 * from the same design and rows, so what is approved is what is printed.
 * Both are private documents on the job.
 *
 * *Approve* locks it: the price is worked out again and held (decision
 * D11: seven days), and nothing about the job changes after — a change is a
 * copy (`SavePrintJobAction::copy`).
 */
class ProofPrintJobAction
{
    /** Sheets drawn as proof pictures; a longer job shows these and says how many more print. */
    public const MAX_PROOF_SHEETS = 30;

    /** Decision D11: days an approved price is held in the cart. */
    public const PRICE_HOLD_DAYS = 7;

    public function __construct(
        private readonly RenderPrintSheetAction $render,
        private readonly SavePrintJobAction $save,
        private readonly PdfConverterInterface $pdf,
        private readonly PageImageInterface $image,
    ) {}

    /**
     * @param  array{declared?: bool, accept_warnings?: bool}  $confirm  NT5: an upload's declaration and its warnings accepted
     */
    public function proof(PrintJob $job, int $userId, string $locale, array $confirm = []): PrintJob
    {
        $this->save->open($job);
        if (! $this->pdf->enabled() || ! $this->image->enabled()) {
            throw ValidationException::withMessages(['job' => __('print.error_proof_unavailable')]);
        }
        if ($job->isUpload()) {
            return $this->proofUpload($job, $userId, $locale, $confirm);
        }
        $layout = PrintLayout::query()->find($job->print_layout_id);
        if ($layout === null) {
            throw ValidationException::withMessages(['layout_id' => __('print.error_job_layout')]);
        }
        // What was typed must still make tags, and the job must still have a price.
        $entry = PrintJobEntry::normalise((array) $job->entry, PrintDesign::fieldKeys((array) $job->design), PrintDesk::settings()['max_rows']);
        $job->entry = $entry;
        $this->save->price($job, $locale);

        $rows = PrintJobEntry::rows($entry, $layout, (int) $job->sheets);
        $options = ['lang' => $locale, 'title' => (string) $job->name, 'grayscale' => ! $job->colour];
        $file = $this->pdf->fromHtml($this->render->document((array) $job->design, $layout, $rows, $options));
        $sheets = array_slice($this->render->execute((array) $job->design, $layout, $rows, $options + ['watermark' => PrintDesk::settings()['watermark']]), 0, self::MAX_PROOF_SHEETS);
        [$w, $h] = PrintSheet::px($layout->sheet_size);

        $store = app(StoreGeneratedDocumentAction::class);
        $proofIds = [];
        foreach ($sheets as $i => $html) {
            $proofIds[] = $store->execute('print_job', $job->id, 'print_proof', 'Proof sheet '.($i + 1), $this->image->fromHtml($html, $w, $h), 'png', $userId, 's'.($i + 1))->id;
        }
        $printId = $store->execute('print_job', $job->id, 'print_file', 'Print file', $file, 'pdf', $userId)->id;

        $job->forceFill([
            'proof_document_ids' => $proofIds, 'print_document_id' => $printId,
            'status' => PrintJobStatus::Proofed, 'proofed_at' => now(),
            'user_id' => $job->user_id ?? $userId,
        ])->save();

        return $job;
    }

    /**
     * NT5 (plan §3.4, §5.5, §5.6, risk R4): the customer's own artwork. The
     * declaration must be given and the preflight's warnings accepted; a
     * refusal stops it. An image is laid out like a design — one tag's
     * artwork on every cell, or the whole sheet — and rendered as any job is;
     * a PDF *is* the print file, its pages pictured by the rasteriser and
     * watermarked by the same Chrome.
     *
     * @param  array{declared?: bool, accept_warnings?: bool}  $confirm
     */
    private function proofUpload(PrintJob $job, int $userId, string $locale, array $confirm): PrintJob
    {
        if ($job->upload_document_id === null || $job->upload_meta === null) {
            throw ValidationException::withMessages(['file' => __('print.upload_error_none')]);
        }
        $this->save->price($job, $locale);
        $preflight = (array) $job->preflight;
        if (($preflight['refusals'] ?? []) !== []) {
            $first = $preflight['refusals'][0];
            throw ValidationException::withMessages(['file' => __((string) $first['key'], (array) ($first['vars'] ?? []))]);
        }
        if (empty($confirm['declared'])) {
            throw ValidationException::withMessages(['declared' => __('print.upload_error_declaration')]);
        }
        if (($preflight['warnings'] ?? []) !== [] && empty($confirm['accept_warnings'])) {
            throw ValidationException::withMessages(['accept_warnings' => __('print.upload_error_accept_warnings')]);
        }

        $bytes = (string) app(ReadGeneratedDocumentAction::class)->execute((int) $job->upload_document_id)['contents'];
        $meta = (array) $job->upload_meta;
        $watermark = PrintDesk::settings()['watermark'];
        $options = ['lang' => $locale, 'title' => (string) $job->name, 'grayscale' => ! $job->colour];
        // PP1: a whole-sheet upload is the size of the job's layout's sheet.
        $jobLayout = PrintLayout::query()->find($job->print_layout_id);
        $sheetLayout = PrintUpload::sheetLayout($jobLayout?->sheet_size);
        $page = PrintUpload::design(...$sheetLayout->sheetMm());
        [$w, $h] = PrintSheet::px($jobLayout?->sheet_size);

        if (($meta['type'] ?? null) === 'pdf') {
            $raster = app(PdfRasterizerInterface::class);
            if (! $raster->enabled()) {
                throw ValidationException::withMessages(['file' => __('print.upload_error_type')]);
            }
            $file = $bytes;
            $pictures = [];
            for ($i = 1; $i <= min((int) $meta['pages'], self::MAX_PROOF_SHEETS); $i++) {
                $png = 'data:image/png;base64,'.base64_encode($raster->toPng($bytes, $i, 96));
                $pictures[] = $this->render->execute($page, $sheetLayout, [[]], $options + ['upload' => $png, 'watermark' => $watermark, 'crop_marks' => false])[0];
            }
        } else {
            $src = 'data:image/'.($meta['type'] === 'png' ? 'png' : 'jpeg').';base64,'.base64_encode($bytes);
            if ($job->upload_kind === 'sheet') {
                [$layout, $design, $rows] = [$sheetLayout, $page, array_fill(0, max(1, (int) $job->sheets), [])];
            } else {
                $layout = PrintLayout::query()->find($job->print_layout_id);
                if ($layout === null) {
                    throw ValidationException::withMessages(['layout_id' => __('print.error_job_layout')]);
                }
                [$design, $rows] = [PrintUpload::design((float) $layout->tag_w_mm, (float) $layout->tag_h_mm), array_fill(0, max(1, (int) $job->sheets) * max(1, $layout->tagsPerSheet()), [])];
            }
            $options += ['upload' => $src, 'crop_marks' => $job->upload_kind === 'sheet' ? false : ($layout->sheet_code === null)];
            $file = $this->pdf->fromHtml($this->render->document($design, $layout, $rows, $options));
            $pictures = array_slice($this->render->execute($design, $layout, $rows, $options + ['watermark' => $watermark]), 0, self::MAX_PROOF_SHEETS);
        }

        $store = app(StoreGeneratedDocumentAction::class);
        $proofIds = [];
        foreach ($pictures as $i => $html) {
            $proofIds[] = $store->execute('print_job', $job->id, 'print_proof', 'Proof sheet '.($i + 1), $this->image->fromHtml($html, $w, $h), 'png', $userId, 's'.($i + 1))->id;
        }
        $printId = $store->execute('print_job', $job->id, 'print_file', 'Print file', $file, 'pdf', $userId)->id;

        $job->forceFill([
            'proof_document_ids' => $proofIds, 'print_document_id' => $printId,
            'status' => PrintJobStatus::Proofed, 'proofed_at' => now(),
            'declared_at' => now(), 'warnings_accepted_at' => ($preflight['warnings'] ?? []) !== [] ? now() : null,
        ])->save();

        return $job;
    }

    public function approve(PrintJob $job, string $locale): PrintJob
    {
        if ($job->status !== PrintJobStatus::Proofed || empty($job->proof_document_ids) || $job->print_document_id === null) {
            throw ValidationException::withMessages(['job' => __($job->isOpen() ? 'print.error_approve_unproofed' : 'print.error_locked')]);
        }

        return DB::transaction(function () use ($job, $locale) {
            // The price is the desk's at approval, held from now on.
            $this->save->price($job, $locale);
            $snapshot = $job->price_breakdown + ['held_until' => now()->addDays(self::PRICE_HOLD_DAYS)->toIso8601String()];
            $job->forceFill(['approved_price_snapshot' => $snapshot, 'status' => PrintJobStatus::Approved, 'approved_at' => now()])->save();
            if ($job->print_template_id !== null) {
                PrintTemplate::query()->whereKey($job->print_template_id)->increment('uses_count');
            }

            return $job;
        });
    }
}
