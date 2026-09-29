<?php

use App\Domains\Bookshop\Actions\CreateVendorAction;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\ProductQuestion;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Models\VendorMember;
use App\Domains\Identity\Models\User;
use App\Domains\Notifications\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * STATUS §5le, product questions and answers: a signed-in customer asks,
 * the shop is told and answers in public, the asker is told; the question
 * shows on the product page once answered; the office may hide it.
 */
function qaShop(): array
{
    Role::findOrCreate('vendor', 'web');
    $created = app(CreateVendorAction::class)->execute(['name' => 'Answer Shop', 'owner_name' => 'Owner', 'owner_email' => 'qa-owner@example.test'], User::factory()->create()->id);
    VendorMember::query()->where('vendor_id', $created['vendor_id'])->update(['agreement_accepted_at' => now()]);

    return [Vendor::query()->findOrFail($created['vendor_id']), User::query()->findOrFail($created['owner_user_id'])];
}

function qaProduct(Vendor $vendor, string $title = 'Maths Workbook 3'): Product
{
    return Product::query()->create([
        'vendor_id' => $vendor->id, 'slug' => Str::slug($title), 'title' => $title, 'price' => 50,
        'currency' => 'MVR', 'tax_class' => 'zero_rated', 'track_stock' => true, 'stock' => 20, 'status' => 'active', 'visibility' => 'shop',
    ]);
}

function qaAs(?User $user = null)
{
    $t = test()->withoutLocalizationMiddleware();

    return $user ? $t->actingAs($user) : $t;
}

it('lets a customer ask, the shop answer in public, and shows it on the product page once answered', function () {
    [$shop, $owner] = qaShop();
    $book = qaProduct($shop);
    $customer = User::factory()->create(['name' => 'Aishath Mohamed']);

    qaAs()->get(route('public.shop.product', $book->slug))->assertOk()
        ->assertSee('data-testid="questions"', false)->assertSee(__('shop.sign_in_to_ask'))->assertSee(__('shop.no_questions'));

    qaAs($customer)->post(route('public.shop.question', $book->slug), ['question' => 'Is this the 2026 syllabus edition?'])
        ->assertRedirect(route('public.shop.product', $book->slug).'#questions');
    $question = ProductQuestion::query()->sole();
    expect($question->vendor_id)->toBe($shop->id)->and($question->answered_at)->toBeNull()
        ->and(UserNotification::query()->where('user_id', $owner->id)->where('title', __('shop.notice_question_title'))->exists())->toBeTrue();

    // Not shown until answered; the asker sees that theirs is waiting.
    qaAs($customer)->get(route('public.shop.product', $book->slug))->assertOk()
        ->assertDontSee('Is this the 2026 syllabus edition?')->assertSee('data-testid="questions-waiting"', false);

    qaAs($owner)->get(route('vendor.reviews.index'))->assertOk()->assertInertia(fn ($page) => $page
        ->where('questions.waiting', 1)->where('questions.questions.0.question', 'Is this the 2026 syllabus edition?'));
    qaAs($owner)->post(route('vendor.questions.answer', $question->id), ['answer' => 'Yes — printed March 2026.'])->assertSessionHasNoErrors();
    expect(UserNotification::query()->where('user_id', $customer->id)->where('title', __('shop.notice_question_answered_title'))->exists())->toBeTrue();

    qaAs()->get(route('public.shop.product', $book->slug))->assertOk()
        ->assertSee('Is this the 2026 syllabus edition?')->assertSee('Yes — printed March 2026.')
        ->assertSee('Aishath M.')->assertDontSee('Aishath Mohamed')
        ->assertSee(__('shop.answer_from', ['vendor' => 'Answer Shop']));

    // Editing the answer does not tell the customer twice.
    qaAs($owner)->post(route('vendor.questions.answer', $question->id), ['answer' => 'Yes — the March 2026 print.']);
    expect(UserNotification::query()->where('user_id', $customer->id)->where('title', __('shop.notice_question_answered_title'))->count())->toBe(1);

    $csv = qaAs($owner)->get(route('vendor.questions.export'));
    expect($csv->streamedContent())->toContain('Is this the 2026 syllabus edition?')->toContain('the March 2026 print');
});

it('refuses a guest, a too-short question, and a fourth waiting question on one product', function () {
    [$shop] = qaShop();
    $book = qaProduct($shop);
    $customer = User::factory()->create();

    qaAs()->post(route('public.shop.question', $book->slug), ['question' => 'Hello there, a question'])->assertRedirect(route('login'));
    qaAs($customer)->post(route('public.shop.question', $book->slug), ['question' => 'Hi?'])->assertSessionHasErrors('question');
    foreach (range(1, 3) as $n) {
        qaAs($customer)->post(route('public.shop.question', $book->slug), ['question' => "Question number {$n} here"])->assertSessionHasNoErrors();
    }
    qaAs($customer)->post(route('public.shop.question', $book->slug), ['question' => 'Question number 4 here'])->assertSessionHasErrors('question');
    expect(ProductQuestion::query()->count())->toBe(3);
});

it('keeps each shop to its own questions', function () {
    [$shop, $owner] = qaShop();
    $other = Vendor::query()->create(['name' => 'Other', 'slug' => 'other-qa', 'code' => 'OQA', 'status' => 'active']);
    $theirs = ProductQuestion::query()->create(['product_id' => qaProduct($other, 'Other Book')->id, 'vendor_id' => $other->id, 'user_id' => User::factory()->create()->id, 'question' => 'Their question', 'status' => 'published']);

    qaAs($owner)->post(route('vendor.questions.answer', $theirs->id), ['answer' => 'Not mine to answer'])->assertNotFound();
    qaAs($owner)->get(route('vendor.reviews.index'))->assertInertia(fn ($page) => $page->where('questions.questions', []));
    expect($theirs->refresh()->answer)->toBeNull();
});

it('lets the office hide a question and its answer with a note, and publish it again', function () {
    [$shop, $owner] = qaShop();
    $book = qaProduct($shop);
    $question = ProductQuestion::query()->create(['product_id' => $book->id, 'vendor_id' => $shop->id, 'user_id' => User::factory()->create()->id, 'question' => 'A rude question', 'answer' => 'An answer', 'answered_at' => now(), 'status' => 'published']);
    Role::findOrCreate('super_admin', 'web');
    Permission::findOrCreate('bookshop.manage', 'web');
    $office = User::factory()->create();
    $office->assignRole('super_admin');
    $office->givePermissionTo('bookshop.manage');

    qaAs($office)->get(route('admin.bookshop.index'))->assertOk()->assertInertia(fn ($page) => $page->where('questions.0.question', 'A rude question'));
    qaAs($office)->post(route('admin.bookshop.questions.moderate', $question->id), ['action' => 'hide'])->assertSessionHasErrors('note');
    qaAs($office)->post(route('admin.bookshop.questions.moderate', $question->id), ['action' => 'hide', 'note' => 'Not about the product'])->assertSessionHasNoErrors();
    qaAs()->get(route('public.shop.product', $book->slug))->assertDontSee('A rude question');
    qaAs($owner)->get(route('vendor.reviews.index'))->assertInertia(fn ($page) => $page->where('questions.questions.0.moderation_note', 'Not about the product'));

    qaAs($office)->post(route('admin.bookshop.questions.moderate', $question->id), ['action' => 'publish'])->assertSessionHasNoErrors();
    qaAs()->get(route('public.shop.product', $book->slug))->assertSee('A rude question');

    qaAs($owner)->post(route('admin.bookshop.questions.moderate', $question->id), ['action' => 'hide', 'note' => 'x'])->assertForbidden();
});
