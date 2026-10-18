<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Models\SubstitutionRequest;
use Illuminate\Validation\ValidationException;

/**
 * The end of a substitution request (KNOWN_ISSUES, TS1). The office marks an
 * assigned request *closed* once the lesson was covered, or *cancelled* — an
 * open one no longer needed, or an assigned one called off. Both are final:
 * the request leaves the open list and its assignment stays as the record of
 * who was asked.
 */
class FinishSubstitutionRequestAction
{
    public const OUTCOMES = ['closed', 'cancelled'];

    public function execute(SubstitutionRequest $request, string $outcome): SubstitutionRequest
    {
        $allowed = match ($outcome) {
            'closed' => ['assigned'],
            'cancelled' => ['open', 'assigned'],
            default => [],
        };
        if (! in_array($request->status, $allowed, true)) {
            throw ValidationException::withMessages(['status' => __($outcome === 'closed' ? 'substitutions.error_cannot_close' : 'substitutions.error_cannot_cancel')]);
        }
        $request->update(['status' => $outcome]);

        return $request->refresh();
    }
}
