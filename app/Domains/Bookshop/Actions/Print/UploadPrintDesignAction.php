<?php

namespace App\Domains\Bookshop\Actions\Print;

use App\Domains\Bookshop\Enums\PrintJobStatus;
use App\Domains\Bookshop\Models\PrintJob;
use App\Domains\Bookshop\Support\PrintDesk;
use App\Domains\Bookshop\Support\PrintUpload;
use App\Domains\Media\Actions\StoreGeneratedDocumentAction;
use App\Support\Contracts\PdfRasterizerInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * NAME_TAGS_PLAN §3.4, §5.5, §5.6 (NT5): the customer's own artwork onto
 * their upload job — a PNG or JPEG, or a PDF where this host can rasterise
 * one. The file is a private document on the job, read only to proof and
 * print it. What it is (pixels, pages, transparency, colour) is kept, and
 * the preflight is worked out from it at once and again whenever the
 * options change. A new file sends a proofed job back to draft.
 */
class UploadPrintDesignAction
{
    public function __construct(
        private readonly SavePrintJobAction $save,
        private readonly PdfRasterizerInterface $rasterizer,
    ) {}

    public function execute(PrintJob $job, UploadedFile $file, string $kind, int $userId, string $locale): PrintJob
    {
        $this->save->open($job);
        if (! $job->isUpload()) {
            throw ValidationException::withMessages(['file' => __('print.upload_error_not_upload')]);
        }
        if (! in_array($kind, PrintUpload::KINDS, true)) {
            throw ValidationException::withMessages(['upload_kind' => __('print.upload_error_kind')]);
        }
        $maxKb = PrintDesk::settings()['max_upload_kb'];
        if ($file->getSize() > $maxKb * 1024) {
            throw ValidationException::withMessages(['file' => __('print.upload_error_size', ['max' => (int) round($maxKb / 1024)])]);
        }
        $bytes = (string) file_get_contents($file->getRealPath());
        $meta = PrintUpload::inspect($bytes);
        if ($meta === null) {
            throw ValidationException::withMessages(['file' => __($this->rasterizer->enabled() ? 'print.upload_error_type_pdf' : 'print.upload_error_type')]);
        }
        // PP5: a photo print is one picture per print — a PNG or a JPEG, never a whole sheet.
        if ($job->printKind() === 'photos') {
            if ($meta['type'] === 'pdf') {
                throw ValidationException::withMessages(['file' => __('print.upload_error_photo_type')]);
            }
            $kind = 'tag';
        }
        if ($meta['type'] === 'pdf') {
            // §5.6: a PDF only where this host can proof it; and only as whole sheets.
            if (! $this->rasterizer->enabled()) {
                throw ValidationException::withMessages(['file' => __('print.upload_error_type')]);
            }
            try {
                $meta['pages'] = $this->rasterizer->pages($bytes);
            } catch (\Throwable) {
                $meta['pages'] = 0;
            }
            if ($meta['pages'] < 1) {
                throw ValidationException::withMessages(['file' => __('print.upload_error_pdf_unreadable')]);
            }
            $kind = 'sheet';
            $job->sheets = $meta['pages'];
        }

        $ext = ['png' => 'png', 'jpeg' => 'jpg', 'pdf' => 'pdf'][$meta['type']];
        $doc = app(StoreGeneratedDocumentAction::class)->execute('print_job', $job->id, 'print_upload', 'Customer artwork', $bytes, $ext, $userId, 'u'.substr(md5($bytes), 0, 8));

        $job->forceFill([
            'upload_document_id' => $doc->id, 'upload_kind' => $kind, 'upload_meta' => $meta,
            'declared_at' => null, 'warnings_accepted_at' => null,
        ]);
        if ($job->status === PrintJobStatus::Proofed) {
            $job->forceFill(['status' => PrintJobStatus::Draft, 'proof_document_ids' => null, 'print_document_id' => null, 'proofed_at' => null]);
        }
        $this->save->price($job, $locale, quietly: true);

        return $job;
    }
}
