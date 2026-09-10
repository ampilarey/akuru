<?php

namespace App\Domains\Forms\Actions;

use App\Domains\Academics\Actions\ResolveAudienceContextAction;
use App\Domains\Forms\Models\Form;
use App\Domains\Forms\Models\FormResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The forms aimed at this person, and whether they have answered.
 *
 * Audience matching goes through Academics' ResolveAudienceContextAction, the
 * same one the noticeboard uses — two copies would be how the noticeboard and
 * the sign-up sheet end up disagreeing about who is in Grade 5.
 */
class ListFormsForUserAction
{
    /**
     * @param  list<string>  $roleNames
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(int $userId, array $roleNames): Collection
    {
        $forms = Form::query()
            ->where('is_published', true)
            ->orderByDesc('id')
            ->get();

        if ($forms->isEmpty()) {
            return collect();
        }

        $matcher = app(ResolveAudienceContextAction::class);
        $context = $matcher->execute($userId, $roleNames);

        $mine = FormResponse::query()
            ->whereIn('form_id', $forms->pluck('id'))
            ->where('user_id', $userId)
            ->get()
            ->keyBy('form_id');

        // Paid state is read from the invoice, never copied onto the response:
        // two records of whether a family has paid is one more than a school
        // can reconcile.
        $invoices = DB::table('invoices')
            ->whereIn('id', $mine->pluck('invoice_id')->filter())
            ->pluck('status', 'id');

        return $forms
            ->filter(fn (Form $form): bool => $matcher->matches($form->target_audience, $form->target_classes, $context))
            ->map(fn (Form $form): array => [
                'id' => (int) $form->id,
                'title' => (string) $form->title,
                'description' => $form->description,
                'fields' => $form->fields ?? [],
                'is_open' => $form->isOpen(),
                'is_anonymous' => (bool) $form->is_anonymous,
                'closes_at' => $form->closes_at?->toIso8601String(),
                // An anonymous form cannot say whether you answered, because it
                // does not know. Claiming otherwise would be a lie the schema
                // could not back up.
                'answered_at' => $form->is_anonymous
                    ? null
                    : $mine->get($form->id)?->submitted_at?->toIso8601String(),
                'requires_parent_confirmation' => (bool) $form->requires_parent_confirmation,
                'fee_amount' => $form->hasFee() ? (float) $form->fee_amount : null,
                'invoice_id' => $mine->get($form->id)?->invoice_id,
                'invoice_status' => $invoices[$mine->get($form->id)?->invoice_id ?? 0] ?? null,
                // A pupil whose answer is still waiting must be told: otherwise
                // the form looks finished to them and nobody chases the parent.
                'awaiting_confirmation' => (bool) $form->requires_parent_confirmation
                    && $mine->has($form->id)
                    && $mine->get($form->id)->confirmed_at === null,
            ])
            ->values();
    }
}
