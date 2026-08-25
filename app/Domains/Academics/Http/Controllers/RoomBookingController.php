<?php

namespace App\Domains\Academics\Http\Controllers;

use App\Domains\Academics\Actions\SaveRoomBookingAction;
use App\Domains\Academics\Exceptions\RoomBookingClashException;
use App\Domains\Academics\Models\AcademicYear;
use App\Domains\Academics\Models\Period;
use App\Domains\Academics\Models\Room;
use App\Domains\Academics\Models\RoomBooking;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RoomBookingController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('rooms.manage'), 403);

        $yearId = $request->integer('academic_year_id') ?: (int) AcademicYear::query()
            ->where('status', 'active')
            ->value('id');

        $bookings = RoomBooking::query()
            ->when($yearId, fn ($query) => $query->where('academic_year_id', $yearId))
            ->orderBy('date')
            ->orderBy('start_time')
            ->get()
            ->map(fn (RoomBooking $booking) => $this->serialize($booking));

        return Inertia::render('Academics/Bookings/Index', [
            'yearId' => $yearId ?: null,
            'years' => AcademicYear::query()->orderByDesc('start_date')->get(['id', 'name', 'status']),
            'rooms' => Room::query()
                ->where('active', true)
                ->where('bookable', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'periods' => Period::query()->orderBy('order')->get(['id', 'name', 'start_time', 'end_time', 'is_break']),
            'bookings' => $bookings,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('rooms.manage'), 403);

        try {
            app(SaveRoomBookingAction::class)->execute(
                $this->validated($request),
                null,
                $request->user()?->id,
            );
        } catch (RoomBookingClashException $exception) {
            throw ValidationException::withMessages([
                'conflicts' => $exception->getMessage(),
            ]);
        }

        return redirect()
            ->route('academics.bookings.index', $request->only(['academic_year_id']))
            ->with('success', 'Booking created.');
    }

    public function update(Request $request, RoomBooking $roomBooking): RedirectResponse
    {
        abort_unless($request->user()?->can('rooms.manage'), 403);

        try {
            app(SaveRoomBookingAction::class)->execute(
                $this->validated($request),
                $roomBooking,
                $request->user()?->id,
            );
        } catch (RoomBookingClashException $exception) {
            throw ValidationException::withMessages([
                'conflicts' => $exception->getMessage(),
            ]);
        }

        return redirect()
            ->route('academics.bookings.index', ['academic_year_id' => $roomBooking->academic_year_id])
            ->with('success', 'Booking updated.');
    }

    public function destroy(Request $request, RoomBooking $roomBooking): RedirectResponse
    {
        abort_unless($request->user()?->can('rooms.manage'), 403);

        $yearId = $roomBooking->academic_year_id;
        $roomBooking->delete();

        return redirect()
            ->route('academics.bookings.index', ['academic_year_id' => $yearId])
            ->with('success', 'Booking removed.');
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('rooms.manage'), 403);

        $yearId = $request->integer('academic_year_id');
        $rows = RoomBooking::query()
            ->when($yearId, fn ($query) => $query->where('academic_year_id', $yearId))
            ->orderBy('date')
            ->get();

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['id', 'date', 'title', 'title_arabic', 'title_dhivehi', 'room_id', 'period_id', 'start', 'end', 'notes']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->id,
                    $row->date?->toDateString(),
                    $row->title,
                    $row->title_arabic,
                    $row->title_dhivehi,
                    $row->room_id,
                    $row->period_id,
                    $row->start_time?->format('H:i'),
                    $row->end_time?->format('H:i'),
                    $row->notes,
                ]);
            }

            fclose($handle);
        }, 'room-bookings.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'term_id' => ['nullable', 'integer', 'exists:terms,id'],
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'title' => ['required', 'string', 'max:255'],
            'title_arabic' => ['nullable', 'string', 'max:255'],
            'title_dhivehi' => ['nullable', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'period_id' => ['nullable', 'integer', 'exists:periods,id'],
            'start_time' => ['nullable', 'string'],
            'end_time' => ['nullable', 'string'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(RoomBooking $booking): array
    {
        return [
            'id' => $booking->id,
            'academic_year_id' => $booking->academic_year_id,
            'room_id' => $booking->room_id,
            'title' => $booking->title,
            'title_arabic' => $booking->title_arabic,
            'title_dhivehi' => $booking->title_dhivehi,
            'date' => $booking->date?->toDateString(),
            'period_id' => $booking->period_id,
            'start_time' => $booking->start_time?->format('H:i'),
            'end_time' => $booking->end_time?->format('H:i'),
            'notes' => $booking->notes,
        ];
    }
}
