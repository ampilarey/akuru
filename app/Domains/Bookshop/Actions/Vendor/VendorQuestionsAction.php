<?php

namespace App\Domains\Bookshop\Actions\Vendor;

use App\Domains\Bookshop\Actions\NotifyBookshopUserAction;
use App\Domains\Bookshop\DTOs\VendorScope;
use App\Domains\Bookshop\Models\ProductQuestion;
use Illuminate\Validation\ValidationException;

/**
 * The shop's product questions (STATUS §5le): every question about its
 * products, those waiting for an answer first, and one public answer each,
 * which it may edit. The asker is told when it is first answered. Only the
 * scope's vendor's questions, ever.
 */
class VendorQuestionsAction
{
    /**
     * @return array{waiting: int, questions: list<array<string, mixed>>}
     */
    public function list(VendorScope $scope): array
    {
        $questions = ProductQuestion::query()->where('vendor_id', $scope->vendorId)->with('product:id,title,slug')
            ->orderByRaw('answered_at is not null')->latest()->limit(300)->get();

        return [
            'waiting' => $questions->whereNull('answered_at')->where('status', ProductQuestion::PUBLISHED)->count(),
            'questions' => $questions->map(fn (ProductQuestion $q) => [
                'id' => $q->id,
                'product' => $q->product?->title,
                'product_slug' => $q->product?->slug,
                'question' => $q->question,
                'answer' => $q->answer,
                'status' => $q->status,
                'moderation_note' => $q->moderation_note,
                'answered_at' => $q->answered_at?->toDateTimeString(),
                'created_at' => $q->created_at?->toDateTimeString(),
            ])->values()->all(),
        ];
    }

    public function answer(VendorScope $scope, int $questionId, string $text): ProductQuestion
    {
        $question = ProductQuestion::query()->where('vendor_id', $scope->vendorId)->whereKey($questionId)->with('product:id,title,slug')->firstOrFail();
        $text = trim($text);
        if ($text === '') {
            throw ValidationException::withMessages(['answer' => __('shop.error_answer_empty')]);
        }
        $first = $question->answered_at === null;
        $question->update(['answer' => mb_substr($text, 0, 2000), 'answered_at' => $question->answered_at ?? now(), 'answered_by' => $scope->userId]);
        if ($first) {
            app(NotifyBookshopUserAction::class)->execute((int) $question->user_id, __('shop.notice_question_answered_title'), __('shop.notice_question_answered_body', ['vendor' => $scope->vendorName, 'title' => (string) $question->product?->title]), '/shop/products/'.$question->product?->slug.'#questions');
        }

        return $question;
    }
}
