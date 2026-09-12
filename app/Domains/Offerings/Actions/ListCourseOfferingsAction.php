<?php

namespace App\Domains\Offerings\Actions;

use App\Domains\Courses\Actions\ListEngineCoursesAction;
use App\Domains\Courses\Actions\ListPublishedAssessmentsAction;
use App\Domains\Offerings\Enums\DeliveryMode;
use App\Domains\Offerings\Enums\OfferingStatus;
use App\Domains\Offerings\Models\CourseOffering;

class ListCourseOfferingsAction
{
    /**
     * @return array<string, mixed>
     */
    public function execute(): array
    {
        $courses = app(ListEngineCoursesAction::class)->execute()->keyBy('id');

        return [
            'courses' => $courses->values()->all(),
            'modes' => array_map(fn ($mode) => $mode->value, DeliveryMode::cases()),
            // SPEC §11.4's vocabulary, read from the enum instead of typed
            // into the form. The form listed `draft/open/closed/archived` —
            // the four states the enum carried *before* §11.4 was implemented
            // — so the three states that slice added were unreachable and the
            // deprecated one was still the only way to end an offering.
            'statuses' => array_map(fn (OfferingStatus $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
                'deprecated' => $status === OfferingStatus::Closed,
            ], OfferingStatus::cases()),
            // SPEC §39: an offering may override `assessment_id` too, so the
            // control needs the same options the template builder gets.
            'assessments' => app(ListPublishedAssessmentsAction::class)->execute()->all(),
            'rows' => CourseOffering::query()
                ->orderBy('title')
                ->get()
                ->map(fn (CourseOffering $offering) => [
                    'id' => $offering->id,
                    'course_id' => $offering->course_id,
                    'course_title' => $courses[$offering->course_id]['title'] ?? '',
                    'title' => $offering->title,
                    'delivery_mode' => $offering->delivery_mode?->value ?? $offering->delivery_mode,
                    'status' => $offering->status?->value ?? $offering->status,
                    'pin_mode' => $offering->pin_mode,
                    'pinned_at' => $offering->pinned_at?->toIso8601String(),
                    'seat_limit' => $offering->seat_limit,
                    'price_override' => $offering->price_override !== null ? (float) $offering->price_override : null,
                    // SPEC §39's offering-level override. Needed on screen for
                    // the same reason as the re-pin log below: a rule that is
                    // stored but never shown cannot be checked or changed.
                    'certificate_rules' => is_array($offering->certificate_rules) ? $offering->certificate_rules : null,
                    // §11.4: "Invalid transitions must be rejected." The
                    // rejection exists; offering the illegal choice and then
                    // erroring is a worse screen than not offering it, so the
                    // edit form narrows the list to what this state allows.
                    'allowed_transitions' => array_map(
                        fn (OfferingStatus $status): string => $status->value,
                        $this->statusOf($offering)->allowedTransitions(),
                    ),
                    // SPEC §28.4's audit trail. Written but never shown is not
                    // an audit trail — the question it exists to answer ("who
                    // changed what enrolled students see, and why") is only
                    // answerable on screen.
                    'repin_events' => app(ListOfferingRepinEventsAction::class)->execute($offering->id)->all(),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * The model casts `status`, but a row written before that cast existed can
     * still arrive as a plain string, and an unrecognised one must not fatal
     * the whole listing.
     */
    private function statusOf(CourseOffering $offering): OfferingStatus
    {
        if ($offering->status instanceof OfferingStatus) {
            return $offering->status;
        }

        return OfferingStatus::tryFrom((string) $offering->status) ?? OfferingStatus::Draft;
    }
}
