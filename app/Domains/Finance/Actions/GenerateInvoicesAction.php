<?php

namespace App\Domains\Finance\Actions;

use App\Domains\Academics\Actions\ListClassesForYearAction;
use App\Domains\Academics\Actions\ListClassRosterAction;
use App\Domains\Finance\Enums\FeeFrequency;
use App\Domains\Finance\Enums\FeeStructureAppliesTo;
use App\Domains\Finance\Enums\InvoiceMonthlyMode;
use App\Domains\Finance\Enums\InvoiceStatus;
use App\Domains\Finance\Enums\InvoiceType;
use App\Domains\Finance\Models\FeeStructure;
use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\InvoiceGenerationLog;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GenerateInvoicesAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return Collection<int, Invoice>
     */
    public function execute(array $data): Collection
    {
        $structure = $this->resolveStructure($data);
        $createdBy = (int) ($data['created_by'] ?? 0);
        if ($createdBy < 1) {
            throw ValidationException::withMessages(['created_by' => 'Created by is required.']);
        }

        $periodStart = Carbon::parse((string) ($data['period_start'] ?? now('Indian/Maldives')->toDateString()), 'Indian/Maldives')->startOfDay();
        $periodEnd = Carbon::parse((string) ($data['period_end'] ?? $periodStart->toDateString()), 'Indian/Maldives')->startOfDay();
        if ($periodEnd->lt($periodStart)) {
            throw ValidationException::withMessages(['period_end' => 'Period end must be on or after start.']);
        }

        $settings = app(ResolveFinanceSettingsAction::class)->execute();
        $mode = isset($data['monthly_mode'])
            ? (InvoiceMonthlyMode::tryFrom((string) $data['monthly_mode']) ?? $settings['monthly_mode'])
            : $settings['monthly_mode'];

        $windows = $this->windows(
            $periodStart,
            $periodEnd,
            $mode,
            isset($data['term_id']) && $data['term_id'] !== '' && $data['term_id'] !== null ? (int) $data['term_id'] : null,
            isset($data['period_key']) && $data['period_key'] !== '' ? (string) $data['period_key'] : null,
        );

        $classIds = $this->classIds($structure, isset($data['class_id']) ? (int) $data['class_id'] : null);
        $includeOptional = (bool) ($data['include_optional'] ?? false);
        $optionalIds = array_map('intval', $data['optional_item_ids'] ?? []);

        $created = collect();
        foreach ($windows as $window) {
            foreach ($classIds as $classId) {
                foreach (app(ListClassRosterAction::class)->execute($classId) as $student) {
                    $invoice = $this->generateForStudent(
                        $structure,
                        (int) $student['student_id'],
                        (string) $student['name'],
                        $classId,
                        $window,
                        $includeOptional,
                        $optionalIds,
                        $createdBy,
                    );
                    if ($invoice !== null) {
                        $created->push($invoice);
                    }
                }
            }
        }

        return $created;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveStructure(array $data): FeeStructure
    {
        if (! empty($data['fee_structure_id'])) {
            $structure = FeeStructure::query()->with('items.feeItem')->find((int) $data['fee_structure_id']);
            if ($structure === null) {
                throw ValidationException::withMessages(['fee_structure_id' => 'Fee structure not found.']);
            }

            return $structure;
        }

        $classId = (int) ($data['class_id'] ?? 0);
        $yearId = (int) ($data['academic_year_id'] ?? 0);
        if ($classId < 1 || $yearId < 1) {
            throw ValidationException::withMessages(['fee_structure_id' => 'A structure or class + year is required.']);
        }

        $structure = FeeStructure::query()
            ->with('items.feeItem')
            ->where('academic_year_id', $yearId)
            ->where('status', 'active')
            ->get()
            ->first(function (FeeStructure $row) use ($classId) {
                if ($row->applies_to === FeeStructureAppliesTo::AllClasses) {
                    return true;
                }

                return in_array($classId, array_map('intval', $row->class_ids ?? []), true);
            });

        if ($structure === null) {
            throw ValidationException::withMessages(['class_id' => 'No active fee structure covers this class.']);
        }

        return $structure;
    }

    /**
     * @return list<int>
     */
    private function classIds(FeeStructure $structure, ?int $onlyClassId): array
    {
        $covered = $structure->applies_to === FeeStructureAppliesTo::AllClasses
            ? app(ListClassesForYearAction::class)->execute($structure->academic_year_id)->pluck('id')->map(fn ($id) => (int) $id)->all()
            : array_values(array_map('intval', $structure->class_ids ?? []));

        if ($onlyClassId) {
            if (! in_array($onlyClassId, $covered, true)) {
                throw ValidationException::withMessages(['class_id' => 'Class is not covered by this structure.']);
            }

            return [$onlyClassId];
        }

        return $covered;
    }

    /**
     * @return list<array{period_key: string, start: Carbon, end: Carbon, month_count: int, kind: string, term_id: ?int}>
     */
    private function windows(Carbon $start, Carbon $end, InvoiceMonthlyMode $mode, ?int $termId, ?string $periodKey): array
    {
        $months = [];
        foreach (CarbonPeriod::create($start->copy()->startOfMonth(), '1 month', $end->copy()->startOfMonth()) as $month) {
            $months[] = $month->copy();
        }
        $monthCount = max(1, count($months));

        if ($mode === InvoiceMonthlyMode::PerMonth && $monthCount > 1 && $periodKey === null) {
            return array_map(fn (Carbon $month) => [
                'period_key' => $month->format('Y-m'),
                'start' => $month->copy()->startOfMonth(),
                'end' => $month->copy()->endOfMonth(),
                'month_count' => 1,
                'kind' => 'month',
                'term_id' => $termId,
            ], $months);
        }

        $kind = $monthCount >= 8 ? 'annual' : ($termId || $monthCount > 1 ? 'term' : 'month');

        return [[
            'period_key' => $periodKey ?: ($termId ? 'term-'.$termId : ($kind === 'annual' ? 'year-'.$start->year : $start->format('Y-m'))),
            'start' => $start,
            'end' => $end,
            'month_count' => $monthCount,
            'kind' => $kind,
            'term_id' => $termId,
        ]];
    }

    /**
     * @param  array{period_key: string, start: Carbon, end: Carbon, month_count: int, kind: string, term_id: ?int}  $window
     * @param  list<int>  $optionalIds
     */
    private function generateForStudent(
        FeeStructure $structure,
        int $studentId,
        string $studentName,
        int $classId,
        array $window,
        bool $includeOptional,
        array $optionalIds,
        int $createdBy,
    ): ?Invoice {
        if (InvoiceGenerationLog::query()
            ->where('student_id', $studentId)
            ->where('fee_structure_id', $structure->id)
            ->where('period_key', $window['period_key'])
            ->exists()) {
            return null;
        }

        $lines = [];
        foreach ($structure->items as $item) {
            if (! $item->is_mandatory && ! $includeOptional && ! in_array((int) $item->fee_item_id, $optionalIds, true)) {
                continue;
            }
            $quantity = $this->quantityFor($item->frequency, $window);
            if ($quantity <= 0) {
                continue;
            }
            $unit = (float) $item->amount;
            $lines[] = [
                'fee_item_id' => $item->fee_item_id,
                'description' => $item->feeItem?->name ?? 'Fee',
                'quantity' => $quantity,
                'unit_price' => $unit,
                'line_total' => round($quantity * $unit, 2),
                'discount_percentage' => 0,
                'discount_amount' => 0,
            ];
        }

        if ($lines === []) {
            return null;
        }

        return DB::transaction(function () use ($structure, $studentId, $studentName, $classId, $window, $createdBy, $lines) {
            $dueDate = $this->dueDate($window, $structure->items->first()?->due_day);
            $invoice = Invoice::query()->create([
                'invoice_number' => 'INV-'.$structure->id.'-'.$studentId.'-'.$window['period_key'],
                'student_id' => $studentId,
                'academic_year_id' => $structure->academic_year_id,
                'term_id' => $window['term_id'],
                'invoice_type' => InvoiceType::SchoolFees,
                'issue_date' => now('Indian/Maldives')->toDateString(),
                'due_date' => $dueDate,
                'status' => InvoiceStatus::Draft,
                'subtotal' => 0,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'total_amount' => 0,
                'paid_amount' => 0,
                'notes' => $studentName,
                'meta' => [
                    'fee_structure_id' => $structure->id,
                    'period_key' => $window['period_key'],
                    'class_id' => $classId,
                ],
                'created_by' => $createdBy,
            ]);

            foreach ($lines as $line) {
                $invoice->lines()->create($line);
            }

            app(ApplyFeeAdjustmentsAction::class)->execute($invoice);

            InvoiceGenerationLog::query()->create([
                'student_id' => $studentId,
                'fee_structure_id' => $structure->id,
                'period_key' => $window['period_key'],
                'invoice_id' => $invoice->id,
            ]);

            return $invoice->fresh(['lines']);
        });
    }

    /**
     * @param  array{month_count: int, kind: string}  $window
     */
    private function quantityFor(?FeeFrequency $frequency, array $window): float
    {
        $frequency ??= FeeFrequency::OneTime;

        return match ($frequency) {
            FeeFrequency::Monthly => (float) $window['month_count'],
            FeeFrequency::Quarterly => $window['month_count'] >= 3 || $window['kind'] !== 'month' ? 1.0 : 0.0,
            FeeFrequency::Semester => in_array($window['kind'], ['term', 'annual'], true) ? 1.0 : 0.0,
            FeeFrequency::Annual, FeeFrequency::OneTime => $window['kind'] === 'annual' ? 1.0 : 0.0,
        };
    }

    /**
     * @param  array{end: Carbon}  $window
     */
    private function dueDate(array $window, ?int $dueDay): string
    {
        $day = $dueDay && $dueDay >= 1 && $dueDay <= 31 ? $dueDay : 15;
        $end = $window['end']->copy();

        return $end->day(min($day, $end->daysInMonth))->toDateString();
    }
}
