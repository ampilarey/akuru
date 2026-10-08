<?php

namespace App\Domains\Library\Actions;

use App\Domains\Library\Models\LibraryReviewAssignment;
use Illuminate\Validation\ValidationException;

/**
 * RESEARCH_ARTICLES_PLAN R3b: the reviewer pool — everyone holding the
 * `reviewer` role on the unified identity (the role L7 already grants on
 * assignment). The office sees each reviewer's open and finished reports
 * and how many days they take; adds a reviewer by email; and removes one
 * who has no report open.
 *
 * Users are read through the configured auth model, never the Identity
 * domain's class (rule 3), the same way `AssignResearchReviewerAction` does.
 */
class ManageReviewerPoolAction
{
    public const ROLE = 'reviewer';

    /**
     * @return list<array{id: int, name: string, email: string, open: int, done: int, overdue: int, average_days: ?float}>
     */
    public function list(): array
    {
        $userModel = config('auth.providers.users.model');
        $reviewers = $userModel::query()->role(self::ROLE)->orderBy('name')->get(['id', 'name', 'email']);
        $assignments = LibraryReviewAssignment::query()
            ->whereIn('reviewer_user_id', $reviewers->pluck('id'))
            ->get()
            ->groupBy('reviewer_user_id');

        return $reviewers->map(function ($user) use ($assignments) {
            $mine = $assignments->get($user->id) ?? collect();
            $done = $mine->where('status', 'done');
            $days = $done->map(fn (LibraryReviewAssignment $a) => $a->created_at && $a->updated_at ? $a->created_at->diffInHours($a->updated_at) / 24 : null)->filter(fn ($d) => $d !== null);

            return [
                'id' => (int) $user->id,
                'name' => (string) $user->name,
                'email' => (string) $user->email,
                'open' => $mine->where('status', 'assigned')->count(),
                'done' => $done->count(),
                'overdue' => $mine->where('status', 'assigned')->filter(fn (LibraryReviewAssignment $a) => $a->due_at !== null && $a->due_at->isPast())->count(),
                'average_days' => $days->isEmpty() ? null : round((float) $days->avg(), 1),
            ];
        })->values()->all();
    }

    public function add(string $email): void
    {
        $userModel = config('auth.providers.users.model');
        $user = $userModel::query()->where('email', trim($email))->first();
        if ($user === null) {
            throw ValidationException::withMessages(['email' => __('admin.library_office_error_no_account')]);
        }
        $user->assignRole(self::ROLE);
    }

    public function remove(int $userId): void
    {
        if (LibraryReviewAssignment::query()->where('reviewer_user_id', $userId)->where('status', 'assigned')->exists()) {
            throw ValidationException::withMessages(['reviewer' => __('admin.library_office_error_reviewer_busy')]);
        }
        $userModel = config('auth.providers.users.model');
        $userModel::query()->findOrFail($userId)->removeRole(self::ROLE);
    }
}
