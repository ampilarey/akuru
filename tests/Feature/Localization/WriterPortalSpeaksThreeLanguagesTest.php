<?php

use App\Domains\Identity\Models\User;
use App\Domains\Library\Actions\ApplyAsWriterAction;
use App\Domains\Library\Actions\AssignResearchReviewerAction;
use App\Domains\Library\Actions\DecideWriterApplicationAction;
use App\Domains\Library\Actions\ListLibraryItemsAction;
use App\Domains\Library\Actions\SaveWriterItemAction;
use App\Domains\Library\Actions\SubmitLibraryItemForReviewAction;
use App\Domains\Library\Enums\LibraryAccessType;
use App\Domains\Library\Enums\LibraryContentType;
use App\Domains\Library\Enums\LibraryItemStatus;
use App\Domains\Library\Models\LibraryItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The Library's writer portal and peer review in Dhivehi and Arabic (BACKLOG
 * C20, slice LT2, STATUS §5pe).
 *
 * The pages read their phrases from the `common` book, and 35 of those were
 * the English in all three languages — the portal's title, its buttons, its
 * table. Around them the pages wrote English out: the application's fields
 * and its agreement, every field of the draft editor, the five declarations,
 * the earnings card, the writer's standing and an item's type, access and
 * status as codes. What the server said was English too: what was saved, the
 * reader pages a save made, and every refusal of the writer's and the
 * reviewer's actions.
 */
uses(RefreshDatabase::class);

/** The pages and components translated here, and every phrase on them is `t.key || 'English'`. */
function writerPortalScreens(): array
{
    return [
        'Pages/Library/Write',
        'Pages/Library/Review',
        'Components/LibraryAuthoring',
        'Components/ReviewStateChip',
    ];
}

/** Where the writer's and the reviewer's refusals and saved messages are written. */
function writerPortalServerFiles(): array
{
    return [
        'app/Domains/Library/Http/Controllers/WriterPortalController.php',
        'app/Domains/Library/Http/Controllers/ReviewerPortalController.php',
        'app/Domains/Library/Actions/ApplyAsWriterAction.php',
        'app/Domains/Library/Actions/SaveWriterItemAction.php',
        'app/Domains/Library/Actions/SaveLibraryItemAction.php',
        'app/Domains/Library/Actions/SubmitLibraryItemForReviewAction.php',
        'app/Domains/Library/Actions/SaveWriterBankDetailsAction.php',
        'app/Domains/Library/Actions/RequestWriterPayoutAction.php',
        'app/Domains/Library/Actions/SaveWriterPublicProfileAction.php',
        'app/Domains/Library/Actions/DeclareReviewerNoConflictAction.php',
        'app/Domains/Library/Actions/SubmitResearchReviewAction.php',
    ];
}

function commonBook(string $locale): array
{
    return require base_path("resources/lang/{$locale}/common.php");
}

function lt2Writer(string $name = 'Aishath Writer'): User
{
    $user = User::factory()->create();
    $application = app(ApplyAsWriterAction::class)->execute($user->id, ['display_name' => $name, 'agreement_accepted' => true]);
    app(DecideWriterApplicationAction::class)->execute($application->id, User::factory()->create()->id, true);

    return $user;
}

it('keys every string on the writer portal and peer review in three languages', function () {
    [$en, $dv, $ar] = [commonBook('en'), commonBook('dv'), commonBook('ar')];

    foreach (writerPortalScreens() as $screen) {
        $source = file_get_contents(resource_path("js/{$screen}.jsx"));
        preg_match_all("/(?<![\\w\$.])t\\.([a-z][a-z0-9_]+) \\|\\| '((?:[^'\\\\]|\\\\.)*)'/", $source, $uses, PREG_SET_ORDER);
        expect($uses)->not->toBeEmpty("{$screen} uses no phrases");

        foreach ($uses as [, $key, $fallback]) {
            expect(array_key_exists($key, $en))->toBeTrue("{$screen}: common.{$key} is missing in English")
                ->and($en[$key])->toBe(stripslashes($fallback), "{$screen}: common.{$key} says something else in English than the screen")
                ->and($dv[$key] ?? $en[$key])->not->toBe($en[$key], "common.{$key} is English in Dhivehi")
                ->and($ar[$key] ?? $en[$key])->not->toBe($en[$key], "common.{$key} is English in Arabic");
        }
    }

    // No bare English on the two pages: a text node, a placeholder or a
    // label written out, or a field a screen reader has no name for.
    foreach (['Pages/Library/Write', 'Pages/Library/Review'] as $screen) {
        $source = file_get_contents(resource_path("js/{$screen}.jsx"));
        expect(preg_match_all('/>\s*[A-Z][A-Za-z]+[^<>{}]*</', $source, $text))->toBe(0, "{$screen} has English text nodes: ".implode(' | ', $text[0] ?? []))
            ->and(preg_match_all('/(placeholder|aria-label|title)="[A-Za-z][^"]*"/', $source, $attrs))->toBe(0, "{$screen} has English attributes: ".implode(' | ', $attrs[0] ?? []))
            ->and(unnamedFields($source))->toBe([], "{$screen} has fields with no name");
    }
});

