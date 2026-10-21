<?php

namespace App\Domains\Bookshop\Models;

use App\Domains\Bookshop\Enums\PrintJobStatus;
use App\Domains\Bookshop\Support\PrintProducts;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * NAME_TAGS_PLAN §6, §8 (NT3): a customer's configured print job — its own
 * copy of the design, what it prints (`entry`), what it is printed on, its
 * price, its proof and its state. Locked once approved.
 */
class PrintJob extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'cart_id', 'product_id', 'print_template_id', 'print_layout_id', 'print_paper_option_id', 'colour', 'finishing',
        'sheets', 'entry', 'print_subject_set_id', 'design', 'source', 'preflight', 'price_per_sheet', 'price_total', 'price_breakdown',
        'approved_price_snapshot', 'proof_document_ids', 'print_document_id', 'status', 'name', 'academic_year_id',
        'upload_document_id', 'upload_kind', 'upload_meta', 'declared_at', 'warnings_accepted_at',
        'proofed_at', 'approved_at', 'ordered_at', 'printing_at', 'finished_at', 'cancelled_at', 'printed_by', 'finished_by',
    ];

    protected function casts(): array
    {
        return [
            'colour' => 'boolean', 'finishing' => 'array', 'sheets' => 'integer', 'entry' => 'array', 'design' => 'array',
            'preflight' => 'array', 'price_per_sheet' => 'decimal:2', 'price_total' => 'decimal:2', 'price_breakdown' => 'array',
            'approved_price_snapshot' => 'array', 'proof_document_ids' => 'array', 'status' => PrintJobStatus::class,
            'proofed_at' => 'datetime', 'approved_at' => 'datetime', 'ordered_at' => 'datetime', 'printing_at' => 'datetime',
            'finished_at' => 'datetime', 'cancelled_at' => 'datetime',
            'upload_meta' => 'array', 'declared_at' => 'datetime', 'warnings_accepted_at' => 'datetime',
        ];
    }

    /** NT5: a job made from the customer's own artwork rather than a template. */
    /** PP2: which print product the job is — name tags, cards, stickers or photos. */
    public function printKind(): string
    {
        $slug = $this->product_id === null ? null : Product::query()->whereKey($this->product_id)->value('slug');

        return PrintProducts::kindOfSlug($slug) ?? PrintProducts::DEFAULT;
    }

    /**
     * PP7a: who prints the job — null for Akuru, or the shop whose print
     * product it is.
     */
    public function printerId(): ?int
    {
        return PrintProducts::printerOfProduct($this->product_id);
    }

    public function isUpload(): bool
    {
        return $this->source === 'upload';
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(PrintTemplate::class, 'print_template_id');
    }

    public function layout(): BelongsTo
    {
        return $this->belongsTo(PrintLayout::class, 'print_layout_id');
    }

    public function paper(): BelongsTo
    {
        return $this->belongsTo(PrintPaperOption::class, 'print_paper_option_id');
    }

    public function subjectSet(): BelongsTo
    {
        return $this->belongsTo(PrintSubjectSet::class, 'print_subject_set_id');
    }

    public function isOpen(): bool
    {
        return $this->status instanceof PrintJobStatus && $this->status->isOpen();
    }
}
