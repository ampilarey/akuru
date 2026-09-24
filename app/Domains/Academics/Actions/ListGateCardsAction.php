<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Models\StudentGateCard;

/**
 * A class's pupils and their gate cards (E18): the office screen lists them,
 * the print sheet draws them. The QR itself is drawn in the browser from
 * `code` (the `qrcode` package), as SVG, so it prints sharp; no server-side
 * image library is needed on the host.
 */
class ListGateCardsAction
{
    public function __construct(private ListClassRosterAction $roster) {}

    /**
     * @return list<array{student_id: int, name: string, student_number: ?string, card: ?array{code: string, readable: string, issued_at: string}}>
     */
    public function execute(int $classId): array
    {
        $roster = $this->roster->execute($classId)->sortBy('name')->values();
        $cards = StudentGateCard::query()->active()->whereIn('student_id', $roster->pluck('student_id'))->get()->keyBy('student_id');

        return $roster->map(function (array $row) use ($cards): array {
            $card = $cards->get($row['student_id']);

            return [
                'student_id' => (int) $row['student_id'],
                'name' => $row['name'],
                'student_number' => $row['student_number'],
                'card' => $card === null ? null : [
                    'code' => $card->code(),
                    'readable' => $card->readable(),
                    'issued_at' => $card->issued_at->toDateString(),
                ],
            ];
        })->all();
    }
}
