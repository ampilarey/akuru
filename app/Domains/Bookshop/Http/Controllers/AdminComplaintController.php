<?php

namespace App\Domains\Bookshop\Http\Controllers;

use App\Domains\Bookshop\Actions\OrderComplaintAction;
use App\Domains\Bookshop\Actions\ResolveVendorScopeAction;
use App\Http\Controllers\Controller;
use App\Support\Csv;
use App\Support\Inertia\Phrases;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * COMMERCE_PARITY_PLAN P7a: the problems customers reported, open first —
 * the office answers each, and the answer reaches the customer.
 */
class AdminComplaintController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);

        return Inertia::render('Bookshop/Complaints', [
            't' => Phrases::once('shop'),
            'complaints' => app(OrderComplaintAction::class)->list(),
        ]);
    }

    public function reply(Request $request, int $complaint): RedirectResponse
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);
        $data = $request->validate(['status' => 'required|string|in:in_progress,resolved', 'reply' => 'required|string|max:2000']);
        app(OrderComplaintAction::class)->reply($complaint, (int) $request->user()->id, $data['status'], $data['reply']);

        return back()->with('success', __('shop.complaint_replied_flash'));
    }

    /** Every listing gets a CSV (conventions). */
    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);
        $rows = app(OrderComplaintAction::class)->list(5000);

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            Csv::put($out, ['reported', 'order', 'shop', 'kind', 'problem', 'status', 'answer', 'answered']);
            foreach ($rows as $r) {
                Csv::put($out, [$r['created_at'], $r['number'], $r['shop'], $r['kind'], $r['body'], $r['status'], $r['reply'], $r['replied_at']]);
            }
            fclose($out);
        }, 'bookstore-complaints.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** The photo: the customer who sent it, the shop's members, or the office. Never cached. */
    public function photo(Request $request, int $complaint)
    {
        $user = $request->user();
        abort_unless($user !== null, 403);
        $complaints = app(OrderComplaintAction::class);
        $parties = $complaints->parties($complaint);
        $member = app(ResolveVendorScopeAction::class)->memberships((int) $user->id);
        abort_unless($user->can('bookshop.manage') || $parties['user_id'] === (int) $user->id || collect($member)->contains('id', $parties['vendor_id']), 403);
        $media = $complaints->photo($complaint);
        abort_if($media === null, 404);

        return response($media['contents'], 200, [
            'Content-Type' => $media['mime'],
            'Content-Disposition' => 'inline; filename="complaint-'.$complaint.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
