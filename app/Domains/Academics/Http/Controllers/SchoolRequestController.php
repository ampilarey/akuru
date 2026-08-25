<?php

namespace App\Domains\Academics\Http\Controllers;

use App\Domains\Academics\Actions\ResolveTeacherIdForUserAction;
use App\Domains\Academics\Actions\ReviewSchoolRequestAction;
use App\Domains\Academics\Actions\SubmitSchoolRequestAction;
use App\Domains\Academics\Enums\SchoolRequestStatus;
use App\Domains\Academics\Enums\SchoolRequestType;
use App\Domains\Academics\Models\SchoolRequest;
use App\Domains\HR\Actions\ListLeaveTypesAction;
use App\Domains\People\Actions\ResolveStaffProfileForUserAction;
use App\Http\Controllers\Controller;
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

        $canReview = (bool) $request->user()?->can('requests.review');
        $teacherId = app(ResolveTeacherIdForUserAction::class)->execute($request->user()?->id);

        $rows = SchoolRequest::query()
            ->when(! $canReview, fn ($query) => $query->where('requester_id', $request->user()->id))
            ->orderByDesc('id')
            ->get()
            ->map(fn (SchoolRequest $row) => $this->serialize($row));

        return Inertia::render('Academics/Requests/Index', [
            'requests' => $rows,
            'types' => array_map(fn (SchoolRequestType $type) => $type->value, SchoolRequestType::cases()),
            'canReview' => $canReview,
            'teacherId' => $teacherId,
            'leaveTypes' => app(ListLeaveTypesAction::class)->execute(true)->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('requests.submit'), 403);

        $data = $request->validate([
            'type' => ['required', Rule::enum(SchoolRequestType::class)],
            'reason' => ['required', 'string', 'max:2000'],
            'teacher_id' => ['nullable', 'integer', 'exists:teachers,id'],
            'leave_type_id' => ['nullable', 'integer', 'exists:leave_types,id'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date'],
            'half_day' => ['sometimes', 'boolean'],
            'document_id' => ['nullable', 'integer', 'exists:documents,id'],
        ]);

        $type = SchoolRequestType::from($data['type']);
        $payload = [];
        $regardingType = null;
        $regardingId = null;

        if ($type === SchoolRequestType::TeacherLeave) {
            $teacherId = isset($data['teacher_id']) && $data['teacher_id'] !== ''
                ? (int) $data['teacher_id']
                : app(ResolveTeacherIdForUserAction::class)->execute($request->user()?->id);
            abort_unless($teacherId !== null, 422, 'A teacher profile is required for leave.');
            $payload = [
                'teacher_id' => $teacherId,
                'from_date' => $data['from_date'] ?? now()->toDateString(),
                'to_date' => $data['to_date'] ?? ($data['from_date'] ?? now()->toDateString()),
            ];
            $regardingType = 'teacher';
            $regardingId = $teacherId;
        }

        if ($type === SchoolRequestType::StaffLeave) {
            $profile = app(ResolveStaffProfileForUserAction::class)->execute((int) $request->user()->id);
            abort_unless($profile !== null, 422, 'A staff profile is required for leave.');
            abort_unless(! empty($data['leave_type_id']), 422, 'A leave type is required.');
            $payload = [
                'staff_profile_id' => (int) $profile['id'],
                'leave_type_id' => (int) $data['leave_type_id'],
                'from_date' => $data['from_date'] ?? now()->toDateString(),
                'to_date' => $data['to_date'] ?? ($data['from_date'] ?? now()->toDateString()),
                'half_day' => (bool) ($data['half_day'] ?? false),
                'document_id' => $data['document_id'] ?? null,
            ];
            $regardingType = 'staff_profile';
            $regardingId = (int) $profile['id'];
        }

        app(SubmitSchoolRequestAction::class)->execute([
            'type' => $type->value,
            'requester_id' => (int) $request->user()->id,
            'regarding_type' => $regardingType,
            'regarding_id' => $regardingId,
            'payload' => $payload,
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
            fputcsv($handle, ['id', 'type', 'status', 'reason', 'requester_id', 'reviewed_at']);
            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->id,
                    $row->type?->value,
                    $row->status?->value,
                    $row->reason,
                    $row->requester_id,
                    $row->reviewed_at?->toDateTimeString(),
                ]);
            }
            fclose($handle);
        }, 'requests.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(SchoolRequest $row): array
    {
        return [
            'id' => $row->id,
            'type' => $row->type?->value,
            'status' => $row->status?->value,
            'reason' => $row->reason,
            'payload' => $row->payload,
            'review_notes' => $row->review_notes,
        ];
    }
}
