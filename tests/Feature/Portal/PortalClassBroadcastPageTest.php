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
