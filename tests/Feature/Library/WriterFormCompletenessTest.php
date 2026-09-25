<?php

use App\Domains\Identity\Models\User;
use App\Domains\Library\Actions\DecideWriterApplicationAction;
use App\Domains\Library\Actions\DecideWriterPayoutAction;
use App\Domains\Library\Actions\RecordWriterEarningForPurchaseAction;
use App\Domains\Library\Actions\ReviewLibraryItemSubmissionAction;
use App\Domains\Library\Models\LibraryCategory;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\LibraryPurchase;
use App\Domains\Library\Models\WriterApplication;
use App\Domains\Library\Models\WriterEarning;
use App\Domains\Library\Models\WriterPayout;
use App\Domains\Library\Models\WriterProfile;
use App\Domains\Notifications\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * LIBRARY_PLAN §11.3 / §11.5 (what a writer describes and declares) and
 * §41 (who is told what). Before 2026-09-25 the draft editor had title,
 * type, access, price, abstract, body and a PDF; a submission needed no
 * declaration; and nothing told a writer their work was published, sold,
 * or paid out — they had to keep opening the portal to see.
 */
function completenessWriter(string $name = 'Aishath Writer'): array
{
    $user = User::factory()->create();
    $application = WriterApplication::query()->create([
        'user_id' => $user->id,
        'display_name' => $name,
        'agreement_accepted_at' => now(),
    ]);
    app(DecideWriterApplicationAction::class)->execute($application->id, User::factory()->create()->id, true);

    return [$user, WriterProfile::query()->where('user_id', $user->id)->firstOrFail()];
}

function notificationsFor(User $user): array
{
    return UserNotification::query()->where('user_id', $user->id)->orderBy('id')->pluck('title')->all();
}

