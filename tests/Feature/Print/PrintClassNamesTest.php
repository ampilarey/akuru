<?php

use App\Domains\Academics\Actions\AssignStudentToClassAction;
use App\Domains\Bookshop\Actions\Print\SavePrintJobAction;
use App\Domains\Bookshop\Models\PrintTemplate;
use App\Domains\Bookshop\Support\PrintClassLists;
use App\Domains\Bookshop\Support\PrintDesk;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

/*
 * PRINT_PRODUCTS_PLAN PP6a (STATUS §5tn): a name list from a class — for
 * the school office, this year's classes; for a teacher, their own; for a
 * parent, none. Only names (and the class, where the design prints it).
 */
uses(RefreshDatabase::class);

/** This year's two classes, three pupils in 3B, its class teacher, and the desk on. */
function pp6aSchool(): array
{
    $year = makeYear(['name' => 'This year', 'start_date' => now()->subMonth()->toDateString(), 'end_date' => now()->addMonths(6)->toDateString(), 'is_current' => true, 'status' => 'active']);
    $teacher = User::factory()->create();
    $b = makeClass($year, 'Grade 3', 'B');
    $b->update(['class_teacher_id' => $teacher->id]);
    $c = makeClass($year, 'Grade 3', 'C');
    foreach ([['Zara', 'Hassan'], ['Ahmed', 'Naseem'], ['Mariyam', 'Ali']] as [$first, $last]) {
        app(AssignStudentToClassAction::class)->execute($b, (int) makeStudent(['first_name' => $first, 'last_name' => $last])->id);
    }
    PrintDesk::ensure();
    config(['print.desk.enabled' => true]);

    return [$b, $c, $teacher];
}

function pp6aJob(User $owner): \App\Domains\Bookshop\Models\PrintJob
{
    // *Plain* prints the name and the class.
    return app(SavePrintJobAction::class)->start(PrintTemplate::query()->where('title', 'Plain')->firstOrFail(), $owner->id, null, 'en');
}

it('offers the office this year\'s classes, a teacher their own, and a parent none', function () {
    [$b, $c, $teacher] = pp6aSchool();
    $head = User::factory()->create();
    $head->assignRole(Role::findOrCreate('headmaster', 'web'));

    expect(collect(PrintClassLists::classesFor($head))->pluck('label')->all())->toBe(['Grade 3 B', 'Grade 3 C'])
        ->and(collect(PrintClassLists::classesFor($teacher))->pluck('id')->all())->toBe([$b->id])
        ->and(PrintClassLists::classesFor(User::factory()->create()))->toBe([])
        ->and(PrintClassLists::classesFor(null))->toBe([]);

    printWeb()->actingAs($teacher)->get(route('public.shop.print.job', pp6aJob($teacher)->id))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('classes', 1)->where('classes.0.label', 'Grade 3 B'));
    $parent = User::factory()->create();
    printWeb()->actingAs($parent)->get(route('public.shop.print.job', pp6aJob($parent)->id))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('classes', []));
});

it('gives a teacher their class\'s names, sorted, with the class where the design prints it', function () {
    [$b, , $teacher] = pp6aSchool();
    $job = pp6aJob($teacher);

    printWeb()->actingAs($teacher)->getJson(route('public.shop.print.class_names', ['job' => $job->id, 'class' => $b->id]))->assertOk()
        ->assertExactJson(['rows' => [
            ['name' => 'Ahmed Naseem', 'class' => 'Grade 3 B'],
            ['name' => 'Mariyam Ali', 'class' => 'Grade 3 B'],
            ['name' => 'Zara Hassan', 'class' => 'Grade 3 B'],
        ]]);
});

it('refuses another class, an empty one, and anyone else\'s job, in the page\'s language', function () {
    [$b, $c, $teacher] = pp6aSchool();
    $job = pp6aJob($teacher);
    app()->setLocale('dv');

    printWeb()->actingAs($teacher)->getJson(route('public.shop.print.class_names', ['job' => $job->id, 'class' => $c->id]))
        ->assertStatus(422)->assertJsonPath('errors.class.0', __('print.error_class_not_yours', [], 'dv'));
    $parent = User::factory()->create();
    printWeb()->actingAs($parent)->getJson(route('public.shop.print.class_names', ['job' => pp6aJob($parent)->id, 'class' => $b->id]))
        ->assertStatus(422)->assertJsonPath('errors.class.0', __('print.error_class_not_yours', [], 'dv'));
    // Not their job: not found, whatever the class.
    printWeb()->actingAs($parent)->getJson(route('public.shop.print.class_names', ['job' => $job->id, 'class' => $b->id]))->assertNotFound();

    $head = User::factory()->create();
    $head->assignRole(Role::findOrCreate('headmaster', 'web'));
    printWeb()->actingAs($head)->getJson(route('public.shop.print.class_names', ['job' => pp6aJob($head)->id, 'class' => $c->id]))
        ->assertStatus(422)->assertJsonPath('errors.class.0', __('print.error_class_empty', [], 'dv'));
});
