<?php

use App\Domains\Academics\Models\BehaviorRecord;
use App\Domains\Identity\Actions\SearchAccountsAction;
use App\Domains\Identity\Models\User;
use App\Domains\People\Actions\ListTeacherRowOptionsAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Pick the person by name, not by a typed id (BACKLOG C22, BH1, STATUS §5rw):
 * the behaviour form and the fee adjustment form find the pupil, the plan
 * form offers the teachers, and the wallet hand credit finds the account.
 */
uses(RefreshDatabase::class);

it('finds the pupil by name on the behaviour form, warns of a twin, and records against the one chosen', function () {
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $aisha = makeStudent(['first_name' => 'Aisha', 'last_name' => 'Rasheed', 'date_of_birth' => '2015-01-01', 'national_id' => 'A111']);
    makeStudent(['first_name' => 'Aisha', 'last_name' => 'Rasheed', 'date_of_birth' => '2015-01-01', 'national_id' => 'A111']);
    makeStudent(['first_name' => 'Hassan', 'last_name' => 'Ali']);
    $teacher = actingPeopleAdmin(['behavior.record']);

    $this->withoutLocalizationMiddleware()->actingAs($teacher)->get('/academics/behavior')
        ->assertInertia(fn (Assert $page) => $page->component('Academics/Behavior/Index')->where('pupil_matches', []));

    $this->withoutLocalizationMiddleware()->actingAs($teacher)->get('/academics/behavior?pupil_q=aisha')
        ->assertInertia(fn (Assert $page) => $page->has('pupil_matches', 2)
            ->where('pupil_matches.0.name', fn ($name) => str_contains($name, 'Aisha'))
            ->where('pupil_matches.0.indistinguishable', true));

    // One letter finds nobody — the search starts at two.
    $this->withoutLocalizationMiddleware()->actingAs($teacher)->get('/academics/behavior?pupil_q=a')
        ->assertInertia(fn (Assert $page) => $page->where('pupil_matches', []));

    $this->withoutLocalizationMiddleware()->actingAs($teacher)->post('/academics/behavior', [
        'student_id' => $aisha->id, 'academic_year_id' => $year->id, 'type' => 'compliment', 'category' => 'conduct',
        'description' => 'Helped', 'date' => '2026-10-12', 'parent_visible' => true,
    ])->assertRedirect();
    expect(BehaviorRecord::query()->sole()->student_id)->toBe($aisha->id);
});

it('finds the pupil by name on the fee adjustment form', function () {
    makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    makeStudent(['first_name' => 'Mariyam', 'last_name' => 'Naseer']);
    $office = actingPeopleAdmin(['finance.manage']);

    $this->withoutLocalizationMiddleware()->actingAs($office)->get('/finance/adjustments?pupil_q=mariyam')
        ->assertInertia(fn (Assert $page) => $page->component('Finance/Adjustments/Index')->has('pupil_matches', 1)
            ->where('pupil_matches.0.name', fn ($name) => str_contains($name, 'Mariyam')));

    // The behaviour gate does not open the finance search.
    $teacher = actingPeopleAdmin(['behavior.record']);
    $this->withoutLocalizationMiddleware()->actingAs($teacher)->get('/finance/adjustments?pupil_q=mariyam')->assertForbidden();
});

it('offers the teachers by name on the plan form, not a leaver unless already chosen', function () {
    $here = makeTeacherRow();
    $left = makeTeacherRow();
    $left->update(['first_name' => 'Gone', 'status' => 'inactive']);

    $options = app(ListTeacherRowOptionsAction::class);
    expect($options->execute()->pluck('id')->all())->toBe([$here->id])
        ->and($options->execute([$left->id])->pluck('id')->sort()->values()->all())->toBe(collect([$here->id, $left->id])->sort()->values()->all());

    $office = actingPeopleAdmin(['registers.manage']);
    $this->withoutLocalizationMiddleware()->actingAs($office)->get('/academics/plans')
        ->assertInertia(fn (Assert $page) => $page->component('Academics/Plans/Index')->has('teachers', 1)
            ->where('teachers.0.id', $here->id)->where('teachers.0.name', 'Fatimat Ali'));
});

it('finds the account a wallet credit goes to by name, email or mobile', function () {
    $user = User::factory()->create(['name' => 'Ibrahim Waheed', 'email' => 'ibrahim@example.test']);
    $user->contacts()->create(['type' => 'mobile', 'value' => '+9607654321', 'is_primary' => true, 'verified_at' => now()]);
    User::factory()->create(['name' => 'Someone Else']);

    $search = app(SearchAccountsAction::class);
    expect(collect($search->execute('ibrahim'))->pluck('id')->all())->toBe([$user->id])
        ->and(collect($search->execute('example.test'))->pluck('id')->all())->toContain($user->id)
        ->and($search->execute('7654321')[0]['mobile'])->toBe('+9607654321')
        ->and($search->execute('i'))->toBe([]);

    // The Institute's screens admit the system admin (ADR-040).
    $office = User::factory()->create();
    \Spatie\Permission\Models\Role::findOrCreate('super_admin', 'web');
    \Spatie\Permission\Models\Permission::findOrCreate('commerce.manage', 'web');
    $office->assignRole('super_admin');
    $office->givePermissionTo('commerce.manage');
    $this->withoutLocalizationMiddleware()->actingAs($office)->get(route('admin.commerce.index', ['account_q' => 'waheed']))
        ->assertInertia(fn (Assert $page) => $page->component('Commerce/Admin')->has('account_matches', 1)
            ->where('account_matches.0.email', 'ibrahim@example.test'));
});

it('asks for no typed id on the four forms', function () {
    $pages = [
        'Academics/Behavior/Index.jsx' => 'behavior_student_id',
        'Academics/Plans/Index.jsx' => 'plans_teacher_id',
        'Finance/Adjustments/Index.jsx' => 'adjustments_student_id',
        'Commerce/Admin.jsx' => 'commerce_user_id',
    ];
    foreach ($pages as $page => $oldKey) {
        $source = (string) file_get_contents(resource_path('js/Pages/'.$page));
        expect($source)->not->toContain($oldKey);
    }
    foreach (['Academics/Behavior/Index.jsx', 'Finance/Adjustments/Index.jsx', 'Commerce/Admin.jsx'] as $page) {
        expect((string) file_get_contents(resource_path('js/Pages/'.$page)))->toContain('PersonPicker');
    }
});