it('saves everything §11.3 asks for, and shows the contents and copyright notice to readers', function () {
    [$writer, $profile] = completenessWriter();
    $category = LibraryCategory::query()->create(['name' => 'Fiqh', 'slug' => 'fiqh']);

    $this->withoutLocalizationMiddleware()->actingAs($writer)
        ->post(route('write.items.store'), [
            'title' => 'The Complete Draft',
            'subtitle' => 'A subtitle',
            'content_type' => 'book',
            'access_type' => 'paid',
            'price' => 60,
            'language' => 'dv',
            'library_category_id' => $category->id,
            'description' => 'A description.',
            'abstract' => 'An abstract.',
            'body' => '<p>One.</p><!-- pagebreak --><p>Two.</p><!-- pagebreak --><p>Three.</p>',
            'toc' => "Chapter One\nChapter Two\n\nChapter Three",
            'tags' => ['fasting', 'ramadan'],
            'co_authors' => ['Ahmed Co', 'Aishath Writer', ''],
            'preview_enabled' => 1,
            'preview_pages' => 1,
            'declarations' => ['copyright' => 1, 'ai_use' => 1],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $item = LibraryItem::query()->where('title', 'The Complete Draft')->firstOrFail();
    expect($item->subtitle)->toBe('A subtitle')
        ->and($item->language)->toBe('dv')
        ->and((int) $item->library_category_id)->toBe($category->id)
        ->and($item->description)->toBe('A description.')
        ->and($item->toc)->toContain('Chapter Two')
        ->and($item->tags->pluck('name')->sort()->values()->all())->toBe(['fasting', 'ramadan'])
        // The writer first, then the co-authors; their own name is not doubled.
        ->and($item->authors->pluck('name')->all())->toBe(['Aishath Writer', 'Ahmed Co'])
        ->and($item->preview_enabled)->toBeTrue()
        ->and((int) $item->preview_pages)->toBe(1)
        // Key order is the database's (MySQL 8 sorts JSON object keys; MariaDB keeps them).
        ->and($item->declarations)->toEqualCanonicalizing(['copyright' => true, 'ai_use' => true])
        ->and($item->declared_at)->not->toBeNull();

    // The portal hands the same back for editing.
    $this->withoutLocalizationMiddleware()->actingAs($writer)
        ->get(route('write.index'))
        ->assertInertia(fn ($page) => $page
            ->where('dashboard.items.0.co_authors', ['Ahmed Co'])
            ->where('dashboard.items.0.tags', fn ($tags) => count($tags) === 2)
            ->where('dashboard.items.0.declarations.copyright', true)
            ->where('options.languages.dv', 'Dhivehi'));

    // Published: readers see the contents, the copyright line and the AI note.
    app(ReviewLibraryItemSubmissionAction::class); // autoload check only
    $this->withoutLocalizationMiddleware()->actingAs($writer)->post(route('write.items.submit', $item->id))->assertSessionHasNoErrors();
    $admin = actingPeopleAdmin(['library.manage']);
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('admin.library.items.review', $item->id), ['decision' => 'approved'])
        ->assertSessionHasNoErrors();

    $this->withoutLocalizationMiddleware()->get(route('public.library.show', $item->slug))
        ->assertOk()
        ->assertSee('data-testid="toc"', false)
        ->assertSee('Chapter Three')
        ->assertSee('© '.now()->format('Y').' Aishath Writer, Ahmed Co')
        ->assertSee('All rights reserved. Published by Akuru Institute.')
        ->assertSee('The author declares that AI tools were used in preparing this work.');
});

it('refuses a submission until the declarations are made — copyright for all, originality and conflict of interest for research', function () {
    [$writer] = completenessWriter('Hassan Writer');

    $this->withoutLocalizationMiddleware()->actingAs($writer)
        ->post(route('write.items.store'), ['title' => 'Undeclared Book', 'content_type' => 'book', 'body' => 'Text'])
        ->assertSessionHasNoErrors();
    $book = LibraryItem::query()->where('title', 'Undeclared Book')->firstOrFail();

    $this->withoutLocalizationMiddleware()->actingAs($writer)
        ->from(route('write.index'))
        ->post(route('write.items.submit', $book->id))
        ->assertSessionHasErrors('declarations');
    expect($book->refresh()->status->value)->toBe('draft');

    $this->withoutLocalizationMiddleware()->actingAs($writer)
        ->put(route('write.items.update', $book->id), ['title' => 'Undeclared Book', 'content_type' => 'book', 'body' => 'Text', 'declarations' => ['copyright' => 1]])
        ->assertSessionHasNoErrors();
    $this->withoutLocalizationMiddleware()->actingAs($writer)
        ->post(route('write.items.submit', $book->id))
        ->assertSessionHasNoErrors();
    expect($book->refresh()->status->value)->toBe('submitted');

    // Research: copyright alone is not enough.
    $this->withoutLocalizationMiddleware()->actingAs($writer)
        ->post(route('write.items.store'), [
            'title' => 'A Study',
            'content_type' => 'research',
            'body' => 'Text',
            'affiliation' => 'Akuru Institute',
            'research_field' => 'Fiqh',
            'suggested_reviewer' => 'Dr Someone',
            'declarations' => ['copyright' => 1],
        ])
        ->assertSessionHasNoErrors();
    $study = LibraryItem::query()->where('title', 'A Study')->firstOrFail();
    expect($study->affiliation)->toBe('Akuru Institute')
        ->and($study->research_field)->toBe('Fiqh')
        ->and($study->suggested_reviewer)->toBe('Dr Someone');

    $this->withoutLocalizationMiddleware()->actingAs($writer)
        ->from(route('write.index'))
        ->post(route('write.items.submit', $study->id))
        ->assertSessionHasErrors('declarations');

    $this->withoutLocalizationMiddleware()->actingAs($writer)
        ->put(route('write.items.update', $study->id), [
            'title' => 'A Study', 'content_type' => 'research', 'body' => 'Text',
            'declarations' => ['copyright' => 1, 'originality' => 1, 'conflict_of_interest' => 1],
        ])
        ->assertSessionHasNoErrors();
    $this->withoutLocalizationMiddleware()->actingAs($writer)
        ->post(route('write.items.submit', $study->id))
        ->assertSessionHasNoErrors();
    expect($study->refresh()->status->value)->toBe('submitted');
});

it('tells the writer, the office and the reader what happened (§41)', function () {
    Role::findOrCreate('admin', 'web');
    Permission::findOrCreate('library.manage', 'web');
    $office = User::factory()->create();
    $office->givePermissionTo('library.manage');

    // Application → the office hears; decision → the applicant hears.
    $applicant = User::factory()->create();
    $this->withoutLocalizationMiddleware()->actingAs($applicant)
        ->post(route('write.apply'), ['display_name' => 'New Writer', 'agreement_accepted' => '1'])
        ->assertSessionHasNoErrors();
    expect(notificationsFor($office))->toBe(['New writer application']);
    $application = WriterApplication::query()->where('user_id', $applicant->id)->firstOrFail();
    app(DecideWriterApplicationAction::class)->execute($application->id, $office->id, true);
    expect(notificationsFor($applicant))->toBe(['You are now an Akuru writer']);

    // Submission → both; changes requested → the writer, with the reason.
    $this->withoutLocalizationMiddleware()->actingAs($applicant)
        ->post(route('write.items.store'), ['title' => 'Told Draft', 'content_type' => 'article', 'access_type' => 'paid', 'price' => 20, 'body' => 'Text', 'declarations' => ['copyright' => 1]])
        ->assertSessionHasNoErrors();
    $item = LibraryItem::query()->where('title', 'Told Draft')->firstOrFail();
    $this->withoutLocalizationMiddleware()->actingAs($applicant)->post(route('write.items.submit', $item->id))->assertSessionHasNoErrors();
    expect(notificationsFor($office))->toBe(['New writer application', 'New library submission'])
        ->and(notificationsFor($applicant))->toBe(['You are now an Akuru writer', 'Submission received']);

    app(ReviewLibraryItemSubmissionAction::class)->execute($item->id, $office->id, 'changes_requested', 'Add references');
    $changes = UserNotification::query()->where('user_id', $applicant->id)->latest('id')->firstOrFail();
    expect($changes->title)->toBe('Changes requested')
        ->and($changes->message)->toContain('Add references')
        ->and($changes->data['href'] ?? null)->toBe('/write');

    // Approved → "Published" once, with where it is.
    $this->withoutLocalizationMiddleware()->actingAs($applicant)->post(route('write.items.submit', $item->id))->assertSessionHasNoErrors();
    app(ReviewLibraryItemSubmissionAction::class)->execute($item->id, $office->id, 'approved');
    $published = UserNotification::query()->where('user_id', $applicant->id)->where('title', 'Published')->get();
    expect($published)->toHaveCount(1)
        ->and($published->first()->data['href'])->toBe('/library/'.$item->refresh()->slug);

    // A paid sale → "New sale" with the writer's share; a payout → "Payout paid".
    $buyer = User::factory()->create();
    $purchase = LibraryPurchase::query()->create([
        'user_id' => $buyer->id, 'library_item_id' => $item->id, 'amount' => 20, 'currency' => 'MVR', 'status' => 'paid', 'purchased_at' => now(),
    ]);
    $earning = app(RecordWriterEarningForPurchaseAction::class)->execute($purchase->id);
    $sale = UserNotification::query()->where('user_id', $applicant->id)->where('title', 'New sale')->firstOrFail();
    expect($sale->message)->toContain('MVR 20.00')->toContain('MVR 14.00');

    $payout = WriterPayout::query()->create([
        'writer_id' => $earning->writer_id, 'amount' => 14, 'currency' => 'MVR', 'status' => 'requested', 'requested_at' => now(),
    ]);
    WriterEarning::query()->whereKey($earning->id)->update(['writer_payout_id' => $payout->id]);
    app(DecideWriterPayoutAction::class)->execute($payout->id, $office->id, true, 'Transferred today.');
    $paid = UserNotification::query()->where('user_id', $applicant->id)->where('title', 'Payout paid')->firstOrFail();
    expect($paid->message)->toContain('MVR 14.00')->toContain('Transferred today.');

    // Every one of them is in the `library` category, which a person can switch off.
    expect(UserNotification::query()->where('category', '!=', 'library')->count())->toBe(0);
});
