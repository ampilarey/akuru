<?php

namespace App\Domains\Bookshop\Actions;

use App\Domains\Bookshop\Models\ProductQuestion;
use Illuminate\Validation\ValidationException;

/**
 * The office moderates product questions (STATUS §5le), as it does
 * reviews: hide one — question and answer — with a note the shop sees, or
 * publish one that was hidden.
 */
class ModerateQuestionAction
{
    /**
     * The newest questions across the shop.
     *
     * @return list<array<string, mixed>>
     */
    public function list(int $limit = 200): array
    {
        return ProductQuestion::query()->with(['product:id,title,slug', 'vendor:id,name'])->latest()->limit($limit)->get()
            ->map(fn (ProductQuestion $q) => [
                'id' => $q->id,
                'product' => $q->product?->title,
                'product_slug' => $q->product?->slug,
                'vendor' => $q->vendor?->name,
                'question' => $q->question,
                'answer' => $q->answer,
                'status' => $q->status,
                'moderation_note' => $q->moderation_note,
                'created_at' => $q->created_at?->toDateTimeString(),
            ])->values()->all();
    }

    public function execute(int $questionId, string $action, int $officeUserId, ?string $note = null): ProductQuestion
    {
        $question = ProductQuestion::query()->whereKey($questionId)->firstOrFail();
        $note = trim((string) $note) !== '' ? mb_substr(trim((string) $note), 0, 500) : null;
        match ($action) {
            'hide' => $question->update(['status' => ProductQuestion::HIDDEN, 'moderated_at' => now(), 'moderated_by' => $officeUserId, 'moderation_note' => $note ?? throw ValidationException::withMessages(['note' => __('shop.error_moderation_note_required')])]),
            'publish' => $question->update(['status' => ProductQuestion::PUBLISHED, 'moderated_at' => now(), 'moderated_by' => $officeUserId, 'moderation_note' => $note]),
            default => throw ValidationException::withMessages(['action' => __('shop.error_moderation_action')]),
        };

        return $question->refresh();
    }
}
