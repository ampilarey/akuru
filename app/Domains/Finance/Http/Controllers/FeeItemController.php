<?php

namespace App\Domains\Finance\Http\Controllers;

use App\Domains\Finance\Actions\ListFeeItemsAction;
use App\Domains\Finance\Actions\SaveFeeItemAction;
use App\Domains\Finance\Enums\FeeFrequency;
use App\Domains\Finance\Enums\FeeItemType;
use App\Domains\Finance\Models\FeeItem;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FeeItemController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('finance.manage'), 403);

        return Inertia::render('Finance/FeeItems/Index', [
            'items' => app(ListFeeItemsAction::class)->execute()->values(),
            'types' => array_map(fn (FeeItemType $type) => $type->value, FeeItemType::cases()),
            'frequencies' => array_map(fn (FeeFrequency $frequency) => $frequency->value, FeeFrequency::cases()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('finance.manage'), 403);

        app(SaveFeeItemAction::class)->execute($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'name_arabic' => ['nullable', 'string', 'max:255'],
            'name_dhivehi' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'default_amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'type' => ['required', 'string'],
            'frequency' => ['required', 'string'],
            'is_mandatory' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]));

        return redirect()->route('finance.fee-items.index')->with('success', 'Fee item saved.');
    }

    public function update(Request $request, FeeItem $feeItem): RedirectResponse
    {
        abort_unless($request->user()?->can('finance.manage'), 403);

        app(SaveFeeItemAction::class)->execute($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'name_arabic' => ['nullable', 'string', 'max:255'],
            'name_dhivehi' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'default_amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'type' => ['required', 'string'],
            'frequency' => ['required', 'string'],
            'is_mandatory' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]), $feeItem);

        return redirect()->route('finance.fee-items.index')->with('success', 'Fee item updated.');
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('finance.manage'), 403);

        $rows = app(ListFeeItemsAction::class)->execute();

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['name', 'amount', 'type', 'frequency', 'mandatory', 'active']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['name'],
                    $row['default_amount'],
                    $row['type'],
                    $row['frequency'],
                    $row['is_mandatory'] ? '1' : '0',
                    $row['is_active'] ? '1' : '0',
                ]);
            }
            fclose($out);
        }, 'fee-items.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
