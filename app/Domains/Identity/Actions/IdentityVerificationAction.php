<?php

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Models\IdentityVerification;
use App\Domains\Media\Actions\ReadPrivateMediaAction;
use App\Domains\Media\Actions\StorePrivateMediaAction;
use App\Domains\Notifications\Actions\SendUserNotificationAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * COMMERCE_PARITY_PLAN P2/P3: a person's identity card — the front, and the
 * back when they send it (optional since C17 slice R3, STATUS §5of: the owner,
 * "all the important informations are on front page only") — and
 * the office's verdict — the one place shops (`vendor`), writers (`writer`)
 * and learners (`learner`, with the child's student id) keep it (rule 11).
 * Other domains call this Action; they never read the table.
 *
 * The images are private media: only `document()` reads them, and only for
 * a verification id, never a media id from a request; the route decides
 * who may ask. They are kept for the life of the account (decision D1).
 */
class IdentityVerificationAction
{
    public const PURPOSES = ['vendor', 'writer', 'learner', 'lender'];

    /** @var list<string> */
    public const MIMES = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];

    public const MAX_BYTES = 8 * 1048576;

    /** Photos are kept at most this many pixels on the long side, re-saved as JPEG (C17 slice R3). */
    public const MAX_SIDE = 1600;

    /** Validation rules for the files, for the forms that take them: the front, and an optional back. */
    public static function fileRules(bool $required = true): array
    {
        $file = ['file', 'mimes:jpeg,jpg,png,webp,pdf', 'max:8192'];

        return ['id_front' => [$required ? 'required' : 'nullable', ...$file], 'id_back' => ['nullable', ...$file]];
    }

    /** A new card for checking. A card already verified stays verified; this one waits. */
    public function submit(int $userId, string $purpose, UploadedFile $front, ?UploadedFile $back = null, ?int $studentId = null): IdentityVerification
    {
        $this->guardPurpose($purpose);
        $frontId = $this->storeSide($front, $userId);
        $backId = $back !== null ? $this->storeSide($back, $userId) : null;

        return IdentityVerification::query()->create([
            'user_id' => $userId,
            'purpose' => $purpose,
            'student_id' => $studentId,
            'front_media_file_id' => $frontId,
            'back_media_file_id' => $backId,
            'status' => IdentityVerification::PENDING,
        ]);
    }

    /**
     * Store one side. A photo is kept smaller — scaled to MAX_SIDE on its long
     * side and re-saved as JPEG, which also drops what the camera wrote into it
     * (location among it) — in the same private store as before, whenever that
     * copy is smaller than what was sent; a PDF is kept as sent (C17 slice R3,
     * STATUS §5of).
     */
    public function storeSide(UploadedFile $file, int $userId): int
    {
        return (int) app(StorePrivateMediaAction::class)->execute($file, $userId, self::MIMES, self::MAX_BYTES, self::MAX_SIDE)['id'];
    }

    /** P3: a card whose sides were stored earlier by `storeSide` (the back may be absent). */
    public function submitStored(int $userId, string $purpose, int $frontId, ?int $backId = null, ?int $studentId = null): IdentityVerification
    {
        $this->guardPurpose($purpose);

        return IdentityVerification::query()->create([
            'user_id' => $userId, 'purpose' => $purpose, 'student_id' => $studentId,
            'front_media_file_id' => $frontId, 'back_media_file_id' => $backId,
            'status' => IdentityVerification::PENDING,
        ]);
    }

    /**
     * P3: a learner's card, whoever uploaded it (the learner, or a parent for a child).
     *
     * @return array{status: string, id: ?int, note: ?string, decided_at: ?string}
     */
    public function learnerStatus(int $studentId): array
    {
        $rows = IdentityVerification::query()->where('purpose', 'learner')->where('student_id', $studentId)->orderByDesc('id')->get();
        $row = $rows->firstWhere('status', IdentityVerification::VERIFIED) ?? $rows->first();

        return ['status' => $row?->status ?? 'none', 'id' => $row?->id, 'note' => $row?->status === IdentityVerification::REJECTED ? $row->note : null, 'decided_at' => $row?->decided_at?->toDateString()];
    }

    /**
     * P3: the learners whose current card is in this state, for the enrolments filter.
     *
     * @return list<int>
     */
    public function learnerIdsWithStatus(string $status): array
    {
        $current = [];
        foreach (IdentityVerification::query()->where('purpose', 'learner')->whereNotNull('student_id')->orderBy('id')->get(['student_id', 'status']) as $v) {
            if (($current[$v->student_id] ?? null) !== IdentityVerification::VERIFIED) {
                $current[$v->student_id] = $v->status;
            }
        }

        return array_values(array_map('intval', array_keys(array_filter($current, fn ($s) => $s === $status))));
    }

    /** P3: the learner's card verified, or the rule off. */
    public function learnerVerified(int $studentId): bool
    {
        return ! self::enforced() || $this->learnerStatus($studentId)['status'] === IdentityVerification::VERIFIED;
    }

    /**
     * Where the person stands: `none`, `pending`, `verified` or `rejected`.
     * Verified once is verified: a later card waiting does not undo it.
     *
     * @return array{status: string, id: ?int, note: ?string, decided_at: ?string}
     */
    public function status(int $userId, string $purpose, ?int $studentId = null): array
    {
        $rows = IdentityVerification::query()->where('user_id', $userId)->where('purpose', $purpose)
            ->when($studentId === null, fn ($q) => $q->whereNull('student_id'), fn ($q) => $q->where('student_id', $studentId))
            ->orderByDesc('id')->get();
        $verified = $rows->firstWhere('status', IdentityVerification::VERIFIED);
        $row = $verified ?? $rows->first();

        return [
            'status' => $row?->status ?? 'none',
            'id' => $row?->id,
            'note' => $row?->status === IdentityVerification::REJECTED ? $row->note : null,
            'decided_at' => $row?->decided_at?->toDateString(),
        ];
    }

    /** Whether the rule is on (config `identity.verification.enforce`; off only in the older tests). */
    public static function enforced(): bool
    {
        return (bool) config('identity.verification.enforce', true);
    }

    /** Verified, or the rule is off. */
    public function isVerified(int $userId, string $purpose, ?int $studentId = null): bool
    {
        if (! self::enforced()) {
            return true;
        }

        return $this->status($userId, $purpose, $studentId)['status'] === IdentityVerification::VERIFIED;
    }

    /**
     * The office's row for a person's current card (verified, else the latest), or null.
     *
     * @return array<string, mixed>|null
     */
    public function officeRow(int $userId, string $purpose): ?array
    {
        $rows = IdentityVerification::query()->with('user:id,name,email,phone')->where('user_id', $userId)
            ->where('purpose', $purpose)->whereNull('student_id')->orderByDesc('id')->get();
        $row = $rows->firstWhere('status', IdentityVerification::VERIFIED) ?? $rows->first();

        return $row === null ? null : $this->row($row);
    }

    /** Any of these people verified for the purpose (a shop's owners). */
    public function anyVerified(array $userIds, string $purpose): bool
    {
        if (! self::enforced()) {
            return true;
        }

        return $userIds !== [] && IdentityVerification::query()->whereIn('user_id', $userIds)
            ->where('purpose', $purpose)->whereNull('student_id')
            ->where('status', IdentityVerification::VERIFIED)->exists();
    }

    /** The office's verdict on one card. A rejection needs a note, which the person reads. */
    public function decide(int $verificationId, int $officeUserId, bool $verified, ?string $note = null, bool $notify = true): IdentityVerification
    {
        $note = trim((string) $note) !== '' ? mb_substr(trim((string) $note), 0, 500) : null;
        if (! $verified && $note === null) {
            throw ValidationException::withMessages(['note' => __('account.id_reject_needs_note')]);
        }

        return DB::transaction(function () use ($verificationId, $officeUserId, $verified, $note, $notify) {
            $row = IdentityVerification::query()->whereKey($verificationId)->lockForUpdate()->firstOrFail();
            $row->fill([
                'status' => $verified ? IdentityVerification::VERIFIED : IdentityVerification::REJECTED,
                'decided_by' => $officeUserId,
                'decided_at' => now(),
                'note' => $note,
            ])->save();

            $row = $row->refresh();
            if (! $notify) {
                return $row;
            }
            $hrefs = ['vendor' => '/vendor', 'writer' => '/write', 'learner' => '/my-learning', 'lender' => '/my-lending'];
            $title = __($verified ? 'account.id_decided_title_verified' : 'account.id_decided_title_rejected');
            $body = __($verified ? 'account.id_decided_body_verified' : 'account.id_decided_body_rejected', ['note' => (string) $note]);
            DB::afterCommit(function () use ($row, $title, $body, $hrefs) {
                try {
                    app(SendUserNotificationAction::class)->execute((int) $row->user_id, $title, $body, ['category' => 'account', 'href' => $hrefs[$row->purpose] ?? '/dashboard']);
                } catch (\Throwable) {
                    // A notice that fails never fails the decision.
                }
            });

            return $row;
        });
    }

    /**
     * Approving an application verifies the card that came with it (the latest
     * one waiting). Silent: the application's own decision is the notice.
     */
    public function decideLatest(int $userId, string $purpose, int $officeUserId, bool $verified, ?string $note = null): void
    {
        $row = IdentityVerification::query()->where('user_id', $userId)->where('purpose', $purpose)
            ->whereNull('student_id')->where('status', IdentityVerification::PENDING)->orderByDesc('id')->first();
        if ($row !== null) {
            $this->decide($row->id, $officeUserId, $verified, $note ?? ($verified ? null : '—'), false);
        }
    }

    /** The purpose of a verification, for the route to decide who may see it. */
    public function purposeOf(int $verificationId): ?string
    {
        return IdentityVerification::query()->whereKey($verificationId)->value('purpose');
    }

    /**
     * One side of the card.
     *
     * @return array{id: int, contents: string, mime: string, original_name: string}|null
     */
    public function document(int $verificationId, string $side): ?array
    {
        $row = IdentityVerification::query()->whereKey($verificationId)->first();
        if ($row === null || ! in_array($side, ['front', 'back'], true)) {
            return null;
        }

        $mediaId = $side === 'front' ? $row->front_media_file_id : $row->back_media_file_id;

        return $mediaId === null ? null : app(ReadPrivateMediaAction::class)->execute((int) $mediaId);
    }

    /**
     * The office's list for a purpose, waiting first.
     *
     * @return list<array<string, mixed>>
     */
    public function list(string $purpose, int $limit = 200): array
    {
        $this->guardPurpose($purpose);
        $rows = IdentityVerification::query()->with('user:id,name,email,phone')->where('purpose', $purpose)
            ->orderByRaw('case when status = ? then 0 else 1 end', [IdentityVerification::PENDING])
            ->orderByDesc('id')->limit($limit)->get();

        return $rows->map(fn (IdentityVerification $v) => $this->row($v))->values()->all();
    }

    /**
     * The office's view of the cards for these learners (P3).
     *
     * @param  list<int>  $studentIds
     * @return array<int, array<string, mixed>> keyed by student id, the latest (or verified) card each
     */
    public function forStudents(array $studentIds): array
    {
        $out = [];
        foreach (IdentityVerification::query()->with('user:id,name,email,phone')->where('purpose', 'learner')
            ->whereIn('student_id', $studentIds)->orderBy('id')->get() as $v) {
            $current = $out[$v->student_id] ?? null;
            if ($current === null || $current['status'] !== IdentityVerification::VERIFIED) {
                $out[$v->student_id] = $this->row($v);
            }
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function row(IdentityVerification $v): array
    {
        return [
            'id' => $v->id,
            'user_id' => $v->user_id,
            'student_id' => $v->student_id,
            'name' => $v->user?->name,
            'email' => $v->user?->email,
            'phone' => $v->user?->phone,
            'status' => $v->status,
            'note' => $v->note,
            'submitted_at' => $v->created_at?->toDateTimeString(),
            'decided_at' => $v->decided_at?->toDateTimeString(),
            'front_url' => route('identity.document', [$v->id, 'front']),
            'back_url' => $v->back_media_file_id !== null ? route('identity.document', [$v->id, 'back']) : null,
            'decide_url' => route('identity.decide', $v->id),
        ];
    }

    private function guardPurpose(string $purpose): void
    {
        if (! in_array($purpose, self::PURPOSES, true)) {
            throw new \InvalidArgumentException("Unknown identity purpose: {$purpose}");
        }
    }
}
