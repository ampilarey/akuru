<?php

namespace App\Domains\Forms\Actions;

use App\Domains\Forms\Models\FormResponse;
use App\Domains\People\Actions\ListGuardianChildrenAction;
use App\Domains\People\Actions\ResolveStudentForUserAction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Answers this guardian's children have given that still need confirming.
 *
 * Without this the feature is a trap: the pupil answers, the form looks done to
 * them, and the parent never learns there is anything to confirm. A pending
 * list is what turns "unconfirmed" from a silent state into a task.
 */
class ListPendingConfirmationsAction
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(int $guardianUserId): Collection
    {
        $children = app(ListGuardianChildrenAction::class)->executeForGuardianUserId($guardianUserId);
        if ($children->isEmpty()) {
            return collect();
        }

        // Children are students; the answers were given by their user accounts.
        $studentIds = $children->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $childUserIds = DB::table('students')
            ->whereIn('id', $studentIds)
            ->whereNotNull('user_id')
            ->pluck('user_id', 'id');

        if ($childUserIds->isEmpty()) {
            return collect();
        }

        $names = $children->keyBy('id');

        return FormResponse::query()
            ->with('form')
            ->whereIn('user_id', $childUserIds->values())
            ->whereNull('confirmed_at')
            ->orderByDesc('submitted_at')
            ->get()
            ->filter(fn (FormResponse $row): bool => (bool) ($row->form?->requires_parent_confirmation))
            ->map(function (FormResponse $row) use ($childUserIds, $names): array {
                $studentId = $childUserIds->search((int) $row->user_id);
                $child = $studentId !== false ? $names->get($studentId) : null;

                return [
                    'response_id' => (int) $row->id,
                    'form_id' => (int) $row->form_id,
                    'form_title' => (string) ($row->form?->title ?? ''),
                    'child_name' => trim(($child->first_name ?? '').' '.($child->last_name ?? '')),
                    'submitted_at' => $row->submitted_at?->toIso8601String(),
                    'answers' => $row->answers ?? [],
                    'fields' => $row->form?->fields ?? [],
                ];
            })
            ->values();
    }

    /** Badge count for the portal. */
    public function count(int $guardianUserId): int
    {
        return $this->execute($guardianUserId)->count();
    }

    /**
     * Whether a user is a pupil whose answers need confirming at all — used to
     * tell them their answer is not finished yet.
     */
    public function isPupil(int $userId): bool
    {
        return app(ResolveStudentForUserAction::class)->execute($userId) !== null;
    }
}
