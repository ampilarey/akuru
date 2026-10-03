<?php

namespace App\Domains\Website\Http\Controllers\Admin\PublicSite;

use App\Domains\Courses\Actions\DeleteCourseCategoryAction;
use App\Domains\Courses\Actions\ListCourseCategoriesAction;
use App\Domains\Courses\Actions\SaveCourseCategoryAction;
use App\Http\Controllers\Controller;
use App\Support\Inertia\Phrases;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The website's course categories (BACKLOG C16 slice N2, STATUS §5nx): the
 * list, add, rename and reorder, delete when unused. `role:super_admin` on
 * the route group, like the rest of the CMS. The owner, on the live site:
 * "there is no cat" — the seeder had never run and there was no screen.
 */
class CourseCategoryController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Website/CourseCategories', [
            'categories' => app(ListCourseCategoriesAction::class)->execute(),
            't' => Phrases::once('admin'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        app(SaveCourseCategoryAction::class)->execute($request->validate($this->rules()));

        return back()->with('success', trans('admin.courses_flash_category_saved'));
    }

    public function update(Request $request, int $category): RedirectResponse
    {
        app(SaveCourseCategoryAction::class)->execute($request->validate($this->rules()), $category);

        return back()->with('success', trans('admin.courses_flash_category_saved'));
    }

    public function destroy(int $category): RedirectResponse
    {
        app(DeleteCourseCategoryAction::class)->execute($category);

        return back()->with('success', trans('admin.courses_flash_category_deleted'));
    }

    /** @return array<string, string> */
    private function rules(): array
    {
        return ['name' => 'required|string|max:120', 'slug' => 'nullable|string|max:120', 'order' => 'nullable|integer|min:0|max:1000'];
    }
}
