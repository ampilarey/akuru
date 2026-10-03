<?php

namespace App\Domains\HR\Http\Controllers;

use App\Domains\HR\Actions\ListAdminInstructorsAction;
use App\Domains\HR\Actions\SaveInstructorAction;
use App\Domains\HR\Models\Instructor;
use App\Domains\Identity\Actions\ListStaffLoginsAction;
use App\Http\Controllers\Controller;
use App\Support\Csv;
use App\Support\Inertia\Phrases;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The instructors shown on the public website (docs/ADMIN_PANEL.md).
 * Inertia since C9 slice 3 (STATUS §5je), with its strings keyed for
 * Dhivehi and Arabic. `role:super_admin` on the route group.
 *
 * Since C16 slice N6 a profile can name the **staff login** it belongs to
 * (`user_id`, one login per profile): that link, with the courses the
 * profile is assigned on the course form, is what lets a teacher's review
 * queue show their own courses (OWNER_ACTIONS 16).
 */
class InstructorController extends Controller
{
    private const RULES = [
        'name' => 'required|string|max:255',
        'bio' => 'nullable|string',
        'qualification' => 'nullable|string|max:255',
        'specialization' => 'nullable|string|max:255',
        'email' => 'nullable|email|max:255',
        'phone' => 'nullable|string|max:30',
        'is_active' => 'boolean',
        'sort_order' => 'nullable|integer|min:0',
        'photo' => 'nullable|image|max:2048',
    ];

    public function index(): Response
    {
        return Inertia::render('Instructors/Index', app(ListAdminInstructorsAction::class)->execute() + ['t' => Phrases::once('admin')]);
    }

    /**
     * CLAUDE.md: "every listing gets CSV export" (admin-panel audit, STATUS §5hs).
     * The public instructor roster as the screen orders it.
     */
    public function export(): StreamedResponse
    {
        $rows = app(ListAdminInstructorsAction::class)->query()->get();

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            Csv::put($out, ['id', 'name', 'email', 'phone', 'qualification', 'specialization', 'courses', 'active', 'sort_order']);
            foreach ($rows as $row) {
                Csv::put($out, [$row->id, $row->name, $row->email, $row->phone, $row->qualification, $row->specialization, $row->courses_count, $row->is_active ? 'yes' : 'no', $row->sort_order]);
            }
            fclose($out);
        }, 'instructors.csv', ['Content-Type' => 'text/csv']);
    }

    public function create(): Response
    {
        return Inertia::render('Instructors/Form', ['instructor' => null] + $this->formProps());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(self::RULES + ['user_id' => ['nullable', 'integer', 'exists:users,id', Rule::unique('instructors', 'user_id')]]);
        app(SaveInstructorAction::class)->execute(null, $data, $request->file('photo'));

        return redirect()->route('admin.instructors.index')->with('success', trans('admin.instructors_created'));
    }

    public function edit(Instructor $instructor): Response
    {
        return Inertia::render('Instructors/Form', ['instructor' => app(ListAdminInstructorsAction::class)->one($instructor)] + $this->formProps());
    }

    public function update(Request $request, Instructor $instructor): RedirectResponse
    {
        $data = $request->validate(self::RULES + ['user_id' => ['nullable', 'integer', 'exists:users,id', Rule::unique('instructors', 'user_id')->ignore($instructor->id)]]);
        app(SaveInstructorAction::class)->execute($instructor, $data, $request->file('photo'));

        return redirect()->route('admin.instructors.index')->with('success', trans('admin.instructors_updated'));
    }

    /**
     * @return array<string, mixed>
     */
    private function formProps(): array
    {
        return ['staff' => app(ListStaffLoginsAction::class)->execute(), 't' => Phrases::once('admin')];
    }

    public function destroy(Instructor $instructor): RedirectResponse
    {
        $instructor->courses()->detach();
        $instructor->delete();

        return redirect()->route('admin.instructors.index')->with('success', trans('admin.instructors_deleted'));
    }
}
