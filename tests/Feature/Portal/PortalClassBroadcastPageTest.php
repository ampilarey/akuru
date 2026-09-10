<?php

use App\Domains\Identity\Models\User;
use App\Domains\Notifications\Models\Message;
use App\Domains\Notifications\Models\MessageThread;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * E2b walked over HTTP: a teacher composes to a class, six families receive it,
 * one replies, and the reply reaches the teacher alone.
 */
function actingBroadcastTeacher(array $seed): User
{
    Role::findOrCreate('teacher', 'web');
    Permission::findOrCreate('messages.broadcast', 'web');

    $user = $seed['teacherUser'];
    $user->assignRole('teacher');
    $user->givePermissionTo('messages.broadcast');

    return $user->fresh();
}

it('lets a teacher send to a class and a parent reply to the teacher only', function () {
    $seed = seedClassWithFamilies(6);
    $teacherUser = actingBroadcastTeacher($seed);
    $guardianUser = User::query()->find($seed['guardians'][0]->user_id);

    $this->withoutLocalizationMiddleware()
        ->actingAs($teacherUser)
        ->get('/portal/messages/new')
        ->assertOk();

    $this->withoutLocalizationMiddleware()
        ->actingAs($teacherUser)
        ->post('/portal/messages', [
            'target_type' => 'class',
            'class_id' => $seed['class']->id,
            'audience' => 'guardians',
            'subject' => 'Parent evening',
            'body' => 'Thursday at 7pm.',
        ])
        ->assertRedirect();

    $threadId = MessageThread::query()->value('id');
    expect(MessageThread::query()->find($threadId)->reply_policy)->toBe('author_only');

    $this->withoutLocalizationMiddleware()
        ->actingAs($guardianUser)
        ->get('/portal/messages')
        ->assertOk()
        ->assertSee('Parent evening');

    $this->withoutLocalizationMiddleware()
        ->actingAs($guardianUser)
        ->post('/portal/messages/'.$threadId.'/reply', ['body' => 'Is transport included?'])
        ->assertRedirect('/portal/messages/'.$threadId);

    // The whole point of the policy: one parent's question does not reach the
    // other five families.
    $delivered = Message::query()
        ->where('thread_id', $threadId)
        ->where('sender_id', $guardianUser->id)
        ->pluck('recipient_id')
        ->map(fn ($id): int => (int) $id)
        ->all();

    expect($delivered)->toBe([(int) $teacherUser->id]);
});

it('refuses a class broadcast from someone without the permission', function () {
    $seed = seedClassWithFamilies(3);
    Role::findOrCreate('teacher', 'web');
    $teacherUser = $seed['teacherUser'];
    $teacherUser->assignRole('teacher');

    $this->withoutLocalizationMiddleware()
        ->actingAs($teacherUser)
        ->post('/portal/messages', [
            'target_type' => 'class',
            'class_id' => $seed['class']->id,
            'subject' => 'Hello',
            'body' => 'Body',
        ])
        ->assertForbidden();

    expect(MessageThread::query()->count())->toBe(0);
});

it('offers no class picker to a family account', function () {
    $seed = seedClassWithFamilies(2);
    $guardianUser = User::query()->find($seed['guardians'][0]->user_id);

    $this->withoutLocalizationMiddleware()
        ->actingAs($guardianUser)
        ->get('/portal/messages/new')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('classes', []));
});

it('still accepts a post with no target_type as a person message', function () {
    $seed = seedClassWithFamilies(2);
    $guardianUser = User::query()->find($seed['guardians'][0]->user_id);
    $teacherUser = $seed['teacherUser'];

    // A stale bundle posts the pre-E2b shape. It must keep working: the deploy
    // only git-pulls, so the form can lag the backend (§5t).
    $this->withoutLocalizationMiddleware()
        ->actingAs($guardianUser)
        ->post('/portal/messages', [
            'recipient_id' => $teacherUser->id,
            'subject' => 'About Sunday',
            'body' => 'Body',
        ])
        ->assertRedirect();

    expect(MessageThread::query()->count())->toBe(1)
        ->and(Message::query()->where('recipient_id', $teacherUser->id)->count())->toBe(1);
});

it('refuses a hand-posted class the sender does not teach', function () {
    $seed = seedClassWithFamilies(3);
    $outsider = makeTeacherRow();
    Role::findOrCreate('teacher', 'web');
    Permission::findOrCreate('messages.broadcast', 'web');
    $user = User::query()->find($outsider->user_id);
    $user->assignRole('teacher');
    $user->givePermissionTo('messages.broadcast');

    $this->withoutLocalizationMiddleware()
        ->actingAs($user->fresh())
        ->post('/portal/messages', [
            'target_type' => 'class',
            'class_id' => $seed['class']->id,
            'subject' => 'Hello',
            'body' => 'Body',
        ])
        ->assertSessionHasErrors('class_id');

    expect(MessageThread::query()->count())->toBe(0);
});

/**
 * The messages page described itself to staff as if they were a parent:
 * "Conversations with your child's teachers."
 *
 * Found by opening it as a teacher in a browser. Nobody had, because
 * `messages.broadcast` did not exist as a permission row until §5bo created
 * it in a migration — so this screen's staff half had never been seen.
 *
 * `canCompose` cannot answer the question: it is true for a family too, whose
 * personal directory is non-empty. The discriminator is "can address a class".
 */
it('tells staff and families apart on the messages page', function () {
    $seed = seedClassWithFamilies(2);
    $teacherUser = actingBroadcastTeacher($seed);
    $guardianUser = User::query()->find($seed['guardians'][0]->user_id);

    // A teacher can address a class.
    $this->withoutLocalizationMiddleware()->actingAs($teacherUser)
        ->get('/portal/messages')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Portal/Messages/Index')
            ->where('canBroadcast', true)
            ->where('canCompose', true)
            ->etc());

    // A family cannot, but can still compose to their child's teachers — the
    // two flags are genuinely different questions.
    $this->withoutLocalizationMiddleware()->actingAs($guardianUser)
        ->get('/portal/messages')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Portal/Messages/Index')
            ->where('canBroadcast', false)
            ->where('canCompose', true)
            ->etc());
});

it('does not offer broadcast to staff who teach no class', function () {
    // messages.broadcast alone is not enough: an admin holds the permission
    // but teaches nothing, so there is no class to address and the page must
    // not claim otherwise.
    Permission::findOrCreate('messages.broadcast', 'web');
    $admin = User::factory()->create();
    $admin->givePermissionTo('messages.broadcast');

    $this->withoutLocalizationMiddleware()->actingAs($admin->fresh())
        ->get('/portal/messages')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('canBroadcast', false)->etc());
});
