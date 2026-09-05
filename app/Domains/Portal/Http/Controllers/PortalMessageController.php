<?php

namespace App\Domains\Portal\Http\Controllers;

use App\Domains\Notifications\Actions\ListMessageInboxAction;
use App\Domains\Notifications\Actions\ListMessageRecipientsAction;
use App\Domains\Notifications\Actions\MarkMessageThreadReadAction;
use App\Domains\Notifications\Actions\ReplyToMessageThreadAction;
use App\Domains\Notifications\Actions\ShowMessageThreadAction;
use App\Domains\Notifications\Actions\StartMessageThreadAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * E2a — the messaging core loop: inbox, thread, reply, and a compose form for
 * families writing to the people who teach their child.
 *
 * Threads arrive as ids, not bound models: Portal may not import
 * Notifications\Models (rule 3), and membership is decided by the Notifications
 * actions that own the data rather than re-checked here.
 */
class PortalMessageController extends Controller
{
    public function index(Request $request): Response
    {
        $userId = $this->userId($request);

        return Inertia::render('Portal/Messages/Index', [
            'threads' => app(ListMessageInboxAction::class)->execute($userId),
            'canCompose' => app(ListMessageRecipientsAction::class)->execute($userId)->isNotEmpty(),
        ]);
    }

    public function create(Request $request): Response
    {
        $userId = $this->userId($request);

        return Inertia::render('Portal/Messages/Create', [
            'recipients' => app(ListMessageRecipientsAction::class)->execute($userId),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $userId = $this->userId($request);

        $data = $request->validate([
            'recipient_id' => ['required', 'integer'],
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        // Authorisation is the directory: a family may write to the teachers of
        // their own child and nobody else. Checking it here as well as when
        // rendering the form stops a hand-posted id from reaching anyone.
        abort_unless(
            app(ListMessageRecipientsAction::class)->allows($userId, (int) $data['recipient_id']),
            403,
        );

        $thread = app(StartMessageThreadAction::class)->execute(
            $userId,
            [(int) $data['recipient_id']],
            $data['subject'],
            $data['body'],
        );

        return redirect()
            ->route('portal.messages.show', $thread->id)
            ->with('success', 'Message sent.');
    }

    public function show(Request $request, int $thread): Response
    {
        $userId = $this->userId($request);

        // Opening a thread is reading it; marking before rendering means the
        // badge the reader just cleared is not still counting on this page.
        $payload = app(ShowMessageThreadAction::class)->execute($thread, $userId);
        abort_if($payload === null, 403);

        app(MarkMessageThreadReadAction::class)->execute($thread, $userId);

        return Inertia::render('Portal/Messages/Show', ['thread' => $payload]);
    }

    public function reply(Request $request, int $thread): RedirectResponse
    {
        $userId = $this->userId($request);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        abort_if(
            app(ShowMessageThreadAction::class)->execute($thread, $userId) === null,
            403,
        );

        app(ReplyToMessageThreadAction::class)->execute($thread, $userId, $data['body']);

        return redirect()
            ->route('portal.messages.show', $thread)
            ->with('success', 'Reply sent.');
    }

    private function userId(Request $request): int
    {
        abort_unless($request->user() !== null, 403);

        return (int) $request->user()->id;
    }
}
