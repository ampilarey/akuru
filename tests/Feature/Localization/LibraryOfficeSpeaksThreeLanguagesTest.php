<?php

use App\Domains\Identity\Models\User;
use App\Domains\Library\Actions\ApplyAsWriterAction;
use App\Domains\Library\Actions\AssertResearchReviewedAction;
use App\Domains\Library\Actions\DecideWriterApplicationAction;
use App\Domains\Library\Actions\SaveWriterItemAction;
use App\Domains\Library\Actions\SubmitLibraryItemForReviewAction;
use App\Domains\Library\Enums\LibraryReadingSignal;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\LibraryReadingAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The Library office in Dhivehi and Arabic (BACKLOG C20, slice LT3, STATUS
 * §5pf).
 *
 * `/admin/library` was English throughout: the queues of applications,
 * submissions and payouts, the item form, the shelf's list, an item's type,
 * access and status as codes, and a submission's history as codes. So was
 * the reading-alerts page, Insights' table titles, every saved message of
 * the office's controller, and every refusal of the office's actions — the
 * peer-review gate's among them. The settings, promotions, reviewers and
 * insights pages already read the `admin` book.
 */
uses(RefreshDatabase::class);

/** The office's pages, and every phrase on them is `t.key || 'English'` (admin) or `common.key || 'English'`. */
function libraryOfficeScreens(): array
{
    return ['Library/Admin', 'Library/ReadingAlerts', 'Library/Insights', 'Library/Settings'];
}

/** Where the office's refusals and saved messages are written. */
function libraryOfficeServerFiles(): array
{
    return [
        'app/Domains/Library/Http/Controllers/AdminLibraryController.php',
        'app/Domains/Library/Actions/AssignResearchReviewerAction.php',
        'app/Domains/Library/Actions/DecideWriterApplicationAction.php',
        'app/Domains/Library/Actions/DecideWriterPayoutAction.php',
        'app/Domains/Library/Actions/ManageReviewerPoolAction.php',
        'app/Domains/Library/Actions/ReviewLibraryItemSubmissionAction.php',
        'app/Domains/Library/Actions/ReviewLibraryReadingAlertAction.php',
        'app/Domains/Library/Actions/SaveLibraryCategoryAction.php',
        'app/Domains/Library/Actions/SaveLibrarySettingsAction.php',
        'app/Domains/Library/Actions/AssertResearchReviewedAction.php',
    ];
}

function officeBook(string $book, string $locale): array
{
    return require base_path("resources/lang/{$locale}/{$book}.php");
}

it('keys every string on the Library office in three languages', function () {
    foreach (libraryOfficeScreens() as $screen) {
        $source = file_get_contents(resource_path("js/Pages/{$screen}.jsx"));
        preg_match_all("/(?<![\\w\$.])(t|common)\\.([a-z][a-z0-9_]+) \\|\\| '((?:[^'\\\\]|\\\\.)*)'/", $source, $uses, PREG_SET_ORDER);
        expect($uses)->not->toBeEmpty("{$screen} uses no phrases");

        foreach ($uses as [, $alias, $key, $fallback]) {
            $book = $alias === 't' ? 'admin' : 'common';
            [$en, $dv, $ar] = [officeBook($book, 'en'), officeBook($book, 'dv'), officeBook($book, 'ar')];
            expect(array_key_exists($key, $en))->toBeTrue("{$screen}: {$book}.{$key} is missing in English")
                ->and($en[$key])->toBe(stripslashes($fallback), "{$screen}: {$book}.{$key} says something else in English than the screen")
                ->and($dv[$key] ?? $en[$key])->not->toBe($en[$key], "{$book}.{$key} is English in Dhivehi")
                ->and($ar[$key] ?? $en[$key])->not->toBe($en[$key], "{$book}.{$key} is English in Arabic");
        }

        expect(preg_match_all('/>\s*[A-Z][A-Za-z]+[^<>{}]*</', $source, $text))->toBe(0, "{$screen} has English text nodes: ".implode(' | ', $text[0] ?? []))
            ->and(preg_match_all('/(placeholder|aria-label|title|data-label)="[A-Za-z][^"]*"/', $source, $attrs))->toBe(0, "{$screen} has English attributes: ".implode(' | ', $attrs[0] ?? []))
            ->and(preg_match_all("/title=\"[A-Z][^\"]*\"|title='[A-Z][^']*'/", $source, $titles))->toBe(0, "{$screen} has English titles: ".implode(' | ', $titles[0] ?? []))
            ->and(unnamedFields($source))->toBe([], "{$screen} has fields with no name");
    }
});

