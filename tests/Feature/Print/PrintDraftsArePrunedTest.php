<?php

use App\Domains\Bookshop\Actions\Print\PrunePrintDraftsAction;
use App\Domains\Bookshop\Models\PrintJob;
use App\Domains\Bookshop\Models\PrintPaperOption;
use App\Domains\Bookshop\Support\PrintDesk;
use App\Domains\Identity\Models\User;
use App\Domains\Media\Actions\StoreGeneratedDocumentAction;
use App\Domains\Media\Enums\DocumentType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * NAME_TAGS_PLAN §7.2 and D13 (STATUS §5rx): print drafts nobody ordered in
 * 30 days are removed with their files, by `print:prune-drafts`, daily.
 */
uses(RefreshDatabase::class);

function prunePrintJob(string $status, int $daysOld, ?int $userId = null): PrintJob
{
    PrintDesk::ensure();
    $paper = PrintPaperOption::query()->orderBy('id')->firstOrFail();
    $job = PrintJob::query()->create(['user_id' => $userId, 'print_paper_option_id' => $paper->id, 'design' => ['tag' => ['w' => 95, 'h' => 60], 'elements' => []], 'sheets' => 1, 'status' => $status, 'source' => 'template']);
    DB::table('print_jobs')->where('id', $job->id)->update(['updated_at' => now()->subDays($daysOld)]);

    return $job->fresh();
}

it('removes old drafts and unapproved proofs with their files, and keeps the rest', function () {
    Storage::fake('local');
    $customer = User::factory()->create();
    $oldDraft = prunePrintJob('draft', 31, $customer->id);
    $oldProof = prunePrintJob('proofed', 45);
    $proof = app(StoreGeneratedDocumentAction::class)->execute('print_job', $oldProof->id, DocumentType::PrintProof, 'Proof', 'png-bytes', 'png', null, 'p1');
    $recentDraft = prunePrintJob('draft', 10, $customer->id);
    $oldApproved = prunePrintJob('approved', 60, $customer->id);
    $oldDraftInCart = prunePrintJob('draft', 40, $customer->id);
    $cart = DB::table('carts')->insertGetId(['user_id' => $customer->id, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('cart_items')->insert(['cart_id' => $cart, 'product_id' => PrintDesk::product()->id, 'quantity' => 1, 'print_job_id' => $oldDraftInCart->id, 'created_at' => now(), 'updated_at' => now()]);
    // Someone else's document under another owner is never reached.
    $other = app(StoreGeneratedDocumentAction::class)->execute('print_job', $recentDraft->id, DocumentType::PrintProof, 'Proof', 'keep', 'png', null, 'p1');

    expect(app(PrunePrintDraftsAction::class)->candidates()->pluck('id')->sort()->values()->all())->toBe(collect([$oldDraft->id, $oldProof->id])->sort()->values()->all());

    $this->artisan('print:prune-drafts', ['--dry-run' => true])->expectsOutput('Print drafts that would be removed: 2')->assertSuccessful();
    expect(PrintJob::query()->count())->toBe(5);

    $this->artisan('print:prune-drafts')->expectsOutput('Print drafts removed: 2')->assertSuccessful();
    expect(PrintJob::query()->pluck('id')->sort()->values()->all())->toBe(collect([$recentDraft->id, $oldApproved->id, $oldDraftInCart->id])->sort()->values()->all())
        ->and(DB::table('documents')->where('id', $proof->id)->exists())->toBeFalse()
        ->and(Storage::disk('local')->exists($proof->media_path))->toBeFalse()
        ->and(DB::table('documents')->where('id', $other->id)->exists())->toBeTrue()
        ->and(Storage::disk('local')->exists($other->media_path))->toBeTrue();
});

it('runs every day on the schedule', function () {
    $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())->map(fn ($e) => $e->command);
    expect($events->contains(fn ($c) => str_contains((string) $c, 'print:prune-drafts')))->toBeTrue();
});
