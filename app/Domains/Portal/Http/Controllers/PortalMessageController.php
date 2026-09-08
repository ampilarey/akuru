<?php

namespace App\Domains\Portal\Http\Controllers;

use App\Domains\Notifications\Actions\ListMessageInboxAction;
use App\Domains\Notifications\Actions\ListMessageRecipientsAction;
use App\Domains\Notifications\Actions\MarkMessageThreadReadAction;
use App\Domains\Notifications\Actions\ReplyToMessageThreadAction;
use App\Domains\Notifications\Actions\ShowMessageThreadAction;
use App\Domains\Notifications\Actions\StartClassMessageThreadAction;
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
 * E2b — the other direction: staff address a class they teach, and the
 * fan-out finally exercises the author-only reply policy that E2a's single
 * recipient could never reach.
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
            // Staff often have no personal directory of their own but can still
            // address a class, so both routes into compose have to count.
            'canCompose' => app(ListMessageRecipientsAction::class)->execute($userId)->isNotEmpty()
                || ($this->canBroadcast($request) && app(ListMessageRecipientsAction::class)->classes($userId)->isNotEmpty()),
        ]);
    }

    public function create(Request $request): Response
    {
        $userId = $this->userId($request);
        $directory = app(ListMessageRecipientsAction::class);

        return Inertia::render('Portal/Messages/Create', [
            'recipients' => $directory->execute($userId),
            // E2b: staff address classes, families address people. One screen,
            // because a person can be both and should not have to know which
            // form they need.
            'classes' => $this->canBroadcast($request) ? $directory->classes($userId) : collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $userId = $this->userId($request);

        $data = $request->validate([
            'target_type' => ['required', 'in:user,class'],
            'recipient_id' => ['required_if:target_type,user', 'integer'],
            'class_id' => ['required_if:target_type,class', 'integer'],
            'audience' => ['nullable', 'in:guardians,students,both'],
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        // The private helpers hand back an id, not a model: Portal may not
        // name Notifications\Models (rule 3).
        $threadId = $data['target_type'] === 'class'
            ? $this->startClassThread($request, $userId, $data)
            : $this->startPersonThread($userId, $data);

        return redirect()
            ->route('portal.messages.show', $threadId)
            ->with('success', 'Message sent.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function startPersonThread(int $userId, array $data): int
    {
        // Authorisation is the directory: a family may write to the teachers of
        // their own child and nobody else. Checking it here as well as when
        // rendering the form stops a hand-posted id from reaching anyone.
        abort_unless(
            app(ListMessageRecipientsAction::class)->allows($userId, (int) $data['recipient_id']),
            403,
        );

        return (int) app(StartMessageThreadAction::class)->execute(
            $userId,
            [(int) $data['recipient_id']],
            $data['subject'],
            $data['body'],
        )->id;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function startClassThread(Request $request, int $userId, array $data): int
    {
        abort_unless($this->canBroadcast($request), 403);

        // Which classes may be addressed is enforced inside the action, so the
        // rule lives with the data rather than being restated per caller.
        return (int) app(StartClassMessageThreadAction::class)->execute(
            $userId,
            (int) $data['class_id'],
            $data['subject'],
            $data['body'],
            $data['audience'] ?? 'guardians',
        )->id;
    }

    private function canBroadcast(Request $request): bool
    {
        return (bool) $request->user()?->can('messages.broadcast');
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
