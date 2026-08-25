<?php

namespace App\Domains\HR\Http\Controllers;

use App\Domains\HR\Actions\ListJobPostingsAction;
use App\Domains\HR\Actions\SaveJobPostingAction;
use App\Domains\HR\Enums\JobPostingStatus;
use App\Domains\HR\Models\JobPosting;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class JobPostingController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('hr.manage'), 403);

        return Inertia::render('HR/Recruitment/Postings', [
            'rows' => app(ListJobPostingsAction::class)->execute()->values(),
            'statuses' => array_map(fn (JobPostingStatus $status) => $status->value, JobPostingStatus::cases()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('hr.manage'), 403);

        app(SaveJobPostingAction::class)->execute($this->validated($request));

        return redirect()->route('hr.postings.index')->with('success', 'Job posting saved.');
    }

    public function update(Request $request, JobPosting $jobPosting): RedirectResponse
    {
        abort_unless($request->user()?->can('hr.manage'), 403);

        app(SaveJobPostingAction::class)->execute($this->validated($request), $jobPosting);

        return redirect()->route('hr.postings.index')->with('success', 'Job posting updated.');
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('hr.manage'), 403);

        $rows = app(ListJobPostingsAction::class)->execute();

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['title', 'department', 'status', 'public', 'closes_at']);
            foreach ($rows as $row) {
                fputcsv($out, [$row['title'], $row['department'], $row['status'], $row['public'] ? '1' : '0', $row['closes_at']]);
            }
            fclose($out);
        }, 'job-postings.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'title_arabic' => ['nullable', 'string', 'max:255'],
            'title_dhivehi' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'requirements' => ['nullable', 'string'],
            'department' => ['nullable', 'string', 'max:255'],
            'employment_type' => ['nullable', 'string', 'max:32'],
            'closes_at' => ['nullable', 'date'],
            'status' => ['required', Rule::enum(JobPostingStatus::class)],
            'public' => ['sometimes', 'boolean'],
        ]);
    }
}
