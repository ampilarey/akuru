<?php

namespace App\Domains\Academics\Http\Controllers;

use App\Domains\Academics\Actions\IssueGateCardsAction;
use App\Domains\Academics\Actions\ListClassRosterAction;
use App\Domains\Academics\Actions\ListGateCardsAction;
use App\Domains\Academics\Models\AcademicYear;
use App\Domains\Academics\Models\ClassRoom;
use App\Http\Controllers\Controller;
use App\Support\Csv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * E18 gate cards: issue them per class, reissue a lost one, print a sheet.
 * Thin (rule 5).
 */
class GateCardController extends Controller
{
    public function index(Request $request, ListGateCardsAction $cards): Response
    {
        $classes = $this->classes();
        $classId = (int) $request->query('class_id', $classes[0]['id'] ?? 0);

        return Inertia::render('Academics/Gate/Cards', [
            'classes' => $classes,
            'class_id' => $classId,
            'pupils' => $classId > 0 ? $cards->execute($classId) : [],
        ]);
    }

    public function issue(Request $request, IssueGateCardsAction $issue, ListClassRosterAction $roster): RedirectResponse
    {
        $data = $request->validate(['class_id' => ['required', 'integer', 'exists:classes,id']]);

        $count = $issue->execute($roster->execute((int) $data['class_id'])->pluck('student_id')->all(), (int) $request->user()->id);

        return back()->with('success', $count === 0
            ? 'Every pupil in this class already has a card.'
            : ($count === 1 ? '1 card issued.' : "{$count} cards issued."));
    }

    public function reissue(Request $request, int $student, IssueGateCardsAction $issue): RedirectResponse
    {
        $issue->execute([$student], (int) $request->user()->id, reissue: true);

        return back()->with('success', 'New card issued. The old one no longer works.');
    }

    public function print(Request $request, ListGateCardsAction $cards): Response
    {
        $data = $request->validate(['class_id' => ['required', 'integer', 'exists:classes,id']]);
        $class = collect($this->classes())->firstWhere('id', (int) $data['class_id']);

        return Inertia::render('Academics/Gate/PrintCards', [
            'class_name' => $class['name'] ?? '',
            'pupils' => array_values(array_filter($cards->execute((int) $data['class_id']), fn (array $row) => $row['card'] !== null)),
        ]);
    }

    /** The class's card list as CSV — for a card printer, or a spare copy of the codes. */
    public function export(Request $request, ListGateCardsAction $cards): StreamedResponse
    {
        $data = $request->validate(['class_id' => ['required', 'integer', 'exists:classes,id']]);
        $rows = $cards->execute((int) $data['class_id']);

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            Csv::put($handle, ['student_id', 'student_number', 'name', 'card_code', 'readable_code', 'issued_at']);
            foreach ($rows as $row) {
                Csv::put($handle, [$row['student_id'], $row['student_number'], $row['name'], $row['card']['code'] ?? '', $row['card']['readable'] ?? '', $row['card']['issued_at'] ?? '']);
            }
            fclose($handle);
        }, 'gate-cards.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function classes(): array
    {
        $yearId = (int) AcademicYear::query()->where('status', 'active')->value('id');

        return ClassRoom::query()
            ->when($yearId > 0, fn ($query) => $query->where('academic_year_id', $yearId))
            ->orderBy('name')->orderBy('section')
            ->get(['id', 'name', 'section'])
            ->map(fn (ClassRoom $class) => ['id' => (int) $class->id, 'name' => trim($class->name.' '.($class->section ?? ''))])
            ->all();
    }
}
