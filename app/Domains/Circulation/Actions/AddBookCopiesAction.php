<?php

namespace App\Domains\Circulation\Actions;

use App\Domains\Circulation\Enums\CopyStatus;
use App\Domains\Circulation\Models\BookCopy;
use App\Domains\Circulation\Models\BookTitle;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Add copies of a title, generating accession numbers.
 *
 * Accession numbers are **allocated, never reused**: the next number continues
 * past the highest that has ever existed, including copies since withdrawn. An
 * accession number is a permanent identifier for a physical object, and
 * reissuing one makes an old loan record point at a different book.
 */
class AddBookCopiesAction
{
    private const PREFIX = 'AK';

    /**
     * @return Collection<int, BookCopy>
     */
    public function execute(int $titleId, int $howMany, ?string $shelf = null): Collection
    {
        if ($howMany < 1 || $howMany > 200) {
            throw ValidationException::withMessages([
                'how_many' => 'Add between 1 and 200 copies at a time.',
            ]);
        }

        $title = BookTitle::query()->find($titleId);

        if ($title === null) {
            throw ValidationException::withMessages(['how_many' => 'That title no longer exists.']);
        }

        return DB::transaction(function () use ($title, $howMany, $shelf): Collection {
            // Highest ever issued, across the whole collection — not a count,
            // which would repeat a number after any withdrawal.
            $highest = (int) DB::table('book_copies')
                ->where('accession_number', 'like', self::PREFIX.'%')
                ->selectRaw('max(cast(substring(accession_number, '.(strlen(self::PREFIX) + 1).') as unsigned)) as n')
                ->value('n');

            $made = collect();

            for ($i = 1; $i <= $howMany; $i++) {
                $made->push(BookCopy::query()->create([
                    'book_title_id' => (int) $title->id,
                    'accession_number' => self::PREFIX.str_pad((string) ($highest + $i), 6, '0', STR_PAD_LEFT),
                    'status' => CopyStatus::Available->value,
                    'shelf' => $shelf !== null && trim($shelf) !== '' ? trim($shelf) : null,
                ]));
            }

            return $made;
        });
    }
}