it('names every history entry, assignment state, alert signal, outcome and detail in Dhivehi and Arabic', function () {
    $needed = [
        ...array_map(fn ($decision) => 'library_office_decision_'.$decision, ['submitted', 'approved', 'changes_requested', 'rejected', 'reviewer_accept', 'reviewer_revise', 'reviewer_reject']),
        ...array_map(fn ($status) => 'library_office_assignment_'.$status, ['assigned', 'done']),
        ...array_map(fn ($case) => 'library_alerts_signal_'.$case->value, LibraryReadingSignal::cases()),
        ...array_map(fn ($case) => 'library_alerts_detail_'.$case->value, LibraryReadingSignal::cases()),
        ...array_map(fn ($outcome) => 'library_alerts_outcome_'.$outcome, ['legitimate', 'watching', 'abuse']),
    ];

    foreach ($needed as $key) {
        expect(trans("admin.{$key}", [], 'en'))->not->toBe("admin.{$key}", "admin.{$key} has no English")
            ->and(trans("admin.{$key}", [], 'dv'))->toMatch('/\p{Thaana}/u', "admin.{$key} in Dhivehi")
            ->and(trans("admin.{$key}", [], 'ar'))->toMatch('/\p{Arabic}/u', "admin.{$key} in Arabic");
    }
});

it('leaves no English in what the office is told, and says it in Dhivehi and Arabic', function () {
    // Written for the writer, not said on this page: the notices a decision
    // sends, and the note kept with an ID card a refused application turned
    // down. The Library's notices stay English (BACKLOG C20).
    $forTheWriter = [
        'Application not accepted.',
        'Your application was approved. Open the writer portal to start a draft.',
        'Your application was not accepted this time.',
    ];
    $english = [];
    foreach (libraryOfficeServerFiles() as $file) {
        $english = [...$english, ...refusalEnglishIn($file)];
    }
    $english = array_values(array_filter($english, fn (string $line) => ! in_array(substr($line, strpos($line, ' ') + 1), $forTheWriter, true)));
    expect($english)->toBe([]);

    foreach (refusalKeysIn(libraryOfficeServerFiles()) as $key) {
        expect(trans($key, [], 'en'))->not->toBe($key, "{$key} has no English")
            ->and(trans($key, [], 'dv'))->toMatch('/\p{Thaana}/u', "{$key} in Dhivehi")
            ->and(trans($key, [], 'ar'))->toMatch('/\p{Arabic}/u', "{$key} in Arabic");
    }
});

