<?php

use App\Domains\Identity\Models\IdentityVerification;
use App\Domains\Identity\Models\User;
use App\Domains\Lending\Models\Lender;
use App\Domains\Notifications\Models\UserNotification;
use App\Support\Authorization\RoleLabels;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * LENDING_AND_USED_BOOKS_PLAN L4 (STATUS §5my): the `lender` role. The
 * owner, on Manage users: "still no lender role". Registering as a lender
 * grants it, so the roles screen shows it beside Vendor and Writer; the
 * office taking it away pauses the lender (who reads why) and giving it
 * back resumes them; for a person who never registered it is only an
 * invitation — the Lending workspace with My lending in it.
 */
beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
    config(['identity.verification.enforce' => true, 'lending.notices.email' => false, 'lending.notices.sms' => false]);
});

function roleWeb()
{
    return test()->withoutLocalizationMiddleware();
}

it('grants the Lender role on registration, shows it on Manage users, and lets the office pause and resume a lender through it', function () {
    $person = User::factory()->create(['name' => 'Aminath Lender']);
    $office = actingSystemAdmin(['bookshop.manage', 'users.manage']);
    expect(RoleLabels::KNOWN)->toContain('lender')->and(RoleLabels::label('lender'))->toBe('Lender');

    // Not a lender yet: no role, and the roles screen offers Lender unticked.
    expect($person->hasRole('lender'))->toBeFalse();
    roleWeb()->actingAs($office)->get(route('admin.users.roles', $person->id))->assertOk()
        ->assertInertia(fn ($p) => $p->where('roles', fn ($roles) => collect($roles)->pluck('key')->contains('lender')));

    // Registering grants it.
    roleWeb()->actingAs($person)->post(route('public.lending.register'), ['display_name' => 'Aminath', 'island' => 'Hithadhoo'])->assertSessionHasNoErrors();
    expect($person->fresh()->hasRole('lender'))->toBeTrue();
    roleWeb()->actingAs($office)->get(route('admin.users.roles', $person->id))->assertInertia(fn ($p) => $p->where('user.roles', fn ($held) => collect($held)->contains('lender')));

    // The lender's card is checked and a book listed: on the shelf.
    $card = fn () => ['id_front' => UploadedFile::fake()->image('f.png'), 'id_back' => UploadedFile::fake()->image('b.png')];
    roleWeb()->actingAs($person)->post(route('public.lending.identity'), $card())->assertSessionHasNoErrors();
    roleWeb()->actingAs($office)->post(route('identity.decide', IdentityVerification::query()->latest('id')->value('id')), ['decision' => 'verify'])->assertRedirect();
    roleWeb()->actingAs($person)->post(route('public.lending.books.store'), ['title' => 'Grade 7 reader', 'condition' => 'good'])->assertSessionHasNoErrors();
    roleWeb()->get(route('public.lending.index'))->assertSee('Grade 7 reader');

    // The office unticks Lender: the lender is paused by the office with a note, the shelf empties, and they cannot resume themselves.
    roleWeb()->actingAs($office)->put(route('admin.users.roles.update', $person->id), ['roles' => []])->assertSessionHasNoErrors();
    $row = Lender::query()->where('user_id', $person->id)->sole();
    expect($person->fresh()->hasRole('lender'))->toBeFalse()->and($row->status)->toBe('paused')->and($row->office_paused)->toBeTrue()->and($row->office_note)->toBe('The office removed your Lender role on Manage users.');
    roleWeb()->get(route('public.lending.index'))->assertDontSee('Grade 7 reader');
    roleWeb()->actingAs($person)->get(route('public.lending.mine'))->assertOk()->assertSee('The office removed your Lender role');
    roleWeb()->actingAs($person)->post(route('public.lending.status', 'resume'), [])->assertSessionHasErrors('status');
    expect(UserNotification::query()->where('user_id', $person->id)->where('title', 'The office paused your lending')->count())->toBe(1);

    // Ticked again: resumed, the book back.
    roleWeb()->actingAs($office)->put(route('admin.users.roles.update', $person->id), ['roles' => ['lender']])->assertSessionHasNoErrors();
    expect($person->fresh()->hasRole('lender'))->toBeTrue()->and($row->refresh()->status)->toBe('active')->and($row->office_paused)->toBeFalse();
    roleWeb()->get(route('public.lending.index'))->assertSee('Grade 7 reader');

    // A person who never registered: the role alone is an invitation — a Lending workspace with My lending — and no lender row appears.
    $invited = User::factory()->create(['name' => 'Hassan Invited']);
    roleWeb()->actingAs($office)->put(route('admin.users.roles.update', $invited->id), ['roles' => ['lender']])->assertSessionHasNoErrors();
    expect($invited->fresh()->hasRole('lender'))->toBeTrue()->and(Lender::query()->where('user_id', $invited->id)->exists())->toBeFalse();
    roleWeb()->actingAs($invited)->post(route('workspace.switch', 'lending'))->assertRedirect(route('public.lending.mine'));
});
