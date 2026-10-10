<?php

use App\Domains\Commerce\Actions\SavePromotionCampaignAction;
use App\Domains\Commerce\Models\PromotionCampaign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The Library office's promotion campaigns in Dhivehi and Arabic (BACKLOG
 * C21, slice PC1, STATUS §5qy).
 *
 * The page read the `admin` book, but what the server said when it refused
 * a campaign was English — a missing name, an end before the start, a
 * percentage over a hundred, who funds it, what it covers — and Laravel's
 * own refusals named the fields by their codes. End's refusal, a campaign
 * already ended from a tab opened before, was said nowhere. The list put
 * an amount and its cap together in English ("MVR 5 (max MVR 20)"), and
 * named a target no longer listed by its code. The names and descriptions
 * the office types stay as written.
 */
uses(RefreshDatabase::class);

/** Where the server writes what the promotions page says. */
function promotionCampaignServerFiles(): array
{
    return [
        'app/Domains/Library/Http/Controllers/AdminLibraryPromotionsController.php',
        'app/Domains/Commerce/Actions/SavePromotionCampaignAction.php',
        'app/Domains/Commerce/Actions/EndPromotionCampaignAction.php',
    ];
}

function phraseBookForPc1(string $book, string $locale): array
{
    return require base_path("resources/lang/{$locale}/{$book}.php");
}

it('keys every string on the promotions page in three languages, and says End\'s refusal under its row', function () {
    [$en, $dv, $ar] = [phraseBookForPc1('admin', 'en'), phraseBookForPc1('admin', 'dv'), phraseBookForPc1('admin', 'ar')];
    $screen = 'resources/js/Pages/Library/Promotions.jsx';
    $source = file_get_contents(base_path($screen));

    preg_match_all("/(?<![\\w\$.])t\\.([a-z][a-z0-9_]+) \\|\\| '((?:[^'\\\\]|\\\\.)*)'/", $source, $uses, PREG_SET_ORDER);
    expect($uses)->not->toBeEmpty();

    foreach ($uses as [, $key, $fallback]) {
        expect(array_key_exists($key, $en))->toBeTrue("admin.{$key} is missing in English")
            ->and(array_key_exists($key, $dv))->toBeTrue("admin.{$key} is missing in Dhivehi")
            ->and(array_key_exists($key, $ar))->toBeTrue("admin.{$key} is missing in Arabic")
            ->and($en[$key])->toBe(stripslashes($fallback), "admin.{$key} says something else in English than the screen")
            ->and($dv[$key])->not->toBe($en[$key], "admin.{$key} is English in Dhivehi")
            ->and($ar[$key])->not->toBe($en[$key], "admin.{$key} is English in Arabic");
    }

    expect(preg_match_all('/>\s*[A-Z][A-Za-z]+[^<>{}]*</', $source, $text))->toBe(0, 'English text nodes: '.implode(' | ', $text[0] ?? []))
        ->and(preg_match_all('/(placeholder|aria-label|title|data-label)="[A-Za-z][^"]*"/', $source, $attrs))->toBe(0, 'English attributes: '.implode(' | ', $attrs[0] ?? []))
        ->and(unnamedFields($source))->toBe([], 'fields with no name')
        ->and(routerVisitsWithoutRow($screen))->toBe([], 'posts with nowhere to say a refusal')
        // An amount and its cap are the phrase book's, not put together in English.
        ->and($source)->not->toContain('`MVR ')->not->toContain('(max MVR')
        // A target no longer listed is named by its kind, not printed as its code.
        ->and($source)->not->toContain('`${target.type} #');
});

it('names each kind of target, each funding source and each state, and puts an amount in its place, in all three languages', function () {
    $kinds = array_values(array_diff(SavePromotionCampaignAction::TARGET_TYPES, ['all', 'gift_card']));
    expect($kinds)->toBe(['library_item', 'library_category', 'writer_profile']);

    $keys = [
        ...array_map(fn (string $kind) => 'admin.library_promotions_kind_'.$kind, $kinds),
        ...array_map(fn (string $source) => 'admin.library_promotions_funding_'.$source, SavePromotionCampaignAction::FUNDING_SOURCES),
        // ListPromotionCampaignsAction: ended by the office, not started yet, run out, or running.
        ...array_map(fn (string $state) => 'admin.library_promotions_state_'.$state, ['live', 'scheduled', 'expired', 'ended']),
    ];
    foreach ($keys as $key) {
        expect(trans($key, [], 'en'))->not->toBe($key, "{$key} has no English")
            ->and(trans($key, [], 'dv'))->toMatch('/\p{Thaana}/u', "{$key} in Dhivehi")
            ->and(trans($key, [], 'ar'))->toMatch('/\p{Arabic}/u', "{$key} in Arabic");
    }

    // The amount, the cap and a card's minimum keep their number wherever the language puts it.
    $said = [
        'en' => ['MVR 20', '(max MVR 20)', 'from MVR 20', 'Item #7 (no longer listed)'],
        'dv' => ['20 ރުފިޔާ', '(އެންމެ ގިނަވެގެން 20 ރުފިޔާ)', '20 ރުފިޔާ ނުވަތަ އެއަށްވުރެ ގިނަ', 'ލިޔުން #7 (މިހާރު ލިސްޓުގައި ނެތް)'],
        'ar' => ['20 روفية', '(بحد أقصى 20 روفية)', '20 روفية أو أكثر', 'العنصر #7 (لم يعد مدرجًا)'],
    ];
    foreach ($said as $locale => [$money, $cap, $from, $unlisted]) {
        $amount = trans('admin.library_promotions_money', ['amount' => 20], $locale);
        expect($amount)->toBe($money)
            ->and(trans('admin.library_promotions_at_most', ['amount' => $amount], $locale))->toBe($cap)
            ->and(trans('admin.library_promotions_from', ['amount' => $amount], $locale))->toBe($from)
            ->and(trans('admin.library_promotions_unlisted', ['kind' => trans('admin.library_promotions_kind_library_item', [], $locale), 'id' => 7], $locale))->toBe($unlisted);
    }

    // The page's Arabic spells the rufiyaa one way, as the books' other phrases do.
    $ar = phraseBookForPc1('admin', 'ar');
    foreach (array_filter($ar, fn ($value, $key) => str_starts_with($key, 'library_promotions_'), ARRAY_FILTER_USE_BOTH) as $key => $value) {
        expect($value)->not->toContain('روفيا', "admin.{$key}");
    }
});

