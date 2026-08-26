<?php

namespace App\Domains\Academics\Http\Controllers;

use App\Domains\Academics\Actions\SavePeriodAction;
use App\Domains\Academics\Models\Period;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PeriodDirectoryController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('manage_timetables'), 403);

        $periods = Period::query()
            ->orderBy('order')
            ->get()
            ->map(fn (Period $period) => $this->serialize($period));

        return Inertia::render('Academics/Periods/Index', [
            'periods' => $periods,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('manage_timetables'), 403);

        app(SavePeriodAction::class)->execute($this->validated($request));

        return redirect()
            ->route('academics.periods.index')
            ->with('success', 'Period created.');
    }

    public function update(Request $request, Period $period): RedirectResponse
    {
        abort_unless($request->user()?->can('manage_timetables'), 403);

        app(SavePeriodAction::class)->execute($this->validated($request), $period);

        return redirect()
            ->route('academics.periods.index')
            ->with('success', 'Period updated.');
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('manage_timetables'), 403);

        $periods = Period::query()->orderBy('order')->get();

        return response()->streamDownload(function () use ($periods): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['id', 'name', 'start_time', 'end_time', 'order', 'is_break', 'is_active']);

            foreach ($periods as $period) {
                fputcsv($handle, [
                    $period->id,
                    $period->name,
                    $this->time($period->start_time),
                    $this->time($period->end_time),
                    $period->order,
                    $period->is_break ? '1' : '0',
                    $period->is_active ? '1' : '0',
                ]);
            }

            fclose($handle);
        }, 'periods.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'name_arabic' => ['nullable', 'string', 'max:255'],
            'name_dhivehi' => ['nullable', 'string', 'max:255'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'order' => ['required', 'integer', 'min:1'],
            'is_break' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Period $period): array
    {
        return [
            'id' => $period->id,
            'name' => $period->name,
            'name_arabic' => $period->name_arabic,
            'name_dhivehi' => $period->name_dhivehi,
            'start_time' => $this->time($period->start_time),
            'end_time' => $this->time($period->end_time),
            'order' => $period->order,
            'is_break' => $period->is_break,
            'is_active' => $period->is_active,
        ];
    }

    private function time(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('H:i');
        }

        return substr((string) $value, 0, 5);
    }
}
