<?php

use App\Domains\Bookshop\Actions\Print\ProofPrintJobAction;
use App\Domains\Bookshop\Enums\PrintJobStatus;
use App\Domains\Bookshop\Models\PrintJob;
use App\Domains\Bookshop\Models\PrintLayout;
use App\Domains\Bookshop\Models\PrintPaperOption;
use App\Domains\Bookshop\Models\PrintTemplate;
use App\Domains\Bookshop\Support\PrintDesign;
use App\Domains\Bookshop\Support\PrintDesk;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The free-form designer for customers (NAME_TAGS_PLAN §3.3, §7.1, §11 NT6,
 * STATUS §5rt): a blank design or a template job opened in the designer,
 * the whole drawing saved and validated by the server, the template never
 * touched, a `{name}` still taking a list, and the designer kept in its own
 * lazy chunk.
 */
uses(RefreshDatabase::class);

function nt6Desk(): void
{
    PrintDesk::ensure();
    PrintPaperOption::query()->orderBy('sort_order')->orderBy('id')->firstOrFail()->update(['price_colour' => 3.00, 'price_bw' => 1.50, 'is_active' => true]);
    config(['print.desk.enabled' => true, 'print.desk.min_sheets' => 1, 'print.desk.tiers' => []]);
    printHostCanPrint();
    Storage::fake('local');
}

/** The design as the designer sends it: the job's own, with these elements. */
function nt6Drawing(PrintJob $job, array $elements): array
{
    return ['tag' => $job->design['tag'], 'background' => ['color' => '#FFFFFF'], 'palette' => $job->design['palette'], 'fields' => [['key' => 'name', 'required' => true]], 'elements' => $elements];
}

it('starts a blank design for a guest or a customer, whose {name} takes a list', function () {
    nt6Desk();
    printWeb()->post(route('public.shop.print.freeform'))->assertRedirect();
    $job = PrintJob::query()->latest('id')->firstOrFail();
    expect($job->source)->toBe('freeform')->and($job->user_id)->toBeNull()->and($job->cart_id)->not->toBeNull()
        ->and(PrintDesign::fieldKeys((array) $job->design))->toBe(['name']);

    printWeb()->get(route('public.shop.print.job', $job->id))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Shop/PrintJob')->where('job.source', 'freeform')->has('designer.images')->has('designer.field_keys')->where('designer.max_elements', PrintDesign::MAX_ELEMENTS));

    // A list of three names on an eight-tag layout: three tags, one sheet.
    printWeb()->postJson(route('public.shop.print.save', $job->id), ['entry' => ['mode' => 'list', 'list' => [['name' => 'Ali'], ['name' => 'Aisha'], ['name' => 'އިބްރާހީމް']]]])
        ->assertOk()->assertJsonPath('job.tags', 3)->assertJsonPath('job.sheets', 1);
});

it('saves the whole drawing through the server\'s validator, scaled to the layout\'s tag', function () {
    nt6Desk();
    $customer = User::factory()->create();
    printWeb()->actingAs($customer)->post(route('public.shop.print.freeform'));
    $job = PrintJob::query()->latest('id')->firstOrFail();
    $font = array_key_first((array) config('print.fonts'));
    $drawing = nt6Drawing($job, [
        ['id' => 't1', 'type' => 'text', 'x' => 5, 'y' => 5, 'w' => 60, 'h' => 12, 'rotate' => 15, 'text' => 'Hello {name}', 'field' => 'name', 'font' => $font, 'size' => 16, 'color' => '#111111'],
        ['id' => 'r1', 'type' => 'rect', 'x' => 2, 'y' => 30, 'w' => 40, 'h' => 10, 'fill' => '{primary}'],
    ]);
    printWeb()->actingAs($customer)->postJson(route('public.shop.print.save', $job->id), ['design' => $drawing])->assertOk()
        ->assertJsonPath('job.design.elements.0.rotate', 15)->assertJsonPath('job.design.elements.1.type', 'rect')->assertJsonPath('job.fields.0.key', 'name');

    // What the validator refuses, the job does not keep: a font it does not know, a picture outside Akuru's library.
    $bad = nt6Drawing($job, [['id' => 't1', 'type' => 'text', 'x' => 5, 'y' => 5, 'w' => 60, 'h' => 12, 'text' => 'x', 'font' => 'comic-sans', 'size' => 16]]);
    printWeb()->actingAs($customer)->postJson(route('public.shop.print.save', $job->id), ['design' => $bad])->assertStatus(422);
    $outside = nt6Drawing($job, [['id' => 'i1', 'type' => 'image', 'x' => 5, 'y' => 5, 'w' => 20, 'h' => 20, 'media' => 999999]]);
    printWeb()->actingAs($customer)->postJson(route('public.shop.print.save', $job->id), ['design' => $outside])->assertStatus(422);
    expect(count($job->fresh()->design['elements']))->toBe(2);

    // A drawing of another size is scaled to the layout's tag.
    $layout = PrintLayout::query()->findOrFail($job->print_layout_id);
    $big = ['tag' => ['w' => $layout->tag_w_mm * 2, 'h' => $layout->tag_h_mm * 2]] + $drawing;
    printWeb()->actingAs($customer)->postJson(route('public.shop.print.save', $job->id), ['design' => $big])->assertOk();
    expect((float) $job->fresh()->design['tag']['w'])->toBe((float) $layout->tag_w_mm);

    // And it proofs like any design.
    printWeb()->actingAs($customer)->postJson(route('public.shop.print.save', $job->id), ['entry' => ['mode' => 'same', 'row' => ['name' => 'Ali']]])->assertOk();
    app(ProofPrintJobAction::class)->proof($job->fresh(), $customer->id, 'en');
    expect($job->fresh()->status)->toBe(PrintJobStatus::Proofed);
});

