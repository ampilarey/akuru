<?php

namespace App\Domains\Courses\Http\Controllers;

use App\Domains\Courses\Components\Arabic\Actions\ListArabicReferenceAction;
use App\Domains\Courses\Components\Quran\Actions\ListQuranAssignmentsAction;
use App\Domains\Courses\Components\Quran\Actions\SaveQuranAssignmentAction;
use App\Domains\Courses\Components\Quran\Models\QuranHifzAssignment;
use App\Domains\People\Actions\ResolveTeacherForUserAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * F5-P2 (ADR-025 gate item 2): the §52.18 assignment board. Engine-owned on
 * purpose — it composes the Quran component's assignments with the Arabic
 * component's letter/haraka reference for the practice picker, which no
 * component may do itself (rule 3; the CatalogQuranOversightController
 * precedent). A teacher sees their own board; staff see all.
 */
class TeachQuranAssignmentController extends Controller
{
    public function index(Request $request): Response|StreamedResponse
    {
        [$teacher] = $this->authorizeTeacher($request);
        $status = (string) $request->query('status', 'all');
        $filters = ['status' => $status];
        if ($teacher !== null && ! $request->user()->can('courses.manage')) {
            $filters['teacher_id'] = $teacher['id'];
        }
        $payload = app(ListQuranAssignmentsAction::class)->execute($filters);

        if ($request->query('format') === 'csv') {
            return response()->streamDownload(function () use ($payload): void {
                $handle = fopen('php://output', 'w');
                fputcsv($handle, ['id', 'student', 'teacher', 'type', 'surah', 'from', 'to', 'due', 'status']);
                foreach ($payload['rows'] as $row) {
                    fputcsv($handle, [
                        $row['id'],
                        $row['student']['name'] ?? '',
                        $row['teacher'] ?? '',
                        $row['assignment_type'],
                        $row['surah'] ?? '',
                        $row['start_ayah_number'],
                        $row['end_ayah_number'],
                        $row['due_date'],
                        $row['status'],
                    ]);
                }
                fclose($handle);
            }, 'quran-assignments.csv', ['Content-Type' => 'text/csv']);
        }

        return Inertia::render('Courses/Teach/QuranAssignments', $payload + [
            'status' => $status,
            'teacher' => $teacher,
            'reference' => app(ListArabicReferenceAction::class)->execute(activeOnly: true),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        [$teacher, $isStaff] = $this->authorizeTeacher($request);
        $data = $this->validated($request);

        $teacherId = $teacher['id'] ?? ($isStaff ? (int) ($data['teacher_id'] ?? 0) : 0);
        if ($teacherId === 0) {
            throw ValidationException::withMessages(['teacher_id' => 'No teacher profile to assign as.']);
        }

        app(SaveQuranAssignmentAction::class)->execute($data + [
            'teacher_id' => $teacherId,
            'created_by' => (int) $request->user()->id,
        ]);

        return back()->with('success', 'Assignment created.');
    }

    public function update(Request $request, int $assignment): RedirectResponse
    {
        [$teacher, $isStaff] = $this->authorizeTeacher($request);
        $row = QuranHifzAssignment::query()->findOrFail($assignment);
        if (! $isStaff && (int) $row->teacher_id !== (int) ($teacher['id'] ?? 0)) {
            abort(403);
        }

        $data = $this->validated($request, partial: true);
        app(SaveQuranAssignmentAction::class)->execute(array_merge(
            [
                'student_id' => $row->student_id,
                'teacher_id' => $row->teacher_id,
                'course_id' => $row->course_id,
                'course_offering_id' => $row->course_offering_id,
                'academic_year_id' => $row->academic_year_id,
                'surah_id' => $row->surah_id,
                'start_ayah_number' => $row->start_ayah_number,
                'end_ayah_number' => $row->end_ayah_number,
                'expected_letter_id' => $row->expected_letter_id,
                'expected_haraka_id' => $row->expected_haraka_id,
                'assignment_type' => $row->assignment_type?->value,
                'due_date' => $row->due_date?->toDateString(),
                'status' => $row->status?->value,
                'notes' => $row->notes,
            ],
            $data,
        ), $row);

        return back()->with('success', 'Assignment updated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return array_filter($request->validate([
            'student_id' => "{$required}|integer",
            'teacher_id' => 'nullable|integer',
            'course_id' => 'nullable|integer',
            'course_offering_id' => 'nullable|integer',
            'academic_year_id' => 'nullable|integer',
            'surah_id' => 'nullable|integer',
            'start_ayah_number' => 'nullable|integer|min:1',
            'end_ayah_number' => 'nullable|integer|min:1',
            'expected_letter_id' => 'nullable|integer',
            'expected_haraka_id' => 'nullable|integer',
            'assignment_type' => "{$required}|string|max:40",
            'due_date' => 'nullable|date',
            'status' => 'nullable|string|max:20',
            'notes' => 'nullable|string|max:5000',
        ]), fn ($value) => $value !== null);
    }

    /**
     * @return array{0: array{id: int, name: string}|null, 1: bool}
     */
    private function authorizeTeacher(Request $request): array
    {
        abort_unless($request->user() !== null, 403);
        $teacher = app(ResolveTeacherForUserAction::class)->execute((int) $request->user()->id);
        $isStaff = $request->user()->can('courses.manage');
        abort_unless($teacher !== null || $isStaff, 403);

        return [$teacher, $isStaff];
    }
}
