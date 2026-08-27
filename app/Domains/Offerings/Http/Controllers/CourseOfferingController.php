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
        app(PinOfferingContentAction::class)->execute($offering, $request->user()?->id);

        return redirect()->route('catalog.offerings.index')->with('success', 'Offering pinned to current revisions.');
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        $payload = app(ListCourseOfferingsAction::class)->execute();

        return response()->streamDownload(function () use ($payload): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'course_title', 'title', 'delivery_mode', 'status', 'pin_mode']);
            foreach ($payload['rows'] as $row) {
                fputcsv($out, [$row['id'], $row['course_title'], $row['title'], $row['delivery_mode'], $row['status'], $row['pin_mode']]);
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
            'seat_limit' => ['nullable', 'integer', 'min:1'],
            'price_override' => ['nullable', 'numeric', 'min:0'],
        ]);
    }
}
