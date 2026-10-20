<?php

namespace App\Domains\Bookshop\Http\Controllers;

use App\Domains\Bookshop\Actions\Print\ListPrintTemplatesAction;
use App\Domains\Bookshop\Actions\Print\RenderPrintSheetAction;
use App\Domains\Bookshop\Actions\Print\SavePrintTemplateAction;
use App\Domains\Bookshop\Actions\Print\UploadPrintImagesAction;
use App\Domains\Bookshop\Models\PrintLayout;
use App\Domains\Bookshop\Models\PrintTemplate;
use App\Domains\Bookshop\Support\PrintDesign;
use App\Http\Controllers\Controller;
use App\Support\Csv;
use App\Support\Inertia\Phrases;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * NAME_TAGS_PLAN §4.7 (NT2): the office's templates — the list, the
 * designer in template mode, the state changes, the artwork library, and a
 * sheet of a template drawn with sample fields for the office to look at.
 */
class AdminPrintTemplateController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);

        $list = app(ListPrintTemplatesAction::class);
        $props = $list->designerProps();

        return Inertia::render('Bookshop/PrintTemplates', [
            't' => Phrases::once('print'), 'templates' => $list->execute(), 'sample' => ListPrintTemplatesAction::sampleRow(app()->getLocale()),
            'fonts' => $props['fonts'], 'font_css' => $props['font_css'], 'images' => $props['images'],
        ]);
    }

    public function designer(Request $request, ?int $template = null): Response
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);
        $list = app(ListPrintTemplatesAction::class);
        $row = $template === null ? null : PrintTemplate::query()->with('layout')->findOrFail($template);

        return Inertia::render('Bookshop/PrintTemplateDesigner', [
            't' => Phrases::once('print'),
            'template' => $row === null ? null : $list->present($row),
            'sample' => ListPrintTemplatesAction::sampleRow(app()->getLocale()),
            ...$list->designerProps(),
        ]);
    }

    public function save(Request $request, ?int $template = null): RedirectResponse
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);
        $data = $request->validate([
            'title' => 'required|string|max:120', 'title_dv' => 'nullable|string|max:120', 'title_ar' => 'nullable|string|max:120',
            'kind' => 'required|string|max:20', 'print_kind' => 'nullable|in:name_tags,cards,stickers,photos', 'print_layout_id' => 'nullable|integer', 'design' => 'required', 'sort_order' => 'nullable|integer|min:0|max:1000',
        ]);
        $saved = app(SavePrintTemplateAction::class)->execute($data, $template === null ? null : PrintTemplate::query()->findOrFail($template), (int) $request->user()->id);

        return redirect()->route('admin.bookshop.print.templates.edit', $saved->id)->with('success', __('print.flash_template_saved'));
    }

    public function status(Request $request, int $template): RedirectResponse
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);
        $data = $request->validate(['status' => 'required|string|max:20']);
        app(SavePrintTemplateAction::class)->setStatus(PrintTemplate::query()->findOrFail($template), (string) $data['status']);

        return back()->with('success', __('print.flash_template_status'));
    }

    public function uploadImages(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);
        $kilobytes = (int) config('bookshop.storefront.images.max_kilobytes', 5120);
        $data = $request->validate(['images' => 'required|array|min:1|max:12', 'images.*' => 'file|image|max:'.$kilobytes, 'alt' => 'nullable|string|max:200']);
        $ids = app(UploadPrintImagesAction::class)->execute(array_values($data['images']), $data['alt'] ?? null, (int) $request->user()->id);

        return back()->with('success', __('print.flash_images_uploaded', ['count' => count($ids)]));
    }

    /** A sheet of the template, its fields filled with samples, for the office to look at in the browser. */
    public function sheet(Request $request, int $template): HttpResponse
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);
        $row = PrintTemplate::query()->with('layout')->findOrFail($template);
        $layout = $row->layout ?? PrintLayout::query()->where('is_active', true)->orderBy('sort_order')->firstOrFail();
        $rows = array_fill(0, $layout->tagsPerSheet(), ListPrintTemplatesAction::sampleRow(app()->getLocale()));
        $html = app(RenderPrintSheetAction::class)->execute((array) $row->design, $layout, $rows, ['browser' => true, 'title' => $row->title, 'lang' => app()->getLocale()])[0];

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);
        $rows = app(ListPrintTemplatesAction::class)->execute();

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            Csv::put($out, ['title', 'title_dv', 'title_ar', 'kind', 'layout', 'status', 'fields', 'uses', 'updated']);
            foreach ($rows as $r) {
                Csv::put($out, [$r['title'], $r['title_dv'], $r['title_ar'], $r['kind'], $r['layout'], $r['status'], implode(' ', $r['fields']), $r['uses_count'], $r['updated_at']]);
            }
            fclose($out);
        }, 'bookstore-print-templates.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @internal the designer's own validation of an unsaved design, so a refusal is said before Save. */
    public function check(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);
        $design = PrintDesign::validate((array) $request->input('design', []));

        return response()->json(['ok' => true, 'fields' => PrintDesign::fieldKeys($design)]);
    }
}
