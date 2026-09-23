<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Enums\SchoolRequestStatus;
use App\Domains\Academics\Models\SchoolRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewSchoolRequestAction
{
    public function __construct(private RequestHandlerRegistry $registry) {}

    public function execute(SchoolRequest $request, SchoolRequestStatus $status, int $reviewerId, ?string $notes = null): SchoolRequest
    {
        if ($request->status !== SchoolRequestStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => 'Only pending requests can be reviewed.',
            ]);
        }

        if (! in_array($status, [SchoolRequestStatus::Approved, SchoolRequestStatus::Rejected, SchoolRequestStatus::Cancelled], true)) {
            throw ValidationException::withMessages([
                'status' => 'Invalid review status.',
            ]);
        }

        // E5: "rejected requests state a reason". Until the requests walk
        // (STATUS §5fw) a rejection with the notes box empty went through, and
        // the family was told "rejected." and nothing else.
        if ($status === SchoolRequestStatus::Rejected && trim((string) $notes) === '') {
            throw ValidationException::withMessages([
                'review_notes' => 'Say why: a rejected request states its reason to the person who asked.',
            ]);
        }

        return DB::transaction(function () use ($request, $status, $reviewerId, $notes): SchoolRequest {
            $request->status = $status;
            $request->reviewed_by = $reviewerId;
            $request->reviewed_at = now();
            $request->review_notes = $notes;
            $request->save();

            if ($status === SchoolRequestStatus::Approved) {
                $this->registry->handlerFor($request)?->onApproved($request->fresh());
            }

            $request->refresh();

            // Inside the transaction on purpose: the notice is a DB write, so a
            // failed handler rolls the notice back with it. Nobody should be
            // told their leave was approved by a transaction that then aborted.
            app(NotifyRequestDecisionAction::class)->execute($request);

            return $request;
        });
    }
}
