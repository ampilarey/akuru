<?php

namespace App\Domains\People\Actions;

use App\Domains\People\Models\EmergencyContact;
use Illuminate\Support\Collection;

/**
 * Who to ring about a child.
 *
 * `emergency_contacts` shipped with the unified student schema in August and
 * has never been written or read. `StudentDirectoryController` even
 * eager-loads the relation and then drops it before serialising — the query
 * runs on every student page and the answer reaches nobody.
 *
 * Ordered by `priority`, which is the whole point of the column: contact 1 is
 * who you ring first, and a list in insertion order is a list you have to think
 * about while a child is hurt.
 */
class ListEmergencyContactsAction
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(int $studentId): Collection
    {
        return $this->serialize(
            EmergencyContact::query()
                ->where('student_id', $studentId)
                ->orderBy('priority')
                ->orderBy('id')
                ->get()
        );
    }

    /**
     * The first contact for each of many students.
     *
     * Exists so another domain can answer "who do I ring" without reaching into
     * People's table (rule 3) — arrays out, no models. Built for the absence
     * list, where the office needs the number on the row rather than one screen
     * away.
     *
     * @param  list<int>  $studentIds
     * @return Collection<int, array<string, mixed>> keyed by student id
     */
    public function firstForStudents(array $studentIds): Collection
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $studentIds))));

        if ($ids === []) {
            return collect();
        }

        return EmergencyContact::query()
            ->whereIn('student_id', $ids)
            ->orderBy('priority')
            ->orderBy('id')
            ->get()
            ->groupBy('student_id')
            ->map(fn (Collection $contacts): array => [
                ...$this->row($contacts->first()),
                // So the screen can say "and 2 more" rather than implying this
                // is the only person who can be reached.
                'others' => $contacts->count() - 1,
            ]);
    }

    /**
     * @param  Collection<int, EmergencyContact>  $contacts
     * @return Collection<int, array<string, mixed>>
     */
    private function serialize(Collection $contacts): Collection
    {
        return $contacts->map(fn (EmergencyContact $contact): array => $this->row($contact))->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function row(EmergencyContact $contact): array
    {
        return [
            'id' => (int) $contact->id,
            'student_id' => (int) $contact->student_id,
            'name' => (string) $contact->name,
            'phone' => (string) $contact->phone,
            'relationship' => $contact->relationship,
            'priority' => (int) $contact->priority,
            'notes' => $contact->notes,
        ];
    }
}
