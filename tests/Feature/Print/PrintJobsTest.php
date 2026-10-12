<?php

use App\Domains\Bookshop\Actions\Cart\SaveCartItemAction;
use App\Domains\Bookshop\Actions\Print\QuotePrintJobAction;
use App\Domains\Bookshop\Actions\Print\SavePrintJobAction;
use App\Domains\Bookshop\Enums\PrintJobStatus;
use App\Domains\Bookshop\Models\Cart;
use App\Domains\Bookshop\Models\PrintFinishingOption;
use App\Domains\Bookshop\Models\PrintJob;
use App\Domains\Bookshop\Models\PrintLayout;
use App\Domains\Bookshop\Models\PrintPaperOption;
use App\Domains\Bookshop\Models\PrintSubjectSet;
use App\Domains\Bookshop\Models\PrintTemplate;
use App\Domains\Bookshop\Support\PrintDesk;
use App\Domains\Identity\Models\User;
use App\Support\Contracts\PageImageInterface;
use App\Support\Contracts\PdfConverterInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The customer's name tags (NAME_TAGS_PLAN §3, §6, §11 NT3, STATUS §5rq): a
 * job started from a template, filled, priced on the server, proofed with
 * watermarked pictures by short-lived signed links, approved and locked.
 */
uses(RefreshDatabase::class);

/** The desk switched on with one paper and one finishing priced, and a host that can print (fakes). */
function nt3OpenDesk(): array
{
    PrintDesk::ensure();
    $paper = PrintPaperOption::query()->orderBy('sort_order')->orderBy('id')->firstOrFail();
    $paper->update(['price_colour' => 3.00, 'price_bw' => 1.50, 'is_active' => true]);
    $finish = PrintFinishingOption::query()->orderBy('sort_order')->orderBy('id')->firstOrFail();
    $finish->update(['price' => 1.25, 'is_active' => true]);
    config(['print.desk.enabled' => true, 'print.desk.min_sheets' => 1, 'print.desk.tiers' => [['sheets' => 10, 'percent' => 5], ['sheets' => 50, 'percent' => 10]]]);
    printHostCanPrint();
    Storage::fake('local');

    return [$paper, $finish];
}

function nt3Customer(): User
{
    return User::factory()->create();
}

function nt3Template(string $kind = 'name_tag'): PrintTemplate
{
    return PrintTemplate::query()->where('kind', $kind)->orderBy('sort_order')->firstOrFail();
}

dataset('prices', [
    // paper, colour, finishing, sheets, tiers → per sheet, subtotal, discount, total
    'colour, no finishing, 1 sheet' => [true, false, 1, 3.00, 3.00, 0.00, 3.00],
    'black and white, 2 sheets' => [false, false, 2, 1.50, 3.00, 0.00, 3.00],
    'colour + finishing, 4 sheets' => [true, true, 4, 4.25, 17.00, 0.00, 17.00],
    'colour, 10 sheets: 5% off' => [true, false, 10, 3.00, 30.00, 1.50, 28.50],
    'colour + finishing, 50 sheets: 10% off' => [true, true, 50, 4.25, 212.50, 21.25, 191.25],
]);

it('prices a job on the server: paper by colour, finishing, sheets, the tier reached, GST inside', function (bool $colour, bool $withFinishing, int $sheets, float $perSheet, float $subtotal, float $discount, float $total) {
    [$paper, $finish] = nt3OpenDesk();
    $quote = app(QuotePrintJobAction::class)->execute($paper->id, $colour, $withFinishing ? [$finish->id] : [], $sheets);

    expect($quote['per_sheet'])->toBe($perSheet)->and($quote['subtotal'])->toBe($subtotal)->and($quote['discount'])->toBe($discount)
        ->and($quote['total'])->toBe($total)->and($quote['unit_price'])->toBe(round($total / $sheets, 2))
        ->and($quote['gst'])->toBe(round($total - $total / 1.08, 2));
})->with('prices');