it('opens a template job in the designer: its copy unlocked, the template untouched, a template job\'s drawing refused before', function () {
    nt6Desk();
    $customer = User::factory()->create();
    $template = PrintTemplate::query()->where('status', 'active')->orderBy('sort_order')->firstOrFail();
    $design = (array) $template->design;
    $design['elements'][0]['locked'] = true;
    $template->update(['design' => $design]);

    printWeb()->actingAs($customer)->post(route('public.shop.print.start'), ['template' => $template->id]);
    $job = PrintJob::query()->latest('id')->firstOrFail();
    expect($job->source)->toBe('template')->and($job->design['elements'][0]['locked'])->toBeTrue();

    // A template job's drawing is not the customer's to send until it is opened in the designer.
    printWeb()->actingAs($customer)->postJson(route('public.shop.print.save', $job->id), ['design' => nt6Drawing($job, [])])->assertStatus(422)
        ->assertJsonPath('errors.design.0', __('print.freeform_error_not_freeform'));

    printWeb()->actingAs($customer)->post(route('public.shop.print.designer', $job->id))->assertRedirect(route('public.shop.print.job', $job->id))->assertSessionHas('success', __('print.flash_freeform'));
    $job->refresh();
    expect($job->source)->toBe('freeform')->and(collect($job->design['elements'])->every(fn ($el) => $el['locked'] === false))->toBeTrue()
        ->and($template->fresh()->design['elements'][0]['locked'])->toBeTrue()->and($job->print_template_id)->toBe($template->id);

    // Someone else cannot.
    printWeb()->actingAs(User::factory()->create())->post(route('public.shop.print.designer', $job->id))->assertNotFound();

    // A layout switch now scales the customer's own drawing, not the template's.
    $moved = $job->design;
    $moved['elements'][0]['x'] = 1.0;
    printWeb()->actingAs($customer)->postJson(route('public.shop.print.save', $job->id), ['design' => $moved])->assertOk();
    $other = PrintLayout::query()->where('is_active', true)->whereKeyNot($job->print_layout_id)->orderBy('sort_order')->firstOrFail();
    printWeb()->actingAs($customer)->postJson(route('public.shop.print.save', $job->id), ['layout_id' => $other->id])->assertOk();
    expect((float) $job->fresh()->design['elements'][0]['x'])->toBeLessThan(5.0);
});

it('keeps an approved design locked, and an upload out of the designer', function () {
    nt6Desk();
    $customer = User::factory()->create();
    printWeb()->actingAs($customer)->post(route('public.shop.print.freeform'));
    $job = PrintJob::query()->latest('id')->firstOrFail();
    printWeb()->actingAs($customer)->postJson(route('public.shop.print.save', $job->id), ['entry' => ['mode' => 'same', 'row' => ['name' => 'Ali']]])->assertOk();
    $proof = app(ProofPrintJobAction::class);
    $proof->approve($proof->proof($job->fresh(), $customer->id, 'en'), 'en');
    printWeb()->actingAs($customer)->postJson(route('public.shop.print.save', $job->id), ['design' => nt6Drawing($job->fresh(), [])])->assertStatus(422)
        ->assertJsonPath('errors.job.0', __('print.error_locked'));

    printWeb()->actingAs($customer)->post(route('public.shop.print.upload.start'));
    $upload = PrintJob::query()->latest('id')->firstOrFail();
    printWeb()->actingAs($customer)->from(route('public.shop.print.job', $upload->id))->post(route('public.shop.print.designer', $upload->id))
        ->assertSessionHasErrors(['job' => __('print.freeform_error_upload')]);
});

it('keeps the designer out of every page but the ones that draw', function () {
    $manifest = json_decode((string) file_get_contents(public_path('build/manifest.json')), true);
    $designer = $manifest['resources/js/Components/Print/Designer.jsx'] ?? null;
    expect($designer)->not->toBeNull()->and($designer['isDynamicEntry'] ?? false)->toBeTrue();
    $printJob = $manifest['resources/js/Pages/Shop/PrintJob.jsx'];
    expect($printJob['imports'] ?? [])->not->toContain('resources/js/Components/Print/Designer.jsx')
        ->and($printJob['dynamicImports'] ?? [])->toContain('resources/js/Components/Print/Designer.jsx');
});
