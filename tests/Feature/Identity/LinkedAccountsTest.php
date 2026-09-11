<?php

use App\Domains\Identity\Actions\LinkAccountAction;
use App\Domains\Identity\Actions\ResolveUserByIdentifierAction;
use App\Domains\Identity\Actions\UnlinkAccountAction;
use App\Domains\Identity\Models\AccountLinkEvent;
use App\Domains\Identity\Models\LinkedAccount;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * E7 — linked accounts and the switcher.
 *
 * This swaps who a session belongs to, so most of these tests are refusals.
 * The one that matters most is the last: **switching into a pupil account must
 * grant a pupil's powers and nothing more**, which is the E6 rule the plan
 * asks to preserve.
 */
function linkSetup(): array
{
    Role::findOrCreate('teacher', 'web');
    Role::findOrCreate('parent', 'web');
    Role::findOrCreate('student', 'web');

    $teacher = User::factory()->create(['name' => 'Aminath Teacher', 'password' => Hash::make('teacher-pass')]);
    $teacher->assignRole('teacher');

    $parent = User::factory()->create(['name' => 'Aminath Parent', 'password' => Hash::make('parent-pass')]);
    $parent->assignRole('parent');

    return ['teacher' => $teacher->fresh(), 'parent' => $parent->fresh()];
}

beforeEach(function () {
    RateLimiter::clear('link-account|1|127.0.0.1');
});

it('links two accounts only when both are proved', function () {
    ['teacher' => $teacher, 'parent' => $parent] = linkSetup();

    $target = app(LinkAccountAction::class)
        ->execute($teacher, $parent->email, 'parent-pass', '127.0.0.1');

    expect((int) $target->id)->toBe((int) $parent->id);

    // Reciprocal: proving both sides earns switching both ways.
    expect(LinkedAccount::query()->verified()->count())->toBe(2)
        ->and(LinkedAccount::query()->where('user_id', $teacher->id)->where('linked_user_id', $parent->id)->exists())->toBeTrue()
        ->and(LinkedAccount::query()->where('user_id', $parent->id)->where('linked_user_id', $teacher->id)->exists())->toBeTrue();

    expect(AccountLinkEvent::query()->where('action', AccountLinkEvent::LINKED)->count())->toBe(1);
});

it('refuses a wrong password, an unknown account, and yourself — in the same words', function () {
    // Telling "no such account" apart from "wrong password" is an
    // account-enumeration oracle, and a signed-in attacker probing which
    // identifiers exist is the likeliest use of this form.
    ['teacher' => $teacher, 'parent' => $parent] = linkSetup();
    $link = app(LinkAccountAction::class);

    $messages = [];

    foreach ([
        [$parent->email, 'wrong-password'],
        ['nobody@example.test', 'anything'],
        [$teacher->email, 'teacher-pass'],
    ] as [$identifier, $password]) {
        try {
            $link->execute($teacher, $identifier, $password, '127.0.0.1');
            $this->fail("Expected a refusal for {$identifier}");
        } catch (ValidationException $e) {
            $messages[] = $e->errors()['identifier'][0];
        }
        RateLimiter::clear('link-account|'.$teacher->id.'|127.0.0.1');
    }

    expect(array_unique($messages))->toHaveCount(1);
    expect(LinkedAccount::query()->count())->toBe(0);

    // Every failure is recorded — including the one naming an account that
    // does not exist, which is the attempt most worth seeing.
    expect(AccountLinkEvent::query()->where('action', AccountLinkEvent::FAILED)->count())->toBe(3);
});

it('rate limits the link form, because it checks passwords', function () {
    // Without this, a link form is an unthrottled password oracle sitting
    // behind an ordinary session.
    ['teacher' => $teacher, 'parent' => $parent] = linkSetup();
    $link = app(LinkAccountAction::class);

    for ($i = 0; $i < 5; $i++) {
        try {
            $link->execute($teacher, $parent->email, 'wrong', '127.0.0.1');
        } catch (ValidationException) {
            // expected
        }
    }

    // The sixth is refused for being too many, even with the right password.
    try {
        $link->execute($teacher, $parent->email, 'parent-pass', '127.0.0.1');
        $this->fail('Expected the rate limiter to refuse');
    } catch (ValidationException $e) {
        expect($e->errors()['identifier'][0])->toContain('Too many attempts');
    }

    expect(LinkedAccount::query()->count())->toBe(0);
});

it('refuses to link an account that has been switched off', function () {
    ['teacher' => $teacher, 'parent' => $parent] = linkSetup();
    $parent->forceFill(['is_active' => false])->save();

    expect(fn () => app(LinkAccountAction::class)
        ->execute($teacher, $parent->email, 'parent-pass', '127.0.0.1'))
        ->toThrow(ValidationException::class);

    expect(LinkedAccount::query()->count())->toBe(0);
});

