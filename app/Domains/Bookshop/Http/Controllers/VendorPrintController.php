<?php

namespace App\Domains\Bookshop\Http\Controllers;

use App\Domains\Bookshop\Actions\Print\PrintProductionAction;
use App\Domains\Bookshop\Actions\Print\SavePrintFinishingAction;
use App\Domains\Bookshop\Actions\Print\SavePrintPaperAction;
use App\Domains\Bookshop\DTOs\VendorScope;
use App\Domains\Bookshop\Http\Controllers\Concerns\AuthorizesVendor;
use App\Domains\Bookshop\Models\PrintFinishingOption;
use App\Domains\Bookshop\Models\PrintJob;
use App\Domains\Bookshop\Models\PrintPaperOption;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Support\PrintSheet;
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
 * PRINT_PRODUCTS_PLAN PP7b (PD10b, PD10e, STATUS §5ui): a shop the office has
 * approved to print keeps its own print desk on `/vendor/print` — its papers
 * and finishings at its own prices, and its queue of paid print orders with
 * each print file. Thin: the rules are the desk's own Actions, scoped to the
 * shop. A shop not approved sees why, and its papers stay unused.
 */
class VendorPrintController extends Controller
{
    use AuthorizesVendor;

    public function index(Request $request): Response
    {
        $scope = $this->authorizeVendor($request);
        $all = $request->boolean('all');

        return Inertia::render('Bookshop/VendorPrint', [
            't' => Phrases::once('print'),
            'shop' => Phrases::once('shop'),
            'vendor' => ['name' => $scope->vendorName, 'slug' => $scope->vendorSlug, 'role' => $scope->role->value],
            'prints' => $this->prints($scope),
            'papers' => $this->paperRows($scope->vendorId),
            'finishing' => $this->finishingRows($scope->vendorId),
            'rows' => app(PrintProductionAction::class)->queue($all, app()->getLocale(), $scope->vendorId),
            'all' => $all,
            'kinds' => PrintPaperOption::KINDS,
            'sheet_sizes' => PrintSheet::codes(),
        ]);
    }

    public function paper(Request $request, ?int $paper = null): RedirectResponse
    {
        $scope = $this->authorizeVendor($request);
        $data = $request->validate([
            'name' => 'required|string|max:120', 'name_dv' => 'nullable|string|max:120', 'name_ar' => 'nullable|string|max:120',
            'gsm' => 'nullable|integer|min:40|max:600', 'kind' => 'required|string|max:20', 'sheet_size' => 'nullable|in:'.implode(',', PrintSheet::codes()),
            'price_bw' => 'required|numeric|min:0|max:10000', 'price_colour' => 'required|numeric|min:0|max:10000', 'is_active' => 'nullable|boolean', 'sort_order' => 'nullable|integer|min:0|max:1000',
        ]);
        $row = $paper === null ? new PrintPaperOption(['vendor_id' => $scope->vendorId]) : PrintPaperOption::query()->ofPrinter($scope->vendorId)->findOrFail($paper);
        app(SavePrintPaperAction::class)->execute($data, $row);

        return back()->with('success', __('print.flash_paper_saved'));
    }

    public function finishing(Request $request, ?int $finishing = null): RedirectResponse
    {
        $scope = $this->authorizeVendor($request);
        $data = $request->validate([
            'name' => 'required|string|max:120', 'name_dv' => 'nullable|string|max:120', 'name_ar' => 'nullable|string|max:120',
            'price' => 'required|numeric|min:0|max:10000', 'cuts_sheet' => 'nullable|boolean', 'is_active' => 'nullable|boolean', 'sort_order' => 'nullable|integer|min:0|max:1000',
        ]);
        $row = $finishing === null ? new PrintFinishingOption(['vendor_id' => $scope->vendorId]) : PrintFinishingOption::query()->ofPrinter($scope->vendorId)->findOrFail($finishing);
        app(SavePrintFinishingAction::class)->execute($data, $row);

        return back()->with('success', __('print.flash_finishing_saved'));
    }

    public function step(Request $request, int $job, string $step): RedirectResponse
    {
        $scope = $this->authorizeVendor($request);
        abort_unless(PrintProductionAction::belongsTo($job, $scope->vendorId), 404);
        $by = (int) $request->user()->id;
        $desk = app(PrintProductionAction::class);
        match ($step) {
            'start' => $desk->start($job, $by),
            'finish' => $desk->finish($job, $by),
            'reprint' => $desk->reprint($job, (string) $request->validate(['reason' => 'required|string|max:500'])['reason'], $by),
            'problem' => $desk->problem($job, (string) $request->validate(['note' => 'required|string|max:500'])['note'], $by),
        };

        return back()->with('success', __('print.flash_queue_'.$step));
    }

