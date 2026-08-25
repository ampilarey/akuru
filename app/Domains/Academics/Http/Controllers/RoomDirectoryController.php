<?php

namespace App\Domains\Academics\Http\Controllers;

use App\Domains\Academics\Actions\SaveRoomAction;
use App\Domains\Academics\Enums\RoomType;
use App\Domains\Academics\Models\Room;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RoomDirectoryController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('rooms.manage'), 403);

        $rooms = Room::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Room $room) => $this->serialize($room));

        return Inertia::render('Academics/Rooms/Index', [
            'rooms' => $rooms,
            'types' => array_map(fn (RoomType $type) => $type->value, RoomType::cases()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('rooms.manage'), 403);

        app(SaveRoomAction::class)->execute($this->validated($request));

        return redirect()
            ->route('academics.rooms.index')
            ->with('success', 'Room created.');
    }

    public function update(Request $request, Room $room): RedirectResponse
    {
        abort_unless($request->user()?->can('rooms.manage'), 403);

        app(SaveRoomAction::class)->execute($this->validated($request, $room->id), $room);

        return redirect()
            ->route('academics.rooms.index')
            ->with('success', 'Room updated.');
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('rooms.manage'), 403);

        $rooms = Room::query()->orderBy('name')->get();

        return response()->streamDownload(function () use ($rooms): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['id', 'name', 'name_arabic', 'name_dhivehi', 'building', 'capacity', 'type', 'bookable', 'active']);

            foreach ($rooms as $room) {
                fputcsv($handle, [
                    $room->id,
                    $room->name,
                    $room->name_arabic,
                    $room->name_dhivehi,
                    $room->building,
                    $room->capacity,
                    $room->type->value,
                    $room->bookable ? '1' : '0',
                    $room->active ? '1' : '0',
                ]);
            }

            fclose($handle);
        }, 'rooms.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?int $roomId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('rooms', 'name')->ignore($roomId)],
            'name_arabic' => ['nullable', 'string', 'max:255'],
            'name_dhivehi' => ['nullable', 'string', 'max:255'],
            'building' => ['nullable', 'string', 'max:255'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'type' => ['required', Rule::enum(RoomType::class)],
            'bookable' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Room $room): array
    {
        return [
            'id' => $room->id,
            'name' => $room->name,
            'name_arabic' => $room->name_arabic,
            'name_dhivehi' => $room->name_dhivehi,
            'building' => $room->building,
            'capacity' => $room->capacity,
            'type' => $room->type->value,
            'bookable' => $room->bookable,
            'active' => $room->active,
        ];
    }
}
