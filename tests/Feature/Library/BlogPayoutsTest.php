<?php

use App\Domains\Identity\Models\User;
use App\Domains\Library\Actions\DecideWriterPayoutAction;
use App\Domains\Library\Models\LibraryTip;
use App\Domains\Library\Models\WriterEarning;
use App\Domains\Library\Models\WriterPayout;
use App\Domains\Library\Models\WriterProfile;
use App\Domains\People\Actions\AttachGuardianAction;
use App\Domains\People\Actions\RecordConsentAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * BLOG_PLAN BL6b (STATUS §5sn, §7.3): a student blogger is never paid to a
 * bank. Under 18, the payout goes to the consenting parent's Akuru wallet,
 * and only while that consent is in force; at 18 or over, to their own.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    config(['library.payouts_enabled' => true, 'library.min_payout' => 10]);
});

/** A student blog with MVR :available of available earnings; the parent consented if under 18. */
function bl6bStudent(string $born = '2012-04-01', float $available = 45.0, bool $consent = true): array
{
    $student = makeStudent(['date_of_birth' => $born]);
    $guardian = makeGuardian();
    app(AttachGuardianAction::class)->execute($student, $guardian, 'mother', true);
    $parent = User::query()->findOrFail($guardian->user_id);
    if ($consent) {
        app(RecordConsentAction::class)->execute('student', $student->id, 'blog_publishing', true, $parent->id, 'portal');
    }
    $user = User::query()->findOrFail($student->user_id);
    $profile = WriterProfile::query()->create(['user_id' => $user->id, 'kind' => 'student', 'display_name' => 'Starling', 'slug' => 'starling-'.$user->id, 'status' => 'active', 'approved_at' => now(), 'guardian_user_id' => $parent->id]);
    $tip = LibraryTip::query()->create(['user_id' => User::factory()->create()->id, 'writer_id' => $profile->id, 'amount' => $available, 'method' => 'wallet', 'status' => 'paid', 'paid_at' => now()->subDays(30)]);
    WriterEarning::query()->create(['writer_id' => $profile->id, 'kind' => 'tip', 'library_tip_id' => $tip->id, 'gross_amount' => $available, 'discount_amount' => 0, 'wallet_amount' => $available, 'bml_amount' => 0, 'platform_commission' => 0, 'writer_amount' => $available, 'status' => 'available', 'available_at' => now()->subDay()]);

    return [$user, $profile, $parent, $student];
}

function bl6bBalance(int $userId): float
{
    return (float) (DB::table('wallets')->where('user_id', $userId)->value('balance') ?? 0);
}

it('pays an under-18 student\'s earnings into the consenting parent\'s wallet, never a bank', function () {
    [$child, $profile, $parent] = bl6bStudent();
    $web = test()->withoutLocalizationMiddleware();

    $web->actingAs($child)->get('/my-blog')->assertInertia(fn (Assert $p) => $p->where('blog.earnings.available', 45)->where('blog.earnings.to_parent', true)->where('blog.earnings.problem', null));
    // A student has no bank details to give.
    $web->actingAs($child)->post('/write/bank-details', ['bank_name' => 'BML', 'account_name' => 'A', 'account_number' => '7730000000001'])->assertSessionHasErrors('writer');

    $web->actingAs($child)->post('/my-blog/payout')->assertSessionHas('success', __('blog.flash_payout_requested'));
    $payout = WriterPayout::query()->sole();
    expect($payout->method)->toBe('wallet')->and($payout->wallet_user_id)->toBe($parent->id)->and((float) $payout->amount)->toBe(45.0);

    $office = actingSystemAdmin(['library.manage']);
    app(DecideWriterPayoutAction::class)->execute($payout->id, $office->id, true);
    expect(bl6bBalance($parent->id))->toBe(45.0)->and(bl6bBalance($child->id))->toBe(0.0)
        ->and(WriterEarning::query()->sole()->status)->toBe('paid')
        ->and(DB::table('wallet_transactions')->where('source_type', 'library_payout')->where('source_id', $payout->id)->value('description'))->toContain('Starling');
    expect(DB::table('user_notifications')->where('user_id', $parent->id)->pluck('data')->map(fn ($d) => json_decode((string) $d, true)['href'] ?? null)->all())->toContain('/my-wallet');
});

it('refuses an under-18 student\'s payout once the parent\'s consent is withdrawn', function () {
    [$child, , $parent, $student] = bl6bStudent();
    app(RecordConsentAction::class)->execute('student', $student->id, 'blog_publishing', false, $parent->id, 'admin');
    test()->withoutLocalizationMiddleware()->actingAs($child)->post('/my-blog/payout')->assertSessionHasErrors('payout');
    expect(WriterPayout::query()->count())->toBe(0);
    test()->withoutLocalizationMiddleware()->actingAs($child)->get('/my-blog')->assertInertia(fn (Assert $p) => $p->where('blog.earnings.problem', __('blog.error_payout_no_parent')));
});

it('pays a student of 18 or over into their own wallet, and leaves a full writer\'s payout as it was', function () {
    [$adult, , $parent] = bl6bStudent('2000-01-01');
    test()->withoutLocalizationMiddleware()->actingAs($adult)->post('/my-blog/payout')->assertSessionHas('success');
    $payout = WriterPayout::query()->sole();
    expect($payout->wallet_user_id)->toBe($adult->id);
    app(DecideWriterPayoutAction::class)->execute($payout->id, actingSystemAdmin(['library.manage'])->id, true);
    expect(bl6bBalance($adult->id))->toBe(45.0)->and(bl6bBalance($parent->id))->toBe(0.0);

    // A full writer still needs a verified ID card and bank details.
    $writer = User::factory()->create();
    WriterProfile::query()->create(['user_id' => $writer->id, 'display_name' => 'Adult Writer', 'slug' => 'adult-'.$writer->id, 'status' => 'active']);
    test()->withoutLocalizationMiddleware()->actingAs($writer)->post('/write/payout-request')->assertSessionHasErrors('payout');

    // With payouts closed, nobody is paid.
    config(['library.payouts_enabled' => false]);
    [$child] = bl6bStudent();
    test()->withoutLocalizationMiddleware()->actingAs($child)->post('/my-blog/payout')->assertSessionHasErrors('payout');
    expect(__('blog.earnings_to_parent', [], 'dv'))->toMatch('/\p{Thaana}/u')->and(__('blog.earnings_to_parent', [], 'ar'))->toMatch('/\p{Arabic}/u');
});
