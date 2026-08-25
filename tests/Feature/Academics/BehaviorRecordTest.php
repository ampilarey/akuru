<?php

use App\Domains\Academics\Actions\SaveBehaviorRecordAction;
use App\Domains\Academics\Enums\BehaviorType;
use App\Domains\Academics\Models\BehaviorRecord;
use App\Domains\Academics\Models\BehaviorRecordAudit;
use App\Domains\Identity\Models\User;
use App\Domains\People\Actions\AttachGuardianAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

it('records behavior and hides non-visible rows from parents', function () {
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $mine = makeStudent(['first_name' => 'Mine']);
    $other = makeStudent(['first_name' => 'Other']);
    $guardian = makeGuardian();
    app(AttachGuardianAction::class)->execute($mine, $guardian, 'father', true);
    $teacher = actingPeopleAdmin(['behavior.record']);

    app(SaveBehaviorRecordAction::class)->execute([
        'student_id' => $mine->id,
        'academic_year_id' => $year->id,
        'type' => BehaviorType::Compliment->value,
        'category' => 'conduct',
        'description' => 'Helped a classmate',
        'date' => '2026-08-25',
        'recorded_by' => $teacher->id,
        'parent_visible' => true,
    ], null, $teacher->id);

    app(SaveBehaviorRecordAction::class)->execute([
        'student_id' => $mine->id,
        'academic_year_id' => $year->id,
        'type' => BehaviorType::Incident->value,
        'category' => 'safety',
        'description' => 'Staff only note',
        'date' => '2026-08-25',
        'recorded_by' => $teacher->id,
        'parent_visible' => false,
    ], null, $teacher->id);

    $parent = User::query()->findOrFail($guardian->user_id);
    $this->withoutLocalizationMiddleware()
        ->actingAs($parent)
        ->get(route('portal.behavior'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Portal/Behavior')
            ->has('records', 1)
            ->where('records.0.description', 'Helped a classmate')
        );

    $this->withoutLocalizationMiddleware()
        ->actingAs($parent)
        ->get(route('portal.behavior', ['student_id' => $other->id]))
        ->assertForbidden();

    $admin = actingPeopleAdmin(['behavior.manage', 'behavior.record']);
    $hidden = BehaviorRecord::query()->where('parent_visible', false)->sole();
    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->delete(route('academics.behavior.destroy', $hidden))
        ->assertRedirect();

    expect(BehaviorRecord::query()->count())->toBe(1)
        ->and(BehaviorRecordAudit::query()->where('action', 'deleted')->count())->toBe(1);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('people.students.show', ['student' => $mine, 'tab' => 'behavior']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('People/Students/Show')
            ->has('behaviorRecords', 1)
        );
});

it('forbids recording without behavior.record', function () {
    $user = actingPeopleAdmin([]);
    Permission::findOrCreate('behavior.record', 'web');

    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->get(route('academics.behavior.index'))
        ->assertForbidden();
});