it('serves the Library office and its reading alerts in Dhivehi', function () {
    $office = actingSystemAdmin(['library.manage']);
    $dv = officeBook('admin', 'dv');

    app()->setLocale('dv');
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->get(route('admin.library.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Library/Admin')
            ->where('t.library_office_title', $dv['library_office_title'])
            ->where('t.library_office_approve_publish', $dv['library_office_approve_publish']));
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->get(route('admin.library.reading-alerts'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Library/ReadingAlerts')
            ->where('t.library_alerts_none', $dv['library_alerts_none']));
});

function lt3SubmittedResearch(): LibraryItem
{
    $writer = User::factory()->create();
    $application = app(ApplyAsWriterAction::class)->execute($writer->id, ['display_name' => 'Office Writer', 'agreement_accepted' => true]);
    app(DecideWriterApplicationAction::class)->execute($application->id, User::factory()->create()->id, true);
    $item = app(SaveWriterItemAction::class)->execute($writer->id, [
        'title' => 'Reef Survey', 'content_type' => 'research', 'access_type' => 'free_public', 'body' => '<p>Reefs.</p>',
        'declarations' => ['copyright' => 1, 'originality' => 1, 'conflict_of_interest' => 1],
    ]);

    return app(SubmitLibraryItemForReviewAction::class)->execute($writer->id, $item->id);
}

it('says what the office saved in Dhivehi, with the reader pages a save made', function () {
    $this->actingAs(actingSystemAdmin(['library.manage']))
        ->withHeader('Referer', url('/dv/admin/library'))
        ->post('/admin/library/items', ['title' => 'Purity', 'content_type' => 'article', 'access_type' => 'free_public', 'body' => '<p>One page.</p>'])
        ->assertSessionHas('success', trans('admin.library_office_flash_saved', [], 'dv').' '.trans_choice('common.library_pages_ready', 1, ['count' => 1], 'dv'));
});

it('refuses to publish research without its accepts in Arabic, and in English as before', function () {
    $item = lt3SubmittedResearch();
    $office = actingSystemAdmin(['library.manage']);

    $this->actingAs($office)
        ->withHeader('Referer', url('/ar/admin/library'))
        ->post("/admin/library/items/{$item->id}/review", ['decision' => 'approved'])
        ->assertSessionHasErrors(['item' => trans('admin.library_office_error_research_gate_one', [], 'ar')]);

    app()->setLocale('en');
    expect(fn () => app(AssertResearchReviewedAction::class)->execute($item->fresh()))
        ->toThrow(ValidationException::class, 'Research is published only after a peer reviewer accepts it. It has no accept in this review round yet.');
});

it('refuses an unknown reviewer and a second category of the same name in Dhivehi', function () {
    $item = lt3SubmittedResearch();
    $office = actingSystemAdmin(['library.manage']);

    $this->actingAs($office)
        ->withHeader('Referer', url('/dv/admin/library'))
        ->post("/admin/library/items/{$item->id}/assign-reviewer", ['reviewer_email' => 'nobody@akuru.test'])
        ->assertSessionHasErrors(['reviewer_email' => trans('admin.library_office_error_no_user', [], 'dv')]);

    $this->actingAs($office)
        ->withHeader('Referer', url('/dv/admin/library'))
        ->post('/admin/library/categories', ['name' => 'Fiqh'])
        ->assertSessionHas('success', trans('admin.library_office_flash_category', [], 'dv'));
    $this->actingAs($office)
        ->withHeader('Referer', url('/dv/admin/library'))
        ->post('/admin/library/categories', ['name' => 'Fiqh'])
        ->assertSessionHasErrors(['slug' => trans('admin.library_office_error_category_slug', [], 'dv')]);
});

it('says a reading alert in Dhivehi — its signal, its detail and a second review refused', function () {
    $office = actingSystemAdmin(['library.manage']);
    $alert = LibraryReadingAlert::query()->create([
        'user_id' => User::factory()->create()->id,
        'signal' => LibraryReadingSignal::RapidPages->value,
        'observed' => 12,
        'threshold' => 8,
        'detail' => '12 pages in 60 seconds.',
    ]);

    app()->setLocale('dv');
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->get(route('admin.library.reading-alerts'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Library/ReadingAlerts')
            ->where('alerts.0.detail_said', trans('admin.library_alerts_detail_rapid_pages', ['observed' => 12, 'window' => 60], 'dv'))
            ->where('alerts.0.detail', '12 pages in 60 seconds.'));
    app()->setLocale('en');

    $this->actingAs($office)
        ->withHeader('Referer', url('/dv/admin/library/reading-alerts'))
        ->post("/admin/library/reading-alerts/{$alert->id}/review", ['outcome' => 'legitimate'])
        ->assertSessionHas('success', trans('admin.library_alerts_flash_reviewed', [], 'dv'));
    $this->actingAs($office)
        ->withHeader('Referer', url('/dv/admin/library/reading-alerts'))
        ->post("/admin/library/reading-alerts/{$alert->id}/review", ['outcome' => 'abuse'])
        ->assertSessionHasErrors(['alert' => trans('admin.library_alerts_error_reviewed', [], 'dv')]);
});

it('refuses a refund window past a year in Arabic on the settings page', function () {
    $this->actingAs(actingSystemAdmin(['library.manage']))
        ->withHeader('Referer', url('/ar/admin/library/settings'))
        ->put('/admin/library/settings', [
            'refund_window_days' => 400, 'default_writer_commission' => 70, 'min_payout' => 100,
            'gift_card_min' => 50, 'gift_card_max' => 5000, 'gift_card_expiry_months' => 12,
            'research_reviews_required' => 1, 'payouts_enabled' => 0,
        ])
        ->assertSessionHasErrors(['refund_window_days' => trans('admin.library_settings_error_refund_window', [], 'ar')]);
});

it('says on its own row what a button without a form was refused', function () {
    foreach (['resources/js/Pages/Library/Admin.jsx', 'resources/js/Pages/Library/ReadingAlerts.jsx'] as $page) {
        expect(routerVisitsWithoutRow($page))->toBe([]);
    }
});
