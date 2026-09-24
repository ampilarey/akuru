<?php

use App\Domains\Academics\Actions\AssignStudentToClassAction;
use App\Domains\Academics\Models\StudentGateCard;
use App\Domains\Academics\Models\StudentMovement;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * E18 gate cards (owner decision 11, STATUS §5ge): a printed QR per pupil,
 * scanned by a camera or a handheld scanner, or typed off the card, recorded
 * through the one movement writer as `qr`.
 */
function gateClassWithPupils(int $count = 2): array
{
    makeYear(['status' => 'active', 'is_current' => true]);
    $year = \App\Domains\Academics\Models\AcademicYear::query()->where('status', 'active')->firstOrFail();
    $class = makeClass($year);
    $pupils = [];
    foreach (range(1, $count) as $i) {
        $pupil = makeStudent(['first_name' => 'Pupil'.$i, 'last_name' => 'Gate']);
        app(AssignStudentToClassAction::class)->execute($class, $pupil->id);
        $pupils[] = $pupil;
    }

    return [$class, $pupils];
}

function gateCardFor(int $studentId): StudentGateCard
{
    return StudentGateCard::query()->active()->where('student_id', $studentId)->firstOrFail();
}

it('issues a card to every pupil on the roll, once, with an unguessable token', function () {
    $admin = actingPeopleAdmin();
    [$class, $pupils] = gateClassWithPupils(3);

    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('academics.gate.cards.issue'), ['class_id' => $class->id])
        ->assertSessionHas('success', '3 cards issued.');

    $tokens = StudentGateCard::query()->pluck('token')->all();
    expect($tokens)->toHaveCount(3)
        ->and(array_unique($tokens))->toHaveCount(3)
        ->and(collect($tokens)->every(fn ($t) => preg_match('/^[A-HJKMNP-Z2-9]{16}$/', $t) === 1))->toBeTrue()
        ->and(collect($tokens)->contains(fn ($t) => str_contains($t, (string) $pupils[0]->id) && strlen((string) $pupils[0]->id) > 3))->toBeFalse();

    // Pressed again after printing: nothing printed stops working.
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('academics.gate.cards.issue'), ['class_id' => $class->id])
        ->assertSessionHas('success', 'Every pupil in this class already has a card.');
    expect(StudentGateCard::query()->active()->pluck('token')->sort()->values()->all())->toBe(collect($tokens)->sort()->values()->all());
});

it('prints a sheet with the code for each pupil who has a card', function () {
    $admin = actingPeopleAdmin();
    [$class, $pupils] = gateClassWithPupils(2);
    app(\App\Domains\Academics\Actions\IssueGateCardsAction::class)->execute([$pupils[0]->id], $admin->id);

    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->get(route('academics.gate.cards.print', ['class_id' => $class->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Academics/Gate/PrintCards')
            ->has('pupils', 1)
            ->where('pupils.0.name', 'Pupil1 Gate')
            ->where('pupils.0.card.code', gateCardFor($pupils[0]->id)->code())
            ->where('pupils.0.card.readable', gateCardFor($pupils[0]->id)->readable()));
});

it('records an arrival from a camera scan, a handheld scanner, or a code typed off the card', function (callable $asScanned) {
    $admin = actingPeopleAdmin();
    [, $pupils] = gateClassWithPupils(1);
    app(\App\Domains\Academics\Actions\IssueGateCardsAction::class)->execute([$pupils[0]->id], $admin->id);
    $card = gateCardFor($pupils[0]->id);

    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('academics.gate.scan'), ['code' => $asScanned($card), 'direction' => 'in'])
        ->assertSessionHas('success', fn ($message) => str_starts_with($message, 'Pupil1 Gate arrived at '));

    $movement = StudentMovement::query()->sole();
    expect($movement->student_id)->toBe($pupils[0]->id)
        ->and($movement->direction->value)->toBe('in')
        ->and($movement->source->value)->toBe('qr')
        ->and($movement->recorded_by)->toBe($admin->id);
})->with([
    'camera' => [fn (StudentGateCard $card) => $card->code()],
    'handheld scanner with a trailing newline' => [fn (StudentGateCard $card) => $card->code()."\r\n"],
    'typed off the card, lower case' => [fn (StudentGateCard $card) => strtolower($card->readable())],
]);

it('refuses a replaced card and says so, and the new card works', function () {
    $admin = actingPeopleAdmin();
    [, $pupils] = gateClassWithPupils(1);
    $issue = app(\App\Domains\Academics\Actions\IssueGateCardsAction::class);
    $issue->execute([$pupils[0]->id], $admin->id);
    $old = gateCardFor($pupils[0]->id)->code();

    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('academics.gate.cards.reissue', $pupils[0]->id))
        ->assertSessionHas('success', 'New card issued. The old one no longer works.');

    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('academics.gate.scan'), ['code' => $old, 'direction' => 'in'])
        ->assertSessionHas('error', fn ($message) => str_contains($message, 'was replaced'));
    expect(StudentMovement::query()->count())->toBe(0);

    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('academics.gate.scan'), ['code' => gateCardFor($pupils[0]->id)->code(), 'direction' => 'out'])
        ->assertSessionHas('success', fn ($message) => str_contains($message, 'left at'));
    expect(StudentMovement::query()->sole()->direction->value)->toBe('out');
});

it('refuses a code that is not a gate card, and one that was never issued', function (string $code, string $says) {
    $admin = actingPeopleAdmin();
    gateClassWithPupils(1);

    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('academics.gate.scan'), ['code' => $code, 'direction' => 'in'])
        ->assertSessionHas('error', $says);
    expect(StudentMovement::query()->count())->toBe(0);
})->with([
    ['https://example.com/some-other-qr', 'That is not an Akuru gate card.'],
    ['AKG:ABCDEFGHJKMNPQRS', 'This card is not recognised.'],
]);

it('keeps the cards and the scanner away from families', function (string $role) {
    Role::findOrCreate($role, 'web');
    $family = User::factory()->create();
    $family->assignRole($role);
    [$class] = gateClassWithPupils(1);

    $this->withoutLocalizationMiddleware()->actingAs($family)->get(route('academics.gate.cards'))->assertForbidden();
    $this->withoutLocalizationMiddleware()->actingAs($family)->get(route('academics.gate.cards.print', ['class_id' => $class->id]))->assertForbidden();
    $this->withoutLocalizationMiddleware()->actingAs($family)->post(route('academics.gate.scan'), ['code' => 'AKG:ABCDEFGHJKMNPQRS', 'direction' => 'in'])->assertForbidden();
})->with(['parent', 'student']);

it('exports the class card list as CSV', function () {
    $admin = actingPeopleAdmin();
    [$class, $pupils] = gateClassWithPupils(2);
    app(\App\Domains\Academics\Actions\IssueGateCardsAction::class)->execute([$pupils[0]->id], $admin->id);

    $csv = $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->get(route('academics.gate.cards.export', ['class_id' => $class->id]))
        ->assertOk()->streamedContent();

    expect($csv)->toContain('card_code')
        ->and($csv)->toContain(gateCardFor($pupils[0]->id)->code())
        ->and($csv)->toContain('Pupil2 Gate');
});
