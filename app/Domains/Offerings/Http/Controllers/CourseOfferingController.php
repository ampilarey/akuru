<?php

namespace App\Domains\Offerings\Http\Controllers;

use App\Domains\Offerings\Actions\ListCourseOfferingsAction;
use App\Domains\Offerings\Actions\PinOfferingContentAction;
use App\Domains\Offerings\Actions\SaveCourseOfferingAction;
use App\Domains\Offerings\Models\CourseOffering;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CourseOfferingController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('courses.manage'), 403);

        return Inertia::render('Offerings/Catalog/Index', app(ListCourseOfferingsAction::class)->execute());
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        app(SaveCourseOfferingAction::class)->execute($this->validated($request) + [
            'created_by' => $request->user()?->id,
        ]);

        return redirect()->route('catalog.offerings.index')->with('success', 'Offering saved.');
    }

    public function update(Request $request, int $offering): RedirectResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        app(SaveCourseOfferingAction::class)->execute(
            $this->validated($request),
            CourseOffering::query()->findOrFail($offering),
        );

        return redirect()->route('catalog.offerings.index')->with('success', 'Offering updated.');
    }

    public function pin(Request $request, int $offering): RedirectResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        // SPEC §28.4 keeps the reason nullable, so a pin is never blocked for
        // want of a comment — but it has to be askable, and it was not
        // captured at all.
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        app(PinOfferingContentAction::class)->execute(
            $offering,
            $request->user()?->id,
            $data['reason'] ?? null,
        );

        return redirect()->route('catalog.offerings.index')->with('success', 'Offering pinned to current revisions.');
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        $payload = app(ListCourseOfferingsAction::class)->execute();

        return response()->streamDownload(function () use ($payload): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'course_title', 'title', 'delivery_mode', 'audience', 'level', 'status', 'pin_mode']);
            foreach ($payload['rows'] as $row) {
                fputcsv($out, [
                    $row['id'], $row['course_title'], $row['title'], $row['delivery_mode'],
                    // SPEC §10.5/§10.6: which batch this is, in the export an
                    // admin actually reads.
                    $row['audience'] ?? '', $row['level'] ?? '',
                    $row['status'], $row['pin_mode'],
                ]);
            }
            fclose($out);
        }, 'course-offerings.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'title' => ['required', 'string', 'max:255'],
            'title_dv' => ['nullable', 'string', 'max:255'],
            'title_ar' => ['nullable', 'string', 'max:255'],
            'delivery_mode' => ['required', 'string', 'max:32'],
            'status' => ['nullable', 'string', 'max:20'],
            'pin_mode' => ['nullable', 'string', 'max:20'],
            // SPEC §10.5/§10.6: who a batch is for and how advanced it is,
            // stored on the offering rather than duplicated onto the course.
            'audience_id' => ['nullable', 'integer', 'exists:audiences,id'],
            'level_id' => ['nullable', 'integer', 'exists:course_levels,id'],
            'seat_limit' => ['nullable', 'integer', 'min:1'],
            'price_override' => ['nullable', 'numeric', 'min:0'],
            // SPEC §39's offering-level override. These were missing, and
            // `validate()` returns only what it validates, so the field was
            // dropped on the way in however it was posted.
            //
            // Every rule is `nullable` rather than `sometimes` — including the
            // booleans — because blank means "inherit the course's answer",
            // which is a different instruction from "not required".
            'certificate_rules' => ['nullable', 'array'],
            'certificate_rules.min_progress_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'certificate_rules.min_attendance_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'certificate_rules.min_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'certificate_rules.assessment_id' => ['nullable', 'integer', 'exists:assessments,id'],
            'certificate_rules.require_final_assessment' => ['nullable', 'boolean'],
            'certificate_rules.require_teacher_approval' => ['nullable', 'boolean'],
            'certificate_rules.require_payment' => ['nullable', 'boolean'],
        ]);
    }
}
