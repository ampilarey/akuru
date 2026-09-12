<?php

namespace App\Domains\Library\Http\Controllers;

use App\Domains\Library\Actions\DetectLibraryReadingAbuseAction;
use App\Domains\Library\Actions\ListMyLibraryAction;
use App\Domains\Library\Actions\PresentLibraryReaderAction;
use App\Domains\Library\Actions\RecordLibraryReadingEventAction;
use App\Domains\Library\Actions\SaveReadingProgressAction;
use App\Domains\Library\Actions\ToggleLibraryBookmarkAction;
use App\Domains\Library\Models\LibraryItem;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * L2 protected reader endpoints. The gate runs on EVERY page request; the
 * PDF original is never served from here (§43.6 — there is no download
 * path in the reader at all).
 */
class LibraryReaderController extends Controller
{
    public function read(Request $request, string $slug)
    {
        $user = $request->user();
        $reader = app(PresentLibraryReaderAction::class)->execute(
            $slug,
            max(1, (int) $request->query('page', 1)),
            $user?->id,
            $user ? trim($user->name.' • '.$user->email) : 'Akuru Institute',
        );
        if ($reader === null) {
            abort(404);
        }
        if ($reader['requires_login']) {
            return redirect()->guest(route('login'));
        }
        if (! $reader['can_read']) {
            return redirect()->route('public.library.show', $slug);
        }

        // §9.2: log the delivered page, then evaluate the three abuse signals.
        // After the gate, never before — a refused request is not a read, and
        // counting it would let a locked-out reader trip their own alert.
        if ($user !== null) {
            app(RecordLibraryReadingEventAction::class)->execute(
                $user->id,
                (int) $reader['id'],
                (int) $reader['page'],
                $request->session()->getId(),
                $request->ip(),
                (string) $request->userAgent(),
            );

            $detector = app(DetectLibraryReadingAbuseAction::class);
            $detector->execute($user->id, (int) $reader['id']);

            // Enforcement is off by default (config `library.abuse.enforce`):
            // refusing a page locks a paying reader out of a book they own.
            if ($detector->shouldBlock($user->id)) {
                abort(429, 'Too many reading sessions are open on this account.');
            }
        }

        return view('public.library.reader', ['reader' => $reader]);
    }

    public function progress(Request $request, string $slug): RedirectResponse
    {
        abort_unless($request->user() !== null, 403);
        $data = $request->validate([
            'page' => 'required|integer|min:1',
            'seconds' => 'nullable|integer|min:0|max:3600',
        ]);
        $item = LibraryItem::query()->where('slug', $slug)->where('status', 'published')->firstOrFail();

        app(SaveReadingProgressAction::class)->execute(
            (int) $request->user()->id,
            $item->id,
            (int) $data['page'],
            (int) ($item->page_count ?? 0),
            (int) ($data['seconds'] ?? 0),
        );

        return back();
    }

    public function bookmark(Request $request, string $slug): RedirectResponse
    {
        abort_unless($request->user() !== null, 403);
        $data = $request->validate([
            'page' => 'required|integer|min:1',
            'note' => 'nullable|string|max:500',
        ]);
        $item = LibraryItem::query()->where('slug', $slug)->where('status', 'published')->firstOrFail();

        app(ToggleLibraryBookmarkAction::class)->execute(
            (int) $request->user()->id,
            $item->id,
            (int) $data['page'],
            $data['note'] ?? null,
        );

        return back();
    }

    public function myLibrary(Request $request)
    {
        abort_unless($request->user() !== null, 403);

        return view('public.library.my', [
            'library' => app(ListMyLibraryAction::class)->execute((int) $request->user()->id),
        ]);
    }
}
