<?php

use App\Domains\Identity\Models\User;
use App\Domains\Notifications\Actions\ListMessageInboxAction;
use App\Domains\Notifications\Actions\ShowMessageThreadAction;
use App\Domains\Notifications\Actions\StartClassMessageThreadAction;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * A class send is one thread with every family on it, and until the family
 * walk (STATUS §5fp) every family was shown every other family by name — in
 * the inbox ("With Hassan Ahmed, Shared Guardian, Naseem Father") and on the
 * thread. The plan's acceptance for E2 is that each family sees only its own
 * conversation. Replies already went to the teacher alone; now the names do
 * too. The teacher, who is writing to everyone, still sees everyone.
 */
it('shows a family the teacher and themselves, and the teacher every family', function () {
    ['teacherUser' => $teacherUser, 'class' => $class, 'guardians' => $guardians] = seedClassWithFamilies(3);

    $thread = app(StartClassMessageThreadAction::class)
        ->execute((int) $teacherUser->id, (int) $class->id, 'Trip', 'Details');

    $name = fn (int $index): string => (string) User::query()->find($guardians[$index]->user_id)->name;

    $familyView = app(ShowMessageThreadAction::class)->execute((int) $thread->id, (int) $guardians[0]->user_id);
    $familyNames = array_column($familyView['participants'], 'name');

    expect($familyNames)->toContain($teacherUser->name)
        ->and($familyNames)->toContain($name(0))
        ->and($familyNames)->not->toContain($name(1))
        ->and($familyNames)->not->toContain($name(2));

    $familyInbox = app(ListMessageInboxAction::class)->execute((int) $guardians[0]->user_id);
    expect($familyInbox->first()['with'])->toBe([$teacherUser->name]);

    $teacherView = app(ShowMessageThreadAction::class)->execute((int) $thread->id, (int) $teacherUser->id);
    expect(array_column($teacherView['participants'], 'name'))
        ->toContain($name(0))
        ->toContain($name(1))
        ->toContain($name(2));

    $teacherInbox = app(ListMessageInboxAction::class)->execute((int) $teacherUser->id);
    expect($teacherInbox->first()['with'])->toHaveCount(3);
});
