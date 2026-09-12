<?php

namespace App\Domains\Library\Actions;

use App\Domains\Library\Models\LibraryReadingAlert;
use Illuminate\Validation\ValidationException;

/**
 * A person closes an alert with a verdict. The row is kept either way — an
 * alert dismissed in September has to be explainable in March, and a pattern of
 * dismissals is itself worth seeing.
 */
class ReviewLibraryReadingAlertAction
{
    private const OUTCOMES = ['legitimate', 'watching', 'abuse'];

    public function execute(LibraryReadingAlert $alert, int $reviewedBy, string $outcome): LibraryReadingAlert
    {
        if (! in_array($outcome, self::OUTCOMES, true)) {
            throw ValidationException::withMessages([
                'outcome' => 'Choose one of: '.implode(', ', self::OUTCOMES).'.',
            ]);
        }

        if (! $alert->isOpen()) {
            throw ValidationException::withMessages(['alert' => 'This alert was already reviewed.']);
        }

        $alert->update([
            'reviewed_by' => $reviewedBy,
            'reviewed_at' => now('Indian/Maldives'),
            'outcome' => $outcome,
        ]);

        return $alert->fresh();
    }
}
