<?php

/**
 * A collision-free suffix for fixtures that write to a unique column.
 *
 * Fixtures across this suite reached for `fake()->unique()->numerify('###')`,
 * which does not do what it reads like. `fake()->unique()` returns a **fresh**
 * `UniqueGenerator` on every call, each with its own empty memory, so it
 * guarantees uniqueness only within a single call — which is to say, never.
 * What those fixtures actually had was three random digits: a thousand
 * possible values against a globally unique column.
 *
 * That is not theoretical. It reddened CI on PR #290 with
 * `Duplicate entry 'ARB101' for key 'subjects.subjects_code_unique'`, on a
 * commit whose only changes were in an unrelated domain and which passed
 * locally. The fixtures had been flaky all along; adding tests elsewhere
 * shifted the random sequence and the coin finally landed badly.
 *
 * A monotonic counter cannot collide at any seed, which is the whole point.
 * `RefreshDatabase` empties the tables between tests, so the counter only ever
 * needs to be unique within a process, and it always is.
 */
function uniqueFixtureSuffix(): string
{
    static $sequence = 0;
    $sequence++;

    return sprintf('%05d', $sequence);
}
