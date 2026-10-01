<?php

namespace App\Domains\Identity\Http\Controllers;

use App\Domains\Identity\Actions\IdentityVerificationAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * COMMERCE_PARITY_PLAN P2/P3: the office opens a submitted identity card and
 * decides it. Who may see a card depends on what it is for: a shop's is the
 * Bookstore office's, a writer's the Library's, a learner's the enrolments
 * office's — checked here against the row, never taken from the request.
 */
class IdentityCheckController extends Controller
{
    public function document(Request $request, int $verification, string $side): Response
    {
        $this->authorizeFor($request, $verification);
        $media = app(IdentityVerificationAction::class)->document($verification, $side);
        abort_if($media === null, 404);

        return response($media['contents'], 200, [
            'Content-Type' => $media['mime'],
            'Content-Disposition' => 'inline; filename="'.addslashes($media['original_name']).'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function decide(Request $request, int $verification): RedirectResponse
    {
        $this->authorizeFor($request, $verification);
        $data = $request->validate([
            'decision' => ['required', 'in:verify,reject'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        app(IdentityVerificationAction::class)->decide($verification, (int) $request->user()->id, $data['decision'] === 'verify', $data['note'] ?? null);

        return back()->with('success', __($data['decision'] === 'verify' ? 'account.id_verified_flash' : 'account.id_rejected_flash'));
    }

    private function authorizeFor(Request $request, int $verification): void
    {
        $user = $request->user();
        $purpose = app(IdentityVerificationAction::class)->purposeOf($verification);
        abort_if($purpose === null, 404);
        $allowed = match ($purpose) {
            'vendor' => $user?->can('bookshop.manage'),
            'writer' => $user?->can('library.manage'),
            'learner' => $user?->hasAnyRole(['super_admin', 'admin', 'headmaster']),
            // LENDING_AND_USED_BOOKS_PLAN L1, D6: the Bookstore team checks lenders' cards.
            'lender' => $user?->can('bookshop.manage'),
            default => false,
        };
        abort_unless((bool) $allowed, 403);
    }
}
