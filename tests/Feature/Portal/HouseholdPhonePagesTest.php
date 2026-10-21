<?php

use App\Domains\Commerce\Actions\CreditWalletAction;
use App\Domains\Identity\Models\User;
use Database\Seeders\SmokeMarkerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/*
 * HP1 (STATUS §5tv): what the household audit found on a phone. The wallet's
 * ledger stacks into cards, each value named; the work photo the smoke data
 * points at exists, so the family's work page draws it.
 */
it('stacks the wallet ledger on a phone, each value named', function () {
    $user = User::factory()->create();
    app(CreditWalletAction::class)->execute($user->id, 25, 'admin', null, 'Top-up');

    $html = $this->withoutLocalizationMiddleware()->actingAs($user)->get('/my-wallet')->assertOk()->getContent();

    expect($html)->toContain('class="table-stack min-w-full text-sm"');
    foreach (['Date', 'Type', 'Source', 'Amount', 'Balance'] as $label) {
        expect($html)->toContain('data-label="'.__('public.'.$label).'"');
    }
});

it('writes the seeded work photo, so the family\'s work page can draw it', function () {
    $this->seed();
    $this->seed(SmokeMarkerSeeder::class);

    $path = DB::table('media_files')->where('original_name', 'SMOKE-Work.jpg')->value('path');
    expect(Storage::disk('local')->exists($path))->toBeTrue()
        ->and(Storage::disk('local')->mimeType($path))->toBe('image/jpeg');

    $parent = User::query()->where('email', 'parent@akuru.edu.mv')->firstOrFail();
    $work = (int) DB::table('student_work')->where('title', 'SMOKE-Work')->value('id');
    $this->withoutLocalizationMiddleware()->actingAs($parent)->get(route('portal.work.photo', $work))->assertOk();
});
