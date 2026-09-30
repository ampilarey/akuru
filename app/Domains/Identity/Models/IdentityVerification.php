<?php

namespace App\Domains\Identity\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** COMMERCE_PARITY_PLAN P2/P3: one submitted identity card, front and back, and the office's verdict. */
class IdentityVerification extends Model
{
    public const PENDING = 'pending';

    public const VERIFIED = 'verified';

    public const REJECTED = 'rejected';

    protected $fillable = [
        'user_id', 'purpose', 'student_id', 'front_media_file_id', 'back_media_file_id',
        'status', 'decided_by', 'decided_at', 'note',
    ];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
