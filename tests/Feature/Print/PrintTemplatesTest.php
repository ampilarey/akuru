<?php

use App\Domains\Bookshop\Actions\Print\ListPrintTemplatesAction;
use App\Domains\Bookshop\Actions\Print\RenderPrintSheetAction;
use App\Domains\Bookshop\Actions\Print\SavePrintTemplateAction;
use App\Domains\Bookshop\Models\PrintImage;
use App\Domains\Bookshop\Models\PrintLayout;
use App\Domains\Bookshop\Models\PrintTemplate;
use App\Domains\Bookshop\Support\PrintDesign;
use App\Domains\Identity\Models\User;
use App\Domains\Media\Models\MediaFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The office's templates and the design model (NAME_TAGS_PLAN §4.7, §5.1,
 * §7.1, §11 NT2, STATUS §5rp): the five shipped designs, the validator's
 * refusals in the page's language, a template saved, moved between states
 * and never deleted, the renderer filling fields on a layout, and the
 * picture library.
 */
uses(RefreshDatabase::class);

function nt2Design(array $elements = [], array $more = []): array
{
    return $more + [
        'tag' => ['w' => 95, 'h' => 60],
        'background' => ['color' => '#FFFFFF'],
        'elements' => $elements ?: [['id' => 'name', 'type' => 'text', 'x' => 5, 'y' => 20, 'w' => 85, 'h' => 16, 'text' => '{name}', 'font' => 'noto-sans-thaana', 'size' => 22, 'weight' => 700, 'color' => '{primary}', 'field' => 'name']],
        'palette' => ['scheme' => 'ocean', 'schemes' => ['ocean' => ['primary' => '#0F4C81', 'secondary' => '#CFE8F3']]],
    ];
}

