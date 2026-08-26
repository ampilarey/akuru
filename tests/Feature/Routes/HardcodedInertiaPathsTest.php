<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

uses(RefreshDatabase::class);

it('redirects the obvious academics gradebook path to the exams gradebook', function () {
    $admin = actingPeopleAdmin(['exams.manage', 'exams.enter-any']);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get('/academics/gradebook')
        ->assertRedirect(route('exams.gradebook.index'));

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get('/academics/gradebook?class_id=6&subject_id=3&term_id=2')
        ->assertRedirect(route('exams.gradebook.index', [
            'class_id' => 6,
            'subject_id' => 3,
            'term_id' => 2,
        ]));

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('exams.gradebook.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('ExamsGrades/Gradebook/Index'));
});

it('has a registered GET route for every static Inertia href', function () {
    $missing = [];

    foreach (inertiaStaticGetPaths() as $path) {
        if (! inertiaPathResolves($path)) {
            $missing[] = $path;
        }
    }

    expect($missing)->toBeEmpty(
        'Hardcoded Inertia GET paths with no matching route (would 404):\n'.implode("\n", $missing)
    );
});

/**
 * @return list<string>
 */
function inertiaStaticGetPaths(): array
{
    $root = resource_path('js');
    $paths = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

    foreach ($iterator as $file) {
        if (! $file->isFile() || ! in_array($file->getExtension(), ['js', 'jsx'], true)) {
            continue;
        }

        $contents = file_get_contents($file->getPathname());
        if ($contents === false) {
            continue;
        }

        preg_match_all('/(?:href)\s*=\s*["\'](\/[A-Za-z][^"\'?#]*)/', $contents, $hrefs);
        preg_match_all('/(?:router\.(?:get|visit)|searchForm\.get)\(\s*["\'](\/[A-Za-z][^"\'?#]*)/', $contents, $visits);

        foreach (array_merge($hrefs[1], $visits[1]) as $path) {
            if (str_contains($path, '${') || str_contains($path, '{')) {
                continue;
            }
            if (preg_match('/\.(css|js|png|jpg|svg|woff2?|webmanifest)$/', $path)) {
                continue;
            }
            $paths[$path] = true;
        }
    }

    ksort($paths);

    return array_keys($paths);
}

function inertiaPathResolves(string $path): bool
{
    foreach ([$path, '/en'.$path] as $candidate) {
        try {
            Route::getRoutes()->match(Request::create($candidate, 'GET'));

            return true;
        } catch (MethodNotAllowedHttpException) {
            return true;
        } catch (NotFoundHttpException) {
            continue;
        }
    }

    return false;
}