    public function file(Request $request, int $job): HttpResponse
    {
        $scope = $this->authorizeVendor($request);
        abort_unless(PrintProductionAction::belongsTo($job, $scope->vendorId), 404);
        $row = PrintJob::query()->withTrashed()->findOrFail($job);
        abort_if($row->print_document_id === null, 404);
        $doc = app(ReadGeneratedDocumentAction::class)->execute((int) $row->print_document_id);

        return response($doc['contents'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="print-job-'.$row->id.'.pdf"',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    /** The queue's CSV, or with `?list=papers|finishing` the shop's papers or finishings. */
    public function export(Request $request): StreamedResponse
    {
        $scope = $this->authorizeVendor($request);
        $list = (string) ($request->validate(['list' => 'nullable|in:papers,finishing'])['list'] ?? 'queue');
        [$header, $rows] = match ($list) {
            'papers' => [['name', 'name_dv', 'name_ar', 'gsm', 'kind', 'sheet_size', 'price_bw', 'price_colour', 'active'],
                array_map(fn ($p) => [$p['name'], $p['name_dv'], $p['name_ar'], $p['gsm'], $p['kind'], $p['sheet_size'], $p['price_bw'], $p['price_colour'], $p['is_active'] ? 'yes' : 'no'], $this->paperRows($scope->vendorId))],
            'finishing' => [['name', 'name_dv', 'name_ar', 'price', 'cuts_sheet', 'active'],
                array_map(fn ($f) => [$f['name'], $f['name_dv'], $f['name_ar'], $f['price'], $f['cuts_sheet'] ? 'yes' : 'no', $f['is_active'] ? 'yes' : 'no'], $this->finishingRows($scope->vendorId))],
            default => [['job', 'name', 'status', 'order', 'order_status', 'customer', 'phone', 'delivery', 'sheets', 'paper', 'colour', 'finishing', 'total', 'ordered_at', 'due', 'reprints'],
                array_map(fn ($r) => [$r['id'], $r['name'], $r['status'], $r['order'], $r['order_status'], $r['customer'], $r['phone'], $r['delivery'], $r['sheets'], $r['paper'],
                    $r['colour'] ? 'yes' : 'no', implode('; ', $r['finishing']), number_format($r['total'], 2, '.', ''), $r['ordered_at'], $r['due'], $r['reprints']],
                    app(PrintProductionAction::class)->queue($request->boolean('all'), app()->getLocale(), $scope->vendorId))],
        };

        return response()->streamDownload(function () use ($header, $rows): void {
            $out = fopen('php://output', 'w');
            Csv::put($out, $header);
            foreach ($rows as $row) {
                Csv::put($out, $row);
            }
            fclose($out);
        }, 'shop-print-'.$list.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function paperRows(int $vendorId): array
    {
        return PrintPaperOption::query()->ofPrinter($vendorId)->orderBy('sort_order')->orderBy('id')->get()->map(fn (PrintPaperOption $p) => [
            'id' => $p->id, 'name' => $p->name, 'name_dv' => $p->name_dv, 'name_ar' => $p->name_ar, 'gsm' => $p->gsm, 'kind' => $p->kind, 'sheet_size' => $p->sheet_size,
            'price_bw' => (string) $p->price_bw, 'price_colour' => (string) $p->price_colour, 'is_active' => $p->is_active, 'sort_order' => $p->sort_order,
        ])->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function finishingRows(int $vendorId): array
    {
        return PrintFinishingOption::query()->ofPrinter($vendorId)->orderBy('sort_order')->orderBy('id')->get()->map(fn (PrintFinishingOption $f) => [
            'id' => $f->id, 'name' => $f->name, 'name_dv' => $f->name_dv, 'name_ar' => $f->name_ar, 'price' => (string) $f->price, 'cuts_sheet' => $f->cuts_sheet, 'is_active' => $f->is_active, 'sort_order' => $f->sort_order,
        ])->values()->all();
    }

    private function prints(VendorScope $scope): bool
    {
        return (bool) Vendor::query()->whereKey($scope->vendorId)->value('print_enabled');
    }
}
