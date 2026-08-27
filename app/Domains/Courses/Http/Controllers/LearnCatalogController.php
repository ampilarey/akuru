<?php

namespace App\Domains\Courses\Http\Controllers;

use App\Domains\Courses\Actions\ListPublishedCoursesAction;
use App\Domains\Courses\Actions\StartCourseCheckoutAction;
use App\Domains\People\Actions\ResolveStudentForUserAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LearnCatalogController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user() !== null, 403);
        $student = app(ResolveStudentForUserAction::class)->execute((int) $request->user()->id);

        return Inertia::render('Courses/Learn/Catalog', [
            'rows' => app(ListPublishedCoursesAction::class)->execute($student !== null ? $student['id'] : null),
        ]);
    }

    public function enroll(Request $request, int $course): RedirectResponse
    {
        abort_unless($request->user() !== null, 403);
        $data = $request->validate([
            'discount_code' => 'nullable|string|max:40',
            'pay_with_wallet' => 'nullable|boolean',
        ]);

        $result = app(StartCourseCheckoutAction::class)->execute(
            (int) $request->user()->id,
            $course,
            null,
            $data['discount_code'] ?? null,
            (bool) ($data['pay_with_wallet'] ?? false),
        );

        if ($result['redirect_url'] !== null) {
            return redirect()->away($result['redirect_url']);
        }
        if ($result['error'] !== null) {
            return redirect()->route('learn.catalog')->with('error', $result['error']);
        }

        return redirect()->route('learn.courses.show', $course)->with(
            'success',
            $result['paid_with_wallet'] ? 'Paid with wallet — you are enrolled.' : 'Enrolled.'
        );
    }
}
