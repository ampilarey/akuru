<?php

namespace App\Domains\Bookshop\Actions\Shop;

use App\Domains\Bookshop\Actions\NotifyBookshopUserAction;
use App\Domains\Bookshop\Models\ProductQuestion;
use Illuminate\Validation\ValidationException;

/**
 * Questions and answers on a product page (STATUS §5le): a signed-in
 * customer asks, the shop answers in public, the office may hide either.
 * Only answered, unhidden questions are shown; the asker is shown by first
 * name and initial, like a reviewer. A customer may have a few questions
 * waiting on one product at a time, so a page cannot be flooded.
 */
class ProductQuestionsAction
{
    public const MAX_WAITING_PER_PRODUCT = 3;

    /**
     * @return array{questions: list<array<string, mixed>>, waiting_mine: int}
     */
    public function forProduct(int $productId, ?int $userId): array
    {
        $questions = ProductQuestion::query()->where('product_id', $productId)->where('status', ProductQuestion::PUBLISHED)
            ->whereNotNull('answered_at')->latest('answered_at')->limit(20)->get();
        $names = CustomerNames::short($questions->pluck('user_id')->all());

        return [
            'questions' => $questions->map(fn (ProductQuestion $q) => [
                'id' => $q->id,
                'question' => $q->question,
                'answer' => $q->answer,
                'name' => $names[$q->user_id] ?? __('shop.a_customer'),
                'date' => $q->created_at?->toDateString(),
            ])->values()->all(),
            'waiting_mine' => $userId === null ? 0 : $this->waiting($userId, $productId),
        ];
    }

    public function ask(int $userId, string $productSlug, string $text): ProductQuestion
    {
        $product = ListShopProductsAction::forSale()->where('slug', $productSlug)->firstOrFail();
        $text = trim($text);
        if (mb_strlen($text) < 5) {
            throw ValidationException::withMessages(['question' => __('shop.error_question_short')]);
        }
        if ($this->waiting($userId, (int) $product->id) >= self::MAX_WAITING_PER_PRODUCT) {
            throw ValidationException::withMessages(['question' => __('shop.error_question_waiting', ['count' => self::MAX_WAITING_PER_PRODUCT])]);
        }

        $question = ProductQuestion::query()->create([
            'product_id' => $product->id,
            'vendor_id' => $product->vendor_id,
            'user_id' => $userId,
            'question' => mb_substr($text, 0, 1000),
            'status' => ProductQuestion::PUBLISHED,
        ]);
        app(NotifyBookshopUserAction::class)->vendor((int) $product->vendor_id, __('shop.notice_question_title'), __('shop.notice_question_body', ['title' => $product->title]), '/vendor/reviews#questions', 'question');

        return $question;
    }

    private function waiting(int $userId, int $productId): int
    {
        return ProductQuestion::query()->where('product_id', $productId)->where('user_id', $userId)->whereNull('answered_at')->where('status', ProductQuestion::PUBLISHED)->count();
    }
}
