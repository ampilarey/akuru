<?php

namespace App\Domains\Bookshop\Actions\Print;

use App\Domains\Bookshop\Enums\PrintJobStatus;
use App\Domains\Bookshop\Models\PrintJob;
use App\Domains\Bookshop\Models\PrintLayout;
use App\Domains\Bookshop\Models\PrintTemplate;
use App\Domains\Bookshop\Support\PrintDesign;
use App\Domains\Bookshop\Support\PrintDesk;
use App\Domains\Bookshop\Support\PrintJobEntry;
use App\Domains\Media\Actions\StoreGeneratedDocumentAction;
use App\Support\Contracts\PageImageInterface;
use App\Support\Contracts\PdfConverterInterface;
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

    public function proof(PrintJob $job, int $userId, string $locale): PrintJob
    {
        $this->save->open($job);
        if (! $this->pdf->enabled() || ! $this->image->enabled()) {
            throw ValidationException::withMessages(['job' => __('print.error_proof_unavailable')]);
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
        [$w, $h] = [(int) config('print.proof.width_px', 794), (int) config('print.proof.height_px', 1123)];

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