it('names every type, access, status, difficulty, language and recommendation the pages are sent', function () {
    $needed = [
        ...array_map(fn ($case) => 'library_type_'.$case->value, LibraryContentType::cases()),
        ...array_map(fn ($case) => 'library_access_'.$case->value, LibraryAccessType::cases()),
        ...array_map(fn ($case) => 'library_status_'.$case->value, LibraryItemStatus::cases()),
        ...array_map(fn ($difficulty) => 'library_difficulty_'.$difficulty, ListLibraryItemsAction::DIFFICULTIES),
        ...array_map(fn ($locale) => 'library_lang_'.$locale, ['en', 'dv', 'ar']),
        ...array_map(fn ($recommendation) => 'review_rec_'.$recommendation, ['accept', 'revise', 'reject']),
        ...array_map(fn ($declaration) => 'library_decl_name_'.$declaration, ['copyright', 'originality', 'conflict_of_interest', 'ethics']),
    ];

    foreach ($needed as $key) {
        expect(trans("common.{$key}", [], 'en'))->not->toBe("common.{$key}", "common.{$key} has no English")
            ->and(trans("common.{$key}", [], 'dv'))->toMatch('/\p{Thaana}/u', "common.{$key} in Dhivehi")
            ->and(trans("common.{$key}", [], 'ar'))->toMatch('/\p{Arabic}/u', "common.{$key} in Arabic");
    }
});

it('leaves no English in what the writer and the reviewer are told, and says it in Dhivehi and Arabic', function () {
    $english = [];
    foreach (writerPortalServerFiles() as $file) {
        $english = [...$english, ...refusalEnglishIn($file)];
    }
    expect($english)->toBe([]);

    foreach (refusalKeysIn(writerPortalServerFiles()) as $key) {
        if (! str_starts_with($key, 'common.')) {
            continue;
        }
        expect(trans($key, [], 'en'))->not->toBe($key, "{$key} has no English")
            ->and(trans($key, [], 'dv'))->toMatch('/\p{Thaana}/u', "{$key} in Dhivehi")
            ->and(trans($key, [], 'ar'))->toMatch('/\p{Arabic}/u', "{$key} in Arabic");
    }
});

it('serves the writer portal in Dhivehi, with the languages a draft may be in named in Dhivehi', function () {
    $writer = lt2Writer();
    $dv = commonBook('dv');

    app()->setLocale('dv');
    $this->withoutLocalizationMiddleware()->actingAs($writer)
        ->get(route('write.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Library/Write')
            ->where('i18n.common.library_write_title', $dv['library_write_title'])
            ->where('i18n.common.library_f_title', $dv['library_f_title'])
            ->where('i18n.common.library_status_changes_requested', $dv['library_status_changes_requested'])
            ->where('options.languages.dv', $dv['library_lang_dv'])
            ->where('options.languages.en', $dv['library_lang_en']));
});

it('says what was saved in the page\'s language, and how many reader pages a save made', function () {
    $writer = lt2Writer();

    $this->actingAs($writer)
        ->withHeader('Referer', url('/dv/write'))
        ->post('/write/bank-details', ['bank_name' => 'BML', 'account_name' => 'Aishath', 'account_number' => '7730000000001'])
        ->assertSessionHas('success', trans('common.library_flash_bank_saved', [], 'dv'));

    $this->actingAs($writer)
        ->withHeader('Referer', url('/ar/write'))
        ->post('/write/items', ['title' => 'Fasting', 'content_type' => 'article', 'access_type' => 'free_public', 'body' => '<p>One page.</p>'])
        ->assertSessionHas('success', trans('common.library_flash_draft_saved', [], 'ar').' '.trans_choice('common.library_pages_ready', 1, ['count' => 1], 'ar'));

    // English reads as it did.
    $this->actingAs($writer)
        ->withHeader('Referer', url('/en/write'))
        ->post('/write/items', ['title' => 'Prayer', 'content_type' => 'article', 'access_type' => 'free_public'])
        ->assertSessionHas('success', 'Draft saved. No reader pages yet — add a body or upload a PDF.');
});

