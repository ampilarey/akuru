<?php

use Illuminate\Support\Facades\Route as RouteFacade;

/**
 * Every route points at a controller method that exists.
 *
 * `Route::resource()` registers all seven actions whether or not the controller
 * implements them, and a route whose method is missing does not fail at boot —
 * it fails with a **500 at the moment a person clicks it**. Five routes were
 * shipped that way:
 *
 *   GET    announcements/{announcement}/edit   AnnouncementController@edit
 *   PUT    announcements/{announcement}        AnnouncementController@update
 *   DELETE announcements/{announcement}        AnnouncementController@destroy
 *   DELETE hifz/programs/{program}             HifzProgramController@destroy
 *   GET    admin/public-site/courses/{course}  CourseController@show
 *
 * The last one is the instructive one. Its registration passed a `names()`
 * array listing six actions and omitting `show`, which reads exactly like the
 * route was excluded — but omitting a name only leaves the route **unnamed**.
 * It still registered, and it still answered 500.
 *
 * Two of these were GET screens, and both had slipped past the screen guards:
 * the census could not resolve a model binding for them, so they sat in
 * `unresolvedDetailScreens()` looking like a fixture gap rather than a live
 * fault. A guard that reports "cannot test this" hides a broken screen just as
 * effectively as no guard at all — which is why this one runs on reflection
 * instead, needing no fixture, no row and no database.
 */
it('points every route at a controller method that exists', function () {
    $broken = [];

    foreach (RouteFacade::getRoutes() as $route) {
        $action = $route->getActionName();

        // Closure routes have no controller to check.
        if (! str_contains($action, '@')) {
            continue;
        }

        [$class, $method] = explode('@', $action, 2);

        if (! class_exists($class)) {
            $broken[] = sprintf('%s %s → class %s does not exist',
                implode('|', $route->methods()), $route->uri(), $class);

            continue;
        }

        if (! method_exists($class, $method)) {
            $broken[] = sprintf('%s %s → %s::%s() does not exist',
                implode('|', $route->methods()), $route->uri(), class_basename($class), $method);
        }
    }

    $broken = array_values(array_unique($broken));

    expect($broken)->toBeEmpty(
        count($broken)." route(s) point at a controller method that does not exist.\n"
        ."Each one answers 500 the moment somebody reaches it:\n  "
        .implode("\n  ", $broken)
        ."\nEither implement the method, or narrow the registration —"
        ." `Route::resource(...)->only([...])` or `->except([...])`.\n"
        ."Note that leaving an action out of `->names([...])` does NOT remove it;\n"
        ."it only leaves the route unnamed, and still registered.\n"
    );
});
