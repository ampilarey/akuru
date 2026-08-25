<?php

namespace App\Domains\HR\Actions;

use App\Domains\HR\Enums\JobApplicationStatus;
use App\Domains\HR\Enums\OnboardingKind;
use App\Domains\HR\Models\JobApplication;
use App\Domains\Identity\Actions\CreateUserAction;
use App\Domains\People\Actions\CreateStaffProfileAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HireApplicantAction
{
    /**
     * @return array{user_id: int, staff_profile_id: int}
     */
    public function execute(int $applicationId, ?int $reviewedBy = null): array
    {
        $application = JobApplication::query()->with('posting')->find($applicationId);
        if ($application === null) {
            throw ValidationException::withMessages(['application' => 'Unknown application.']);
        }

        if (! $application->email) {
            throw ValidationException::withMessages(['email' => 'An email is required to hire.']);
        }

        $parts = preg_split('/\s+/', trim($application->name), 2) ?: [$application->name];

        return DB::transaction(function () use ($application, $parts, $reviewedBy): array {
            $user = app(CreateUserAction::class)->execute(
                $application->name,
                (string) $application->email,
                null,
                $application->mobile,
            );

            $employment = (string) ($application->posting?->employment_type ?: 'full_time');
            if (! in_array($employment, ['full_time', 'part_time', 'contract', 'volunteer'], true)) {
                $employment = 'full_time';
            }

            $profile = app(CreateStaffProfileAction::class)->execute([
                'user_id' => $user['id'],
                'first_name' => $parts[0] ?? $application->name,
                'last_name' => $parts[1] ?? '',
                'phone' => $application->mobile,
                'department' => $application->posting?->department,
                'employment_type' => $employment,
            ]);

            app(SeedStaffChecklistAction::class)->execute((int) $profile['id'], OnboardingKind::Onboarding);

            app(SaveJobApplicationAction::class)->execute([
                'job_posting_id' => $application->job_posting_id,
                'name' => $application->name,
                'mobile' => $application->mobile,
                'email' => $application->email,
                'cv_document_id' => $application->cv_document_id,
                'cover_note' => $application->cover_note,
                'status' => JobApplicationStatus::Hired->value,
                'reviewed_by' => $reviewedBy,
                'note' => 'Hired',
            ], $application);

            return [
                'user_id' => $user['id'],
                'staff_profile_id' => (int) $profile['id'],
            ];
        });
    }
}