it('refuses a submission without its declarations in Dhivehi, naming each one in Dhivehi', function () {
    $writer = lt2Writer();
    $item = app(SaveWriterItemAction::class)->execute($writer->id, [
        'title' => 'Monsoon Fisheries', 'content_type' => 'research', 'access_type' => 'free_public', 'body' => '<p>Findings.</p>',
        'declarations' => ['copyright' => 1],
    ]);

    $this->actingAs($writer)
        ->withHeader('Referer', url('/dv/write'))
        ->post("/write/items/{$item->id}/submit")
        ->assertSessionHasErrors(['declarations' => trans_choice('common.library_error_declarations', 2, [
            'list' => trans('common.library_decl_name_originality', [], 'dv').trans('common.library_list_joiner', [], 'dv').trans('common.library_decl_name_conflict_of_interest', [], 'dv'),
        ], 'dv')]);
    expect(LibraryItem::query()->find($item->id)->status)->toBe(LibraryItemStatus::Draft);

    // And in English, word for word as before.
    app()->setLocale('en');
    expect(fn () => app(SubmitLibraryItemForReviewAction::class)->execute($writer->id, $item->id))
        ->toThrow(ValidationException::class, 'Before submitting, confirm the originality, conflict of interest declarations on the draft.');
});

it('refuses an application in Dhivehi: a field named in Dhivehi, and a second one while the first waits', function () {
    $user = User::factory()->create();

    // Laravel's own sentence, about a field it now names in Dhivehi — it
    // said "id front ބޭނުންވޭ." before the writer's fields had names.
    $this->actingAs($user)
        ->withHeader('Referer', url('/dv/write'))
        ->post('/write/apply', ['display_name' => 'Waiting Writer', 'agreement_accepted' => '1'])
        ->assertSessionHasErrors(['id_front' => 'އައިޑީ ކާޑުގެ ކުރިމަތި ބޭނުންވޭ.']);

    app(ApplyAsWriterAction::class)->execute($user->id, ['display_name' => 'Waiting Writer', 'agreement_accepted' => true]);
    $this->actingAs($user)
        ->withHeader('Referer', url('/dv/write'))
        ->post('/write/apply', [
            'display_name' => 'Waiting Writer',
            'agreement_accepted' => '1',
            'id_front' => UploadedFile::fake()->image('id-front.png', 600, 400),
        ])
        ->assertSessionHasErrors(['application' => trans('common.library_error_application_pending', [], 'dv')]);
});

it('tells a reviewer in Arabic that the paper is open, and that a closed round takes no report', function () {
    $writer = lt2Writer();
    $item = app(SaveWriterItemAction::class)->execute($writer->id, [
        'title' => 'Tides', 'content_type' => 'research', 'access_type' => 'free_public', 'body' => '<p>Tides.</p>',
        'declarations' => ['copyright' => 1, 'originality' => 1, 'conflict_of_interest' => 1],
    ]);
    app(SubmitLibraryItemForReviewAction::class)->execute($writer->id, $item->id);
    $reviewer = User::factory()->create(['email' => 'lt2-reviewer@akuru.test']);
    $assignment = app(AssignResearchReviewerAction::class)->execute($item->id, $reviewer->email, User::factory()->create()->id);

    $this->actingAs($reviewer)
        ->withHeader('Referer', url('/ar/review'))
        ->post("/review/{$assignment->id}/declare")
        ->assertSessionHas('success', trans('common.review_flash_declared', [], 'ar'));

    // The paper went back to its writer: the reviewer is told so in Arabic.
    LibraryItem::query()->whereKey($item->id)->update(['status' => LibraryItemStatus::ChangesRequested->value]);
    $this->actingAs($reviewer)
        ->withHeader('Referer', url('/ar/review'))
        ->post("/review/{$assignment->id}", ['recommendation' => 'accept'])
        ->assertSessionHasErrors(['assignment' => trans('common.review_error_not_with_reviewers', [], 'ar')]);
});

it('says on its own row or card what a button without a form was refused', function () {
    foreach (['resources/js/Pages/Library/Write.jsx', 'resources/js/Pages/Library/Review.jsx'] as $page) {
        expect(routerVisitsWithoutRow($page))->toBe([]);
    }

    $write = file_get_contents(resource_path('js/Pages/Library/Write.jsx'));
    // The bank form showed none of its refusals; the application dropped
    // every field's but four; the editor and the author page list them all.
    expect($write)->toContain('<FormErrors errors={bank.errors}')
        ->and($write)->toContain('<FormErrors errors={form.errors} except={APPLY_INLINE} />')
        ->and($write)->toContain('errorsFor(`item:${item.id}`)')
        ->and($write)->toContain("errorsFor('payout')");
});
