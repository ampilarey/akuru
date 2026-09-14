<?php

namespace App\Domains\Academics\Http\Controllers;

use App\Domains\Academics\Actions\ActivateAcademicYearAction;
use App\Domains\Academics\Actions\CloseAcademicYearAction;
use App\Domains\Academics\Enums\AcademicYearStatus;
use App\Domains\Academics\Enums\TermStatus;
use App\Domains\Academics\Models\AcademicYear;
use App\Domains\Academics\Models\Term;
use App\Http\Controllers\Controller;
use App\Support\Csv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AcademicYearController extends Controller
{
    /**
     * CLAUDE.md: *"every listing gets CSV export."* The backbone every
     * time-scoped record in the system hangs off (rule 10), with each year's
     * terms flattened one per row — a year with no terms still appears, since
     * an empty year is exactly the thing somebody would be checking for.
     */
    public function export(): StreamedResponse
    {
        $years = AcademicYear::query()
            ->with('termRecords')
            ->orderByDesc('start_date')
            ->get();

        return response()->streamDownload(function () use ($years): void {
            $handle = fopen('php://output', 'w');
            Csv::put($handle, ['year_id', 'year', 'year_status', 'year_start', 'year_end', 'term', 'term_status', 'term_start', 'term_end']);

            foreach ($years as $year) {
                $terms = $year->termRecords;

                // `->value` on both: these columns are backed enums, and
                // handing an enum object to fputcsv is a fatal error *inside*
                // the stream, which arrives as a truncated download rather
                // than an error page. Caught by the test asserting the body.
                if ($terms->isEmpty()) {
                    Csv::put($handle, [$year->id, $year->name, $year->status?->value, $year->start_date?->toDateString(), $year->end_date?->toDateString(), '', '', '', '']);

                    continue;
                }

                foreach ($terms as $term) {
                    Csv::put($handle, [
                        $year->id, $year->name, $year->status?->value,
                        $year->start_date?->toDateString(), $year->end_date?->toDateString(),
                        $term->name, $term->status?->value,
                        $term->start_date?->toDateString(), $term->end_date?->toDateString(),
                    ]);
                }
            }

            fclose($handle);
        }, 'academic-years.csv', ['Content-Type' => 'text/csv']);
    }

    public function index(): Response
    {
        return Inertia::render('Academics/Years/Index', [
            'years' => AcademicYear::query()
                ->with('termRecords')
                ->orderByDesc('start_date')
                ->get()
                ->map(fn (AcademicYear $year) => $this->serializeYear($year)),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:academic_years,name'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'description' => ['nullable', 'string'],
        ]);

        AcademicYear::query()->create($data + [
            'status' => AcademicYearStatus::Upcoming,
            'is_current' => false,
        ]);

        return redirect()->route('academics.years.index')->with('success', 'Academic year created.');
    }

    public function storeTerm(Request $request, AcademicYear $academicYear): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $academicYear->termRecords()->create($data + [
            'status' => TermStatus::Upcoming,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        return redirect()->route('academics.years.index')->with('success', 'Term created.');
    }

    public function closeTerm(AcademicYear $academicYear, Term $term): RedirectResponse
    {
        abort_unless($term->academic_year_id === $academicYear->id, 404);
        $term->forceFill(['status' => TermStatus::Closed])->save();

        return redirect()->route('academics.years.index')->with('success', 'Term closed.');
    }

    public function activate(AcademicYear $academicYear): RedirectResponse
    {
        try {
            app(ActivateAcademicYearAction::class)->execute($academicYear);
        } catch (RuntimeException $exception) {
            return redirect()->route('academics.years.index')->with('success', $exception->getMessage());
        }

        return redirect()->route('academics.years.index')->with('success', 'Academic year activated.');
    }

    public function close(AcademicYear $academicYear): RedirectResponse
    {
        try {
            app(CloseAcademicYearAction::class)->execute($academicYear);
        } catch (RuntimeException $exception) {
            return redirect()->route('academics.years.index')->with('success', $exception->getMessage());
        }

        return redirect()->route('academics.years.index')->with('success', 'Academic year closed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeYear(AcademicYear $year): array
    {
        return [
            'id' => $year->id,
            'name' => $year->name,
            'start_date' => $year->start_date?->toDateString(),
            'end_date' => $year->end_date?->toDateString(),
            'status' => $year->status?->value,
            'is_current' => $year->is_current,
            'terms' => $year->termRecords->map(fn (Term $term) => [
                'id' => $term->id,
                'name' => $term->name,
                'start_date' => $term->start_date?->toDateString(),
                'end_date' => $term->end_date?->toDateString(),
                'status' => $term->status?->value,
            ]),
        ];
    }
}
