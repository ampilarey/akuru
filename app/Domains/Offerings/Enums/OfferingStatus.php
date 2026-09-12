<?php

namespace App\Domains\Offerings\Enums;

/**
 * SPEC §11.4 "Offering Status Workflow":
 *
 *   > Draft → Open → In Progress → Completed → Cancelled → Archived
 *   >
 *   > Draft offerings are not visible to students.
 *   > Open offerings can accept enrollment if rules allow.
 *   > In Progress offerings are currently running.
 *   > Completed offerings are finished but historical records remain.
 *   > Cancelled offerings keep historical/admin records.
 *   > Archived offerings are hidden from new enrollment.
 *   > Invalid transitions must be rejected.
 *
 * §11.4 names **six** states. This enum had four — Draft, Open, Closed,
 * Archived — so three were missing and one was invented.
 *
 * The missing three are not bookkeeping. §11.4 gives "Completed" and
 * "Cancelled" different meanings, and collapsing both into a single `Closed`
 * makes a finished cohort indistinguishable from an abandoned one: the
 * question "did these students complete the course, or was it called off?"
 * had no answer in the data. "In Progress" is likewise the only way to say a
 * cohort is running rather than still taking enrolments.
 */
enum OfferingStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /**
     * Not in §11.4, and not removed either.
     *
     * Rule 9: never drop a value that existing rows may hold. No code path
     * writes `closed` today — the case was declared and never used — but a
     * deployment's data is not something this repo can inspect, and an enum
     * case that disappears turns every row holding it into a cast error on
     * read. It stays, deprecated, and transitions out of it into the §11.4
     * vocabulary.
     *
     * @deprecated Use Completed or Cancelled, which say which one happened.
     */
    case Closed = 'closed';

    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Open => 'Open',
            self::InProgress => 'In progress',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
            self::Closed => 'Closed (legacy)',
            self::Archived => 'Archived',
        };
    }

    /**
     * Whether an offering in this state accepts new enrolment.
     *
     * §11.4: only Open "can accept enrollment if rules allow"; Archived is
     * "hidden from new enrollment", and the rest are either invisible (Draft)
     * or finished.
     */
    public function acceptsEnrolment(): bool
    {
        return $this === self::Open;
    }

    /**
     * Whether students can see the offering at all. §11.4: "Draft offerings
     * are not visible to students."
     */
    public function isVisibleToStudents(): bool
    {
        return $this !== self::Draft;
    }

    /**
     * The states this one may move to.
     *
     * Cancelled is reachable from every live state rather than only from
     * Completed: §11.4's arrow diagram reads as the happy path, and its prose
     * describes Cancelled as a thing that "keeps historical/admin records" —
     * which only makes sense for a cohort abandoned part-way. A workflow that
     * forced an admin to mark a cancelled batch "completed" first would put a
     * false record in the history §11.4 exists to protect.
     *
     * Archived is terminal, and Completed and Cancelled lead only there.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Open, self::Cancelled, self::Archived],
            self::Open => [self::InProgress, self::Cancelled, self::Archived, self::Draft],
            self::InProgress => [self::Completed, self::Cancelled],
            self::Completed => [self::Archived],
            self::Cancelled => [self::Archived],
            // A legacy row can be told apart by a human and moved into the
            // vocabulary §11.4 actually defines.
            self::Closed => [self::Completed, self::Cancelled, self::Archived],
            self::Archived => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }
}