it('ships five active templates on the 2 × 4 layout, each asking for at least one field, and lists them for the office only', function () {
    expect(PrintTemplate::query()->count())->toBe(5)
        ->and(PrintTemplate::query()->where('status', 'active')->count())->toBe(5)
        ->and(PrintTemplate::query()->get()->every(fn (PrintTemplate $t) => PrintDesign::fieldKeys((array) $t->design) !== [] && $t->layout?->tagsPerSheet() === 8))->toBeTrue()
        ->and(PrintTemplate::query()->where('kind', 'subject_tag')->first()->titleIn('dv'))->toMatch('/\p{Thaana}/u');

    printWeb()->actingAs(User::factory()->create())->get(route('admin.bookshop.print.templates'))->assertForbidden();
    printWeb()->actingAs(printOffice())->get(route('admin.bookshop.print.templates'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Bookshop/PrintTemplates')->has('templates', 5)->has('fonts.noto-sans-thaana')->has('font_css')->where('sample.name', __('print.sample_name'))
            ->where('templates.0.title', 'Plain')->where('templates.0.fields', ['name', 'class']));
    printWeb()->actingAs(printOffice())->get(route('admin.bookshop.print.templates.create'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Bookshop/PrintTemplateDesigner')->where('template', null)->has('layouts', 5)->where('field_keys', PrintDesign::FIELD_KEYS)->where('max_elements', 60)->where('kinds', PrintTemplate::KINDS));
});

it('refuses a broken design in the page\'s language and normalises a sound one', function () {
    app()->setLocale('dv');
    $refuses = function (array $design, string $key, array $vars = []): void {
        try {
            PrintDesign::validate($design);
            test()->fail('validated '.$key);
        } catch (ValidationException $e) {
            expect(collect($e->errors())->flatten()->first())->toBe(__('print.'.$key, $vars, 'dv'));
        }
    };
    $refuses(nt2Design([], ['tag' => ['w' => 5, 'h' => 60]]), 'error_design_tag');
    $refuses(nt2Design(array_fill(0, 61, ['type' => 'line', 'x' => 1, 'y' => 1, 'w' => 10, 'h' => 0.5])), 'error_design_elements', ['max' => 60]);
    $refuses(nt2Design([['type' => 'star', 'x' => 1, 'y' => 1]]), 'error_design_element_type', ['n' => 1]);
    $refuses(nt2Design([['type' => 'line', 'x' => 1, 'y' => 1, 'w' => 10, 'h' => 0.5], ['type' => 'rect', 'x' => 120, 'y' => 1, 'w' => 10, 'h' => 10]]), 'error_design_off_tag', ['n' => 2]);
    $refuses(nt2Design([['type' => 'text', 'x' => 1, 'y' => 1, 'w' => 10, 'h' => 5, 'text' => 'x', 'font' => 'comic-sans']]), 'error_design_font', ['n' => 1]);
    $refuses(nt2Design([['type' => 'rect', 'x' => 1, 'y' => 1, 'w' => 10, 'h' => 10, 'fill' => 'red']]), 'error_design_colour');
    $refuses(nt2Design([['type' => 'image', 'x' => 1, 'y' => 1, 'w' => 10, 'h' => 10]]), 'error_design_image', ['n' => 1]);
    $refuses(nt2Design([['type' => 'text', 'x' => 1, 'y' => 1, 'w' => 10, 'h' => 5, 'text' => 'x', 'font' => 'lora', 'field' => '--']]), 'error_design_field', ['n' => 1]);

    $design = PrintDesign::validate(nt2Design([
        ['type' => 'text', 'x' => 1, 'y' => 1, 'w' => 50, 'h' => 10, 'text' => '{name}', 'font' => 'faruma', 'size' => 200, 'weight' => 700, 'color' => '{PRIMARY}', 'field' => 'Name', 'align' => 'top'],
        ['id' => 'same', 'type' => 'text', 'x' => 1, 'y' => 20, 'w' => 50, 'h' => 10, 'text' => '{class}', 'font' => 'lora', 'field' => 'class', 'locked' => true],
        ['id' => 'same', 'type' => 'ellipse', 'x' => 60, 'y' => 20, 'w' => 20, 'h' => 20, 'fill' => 'none', 'stroke' => '#abcdef', 'stroke_mm' => 9, 'radius' => 4],
    ], ['palette' => [], 'fields' => [['key' => 'class', 'required' => true]]]));

    expect($design['v'])->toBe(1)->and($design['tag'])->toBe(['w' => 95.0, 'h' => 60.0])
        ->and($design['elements'][0]['size'])->toBe(72)->and($design['elements'][0]['weight'])->toBe(400) // Faruma has one weight
        ->and($design['elements'][0]['color'])->toBe('{primary}')->and($design['elements'][0]['align'])->toBe('center')->and($design['elements'][0]['field'])->toBe('name')
        ->and($design['elements'][1]['locked'])->toBeTrue()
        ->and($design['elements'][2]['id'])->not->toBe('same')->and($design['elements'][2]['fill'])->toBeNull()->and($design['elements'][2]['stroke'])->toBe('#ABCDEF')->and($design['elements'][2]['stroke_mm'])->toBe(5.0)->and($design['elements'][2]['radius'])->toBe(0.0)
        ->and($design['palette']['scheme'])->toBe('maroon')
        ->and($design['fields'])->toBe([['key' => 'name', 'required' => true], ['key' => 'class', 'required' => true]])
        ->and(PrintDesign::fieldKeys($design))->toBe(['name', 'class']);
});

it('saves a template from the designer, changes it, moves it draft → active → retired, and never deletes one', function () {
    $office = printOffice();
    app()->setLocale('ar');
    $layout = PrintLayout::query()->where('sheet_code', 'L7160')->firstOrFail();
    $post = fn (array $data, ?int $id = null) => printWeb()->actingAs($office)->from(route('admin.bookshop.print.templates.create'))->post(route('admin.bookshop.print.templates.save', $id), $data);
    $meta = ['title' => 'Crest 2027', 'title_dv' => 'ކްރެސްޓް', 'title_ar' => 'شعار', 'kind' => 'name_tag', 'print_layout_id' => $layout->id, 'sort_order' => 9];

    $post($meta + ['design' => 'not json'])->assertSessionHasErrors(['design' => __('print.error_design_missing', [], 'ar')]);
    $post(['kind' => 'banner'] + $meta + ['design' => json_encode(nt2Design())])->assertSessionHasErrors(['kind' => __('print.error_template_kind', [], 'ar')]);
    $post($meta + ['design' => json_encode(nt2Design([['type' => 'rect', 'x' => 0, 'y' => 0, 'w' => 10, 'h' => 10, 'fill' => 'maroon']]))])->assertSessionHasErrors(['elements' => __('print.error_design_colour', [], 'ar')]);

    $design = nt2Design([['id' => 'band', 'type' => 'rect', 'x' => 0, 'y' => 0, 'w' => 95, 'h' => 20, 'fill' => '{primary}', 'locked' => true]]);
    $saved = $post($meta + ['design' => json_encode($design)])->assertSessionHasNoErrors()->assertSessionHas('success', __('print.flash_template_saved', [], 'ar'));
    $template = PrintTemplate::query()->where('title', 'Crest 2027')->firstOrFail();
    $saved->assertRedirect(route('admin.bookshop.print.templates.edit', $template->id));
    expect($template->status)->toBe('draft')->and($template->created_by)->toBe($office->id)->and($template->print_layout_id)->toBe($layout->id)
        ->and($template->design['elements'][0]['locked'])->toBeTrue()->and($template->design['fields'])->toBe([])->and($template->titleIn('ar'))->toBe('شعار');

    // Active needs a field: this one has none.
    $status = fn (string $s) => printWeb()->actingAs($office)->from(route('admin.bookshop.print.templates'))->post(route('admin.bookshop.print.templates.status', $template->id), ['status' => $s]);
    $status('active')->assertSessionHasErrors(['status' => __('print.error_template_no_fields', [], 'ar')]);
    $status('deleted')->assertSessionHasErrors(['status' => __('print.error_template_status', [], 'ar')]);
    expect($template->fresh()->status)->toBe('draft');

    // The designer adds a name field and saves again; then it can go active, then retired — and still exists.
    $design['elements'][] = ['id' => 'name', 'type' => 'text', 'x' => 4, 'y' => 24, 'w' => 87, 'h' => 16, 'text' => '{name}', 'font' => 'amiri', 'size' => 20, 'color' => '#FFFFFF', 'field' => 'name'];
    $post(['title' => 'Crest 2028'] + $meta + ['design' => json_encode($design)], $template->id)->assertSessionHasNoErrors();
    $template->refresh();
    expect($template->title)->toBe('Crest 2028')->and(PrintDesign::fieldKeys($template->design))->toBe(['name'])->and(PrintTemplate::query()->count())->toBe(6);
    $status('active')->assertSessionHasNoErrors()->assertSessionHas('success', __('print.flash_template_status', [], 'ar'));
    expect($template->fresh()->status)->toBe('active');
    $status('retired')->assertSessionHasNoErrors();
    expect($template->fresh()->status)->toBe('retired')->and(PrintTemplate::query()->whereKey($template->id)->exists())->toBeTrue();
    expect(collect(app('router')->getRoutes()->getRoutesByName())->keys()->filter(fn ($n) => str_starts_with($n, 'admin.bookshop.print.templates') && (str_contains($n, 'delete') || str_contains($n, 'destroy')))->all())->toBe([]);

    // The designer's own check answers JSON: a refusal, then the fields of a sound design.
    printWeb()->actingAs($office)->postJson(route('admin.bookshop.print.templates.check'), ['design' => nt2Design([], ['tag' => ['w' => 400, 'h' => 60]])])->assertStatus(422)->assertJsonValidationErrors('tag');
    printWeb()->actingAs($office)->postJson(route('admin.bookshop.print.templates.check'), ['design' => $design])->assertOk()->assertJson(['ok' => true, 'fields' => ['name']]);
});

it('renders a template on a layout, one tag per row with the fields filled and the palette resolved, and as many sheets as the rows need', function () {
    $layout = PrintLayout::query()->where('columns', 2)->where('rows', 4)->firstOrFail();
    $render = app(RenderPrintSheetAction::class);
    $rows = [];
    foreach (['އައިޝަތު ނަދާ', 'محمد أمين', 'Hawwa Zara', 'Ali Rasheed', 'Fathimath', 'Ibrahim', 'Mariyam', 'Hassan'] as $i => $name) {
        $rows[] = ['name' => $name, 'class' => 'Grade '.($i + 1), 'school' => 'Akuru', 'subject' => 'Dhivehi'];
    }

    foreach (PrintTemplate::query()->get() as $template) {
        $sheets = $render->execute((array) $template->design, $layout, $rows, ['lang' => 'dv', 'title' => $template->title]);
        expect($sheets)->toHaveCount(1, $template->title);
        $html = $sheets[0];
        expect(substr_count($html, 'class="tag"'))->toBe(8)
            ->and($html)->toContain('އައިޝަތު ނަދާ', 'محمد أمين', 'Hawwa Zara', 'lang="dv"', "font-family: 'Noto Sans Thaana'")
            ->and($html)->not->toContain('{name}', '{subject}', '{class}', '{school}', '{primary}', '{secondary}')
            ->and($html)->toContain('#7A1F2B'); // the maroon scheme's primary, from a token
    }

    // 25 rows on 8 per sheet: 4 sheets, the last with one tag; a tag that names a field the row lacks is blank there, not the token.
    $many = $render->execute((array) PrintTemplate::query()->first()->design, $layout, array_fill(0, 25, ['name' => 'Only']), []);
    expect($many)->toHaveCount(4)->and(substr_count($many[3], 'class="tag"'))->toBe(1)->and($many[0])->not->toContain('{class}')
        ->and(RenderPrintSheetAction::count($layout, 25))->toBe(['tags' => 25, 'sheets' => 4]);

    // An unfilled row still draws the shapes: the locked band stays where the design put it.
    $band = $render->execute(PrintDesign::validate(nt2Design([['id' => 'band', 'type' => 'rect', 'x' => 0, 'y' => 0, 'w' => 95, 'h' => 20, 'fill' => '{primary}', 'locked' => true]])), $layout, [[]])[0];
    expect($band)->toContain('left: 0mm; top: 0mm; width: 95mm; height: 20mm;', 'background: #0F4C81');

    // The office's sample sheet: HTML in the browser with the samples, and a refusal for a stranger.
    $office = printOffice();
    $first = PrintTemplate::query()->first();
    printWeb()->actingAs(User::factory()->create())->get(route('admin.bookshop.print.templates.sheet', $first->id))->assertForbidden();
    $page = printWeb()->actingAs($office)->get(route('admin.bookshop.print.templates.sheet', $first->id))->assertOk()->assertHeader('Content-Type', 'text/html; charset=UTF-8');
    expect($page->getContent())->toContain(__('print.sample_name'), '/fonts/print/')->and(substr_count($page->getContent(), 'class="tag"'))->toBe(8);
});

it('keeps a picture library for templates: uploads stored as public media, listed for the designer, embedded in the print file, and capped', function () {
    Storage::fake('public');
    $office = printOffice();
    app()->setLocale('dv');

    printWeb()->actingAs($office)->from(route('admin.bookshop.print.templates.create'))
        ->post(route('admin.bookshop.print.templates.images'), ['images' => [UploadedFile::fake()->image('crest.png', 200, 200), UploadedFile::fake()->image('border.jpg', 600, 100)], 'alt' => 'School crest'])
        ->assertSessionHasNoErrors()->assertSessionHas('success', __('print.flash_images_uploaded', ['count' => 2], 'dv'));
    printWeb()->actingAs($office)->post(route('admin.bookshop.print.templates.images'), ['images' => [UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')]])->assertSessionHasErrors('images.0');

    $images = PrintImage::query()->orderBy('sort_order')->get();
    expect($images)->toHaveCount(2)->and($images[0]->alt)->toBe('School crest')->and($images[0]->uploaded_by)->toBe($office->id)->and(MediaFile::query()->findOrFail($images[0]->media_file_id)->visibility)->toBe('public');
    $props = app(ListPrintTemplatesAction::class)->designerProps();
    expect($props['images'])->toHaveCount(2)->and($props['images'][0]['media'])->toBe($images[0]->media_file_id)->and($props['images'][0]['url'])->toContain('print-images/');

    // Placed on a design, the picture is read into the print file as a data: URI, and the browser's preview gets its URL.
    $layout = PrintLayout::query()->first();
    $design = PrintDesign::validate(nt2Design([['type' => 'image', 'x' => 5, 'y' => 5, 'w' => 20, 'h' => 20, 'media' => $images[0]->media_file_id, 'fit' => 'cover']]));
    $file = app(RenderPrintSheetAction::class)->execute($design, $layout, [[]])[0];
    $browser = app(RenderPrintSheetAction::class)->execute($design, $layout, [[]], ['browser' => true])[0];
    expect($file)->toContain('src="data:image/png;base64,', 'class="cover"')->and($browser)->not->toContain('data:image/png')->and($browser)->toContain('print-images/');

    // A picture that is not public media is left out, not fetched.
    MediaFile::query()->whereKey($images[1]->media_file_id)->update(['visibility' => 'private']);
    $private = PrintDesign::validate(nt2Design([['type' => 'image', 'x' => 5, 'y' => 5, 'w' => 20, 'h' => 20, 'media' => $images[1]->media_file_id]]));
    expect(app(RenderPrintSheetAction::class)->execute($private, $layout, [[]])[0])->not->toContain('<img');

    // The cap.
    $rows = [];
    for ($i = 0; $i < 119; $i++) {
        $rows[] = ['media_file_id' => $images[0]->media_file_id, 'sort_order' => $i + 3, 'created_at' => now(), 'updated_at' => now()];
    }
    PrintImage::query()->insert($rows);
    printWeb()->actingAs($office)->from(route('admin.bookshop.print.templates.create'))->post(route('admin.bookshop.print.templates.images'), ['images' => [UploadedFile::fake()->image('one.png')]])
        ->assertSessionHasErrors(['images' => __('print.error_too_many_images', ['max' => 120], 'dv')]);
});

it('exports the templates as CSV with their fields and uses', function () {
    $office = printOffice();
    app(SavePrintTemplateAction::class)->execute(['title' => 'Band', 'kind' => 'both', 'design' => nt2Design()], null, $office->id);
    $csv = printWeb()->actingAs($office)->get(route('admin.bookshop.print.templates.export'))->assertOk()->streamedContent();
    expect($csv)->toContain('title,title_dv,title_ar,kind,layout,status,fields,uses,updated', 'Plain,', 'name class', 'Band,,,both,,draft,name,0');
});
