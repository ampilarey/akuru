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

        return DB::transaction(function () use ($request, $status, $reviewerId, $notes): SchoolRequest {
            $request->status = $status;
            $request->reviewed_by = $reviewerId;
            $request->reviewed_at = now();
            $request->review_notes = $notes;
            $request->save();

            if ($status === SchoolRequestStatus::Approved) {
                $this->registry->handlerFor($request)?->onApproved($request->fresh());
            }

            return $request->refresh();
        });
    }
}
