<?php

namespace App\Domains\Forms\Actions;

use App\Domains\Forms\Models\Form;
use App\Domains\Forms\Models\FormResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The results table somebody has to work from.
 *
 * On an anonymous form the respondent column is absent rather than blank: a
 * column headed "who" full of dashes invites someone to go looking for the
 * answer in the database.
 */
class ListFormResponsesAction
{
    /**
     * @return array{form: array<string, mixed>, rows: Collection<int, array<string, mixed>>}
     */
    public function execute(int $formId): array
    {
        $form = Form::query()->findOrFail($formId);

        $responses = FormResponse::query()
            ->where('form_id', $form->id)
            ->orderByDesc('submitted_at')
            ->get();

        $names = $form->is_anonymous
            ? collect()
            : DB::table('users')
                ->whereIn('id', $responses->pluck('user_id')->filter())
                ->pluck('name', 'id');

        // The office works from pupils, not accounts. An answer that was
        // resolved to a pupil (their own, or the child a guardian answered
        // for) is named after the pupil; the account name is the fallback.
        // The sign-up walk (STATUS §5fq) read the pupil's account name where
        // it expected the pupil, because a pupil's login is not the pupil.
        $pupils = $form->is_anonymous
            ? collect()
            : DB::table('students')
                ->whereIn('id', $responses->pluck('student_id')->filter())
                ->get(['id', 'first_name', 'last_name'])
                ->mapWithKeys(fn ($row): array => [(int) $row->id => trim($row->first_name.' '.$row->last_name)]);

        return [
            'form' => [
                'id' => (int) $form->id,
                'title' => (string) $form->title,
                'description' => $form->description,
                'fields' => $form->fields ?? [],
                'is_anonymous' => (bool) $form->is_anonymous,
                'is_open' => $form->isOpen(),
                // The rest of the form's own settings, so the results screen
                // can send it back unchanged with a new closing time.
                'is_published' => (bool) $form->is_published,
                'target_audience' => $form->target_audience,
                'target_classes' => $form->target_classes,
                'fee_amount' => $form->hasFee() ? (float) $form->fee_amount : null,
                'opens_at' => $form->opens_at?->toIso8601String(),
                'closes_at' => $form->closes_at?->toIso8601String(),
                'responses' => $responses->count(),
                'requires_parent_confirmation' => (bool) $form->requires_parent_confirmation,
                // Counted separately because acting on unconfirmed answers is
                // the mistake this feature exists to prevent.
                'confirmed' => $responses->whereNotNull('confirmed_at')->count(),
            ],
            'rows' => $responses->map(fn (FormResponse $row): array => array_filter([
                'id' => (int) $row->id,
                'respondent' => $form->is_anonymous
                    ? null
                    : ($pupils[(int) $row->student_id] ?? $names[$row->user_id] ?? 'Unknown'),
                'submitted_at' => $row->submitted_at?->toIso8601String(),
                'confirmed_at' => $form->requires_parent_confirmation
                    ? ($row->confirmed_at?->toIso8601String() ?? '')
                    : null,
                'answers' => $row->answers ?? [],
            ], fn ($value): bool => $value !== null))->values(),
        ];
    }
}
