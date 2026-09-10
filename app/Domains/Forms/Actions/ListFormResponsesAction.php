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

        return [
            'form' => [
                'id' => (int) $form->id,
                'title' => (string) $form->title,
                'fields' => $form->fields ?? [],
                'is_anonymous' => (bool) $form->is_anonymous,
                'is_open' => $form->isOpen(),
                'responses' => $responses->count(),
            ],
            'rows' => $responses->map(fn (FormResponse $row): array => array_filter([
                'id' => (int) $row->id,
                'respondent' => $form->is_anonymous ? null : ($names[$row->user_id] ?? 'Unknown'),
                'submitted_at' => $row->submitted_at?->toIso8601String(),
                'answers' => $row->answers ?? [],
            ], fn ($value): bool => $value !== null))->values(),
        ];
    }
}
