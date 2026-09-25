<?php

namespace App\Domains\Library\Actions;

use App\Domains\Library\Models\WriterApplication;
use App\Domains\Library\Models\WriterProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * L5 (§43.2 — admin must approve writers). Approval creates the writer
 * profile from the application and assigns the `writer` role on the
 * unified identity (ROADMAP §9: writer is a role on People, never a
 * separate identity). Only pending applications are decidable.
 */
class DecideWriterApplicationAction
{
    public function execute(int $applicationId, int $decidedBy, bool $approve, ?string $note = null): WriterApplication
    {
        return DB::transaction(function () use ($applicationId, $decidedBy, $approve, $note) {
            $application = WriterApplication::query()
                ->whereKey($applicationId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($application->status !== 'pending') {
                throw ValidationException::withMessages(['application' => 'This application has already been decided.']);
            }

            $application->fill([
                'status' => $approve ? 'approved' : 'rejected',
                'decided_by' => $decidedBy,
                'decided_at' => now(),
                'decision_note' => $note,
            ])->save();

            if ($approve) {
                WriterProfile::query()->firstOrCreate(
                    ['user_id' => $application->user_id],
                    [
                        'display_name' => $application->display_name,
                        // L8: the author page's address, from the name.
                        'slug' => SaveWriterPublicProfileAction::slugFor($application->display_name),
                        'bio' => $application->bio,
                        'qualifications' => $application->qualifications,
                        'expertise' => $application->expertise,
                        'status' => 'active',
                        'approved_at' => now(),
                        'approved_by' => $decidedBy,
                    ],
                );
                // Role on the unified identity without a cross-domain model
                // import (rule 3): resolve the auth user model from config.
                $userModel = config('auth.providers.users.model');
                $userModel::query()->findOrFail($application->user_id)->assignRole('writer');
            }

            // §41: the applicant hears the decision, and the reason if given.
            app(NotifyLibraryUserAction::class)->execute(
                (int) $application->user_id,
                $approve ? 'You are now an Akuru writer' : 'Writer application not accepted',
                $approve
                    ? 'Your application was approved. Open the writer portal to start a draft.'
                    : ($note !== null && trim($note) !== '' ? trim($note) : 'Your application was not accepted this time.'),
                '/write',
            );

            return $application->refresh();
        });
    }
}
