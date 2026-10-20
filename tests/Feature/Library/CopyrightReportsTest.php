<?php

use App\Domains\Commerce\Models\WalletTransaction;
use App\Domains\Identity\Models\User;
use App\Domains\Library\Actions\ApplyAsWriterAction;
use App\Domains\Library\Actions\DecideWriterApplicationAction;
use App\Domains\Library\Actions\PublishLibraryItemAction;
use App\Domains\Library\Actions\SaveWriterItemAction;
use App\Domains\Library\Enums\LibraryItemStatus;
use App\Domains\Library\Models\LibraryCopyrightReport;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Notifications\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * BP3 (BACKLOG B11, STATUS §5ts): anyone may say a published work copies
 * theirs; the office upholds (the work leaves the shelf, its writer is told)
 * or dismisses, and says why. And a large wallet credit made by hand is told
 * to the other commerce managers.
 */
uses(RefreshDatabase::class);

function bp3Writer(): array
{
    $user = User::factory()->create();
    $application = app(ApplyAsWriterAction::class)->execute($user->id, [
        'display_name' => 'Ustadha Aminath', 'bio' => 'Teaches grammar.', 'qualifications' => 'MA',
        'expertise' => 'Nahw', 'agreement_accepted' => true,
    ]);
    app(DecideWriterApplicationAction::class)->execute($application->id, User::factory()->create()->id, true);

    return [$user];
}

function bp3Item(int $writerUserId, string $title, bool $publish = true): LibraryItem
{
    $item = app(SaveWriterItemAction::class)->execute($writerUserId, [
        'title' => $title, 'content_type' => 'article', 'access_type' => 'free_public',
        'body' => '<p>'.$title.' body</p>', 'abstract' => $title.' in one line.',
    ]);
    if ($publish) {
        $item = app(PublishLibraryItemAction::class)->execute($item->id, User::factory()->create()->id);
    }

    return $item->fresh();
}

function bp3Report(array $overrides = []): array
{
    return array_merge([
        'name' => 'Hawwa Rasheed',
        'email' => 'hawwa@example.mv',
        'work_claimed' => 'Nahw for beginners (2019)',
        'statement' => 'Chapters 2 and 3 are my book, word for word.',
        'good_faith' => '1',
    ], $overrides);
}

it('takes a report on a published work and tells the office', function () {
    [$writer] = bp3Writer();
    $item = bp3Item($writer->id, 'Copied grammar');
    $office = actingSystemAdmin(['library.manage']);
    auth()->logout();
    $web = test()->withoutLocalizationMiddleware();

    $web->get("/library/{$item->slug}")->assertOk()->assertSee('data-testid="copyright-report"', false);
    $web->post("/library/{$item->slug}/copyright-report", bp3Report())->assertSessionHas('copyright_sent');
    $report = LibraryCopyrightReport::query()->sole();
    expect($report->status)->toBe('open')->and($report->library_item_id)->toBe($item->id)->and($report->reporter_user_id)->toBeNull();
    expect(UserNotification::query()->where('user_id', $office->id)->where('title', __('library_copyright.notice_new_title'))->exists())->toBeTrue();

    // The same person again, while it is open: refused, in the form's own bag.
    $web->post("/library/{$item->slug}/copyright-report", bp3Report())->assertSessionHasErrorsIn('copyright', 'statement');
    // The statement must be made.
    $web->post("/library/{$item->slug}/copyright-report", bp3Report(['email' => 'other@example.mv', 'good_faith' => null]))->assertSessionHasErrorsIn('copyright', 'good_faith');
    expect(LibraryCopyrightReport::query()->count())->toBe(1);
});

it('will not take a report on a work that is not published', function () {
    [$writer] = bp3Writer();
    $draft = bp3Item($writer->id, 'A draft', publish: false);

    test()->withoutLocalizationMiddleware()->post("/library/{$draft->slug}/copyright-report", bp3Report())->assertNotFound();
    expect(LibraryCopyrightReport::query()->count())->toBe(0);
});

