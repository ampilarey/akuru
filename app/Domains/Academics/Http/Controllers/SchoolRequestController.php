<?php

namespace App\Domains\Academics\Http\Controllers;

use App\Domains\Academics\Actions\BuildSchoolRequestPayloadAction;
use App\Domains\Academics\Actions\ListSchoolRequestsAction;
use App\Domains\Academics\Actions\ResolveTeacherIdForUserAction;
use App\Domains\Academics\Actions\ReviewSchoolRequestAction;
use App\Domains\Academics\Actions\SubmitSchoolRequestAction;
use App\Domains\Academics\Enums\SchoolRequestStatus;
use App\Domains\Academics\Enums\SchoolRequestType;
use App\Domains\Academics\Models\SchoolRequest;
use App\Domains\HR\Actions\ListLeaveTypesAction;
use App\Domains\People\Actions\ListGuardianChildrenAction;
use App\Domains\People\Actions\ResolveStaffProfileForUserAction;
use App\Http\Controllers\Controller;
use App\Support\Csv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SchoolRequestController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('requests.submit') || $request->user()?->can('requests.review'), 403);

        $userId = (int) $request->user()->id;
        $canReview = (bool) $request->user()?->can('requests.review');
        $teacherId = app(ResolveTeacherIdForUserAction::class)->execute($userId);
        $hasStaffProfile = app(ResolveStaffProfileForUserAction::class)->execute($userId) !== null;

        // Only the types this person can actually file: the two leave types
        // need a teacher or staff profile, and a family offered them was
        // refused with "a staff profile is required" (STATUS §5fw).
        $types = collect(SchoolRequestType::cases())
            ->map(fn (SchoolRequestType $type) => $type->value)
            ->filter(fn (string $type) => match ($type) {
                SchoolRequestType::TeacherLeave->value => $teacherId !== null,
                SchoolRequestType::StaffLeave->value => $hasStaffProfile,
                default => true,
            })
            ->values()
            ->all();

        return Inertia::render('Academics/Requests/Index', [
            'requests' => app(ListSchoolRequestsAction::class)->execute($userId, $canReview),
            'types' => $types,
            'canReview' => $canReview,
            'teacherId' => $teacherId,
            'leaveTypes' => app(ListLeaveTypesAction::class)->execute(true)->values(),
            'children' => app(ListGuardianChildrenAction::class)->executeForGuardianUserId($userId)
                ->map(fn (object $child) => ['id' => (int) $child->id, 'name' => trim($child->first_name.' '.$child->last_name)])
                ->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('requests.submit'), 403);

        $data = $request->validate([
            'type' => ['required', Rule::enum(SchoolRequestType::class)],
            'reason' => ['required', 'string', 'max:2000'],
            'teacher_id' => ['nullable', 'integer', 'exists:teachers,id'],
            'student_id' => ['nullable', 'integer', 'exists:students,id'],
            'leave_type_id' => ['nullable', 'integer', 'exists:leave_types,id'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date'],
            'half_day' => ['sometimes', 'boolean'],
            'document_id' => ['nullable', 'integer', 'exists:documents,id'],
            'document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        $type = SchoolRequestType::from($data['type']);
        $about = app(BuildSchoolRequestPayloadAction::class)->execute($type, (int) $request->user()->id, $data, $request->file('document'));

        app(SubmitSchoolRequestAction::class)->execute([
            'type' => $type->value,
            'requester_id' => (int) $request->user()->id,
            'regarding_type' => $about['regarding_type'],
            'regarding_id' => $about['regarding_id'],
            'payload' => $about['payload'],
            'reason' => $data['reason'],
        ]);

        return redirect()->route('academics.requests.index')->with('success', 'Request submitted.');
    }

    public function review(Request $request, SchoolRequest $schoolRequest): RedirectResponse
    {
        abort_unless($request->user()?->can('requests.review'), 403);

        $data = $request->validate([
            'status' => ['required', Rule::enum(SchoolRequestStatus::class)],
            'review_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        app(ReviewSchoolRequestAction::class)->execute(
            $schoolRequest,
            SchoolRequestStatus::from($data['status']),
            (int) $request->user()->id,
            $data['review_notes'] ?? null,
        );

        return redirect()->route('academics.requests.index')->with('success', 'Request reviewed.');
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('requests.review') || $request->user()?->can('requests.submit'), 403);

        $rows = SchoolRequest::query()->orderByDesc('id')->get();

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            Csv::put($handle, ['id', 'type', 'status', 'reason', 'requester_id', 'regarding_type', 'regarding_id', 'submitted_at', 'reviewed_by', 'reviewed_at', 'review_notes']);
            foreach ($rows as $row) {
                Csv::put($handle, [
                    $row->id,
                    $row->type?->value,
                    $row->status?->value,
                    $row->reason,
                    $row->requester_id,
                    $row->regarding_type,
                    $row->regarding_id,
                    $row->created_at?->toDateTimeString(),
                    $row->reviewed_by,
                    $row->reviewed_at?->toDateTimeString(),
                    $row->review_notes,
                ]);
            }
            fclose($handle);
        }, 'requests.csv', ['Content-Type' => 'text/csv']);
    }
}
