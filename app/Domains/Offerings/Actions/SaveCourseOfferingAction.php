<?php

namespace App\Domains\Offerings\Actions;

use App\Domains\Courses\Actions\NormalizeCertificateRulesAction;
use App\Domains\Courses\Actions\ResolveEngineCourseAction;
use App\Domains\Offerings\Enums\DeliveryMode;
use App\Domains\Offerings\Enums\OfferingStatus;
use App\Domains\Offerings\Models\CourseOffering;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaveCourseOfferingAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?CourseOffering $offering = null): CourseOffering
    {
        $course = app(ResolveEngineCourseAction::class)->execute((int) $data['course_id']);
        $mode = DeliveryMode::tryFrom((string) ($data['delivery_mode'] ?? ''));
        if ($mode === null) {
            throw ValidationException::withMessages(['delivery_mode' => 'Invalid delivery mode.']);
        }

        $title = (string) $data['title'];
        $slug = $data['slug'] ?? Str::slug($title);
        if ($slug === '') {
            $slug = 'offering-'.Str::lower(Str::random(6));
        }

        $exists = CourseOffering::query()
            ->where('course_id', $course['id'])
            ->where('slug', $slug)
            ->when($offering, fn ($query) => $query->where('id', '!=', $offering->id))
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages(['slug' => 'Offering slug must be unique within the course.']);
        }

        $payload = [
            'course_id' => $course['id'],
            'title' => $title,
            'title_dv' => $data['title_dv'] ?? null,
            'title_ar' => $data['title_ar'] ?? null,
            'slug' => $slug,
            'delivery_mode' => $mode,
            'status' => OfferingStatus::tryFrom((string) ($data['status'] ?? OfferingStatus::Draft->value)) ?? OfferingStatus::Draft,
            // SPEC §28.5 sets the default by delivery mode, not one default
            // for all of them. This defaulted every offering to `latest`, so a
            // face-to-face or live-online cohort had its content change
            // underneath it mid-term unless someone remembered to pin — the
            // opposite of what §28.5 asks for.
            'pin_mode' => $this->pinMode($data['pin_mode'] ?? null, $mode),
            'seat_limit' => isset($data['seat_limit']) && $data['seat_limit'] !== '' ? (int) $data['seat_limit'] : null,
            'price_override' => isset($data['price_override']) && $data['price_override'] !== '' ? round((float) $data['price_override'], 2) : null,
            // SPEC §39: "Certificate rules may be set at course level and
            // overridden at offering level." The column, the cast and this
            // assignment all existed; nothing could reach them, because the
            // controller's validator did not list the field and
            // `$request->validate()` returns only what it validates. The
            // override half of §39 was unreachable from the product.
            //
            // Sparse on purpose — see NormalizeCertificateRulesAction. An
            // absent key inherits the template's answer; storing `false` for
            // every unticked box would quietly switch off the course's own
            // requirements.
            //
            // A posted-but-empty override clears any previous one; an *absent*
            // key leaves what is there alone, so a caller that does not deal
            // in certificate rules cannot wipe them in passing.
            'certificate_rules' => array_key_exists('certificate_rules', $data)
                ? app(NormalizeCertificateRulesAction::class)->execute($data['certificate_rules'], sparse: true)
                : ($offering?->certificate_rules),
            'academic_year_id' => $data['academic_year_id'] ?? null,
            'term_id' => $data['term_id'] ?? null,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'created_by' => $data['created_by'] ?? null,
        ];

        if ($offering === null) {
            return CourseOffering::query()->create($payload);
        }

        // SPEC §11.4: "Invalid transitions must be rejected." An edit form
        // posts the whole offering back, status included, so this is the path
        // an invalid transition would actually arrive through — it took
        // whatever `status` came in and stored it. The status is removed from
        // the general payload and routed through the transition rules, so an
        // ordinary edit cannot reopen a completed cohort in passing.
        $requested = $payload['status'];
        unset($payload['status']);

        $offering->fill($payload);
        $offering->save();

        app(TransitionOfferingStatusAction::class)->execute(
            $offering,
            $requested,
            $data['created_by'] ?? null,
        );

        return $offering->refresh();
    }

    /**
     * SPEC §28.5:
     *
     *   > Default for self-learning offerings: Always latest published
     *   >
     *   > Scheduled offerings such as face-to-face, live online, blended, and
     *   > hybrid should default to pinned mode when the offering opens.
     *
     * An explicit choice always wins; this only decides what happens when the
     * caller says nothing. Self-learning is the only mode that tracks the
     * latest published content, because it is the only one without a cohort
     * moving through the material together.
     */
    private function pinMode(mixed $given, DeliveryMode $mode): string
    {
        if (in_array($given, ['latest', 'pinned'], true)) {
            return $given;
        }

        return $mode === DeliveryMode::SelfLearning ? 'latest' : 'pinned';
    }
}