it('unlinks in both directions, from either side', function () {
    // A link asserts "these two accounts are one person". Revoking it from one
    // side and leaving the other standing would leave an account that can
    // still reach back into one that has disowned it.
    ['teacher' => $teacher, 'parent' => $parent] = linkSetup();
    app(LinkAccountAction::class)->execute($teacher, $parent->email, 'parent-pass', '127.0.0.1');

    app(UnlinkAccountAction::class)->execute($parent, (int) $teacher->id, '127.0.0.1');

    expect(LinkedAccount::query()->count())->toBe(0)
        ->and(AccountLinkEvent::query()->where('action', AccountLinkEvent::UNLINKED)->count())->toBe(1);

    // Unlinking something that is not linked is refused, not silently ignored.
    expect(fn () => app(UnlinkAccountAction::class)->execute($parent, (int) $teacher->id))
        ->toThrow(ValidationException::class);
});

it('switches over http and lands on the other identity', function () {
    ['teacher' => $teacher, 'parent' => $parent] = linkSetup();

    $this->withoutLocalizationMiddleware()->actingAs($teacher)
        ->post(route('account.linked.store'), ['identifier' => $parent->email, 'password' => 'parent-pass'])
        ->assertSessionHasNoErrors();

    $this->withoutLocalizationMiddleware()->actingAs($teacher)
        ->get(route('account.linked'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Identity/LinkedAccounts')
            ->has('accounts', 1)
            ->where('accounts.0.name', 'Aminath Parent')
            ->etc());

    $this->withoutLocalizationMiddleware()->actingAs($teacher)
        ->post(route('account.switch', $parent->id))
        ->assertRedirect(route('dashboard'));

    expect(auth()->id())->toBe($parent->id);
    expect(AccountLinkEvent::query()->where('action', AccountLinkEvent::SWITCHED)->count())->toBe(1);
});

it('refuses to switch to an account that is not linked to you', function () {
    ['teacher' => $teacher, 'parent' => $parent] = linkSetup();

    // A stranger whose id somebody happens to know.
    $stranger = User::factory()->create(['password' => Hash::make('stranger-pass')]);

    $this->withoutLocalizationMiddleware()->actingAs($teacher)
        ->post(route('account.switch', $stranger->id))
        ->assertSessionHasErrors('account');

    expect(auth()->id())->toBe((int) $teacher->id);

    // A link that exists but belongs to somebody else is no use either: the
    // parent links the stranger, and the teacher still cannot reach them.
    app(LinkAccountAction::class)->execute($parent, $stranger->email, 'stranger-pass', '127.0.0.1');

    $this->withoutLocalizationMiddleware()->actingAs($teacher)
        ->post(route('account.switch', $stranger->id))
        ->assertSessionHasErrors('account');

    expect(auth()->id())->toBe((int) $teacher->id);

    // …while the parent, who proved it, can.
    $this->withoutLocalizationMiddleware()->actingAs($parent)
        ->post(route('account.switch', $stranger->id))
        ->assertRedirect(route('dashboard'));

    expect(auth()->id())->toBe((int) $stranger->id);
});

it('offers the switcher on every screen, not just a settings page', function () {
    // The plan's acceptance is "switches in two taps". A switcher that lives
    // only on a settings page is four.
    ['teacher' => $teacher, 'parent' => $parent] = linkSetup();
    app(LinkAccountAction::class)->execute($teacher, $parent->email, 'parent-pass', '127.0.0.1');

    $this->withoutLocalizationMiddleware()->actingAs($teacher)
        ->get(route('portal.home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('auth.linked_accounts', 1)
            ->where('auth.linked_accounts.0.name', 'Aminath Parent')
            ->etc());

    // Somebody with one account carries no extra payload.
    $alone = User::factory()->create();
    $this->withoutLocalizationMiddleware()->actingAs($alone)
        ->get(route('portal.home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('auth.linked_accounts', 0)->etc());
});

it('grants the target account its own powers and nothing more', function () {
    // **The E6 rule.** Acting as a pupil must not grant guardian confirmation.
    // No special-casing achieves this — it falls out of the switch being a
    // real login rather than impersonation.
    ['parent' => $parent] = linkSetup();

    $pupil = User::factory()->create(['name' => 'The Child', 'password' => Hash::make('pupil-pass')]);
    $pupil->assignRole('student');

    app(LinkAccountAction::class)->execute($parent, $pupil->email, 'pupil-pass', '127.0.0.1');

    $this->withoutLocalizationMiddleware()->actingAs($parent)
        ->post(route('account.switch', $pupil->id))->assertRedirect(route('dashboard'));

    $now = auth()->user();

    expect($now->id)->toBe($pupil->id)
        ->and($now->hasRole('student'))->toBeTrue()
        // Nothing of the parent identity survives the swap.
        ->and($now->hasRole('parent'))->toBeFalse();

    // (Password confirmation not carrying across has its own test, which sets
    // the timestamp first — asserting it absent here would prove nothing.)
});

it('refuses a link row that was never verified', function () {
    // `verified_at` exists so an unproved link is representable and refused,
    // rather than unrepresentable and assumed. Nothing in the app writes one —
    // which is exactly why the refusal needs a test, or the scope could be
    // deleted tomorrow and nothing would notice.
    ['teacher' => $teacher, 'parent' => $parent] = linkSetup();

    LinkedAccount::query()->create([
        'user_id' => (int) $teacher->id,
        'linked_user_id' => (int) $parent->id,
        'verified_at' => null,
    ]);

    $this->withoutLocalizationMiddleware()->actingAs($teacher)
        ->post(route('account.switch', $parent->id))
        ->assertSessionHasErrors('account');

    expect(auth()->id())->toBe((int) $teacher->id);

    // It is not offered in the switcher either.
    expect(app(\App\Domains\Identity\Actions\ListLinkedAccountsAction::class)
        ->execute((int) $teacher->id))->toHaveCount(0);
});

it('rotates the session and drops password confirmation on the way through', function () {
    // Changing who a session belongs to without rotating it is session
    // fixation. And confirming your password as a teacher must not carry into
    // the other identity — the plan asks for re-auth on sensitive actions.
    //
    // An earlier version of this test asserted the confirmation timestamp was
    // absent *without ever setting it*, so it passed against a build that
    // never cleared anything. Setting it first is the whole test.
    ['teacher' => $teacher, 'parent' => $parent] = linkSetup();
    app(LinkAccountAction::class)->execute($teacher, $parent->email, 'parent-pass', '127.0.0.1');

    $this->withoutLocalizationMiddleware()
        ->actingAs($teacher)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('account.switch', $parent->id))
        ->assertRedirect(route('dashboard'))
        ->assertSessionMissing('auth.password_confirmed_at');

    expect(auth()->id())->toBe((int) $parent->id);
});

it('rotates the session id itself, not just its contents', function () {
    // Session fixation: changing who a session belongs to while keeping its id
    // means anyone holding the old id now holds the new identity.
    //
    // Asserted against the action rather than over HTTP, because the test
    // client rebuilds the session between requests and would show a changed id
    // whether or not the code rotated it — a green test that proves nothing.
    ['teacher' => $teacher, 'parent' => $parent] = linkSetup();
    app(LinkAccountAction::class)->execute($teacher, $parent->email, 'parent-pass', '127.0.0.1');

    $this->actingAs($teacher);

    $request = request();
    $request->setLaravelSession(app('session.store'));
    $request->session()->start();
    $before = $request->session()->getId();

    app(\App\Domains\Identity\Actions\SwitchAccountAction::class)
        ->execute($request, $teacher, (int) $parent->id);

    expect($request->session()->getId())->not->toBe($before);
});

it('carries the confirmation through the dashboard router', function () {
    // Found in a browser, not by the suite. `/dashboard` is a pure router: it
    // works out where you belong and redirects again. The flash aimed at the
    // destination was consumed by that hop, so the switch worked and said
    // nothing — the "nothing happened" failure this codebase keeps fixing.
    //
    // Every existing test asserted the redirect, never what the person reads
    // at the end of it, which is why none of them noticed.
    ['teacher' => $teacher, 'parent' => $parent] = linkSetup();
    app(LinkAccountAction::class)->execute($teacher, $parent->email, 'parent-pass', '127.0.0.1');

    $this->withoutLocalizationMiddleware()->actingAs($teacher)
        ->post(route('account.switch', $parent->id))
        ->assertRedirect(route('dashboard'));

    // Follow the hop the way a browser does: the message must survive it.
    $this->withoutLocalizationMiddleware()
        ->get(route('dashboard'))
        ->assertRedirect(route('portal.home'))
        ->assertSessionHas('success', fn (string $message): bool => str_contains($message, 'Aminath Parent'));
});

it('resolves an identifier without signing anybody in', function () {
    // The extracted resolver is the reason linking can prove ownership without
    // logging you into the account you are proving.
    ['parent' => $parent] = linkSetup();

    $found = app(ResolveUserByIdentifierAction::class)->execute($parent->email);

    expect($found?->id)->toBe($parent->id)
        ->and(auth()->check())->toBeFalse();

    expect(app(ResolveUserByIdentifierAction::class)->execute('nobody@example.test'))->toBeNull()
        ->and(app(ResolveUserByIdentifierAction::class)->execute(''))->toBeNull();
});