it('upholds a report: the work leaves the shelf and its writer is told why', function () {
    [$writer] = bp3Writer();
    $item = bp3Item($writer->id, 'Copied grammar');
    $web = test()->withoutLocalizationMiddleware();
    $web->post("/library/{$item->slug}/copyright-report", bp3Report());
    $report = LibraryCopyrightReport::query()->sole();

    $office = actingSystemAdmin(['library.manage']);
    $web->actingAs($office)->get('/admin/library/copyright')->assertOk()
        ->assertInertia(fn (Assert $p) => $p->component('Library/CopyrightReports')->where('reports.0.id', $report->id)->where('open_count', 1)->has('t.uphold'));
    // A decision says why.
    $web->actingAs($office)->post("/admin/library/copyright/{$report->id}", ['outcome' => 'upheld', 'note' => ''])->assertSessionHasErrors('note');
    $web->actingAs($office)->post("/admin/library/copyright/{$report->id}", ['outcome' => 'upheld', 'note' => 'The chapters match the 2019 book.'])->assertSessionHas('success');

    expect($report->fresh()->status)->toBe('upheld')->and($report->fresh()->decided_by)->toBe($office->id)
        ->and($item->fresh()->status)->toBe(LibraryItemStatus::Archived);
    $notice = UserNotification::query()->where('user_id', $writer->id)->where('title', __('library_copyright.notice_upheld_title'))->sole();
    expect($notice->message)->toContain('The chapters match the 2019 book.');

    // Decided once only.
    $web->actingAs($office)->post("/admin/library/copyright/{$report->id}", ['outcome' => 'dismissed', 'note' => 'Again'])->assertSessionHasErrors();
    expect($report->fresh()->status)->toBe('upheld');
    // Off the shelf, it cannot be reported again.
    auth()->logout();
    $web->post("/library/{$item->slug}/copyright-report", bp3Report(['email' => 'new@example.mv']))->assertNotFound();
});

it('dismisses a report and keeps the work, and lists and exports for the office only', function () {
    [$writer] = bp3Writer();
    $item = bp3Item($writer->id, 'Original grammar');
    $web = test()->withoutLocalizationMiddleware();
    $web->post("/library/{$item->slug}/copyright-report", bp3Report());
    $report = LibraryCopyrightReport::query()->sole();

    $web->actingAs(User::factory()->create())->get('/admin/library/copyright')->assertForbidden();
    $web->actingAs(User::factory()->create())->post("/admin/library/copyright/{$report->id}", ['outcome' => 'dismissed', 'note' => 'x'])->assertForbidden();
    $web->actingAs(User::factory()->create())->get('/admin/library/copyright/export')->assertForbidden();

    $office = actingSystemAdmin(['library.manage']);
    $web->actingAs($office)->post("/admin/library/copyright/{$report->id}", ['outcome' => 'dismissed', 'note' => 'Different books on the same topic.'])->assertSessionHas('success');
    expect($report->fresh()->status)->toBe('dismissed')->and($item->fresh()->status)->toBe(LibraryItemStatus::Published)
        ->and(UserNotification::query()->where('user_id', $writer->id)->where('title', __('library_copyright.notice_upheld_title'))->exists())->toBeFalse();

    // Decided, it leaves the open list and shows with "all".
    $web->actingAs($office)->get('/admin/library/copyright')->assertInertia(fn (Assert $p) => $p->has('reports', 0)->where('open_count', 0));
    $web->actingAs($office)->get('/admin/library/copyright?all=1')->assertInertia(fn (Assert $p) => $p->where('reports.0.status', 'dismissed'));
    $csv = $web->actingAs($office)->get('/admin/library/copyright/export?all=1')->assertOk()->streamedContent();
    expect($csv)->toContain('Original grammar')->toContain('hawwa@example.mv')->toContain('Different books on the same topic.');
});

it('tells the other commerce managers of a large wallet credit made by hand, and nobody of a small one', function () {
    config(['commerce.wallet.alert_credit_at' => 1000]);
    $actor = actingSystemAdmin(['commerce.manage']);
    $other = User::factory()->create();
    $other->givePermissionTo('commerce.manage');
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    $owner = User::factory()->create(['name' => 'Ibrahim Wallet']);
    $web = test()->withoutLocalizationMiddleware();
    $title = __('admin.commerce_alert_credit_title');

    $web->actingAs($actor)->post('/admin/commerce/wallet-credits', ['user_id' => $owner->id, 'amount' => 50])->assertSessionHasNoErrors();
    expect(UserNotification::query()->where('title', $title)->count())->toBe(0);

    $web->actingAs($actor)->post('/admin/commerce/wallet-credits', ['user_id' => $owner->id, 'amount' => 1500])->assertSessionHasNoErrors();
    expect(WalletTransaction::query()->where('user_id', $owner->id)->count())->toBe(2);
    $notice = UserNotification::query()->where('title', $title)->sole();
    expect($notice->user_id)->toBe($other->id)->and($notice->message)->toContain('1,500.00')->toContain('Ibrahim Wallet');
});
