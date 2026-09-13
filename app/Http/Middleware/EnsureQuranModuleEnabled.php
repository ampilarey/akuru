<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SPEC §52.27 "Feature Flag":
 *
 *   > The Qur'an/Hifz module should be feature-flagged.
 *   >
 *   > ```env
 *   > QURAN_HIFZ_MODULE_ENABLED=false
 *   > ```
 *   >
 *   > **The main platform must work even if the Qur'an/Hifz module is
 *   > disabled.**
 *
 * and §52.29's last acceptance criterion says the same thing again.
 *
 * **There was no flag.** `config/quran.php` held `halaqa_dual_write` and
 * `translation_source` and nothing else, and `QURAN_HIFZ_MODULE_ENABLED`
 * appeared nowhere in the codebase — so the claim "the main platform works
 * with the module disabled" could not be tested, because the module could not
 * be disabled.
 *
 * That is the more striking half, because **§51's module *is* flagged**:
 * `AI_PRONUNCIATION_ENABLED` is threaded through the Pronunciation domain and
 * the practice screen reads it. §52 asks for the same thing one section later
 * and got nothing.
 *
 * **The decision is by controller namespace, not by a list of route names.**
 * The 57 Qur'an/Hifz routes are declared inline throughout
 * `routes/web_localized.php` rather than in one group, so a name-prefix list
 * would be the very kind of hand-maintained inventory that goes stale the
 * moment somebody adds a route — the defect this session has fixed repeatedly.
 * A controller's namespace cannot drift away from the module it belongs to.
 *
 * Two controllers sit outside the component namespace for historical reasons
 * and are named explicitly; `QuranModuleRoutesAreFlaggedTest` enumerates every
 * route whose URI mentions the module and fails if one is not covered, so a
 * third outlier cannot appear unnoticed.
 */
class EnsureQuranModuleEnabled
{
    /**
     * Controllers that belong to the module but do not live under its
     * namespace. Kept explicit rather than matched by name pattern, because
     * "contains the word Quran" would also catch a controller that merely
     * reads the shared Qur'an dataset (rule 11's single source of truth),
     * which is not part of this module and must keep working when it is off.
     *
     * @var list<string>
     */
    public const EXTRA_CONTROLLERS = [
        \App\Domains\Courses\Http\Controllers\CatalogQuranOversightController::class,
        \App\Domains\Courses\Http\Controllers\TeachQuranAssignmentController::class,
    ];

    /**
     * Single **actions** that belong to the module while their controller does
     * not. Matched whole, `Controller@method`, never by prefix: matching the
     * class here would have taken the entire student directory down with the
     * module, because every other `StudentController` method starts with the
     * same string. That mistake was made and caught while writing this.
     *
     * @var list<string>
     */
    public const EXTRA_ACTIONS = [
        // The student record's Hifz progress tab renders `quran_progress`
        // rows, which are module data. The rest of the student record is not.
        \App\Domains\People\Http\Controllers\StudentController::class.'@quranProgress',
    ];

    /**
     * Routes whose URI mentions the module but which are **not** part of it —
     * examined one by one, and listed so the decision is visible rather than
     * inferred from the absence of an entry above.
     *
     * `StudentController@quranProgress` is the student record's Hifz progress
     * tab and **is** module surface, so it is in EXTRA_CONTROLLERS rather than
     * here.
     *
     * `ELearningController@quranLessons` is the legacy Academics e-learning
     * landing page. It lists *courses* whose subject happens to be Qur'an, and
     * courses are the main platform — switching the recitation module off must
     * not hide ordinary courses from a catalogue, which is precisely the
     * "the main platform must work" §52.27 is asking for.
     *
     * @var list<string>
     */
    public const EXAMINED_NOT_MODULE = [
        \App\Domains\Academics\Legacy\Http\Controllers\ELearningController::class.'@quranLessons',
    ];

    /**
     * @var list<string>
     */
    public const NAMESPACES = [
        'App\\Domains\\Courses\\Components\\Quran\\',
        'App\\Domains\\Hifz\\',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (config('quran.module_enabled')) {
            return $next($request);
        }

        // 404 rather than 403: a disabled module is not a permission problem,
        // and telling somebody "forbidden" invites them to go looking for the
        // permission that would let them in.
        abort_if(self::coversAction($request->route()?->getActionName()), 404);

        return $next($request);
    }

    public static function coversAction(?string $action): bool
    {
        if ($action === null || $action === '') {
            return false;
        }

        foreach (self::NAMESPACES as $namespace) {
            if (str_starts_with($action, $namespace)) {
                return true;
            }
        }

        foreach (self::EXTRA_CONTROLLERS as $controller) {
            if (str_starts_with($action, $controller.'@')) {
                return true;
            }
        }

        // Whole-string, not prefix — see EXTRA_ACTIONS.
        return in_array($action, self::EXTRA_ACTIONS, true);
    }
}
