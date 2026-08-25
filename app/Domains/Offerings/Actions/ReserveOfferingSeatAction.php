<?php

namespace App\Domains\Offerings\Actions;

use App\Domains\Offerings\Models\CourseOffering;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReserveOfferingSeatAction
{
    /**
     * @return array{id: int, course_id: int, seat_limit: int|null}
     */
    public function execute(int $offeringId): array
    {
        return DB::transaction(function () use ($offeringId): array {
            $offering = CourseOffering::query()->lockForUpdate()->findOrFail($offeringId);
            if ($offering->seat_limit !== null) {
                $taken = DB::table('course_enrollments')
                    ->where('course_offering_id', $offering->id)
                    ->whereIn('status', ['active', 'approved', 'pending', 'completed'])
                    ->lockForUpdate()
                    ->count();
                if ($taken >= $offering->seat_limit) {
                    throw ValidationException::withMessages([
                        'course_offering_id' => 'This offering has no remaining seats.',
                    ]);
                }
            }

            return [
                'id' => $offering->id,
                'course_id' => $offering->course_id,
                'seat_limit' => $offering->seat_limit,
            ];
        });
    }
}
