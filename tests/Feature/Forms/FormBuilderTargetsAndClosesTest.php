<?php

use App\Domains\Forms\Models\Form;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * E6's acceptance is a sign-up "targeted at one class" that "closed forms
 * reject". `SaveFormAction` honoured `target_classes` and `closes_at` from the
 * first slice; the builder offered neither, and no screen could close a sheet
 * once sent. The sign-up walk (STATUS §5fq) found it. The builder now offers
 * the classes and a closing time, and the results page can close a sheet
 * now without touching anything frozen.
 */
function actingFormsManager(): User
{
    $user = User::factory()->create();
    Permission::findOrCreate('forms.manage', 'web');
    $user->givePermissionTo('forms.manage');

    return $user;
}

it('offers the classes to target and stores the target and the closing time', function () {
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $class = makeClass($year);
    $manager = actingFormsManager();

    $this->withoutLocalizationMiddleware()
        ->actingAs($manager)
        ->get('/forms')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Forms/Index')->has('classes', 1));

    $this->withoutLocalizationMiddleware()
        ->actingAs($manager)
        ->post('/forms', [
            'title' => 'Trip',
            'fields' => [['label' => 'Coming?', 'type' => 'yes_no', 'required' => true]],
            'target_classes' => [$class->id],
            'closes_at' => now()->addDay()->toIso8601String(),
            'is_published' => true,
        ])
        ->assertRedirect('/forms');

    $form = Form::query()->where('title', 'Trip')->firstOrFail();

    expect(array_map('intval', $form->target_classes))->toBe([(int) $class->id])
        ->and($form->isOpen())->toBeTrue();
});

it('closes a sheet from the results screen without touching what is frozen', function () {
    $manager = actingFormsManager();
    $form = Form::query()->create([
        'created_by' => $manager->id, 'title' => 'Trip', 'is_published' => true, 'fee_amount' => 15,
        'fields' => [['key' => 'f1', 'label' => 'Coming?', 'type' => 'yes_no', 'options' => [], 'required' => true]],
    ]);

    $payload = app(\App\Domains\Forms\Actions\ListFormResponsesAction::class)->execute((int) $form->id)['form'];

    $this->withoutLocalizationMiddleware()
        ->actingAs($manager)
        ->put('/forms/'.$form->id, [
            ...$payload,
            'closes_at' => now()->toIso8601String(),
        ])
        ->assertRedirect('/forms');

    $form->refresh();

    expect($form->isOpen())->toBeFalse()
        ->and((float) $form->fee_amount)->toBe(15.0)
        ->and($form->fields[0]['key'])->toBe('f1');

    foreach (['Forms/Index' => ['target_classes', 'closes_at'], 'Forms/Results' => ['Close sign-up now']] as $view => $needles) {
        $source = file_get_contents(resource_path("js/Pages/{$view}.jsx"));
        foreach ($needles as $needle) {
            expect($source)->toContain($needle);
        }
    }
});
