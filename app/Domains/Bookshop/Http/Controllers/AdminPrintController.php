<?php

namespace App\Domains\Bookshop\Http\Controllers;

use App\Domains\Bookshop\Actions\Print\ListPrintDeskAction;
use App\Domains\Bookshop\Actions\Print\SavePrintFinishingAction;
use App\Domains\Bookshop\Actions\Print\SavePrintLayoutAction;
use App\Domains\Bookshop\Actions\Print\SavePrintPaperAction;
use App\Domains\Bookshop\Actions\Print\SavePrintSettingsAction;
use App\Domains\Bookshop\Actions\Print\SavePrintSubjectSetAction;
use App\Domains\Bookshop\Models\PrintFinishingOption;
use App\Domains\Bookshop\Models\PrintJob;
use App\Domains\Bookshop\Models\PrintLayout;
use App\Domains\Bookshop\Models\PrintPaperOption;
use App\Domains\Bookshop\Models\PrintSubjectSet;
use App\Domains\Media\Actions\ReadGeneratedDocumentAction;
use App\Http\Controllers\Controller;
use App\Support\Csv;
use App\Support\Inertia\Phrases;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * NAME_TAGS_PLAN §4 (NT1): the print desk's office — its settings and the
 * four lists it prices and prints from. `/admin/bookshop/print`.
 */
class AdminPrintController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);

        return Inertia::render('Bookshop/Print', ['t' => Phrases::once('print'), ...app(ListPrintDeskAction::class)->execute()]);
    }

    public function settings(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);
        $data = $request->validate([
            'enabled' => 'required|boolean', 'lead_days' => 'required|integer|min:0|max:60', 'min_sheets' => 'required|integer|min:1|max:1000',
            'tiers' => 'nullable|array|max:10', 'tiers.*.sheets' => 'required|integer|min:2|max:100000', 'tiers.*.percent' => 'required|numeric|min:0.01|max:99',
            'cash_allowed' => 'nullable|boolean', 'watermark' => 'nullable|string|max:60', 'max_rows' => 'nullable|integer|min:1|max:5000', 'max_upload_kb' => 'nullable|integer|min:256|max:51200',
        ]);
        app(SavePrintSettingsAction::class)->execute($data);

        return back()->with('success', __('print.flash_settings_saved'));
    }

    public function paper(Request $request, ?int $paper = null): RedirectResponse
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);
        $data = $request->validate([
            'name' => 'required|string|max:120', 'name_dv' => 'nullable|string|max:120', 'name_ar' => 'nullable|string|max:120',
            'gsm' => 'nullable|integer|min:40|max:600', 'kind' => 'required|string|max:20',
            'price_bw' => 'required|numeric|min:0|max:10000', 'price_colour' => 'required|numeric|min:0|max:10000', 'is_active' => 'nullable|boolean', 'sort_order' => 'nullable|integer|min:0|max:1000',
        ]);
        app(SavePrintPaperAction::class)->execute($data, $paper === null ? null : PrintPaperOption::query()->findOrFail($paper));

        return back()->with('success', __('print.flash_paper_saved'));
    }

    public function finishing(Request $request, ?int $finishing = null): RedirectResponse
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);
        $data = $request->validate([
            'name' => 'required|string|max:120', 'name_dv' => 'nullable|string|max:120', 'name_ar' => 'nullable|string|max:120',
            'price' => 'required|numeric|min:0|max:10000', 'cuts_sheet' => 'nullable|boolean', 'is_active' => 'nullable|boolean', 'sort_order' => 'nullable|integer|min:0|max:1000',
        ]);
        app(SavePrintFinishingAction::class)->execute($data, $finishing === null ? null : PrintFinishingOption::query()->findOrFail($finishing));

        return back()->with('success', __('print.flash_finishing_saved'));
    }

    public function layout(Request $request, ?int $layout = null): RedirectResponse
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);
        $data = $request->validate([
            'name' => 'required|string|max:80', 'columns' => 'required|integer|min:1|max:20', 'rows' => 'required|integer|min:1|max:30',
            'tag_w_mm' => 'required|numeric|min:1|max:210', 'tag_h_mm' => 'required|numeric|min:1|max:297',
            'margin_top_mm' => 'nullable|numeric|min:0|max:100', 'margin_left_mm' => 'nullable|numeric|min:0|max:100',
            'gutter_x_mm' => 'nullable|numeric|min:0|max:50', 'gutter_y_mm' => 'nullable|numeric|min:0|max:50',
            'sheet_code' => 'nullable|string|max:40', 'is_active' => 'nullable|boolean', 'sort_order' => 'nullable|integer|min:0|max:1000',
        ]);
        app(SavePrintLayoutAction::class)->execute($data, $layout === null ? null : PrintLayout::query()->findOrFail($layout));

        return back()->with('success', __('print.flash_layout_saved'));
    }

    public function subjectSet(Request $request, ?int $set = null): RedirectResponse
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);
        $data = $request->validate([
            'label' => 'required|string|max:120', 'label_dv' => 'nullable|string|max:120', 'label_ar' => 'nullable|string|max:120',
            'subjects_en' => 'required|string|max:4000', 'subjects_dv' => 'required|string|max:4000', 'subjects_ar' => 'required|string|max:4000',
            'is_active' => 'nullable|boolean', 'sort_order' => 'nullable|integer|min:0|max:1000',
        ]);
        app(SavePrintSubjectSetAction::class)->execute($data, $set === null ? null : PrintSubjectSet::query()->findOrFail($set));

        return back()->with('success', __('print.flash_subject_set_saved'));
    }

    /** Every listing gets a CSV (conventions): one of the desk's four lists. */
    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);
        $list = (string) $request->validate(['list' => 'required|string|in:papers,finishing,layouts,subject_sets'])['list'];
        [$header, $rows] = app(ListPrintDeskAction::class)->csv($list);

        return response()->streamDownload(function () use ($header, $rows): void {
            $out = fopen('php://output', 'w');
            Csv::put($out, $header);
            foreach ($rows as $row) {
                Csv::put($out, $row);
            }
            fclose($out);
        }, 'bookstore-print-'.str_replace('_', '-', $list).'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * NAME_TAGS_PLAN §3.7, decision D6 (NT3): a job's print-ready PDF — the
     * office's alone. No customer route returns it.
     */
    public function jobFile(Request $request, int $job): HttpResponse
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);
        $row = PrintJob::query()->withTrashed()->findOrFail($job);
        abort_if($row->print_document_id === null, 404);
        $doc = app(ReadGeneratedDocumentAction::class)->execute((int) $row->print_document_id);

        return response($doc['contents'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="print-job-'.$row->id.'.pdf"',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }
}
