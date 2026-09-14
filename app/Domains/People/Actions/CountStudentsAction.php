<?php

namespace App\Domains\People\Actions;

use App\Domains\People\Models\Student;

/**
 * How many students — asked two different ways, because "students" is one word
 * for two numbers and they drift apart the moment anybody graduates.
 *
 * `execute()` used to be the only answer: every row in the table, whatever its
 * status. A supervisor dashboard headed **Students** showed it, and so did the
 * §33 catalog report under a comment reading *"this is the roll of the
 * institute"* — which it was not. Both counted `graduated`, `transferred` and
 * `withdrawn` pupils as if they were still here, and neither said so.
 *
 * The two questions are both legitimate, which is why this is a distinction
 * rather than a fix:
 *
 *  - **On the roll** — who is here now. What an operational screen means by
 *    "Students", and the number that is wrong if a leaver is in it.
 *  - **Ever enrolled** — who the Institute has taught. A cumulative claim, and
 *    the right one for the homepage's "students taught", where counting only
 *    today's roll would *undersell* every year that has finished.
 *
 * Which statuses count as "on the roll" is defined once, on
 * `Student::scopeOnTheRoll()`, so this count and every filter that decides
 * whether somebody is still a pupil cannot drift apart (rule 11).
 */
class CountStudentsAction
{
    /**
     * Students currently on the roll.
     */
    public function onTheRoll(): int
    {
        return Student::query()->onTheRoll()->count();
    }

    /**
     * Every student the Institute has ever had on its books.
     */
    public function everEnrolled(): int
    {
        return Student::query()->count();
    }
}
