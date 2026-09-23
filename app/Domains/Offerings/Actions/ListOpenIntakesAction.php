<?php

namespace App\Domains\Offerings\Actions;

use App\Domains\Offerings\Enums\DeliveryMode;
use App\Domains\Offerings\Enums\OfferingStatus;
use App\Domains\Offerings\Models\CourseOffering;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The intakes a learner can choose between: every **open, scheduled**
 * offering of the given courses — face-to-face, live online, blended,
 * hybrid — with the seats it has left and its next session.
 *
 * Phase 1B built the offering (CRUD, sessions, attendance, seat locks,
 * pinning) and its definition of done says *"Student enrollment can link to
 * an offering"*. It could, through the actions; no screen let a person
 * choose one. The learner catalog enrolled into the default self-learning
 * offering and nothing else, so every face-to-face batch ever created had
 * a roster only a seeder or a test could fill (1B audit D1, STATUS §5fh).
 *
 * Seats are counted the way `ReserveOfferingSeatAction` counts them —
 * the same statuses, soft-deletes excluded — so what the catalog shows is
 * what the lock will enforce. `course_enrollments` is read by query, not
 * model (rule 3), as the seat lock already does.
 *
 * @return Collection<int, array<string, mixed>> keyed by course id, each a
 *                                               list of intakes
 */
class ListOpenIntakesAction
{
    public const OCCUPYING = ['active', 'approved', 'pending', 'completed'];

    /**
     * @param  list<int>  $courseIds
     */
    public function execute(array $courseIds): Collection
    {
        if ($courseIds === []) {
            return collect();
        }

        $offerings = CourseOffering::query()
            ->whereIn('course_id', $courseIds)
            ->where('status', OfferingStatus::Open)
            ->where('delivery_mode', '!=', DeliveryMode::SelfLearning)
            ->orderBy('title')
            ->get();

        if ($offerings->isEmpty()) {
            return collect();
        }

        $held = DB::table('course_enrollments')
            ->whereIn('course_offering_id', $offerings->pluck('id'))
            ->whereIn('status', self::OCCUPYING)
            ->whereNull('deleted_at')
            ->groupBy('course_offering_id')
            ->selectRaw('course_offering_id, count(*) as held')
            ->pluck('held', 'course_offering_id');

        $nextSession = DB::table('course_offering_sessions')
            ->whereIn('course_offering_id', $offerings->pluck('id'))
            ->whereNull('deleted_at')
            ->where('starts_at', '>=', now('Indian/Maldives'))
            ->orderBy('starts_at')
            ->get(['course_offering_id', 'title', 'starts_at', 'location_name'])
            ->groupBy('course_offering_id')
            ->map(fn (Collection $rows) => $rows->first());

        return $offerings
            ->map(function (CourseOffering $offering) use ($held, $nextSession): array {
                $limit = $offering->seat_limit !== null ? (int) $offering->seat_limit : null;
                $taken = (int) ($held[$offering->id] ?? 0);
                $next = $nextSession->get($offering->id);

                return [
                    'id' => (int) $offering->id,
                    'course_id' => (int) $offering->course_id,
                    'title' => $offering->title,
                    'delivery_mode' => $offering->delivery_mode instanceof DeliveryMode ? $offering->delivery_mode->value : (string) $offering->delivery_mode,
                    'delivery_mode_label' => $offering->delivery_mode instanceof DeliveryMode ? $offering->delivery_mode->label() : (string) $offering->delivery_mode,
                    'seat_limit' => $limit,
                    'seats_left' => $limit === null ? null : max(0, $limit - $taken),
                    'full' => $limit !== null && $taken >= $limit,
                    'price_override' => $offering->price_override !== null ? (float) $offering->price_override : null,
                    'next_session' => $next ? [
                        'title' => $next->title,
                        'starts_at' => (string) $next->starts_at,
                        'location_name' => $next->location_name,
                    ] : null,
                ];
            })
            ->groupBy('course_id')
            ->map(fn (Collection $rows) => $rows->values()->all());
    }
}
