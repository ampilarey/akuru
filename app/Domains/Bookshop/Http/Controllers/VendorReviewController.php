<?php

namespace App\Domains\Bookshop\Http\Controllers;

use App\Domains\Bookshop\Actions\Vendor\VendorQuestionsAction;
use App\Domains\Bookshop\Actions\Vendor\VendorReviewsAction;
use App\Domains\Bookshop\Http\Controllers\Concerns\AuthorizesVendor;
use App\Http\Controllers\Controller;
use App\Support\Csv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * BOOKSHOP_PLAN slice B7: the shop's reviews (`/vendor/reviews`) — read
 * them, reply in public. Since §5le its product questions too — answer them
 * in public. Thin: which reviews and who may reply are the
 * Action's, through the `VendorScope`.
 */
class VendorReviewController extends Controller
{
    use AuthorizesVendor;

    public function index(Request $request): Response
    {
        $scope = $this->authorizeVendor($request);

        return Inertia::render('Bookshop/VendorReviews', [
            't' => trans('shop'),
            'vendor' => ['name' => $scope->vendorName, 'slug' => $scope->vendorSlug, 'role' => $scope->role->value],
            'reviews' => app(VendorReviewsAction::class)->list($scope),
            // §5le: the shop's product questions, on the same page.
            'questions' => app(VendorQuestionsAction::class)->list($scope),
        ]);
    }

    /** §5le: answer a customer's question about a product, in public. */
    public function answer(Request $request, int $question): RedirectResponse
    {
        $scope = $this->authorizeVendor($request);
        $data = $request->validate(['answer' => 'required|string|max:2000']);

        app(VendorQuestionsAction::class)->answer($scope, $question, $data['answer']);

        return back()->with('success', __('shop.answer_saved_flash'));
    }

    /** §5le: the shop's questions as CSV. */
    public function exportQuestions(Request $request): StreamedResponse
    {
        $scope = $this->authorizeVendor($request);
        $rows = app(VendorQuestionsAction::class)->list($scope)['questions'];

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            Csv::put($out, ['date', 'product', 'question', 'answer', 'answered_at', 'status']);
            foreach ($rows as $q) {
                Csv::put($out, [$q['created_at'], $q['product'], $q['question'], $q['answer'], $q['answered_at'], $q['status']]);
            }
            fclose($out);
        }, $scope->vendorSlug.'-questions.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function reply(Request $request, int $review): RedirectResponse
    {
        $scope = $this->authorizeVendor($request);
        $data = $request->validate(['reply' => 'required|string|max:2000']);

        app(VendorReviewsAction::class)->reply($scope, $review, $data['reply']);

        return back()->with('success', __('shop.reply_saved_flash'));
    }

    /** Every listing gets a CSV (conventions): the shop's reviews. */
    public function export(Request $request): StreamedResponse
    {
        $scope = $this->authorizeVendor($request);
        $rows = app(VendorReviewsAction::class)->list($scope)['reviews'];

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            Csv::put($out, ['date', 'product', 'order', 'rating', 'review', 'status', 'reply']);
            foreach ($rows as $r) {
                Csv::put($out, [$r['created_at'], $r['product'], $r['order_number'], $r['rating'], $r['body'], $r['status'], $r['reply']]);
            }
            fclose($out);
        }, $scope->vendorSlug.'-reviews.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
