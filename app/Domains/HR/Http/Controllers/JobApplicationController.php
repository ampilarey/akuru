<?php

namespace App\Domains\HR\Http\Controllers;

use App\Domains\HR\Actions\HireApplicantAction;
use App\Domains\HR\Actions\ListJobApplicationsAction;
use App\Domains\HR\Actions\ListJobPostingsAction;
use App\Domains\HR\Actions\SaveJobApplicationAction;
use App\Domains\HR\Enums\JobApplicationStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class JobApplicationController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('hr.manage'), 403);

        return Inertia::render('HR/Recruitment/Applications', [
            'postings' => app(ListJobPostingsAction::class)->execute()->values(),
            'statuses' => array_map(fn (JobApplicationStatus $status) => $status->value, JobApplicationStatus::cases()),
            'rows' => app(ListJobApplicationsAction::class)->execute($request->integer('job_posting_id') ?: null)->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('hr.manage'), 403);

        app(SaveJobApplicationAction::class)->execute($request->validate([
            'job_posting_id' => ['required', 'integer', 'exists:job_postings,id'],
            'name' => ['required', 'string', 'max:255'],
            'mobile' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email'],
            'cover_note' => ['nullable', 'string'],
            'status' => ['nullable', Rule::enum(JobApplicationStatus::class)],
        ]) + ['reviewed_by' => $request->user()?->id]);

        return redirect()->route('hr.applications.index')->with('success', 'Application recorded.');
    }

    public function hire(Request $request, int $application): RedirectResponse
    {
        abort_unless($request->user()?->can('hr.manage'), 403);

        $result = app(HireApplicantAction::class)->execute($application, $request->user()?->id);

        return redirect()
            ->route('hr.onboarding.index', ['staff_profile_id' => $result['staff_profile_id']])
            ->with('success', 'Applicant hired and onboarding opened.');
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('hr.manage'), 403);

        $rows = app(ListJobApplicationsAction::class)->execute($request->integer('job_posting_id') ?: null);

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['name', 'email', 'job_title', 'status']);
            foreach ($rows as $row) {
                fputcsv($out, [$row['name'], $row['email'], $row['job_title'], $row['status']]);
            }
            fclose($out);
        }, 'job-applications.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