it('refuses a price it cannot give, in the page\'s language', function () {
    [$paper, $finish] = nt3OpenDesk();
    app()->setLocale('dv');
    $quote = app(QuotePrintJobAction::class);
    $refuses = function (callable $call, string $key, array $vars = []) {
        try {
            $call();
            test()->fail('quoted');
        } catch (ValidationException $e) {
            expect(collect($e->errors())->flatten()->first())->toBe(__($key, $vars, 'dv'));
        }
    };
    $refuses(fn () => $quote->execute(null, true, [], 1), 'print.error_job_paper');
    $paper->update(['price_colour' => 0]);
    $refuses(fn () => $quote->execute($paper->id, true, [], 1), 'print.error_job_paper_no_colour');
    $refuses(fn () => $quote->execute($paper->id, false, [999], 1), 'print.error_job_finishing');
    config(['print.desk.min_sheets' => 3]);
    $refuses(fn () => $quote->execute($paper->id, false, [], 2), 'print.error_job_min_sheets', ['min' => 3]);
});

it('opens the door only while the service is on, except to the office, and starts a guest\'s job on their basket', function () {
    nt3OpenDesk();
    config(['print.desk.enabled' => false]);
    printWeb()->get(route('public.shop.print'))->assertNotFound();
    printWeb()->actingAs(printOffice())->get(route('public.shop.print'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Shop/PrintDoor')->where('enabled', false)->has('templates', 5));
    auth()->logout();

    config(['print.desk.enabled' => true]);
    printWeb()->get(route('public.shop.print'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Shop/PrintDoor')->where('signed_in', false)->has('templates', 5)->has('sample.name'));
    $template = nt3Template();
    $start = printWeb()->post(route('public.shop.print.start'), ['template' => $template->id]);
    $job = PrintJob::query()->latest('id')->firstOrFail();
    $start->assertRedirect(route('public.shop.print.job', $job->id));
    expect($job->user_id)->toBeNull()->and($job->cart_id)->not->toBeNull()->and($job->status)->toBe(PrintJobStatus::Draft)
        ->and($job->print_template_id)->toBe($template->id)->and($job->design['fields'])->toBe($template->design['fields']);

    // The guest sees and saves their own job; the proof needs an account.
    printWeb()->get(route('public.shop.print.job', $job->id))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Shop/PrintJob')->where('job.status', 'draft')->where('signed_in', false));
    printWeb()->postJson(route('public.shop.print.save', $job->id), ['entry' => ['mode' => 'same', 'row' => ['name' => 'އައިޝަތު', 'class' => '3B']], 'sheets' => 2])
        ->assertOk()->assertJsonPath('job.sheets', 2)->assertJsonPath('job.price.total', 6)->assertJsonPath('job.tags', 16);
    printWeb()->post(route('public.shop.print.proof.make', $job->id))->assertRedirect();
    expect($job->fresh()->status)->toBe(PrintJobStatus::Draft);

    // Signing in takes the job along with the basket.
    $customer = nt3Customer();
    printWeb()->actingAs($customer)->get(route('public.shop.print.job', $job->id))->assertOk();
    expect($job->fresh()->user_id)->toBe($customer->id)->and($job->fresh()->cart_id)->toBeNull();

    // Someone else's session does not find it.
    printWeb()->actingAs(nt3Customer())->get(route('public.shop.print.job', $job->id))->assertNotFound();
    printWeb()->actingAs(nt3Customer())->postJson(route('public.shop.print.save', $job->id), ['sheets' => 3])->assertNotFound();
});

it('makes as many sheets as the list or the subject set needs, and fits the design to the layout', function () {
    nt3OpenDesk();
    $customer = nt3Customer();
    $job = app(SavePrintJobAction::class)->start(nt3Template(), $customer->id, null, 'en');
    $save = fn (array $data) => printWeb()->actingAs($customer)->postJson(route('public.shop.print.save', $job->id), $data);

    // 25 names, one a line, on 2 × 4: four sheets.
    $names = implode("\n", array_map(fn ($i) => "Child {$i}\t3B", range(1, 25)))."\n\n";
    $save(['entry' => ['mode' => 'list', 'list' => $names]])->assertOk()->assertJsonPath('job.sheets', 4)->assertJsonPath('job.tags', 25)->assertJsonPath('job.price.total', 12);

    // Empty lines are left out and said; a name too long for its box is warned.
    $list = [['name' => 'Ali', 'class' => '1A'], ['name' => '', 'class' => ''], ['name' => str_repeat('Mohamed ', 8), 'class' => '1A']];
    $warned = $save(['entry' => ['mode' => 'list', 'list' => $list]])->assertOk()->json('job.warnings');
    expect($warned)->toContain(__('print.warn_empty_rows', ['count' => 1]))
        ->and(collect($warned)->contains(fn ($w) => str_contains($w, 'Mohamed')))->toBeTrue();

    // A subject set on a subject design: every subject with the child's name, printed in the chosen language.
    $subjectJob = app(SavePrintJobAction::class)->start(nt3Template('subject_tag'), $customer->id, null, 'en');
    $set = PrintSubjectSet::query()->firstOrFail();
    printWeb()->actingAs($customer)->postJson(route('public.shop.print.save', $subjectJob->id), ['entry' => [
        'mode' => 'same', 'row' => ['name' => 'Hawwa'], 'subjects' => ['set_id' => $set->id, 'items' => $set->subjects, 'language' => 'dv'],
    ]])->assertOk()->assertJsonPath('job.tags', count($set->subjects))->assertJsonPath('job.sheets', (int) ceil(count($set->subjects) / 8));
    // …but not on a name-tag design with no place for a subject.
    $save(['entry' => ['mode' => 'same', 'row' => ['name' => 'Hawwa'], 'subjects' => ['set_id' => $set->id, 'items' => $set->subjects]]])
        ->assertStatus(422)->assertJsonPath('errors.subjects.0', __('print.error_job_no_subject_field'));

    // A smaller label: the design scales to it.
    $label = PrintLayout::query()->where('sheet_code', 'L7160')->firstOrFail();
    $save(['layout_id' => $label->id, 'entry' => ['mode' => 'same', 'row' => ['name' => 'Ali']]])->assertOk()->assertJsonPath('job.per_sheet_tags', 21);
    $design = $job->fresh()->design;
    // MySQL 8 keeps a JSON object's keys in its own order: compare the values.
    expect($design['tag']['w'])->toBe($label->tag_w_mm)->and($design['tag']['h'])->toBe($label->tag_h_mm)
        ->and(collect($design['elements'])->every(fn ($el) => $label->tag_w_mm + 0.05 >= $el['x'] + $el['w'] && $label->tag_h_mm + 0.05 >= $el['y'] + $el['h']))->toBeTrue();

    // Too many lines, and nothing typed, are refused.
    config(['print.desk.max_rows' => 3]);
    $save(['entry' => ['mode' => 'list', 'list' => array_fill(0, 4, ['name' => 'A'])]])->assertStatus(422)->assertJsonPath('errors.list.0', __('print.error_job_too_many_rows', ['max' => 3]));
    $save(['entry' => ['mode' => 'same', 'row' => ['name' => '']]])->assertStatus(422)->assertJsonPath('errors.row.0', __('print.error_job_empty'));
});

it('proofs a job into a print file and watermarked sheet pictures, served only by a short-lived signed link to their owner', function () {
    nt3OpenDesk();
    $customer = nt3Customer();
    $job = app(SavePrintJobAction::class)->start(nt3Template(), $customer->id, null, 'dv');
    printWeb()->actingAs($customer)->postJson(route('public.shop.print.save', $job->id), ['entry' => ['mode' => 'list', 'list' => array_map(fn ($i) => ['name' => "ނަން {$i}", 'class' => '2A'], range(1, 10))], 'colour' => false])->assertOk();

    $pdf = nt0FakePdf();
    $image = nt0FakePageImage();
    app()->instance(PdfConverterInterface::class, $pdf);
    app()->instance(PageImageInterface::class, $image);
    printWeb()->actingAs($customer)->from(route('public.shop.print.job', $job->id))->post(route('public.shop.print.proof.make', $job->id))
        ->assertSessionHasNoErrors()->assertSessionHas('success', __('print.flash_proofed'));

    $job->refresh();
    expect($job->status)->toBe(PrintJobStatus::Proofed)->and($job->proof_document_ids)->toHaveCount(2)->and($job->print_document_id)->not->toBeNull()
        ->and($pdf->seen)->toHaveCount(1)->and(substr_count($pdf->seen[0], 'class="sheet grey"'))->toBe(2)
        ->and(str_contains($pdf->seen[0], 'class="watermark"'))->toBeFalse()->and(str_contains($pdf->seen[0], 'ނަން 10'))->toBeTrue()
        ->and($image->seen)->toHaveCount(2)->and(str_contains($image->seen[0][0], PrintDesk::settings()['watermark']))->toBeTrue()
        ->and($image->seen[0][1])->toBe(794)->and($image->seen[0][2])->toBe(1123);

    // The page carries signed links; the link works for its owner, not without its signature, not for anyone else.
    $links = printWeb()->actingAs($customer)->get(route('public.shop.print.job', $job->id))->assertOk()->viewData('page')['props']['job']['proof_images'];
    expect($links)->toHaveCount(2)->and($links[0])->toContain('signature=', 'expires=');
    printWeb()->actingAs($customer)->get($links[0])->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('Cache-Control', 'max-age=0, no-store, private');
    printWeb()->actingAs($customer)->get(route('public.shop.print.proof', ['job' => $job->id, 'sheet' => 1]))->assertForbidden();
    printWeb()->actingAs(nt3Customer())->get($links[0])->assertNotFound();
    $this->travel(16)->minutes();
    printWeb()->actingAs($customer)->get($links[0])->assertForbidden();
    $this->travelBack();
    // There is no sheet beyond the pictures: the print file is never one of them.
    printWeb()->actingAs($customer)->get(URL::temporarySignedRoute('public.shop.print.proof', now()->addMinutes(5), ['job' => $job->id, 'sheet' => 3]))->assertNotFound();

    // The print file is the office's alone.
    printWeb()->actingAs($customer)->get(route('admin.bookshop.print.jobs.file', $job->id))->assertForbidden();
    $file = printWeb()->actingAs(printOffice())->get(route('admin.bookshop.print.jobs.file', $job->id))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($file->getContent())->toStartWith('%PDF');
});

it('approves a proof, holds its price for seven days, and refuses any change after: a change is a copy', function () {
    [$paper] = nt3OpenDesk();
    $customer = nt3Customer();
    $template = nt3Template();
    $job = app(SavePrintJobAction::class)->start($template, $customer->id, null, 'en');
    $web = fn () => printWeb()->actingAs($customer)->from(route('public.shop.print.job', $job->id));
    printWeb()->actingAs($customer)->postJson(route('public.shop.print.save', $job->id), ['entry' => ['mode' => 'same', 'row' => ['name' => 'Ali']], 'sheets' => 2])->assertOk();

    // Approving before a proof is refused.
    $web()->post(route('public.shop.print.approve', $job->id))->assertSessionHasErrors(['job' => __('print.error_approve_unproofed')]);
    $web()->post(route('public.shop.print.proof.make', $job->id))->assertSessionHasNoErrors();

    // A change after the proof sends it back to draft — the proof no longer shows it.
    printWeb()->actingAs($customer)->postJson(route('public.shop.print.save', $job->id), ['sheets' => 3])->assertOk()->assertJsonPath('job.status', 'draft')->assertJsonPath('job.proof_images', []);
    $web()->post(route('public.shop.print.proof.make', $job->id))->assertSessionHasNoErrors();
    $web()->post(route('public.shop.print.approve', $job->id))->assertSessionHasNoErrors()->assertSessionHas('success', __('print.flash_approved'));

    $job->refresh();
    expect($job->status)->toBe(PrintJobStatus::Approved)->and($job->approved_at)->not->toBeNull()
        ->and((float) $job->approved_price_snapshot['total'])->toBe(9.0)
        ->and(abs(now()->addDays(7)->diffInMinutes(\Carbon\Carbon::parse($job->approved_price_snapshot['held_until']))))->toBeLessThan(2)
        ->and($template->fresh()->uses_count)->toBe(1);

    // Locked: every change is refused in the page's language.
    app()->setLocale('ar');
    printWeb()->actingAs($customer)->postJson(route('public.shop.print.save', $job->id), ['sheets' => 5])->assertStatus(422)->assertJsonPath('errors.job.0', __('print.error_locked', [], 'ar'));
    $web()->post(route('public.shop.print.proof.make', $job->id))->assertSessionHasErrors(['job' => __('print.error_locked', [], 'ar')]);
    $web()->post(route('public.shop.print.approve', $job->id))->assertSessionHasErrors('job');
    // An office price change later does not touch the held price.
    $paper->update(['price_colour' => 9]);
    expect((float) $job->fresh()->approved_price_snapshot['total'])->toBe(9.0);

    // *Change* makes a draft copy with everything it had.
    printWeb()->actingAs($customer)->post(route('public.shop.print.copy', $job->id))->assertRedirect();
    $copy = PrintJob::query()->latest('id')->firstOrFail();
    expect($copy->id)->not->toBe($job->id)->and($copy->status)->toBe(PrintJobStatus::Draft)->and($copy->entry['row']['name'])->toBe('Ali')
        ->and($copy->proof_document_ids)->toBeNull()->and($copy->approved_price_snapshot)->toBeNull()->and($copy->user_id)->toBe($customer->id);
});

it('refuses a proof where the host cannot print, and keeps the bare print product out of the cart', function () {
    nt3OpenDesk();
    $customer = nt3Customer();
    $job = app(SavePrintJobAction::class)->start(nt3Template(), $customer->id, null, 'en');
    printWeb()->actingAs($customer)->postJson(route('public.shop.print.save', $job->id), ['entry' => ['mode' => 'same', 'row' => ['name' => 'Ali']]])->assertOk();
    app()->instance(PdfConverterInterface::class, new class implements PdfConverterInterface
    {
        public function enabled(): bool
        {
            return false;
        }

        public function fromHtml(string $html): string
        {
            return '';
        }
    });
    printWeb()->actingAs($customer)->from(route('public.shop.print.job', $job->id))->post(route('public.shop.print.proof.make', $job->id))
        ->assertSessionHasErrors(['job' => __('print.error_proof_unavailable')]);

    PrintDesk::product()->update(['status' => 'active']);
    expect(fn () => app(SaveCartItemAction::class)->add(Cart::query()->create(['user_id' => $customer->id]), PrintDesk::PRODUCT_SLUG, null, 1))
        ->toThrow(ValidationException::class, __('print.error_cart_needs_design'));
});

it('lists My designs, renames one, and removes one no order holds', function () {
    nt3OpenDesk();
    $customer = nt3Customer();
    $save = app(SavePrintJobAction::class);
    $a = $save->start(nt3Template(), $customer->id, null, 'en');
    $b = $save->start(nt3Template('subject_tag'), $customer->id, null, 'en');
    $save->start(nt3Template(), nt3Customer()->id, null, 'en');

    printWeb()->get(route('public.shop.print.designs'))->assertRedirect();
    printWeb()->actingAs($customer)->get(route('public.shop.print.designs'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Shop/PrintDesigns')->has('designs', 2));

    printWeb()->actingAs($customer)->post(route('public.shop.print.rename', $a->id), ['name' => 'Aisha, Grade 3'])->assertSessionHasNoErrors();
    expect($a->fresh()->name)->toBe('Aisha, Grade 3');

    printWeb()->actingAs($customer)->delete(route('public.shop.print.destroy', $b->id))->assertRedirect(route('public.shop.print.designs'));
    expect(PrintJob::query()->find($b->id))->toBeNull()->and(PrintJob::withTrashed()->find($b->id))->not->toBeNull();

    $a->forceFill(['status' => PrintJobStatus::Ordered])->save();
    printWeb()->actingAs($customer)->from(route('public.shop.print.designs'))->delete(route('public.shop.print.destroy', $a->id))
        ->assertSessionHasErrors(['job' => __('print.error_job_in_order')]);
});

it('cuts a proof picture to the page, and sets every text with a face for each script', function () {
    // Chrome's window is taller than the page (its viewport is shorter than the window): the picture is cut back to A4.
    $tall = imagecreatetruecolor(794, 1363);
    ob_start();
    imagepng($tall);
    $png = (string) ob_get_clean();
    imagedestroy($tall);
    $cut = \App\Support\Services\ChromePageImage::crop($png, 794, 1123);
    [$w, $h] = getimagesizefromstring($cut);
    expect($w)->toBe(794)->and($h)->toBe(1123)
        ->and(\App\Support\Services\ChromePageImage::crop('not a png', 794, 1123))->toBe('not a png');

    // A Thaana face has no Latin: a Latin name falls to Figtree, an Arabic one to Cairo, never the browser's serif.
    expect(\App\Support\Services\PrintFontFaces::stack('noto-sans-thaana'))->toBe("'Noto Sans Thaana', 'Figtree', 'Cairo', sans-serif")
        ->and(\App\Support\Services\PrintFontFaces::stack('lora'))->toBe("'Lora', 'Figtree', 'Noto Sans Thaana', 'Cairo', sans-serif");
    $html = app(\App\Domains\Bookshop\Actions\Print\RenderPrintSheetAction::class)->execute(nt3Template()->design, PrintLayout::query()->firstOrFail(), [['name' => 'Ali']])[0];
    expect($html)->toContain('font-family: &#039;Noto Sans Thaana&#039;, &#039;Figtree&#039;');
});
