<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Models\StudentGateCard;
use Illuminate\Support\Facades\DB;

/**
 * Issue gate cards (E18).
 *
 * By default only pupils without a card get one, so "issue cards for the
 * class" can be pressed again after a new pupil joins without invalidating
 * every card already printed. `reissue` is for a lost card: the old one is
 * revoked in the same transaction, so there is never a moment with two
 * working cards or none.
 *
 * Tokens are 16 characters from an alphabet without look-alikes (no 0/O,
 * 1/I/L), 80 bits: printed under the QR code, a person can read one out if a
 * scan fails, and nobody can guess another pupil's.
 */
class IssueGateCardsAction
{
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    /**
     * @param  list<int>  $studentIds
     * @return int how many cards were issued
     */
    public function execute(array $studentIds, int $issuedBy, bool $reissue = false): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $studentIds))));
        if ($ids === []) {
            return 0;
        }

        return DB::transaction(function () use ($ids, $issuedBy, $reissue): int {
            $withCard = StudentGateCard::query()->active()->whereIn('student_id', $ids)->pluck('student_id')->all();

            if ($reissue && $withCard !== []) {
                StudentGateCard::query()->active()->whereIn('student_id', $ids)
                    ->update(['revoked_at' => now(), 'revoked_by' => $issuedBy, 'updated_at' => now()]);
                $withCard = [];
            }

            $issued = 0;
            foreach (array_diff($ids, $withCard) as $studentId) {
                StudentGateCard::query()->create([
                    'student_id' => $studentId,
                    'token' => $this->token(),
                    'issued_by' => $issuedBy,
                    'issued_at' => now(),
                ]);
                $issued++;
            }

            return $issued;
        });
    }

    private function token(): string
    {
        do {
            $token = '';
            for ($i = 0; $i < 16; $i++) {
                $token .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
        } while (StudentGateCard::query()->where('token', $token)->exists());

        return $token;
    }
}