it('leaves no English in what the server says on the promotions page, and says it in Dhivehi and Arabic', function () {
    $english = [];
    foreach (promotionCampaignServerFiles() as $file) {
        $english = [...$english, ...refusalEnglishIn($file)];
    }
    expect($english)->toBe([]);

    $keys = refusalKeysIn(promotionCampaignServerFiles());
    expect($keys)->toContain(
        'common.campaign_error_name', 'common.campaign_error_type', 'common.error_discount_value', 'common.campaign_error_ends',
        'common.campaign_error_funding', 'common.campaign_error_target', 'common.campaign_error_target_id', 'common.campaign_error_already_ended',
        'admin.library_promotions_attr_name', 'admin.library_promotions_attr_targets', 'admin.library_promotions_attr_banner',
    );
    foreach ($keys as $key) {
        expect(trans($key, [], 'en'))->not->toBe($key, "{$key} has no English")
            ->and(trans($key, [], 'dv'))->toMatch('/\p{Thaana}/u', "{$key} in Dhivehi")
            ->and(trans($key, [], 'ar'))->toMatch('/\p{Arabic}/u', "{$key} in Arabic");
    }
});

it('starts and ends a campaign in Dhivehi, and says what it refused in Dhivehi', function () {
    $office = actingSystemAdmin(['library.manage']);
    [$dv, $common] = [phraseBookForPc1('admin', 'dv'), phraseBookForPc1('common', 'dv')];
    app()->setLocale('dv');
    $offer = fn (array $data) => $data + ['name' => 'PC1 offer', 'discount_type' => 'percentage', 'discount_value' => 10, 'funding_source' => 'akuru'];

    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->get(route('admin.library.promotions'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Library/Promotions')->where('t.library_promotions_end', $dv['library_promotions_end']));

    // A campaign with no name: Laravel's refusal, the field named in Dhivehi.
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('admin.library.promotions.store'), $offer(['name' => '']))
        ->assertSessionHasErrors(['name' => trans('validation.required', ['attribute' => $dv['library_promotions_attr_name']], 'dv')]);
    // An end before its start, and a percentage over a hundred.
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('admin.library.promotions.store'), $offer(['starts_at' => now()->addDay()->toDateTimeString(), 'ends_at' => now()->toDateTimeString()]))
        ->assertSessionHasErrors(['ends_at' => $common['campaign_error_ends']]);
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('admin.library.promotions.store'), $offer(['discount_value' => 150]))
        ->assertSessionHasErrors(['discount_value' => $common['error_discount_value']]);
    expect(PromotionCampaign::query()->count())->toBe(0);

    // Started, ended, and End pressed again from a page opened before.
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('admin.library.promotions.store'), $offer([]))
        ->assertSessionHasNoErrors()->assertSessionHas('success', $dv['library_promotions_saved']);
    $campaign = PromotionCampaign::query()->where('name', 'PC1 offer')->firstOrFail();
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('admin.library.promotions.end', $campaign->id))
        ->assertSessionHas('success', $dv['library_promotions_ended']);
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('admin.library.promotions.end', $campaign->id))
        ->assertSessionHasErrors(['campaign' => $common['campaign_error_already_ended']]);

    // What only a hand-made post can send, said the same wherever the action is called from.
    $save = fn (array $data) => fn () => app(SavePromotionCampaignAction::class)->execute($offer($data));
    expect($save(['name' => '  ']))->toThrow(ValidationException::class, $common['campaign_error_name'])
        ->and($save(['discount_type' => 'free']))->toThrow(ValidationException::class, $common['campaign_error_type'])
        ->and($save(['funding_source' => 'nobody']))->toThrow(ValidationException::class, $common['campaign_error_funding'])
        ->and($save(['targets' => [['type' => 'shelf']]]))->toThrow(ValidationException::class, str_replace(':type', 'shelf', $common['campaign_error_target']))
        ->and($save(['targets' => [['type' => 'library_item']]]))->toThrow(ValidationException::class, $common['campaign_error_target_id']);
});
