<?php

namespace App\Domains\Academics\Http\Controllers;

use App\Domains\Academics\Actions\ListPlanTopicsForRegisterAction;
use App\Domains\Academics\Actions\ListTeacherTodayRegistersAction;
use App\Domains\Academics\Actions\ResolveTeacherIdForUserAction;
use App\Domains\Academics\Actions\SubmitRegisterAction;
use App\Domains\Academics\Models\LessonLog;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeacherRegisterController extends Controller
{
    public function today(Request $request): Response
    {
        abort_unless($request->user()?->can('registers.fill') || $request->user()?->can('registers.manage'), 403);

        $teacherId = app(ResolveTeacherIdForUserAction::class)->execute($request->user()?->id);
        $date = $request->string('date')->toString() ?: now()->timezone(config('app.timezone'))->toDateString();

        $registers = $teacherId
            ? app(ListTeacherTodayRegistersAction::class)->execute($teacherId, $date)
            : collect();

        return Inertia::render('Academics/Registers/Today', [
            'date' => $date,
            'teacherId' => $teacherId,
            'registers' => $registers,
        ]);
    }

    public function show(Request $request, LessonLog $lessonLog): Response
    {
        $this->authorizeView($request, $lessonLog);

        return Inertia::render('Academics/Registers/Show', [
            'register' => app(ListTeacherTodayRegistersAction::class)->serialize(collect([$lessonLog]))->first(),
            'topics' => app(ListPlanTopicsForRegisterAction::class)->execute($lessonLog),
            'homework' => $lessonLog->homework,
            'materials' => is_array($lessonLog->materials) ? implode(', ', $lessonLog->materials) : '',
            'notes' => $lessonLog->notes,
            'canSubmit' => $this->canSubmit($request, $lessonLog),
        ]);
    }

    public function update(Request $request, LessonLog $lessonLog): RedirectResponse
    {
        abort_unless($request->user()?->can('registers.fill') || $request->user()?->can('registers.manage'), 403);

        app(SubmitRegisterAction::class)->execute(
            $lessonLog,
            $request->validate([
                'plan_topic_id' => ['nullable', 'integer', 'exists:plan_topics,id'],
                'taught_summary' => ['nullable', 'string', 'max:5000'],
                'homework' => ['nullable', 'string', 'max:5000'],
                'materials' => ['nullable'],
                'notes' => ['nullable', 'string', 'max:2000'],
            ]),
            (int) $request->user()->id,
            (bool) $request->user()?->can('registers.manage'),
        );

        return redirect()
            ->route('academics.registers.show', $lessonLog)
            ->with('success', 'Register submitted.');
    }

    private function authorizeView(Request $request, LessonLog $lessonLog): void
    {
        $user = $request->user();
        abort_unless($user?->can('registers.fill') || $user?->can('registers.manage'), 403);

        if ($user?->can('registers.manage')) {
            return;
        }

        $teacherId = app(ResolveTeacherIdForUserAction::class)->execute($user?->id);
        abort_unless($teacherId !== null && (int) $lessonLog->teacher_id === $teacherId, 403);
    }

    private function canSubmit(Request $request, LessonLog $lessonLog): bool
    {
        if ($lessonLog->status?->value === 'locked') {
            return false;
        }

        $user = $request->user();
        if ($user?->can('registers.manage')) {
            return true;
        }

        $teacherId = app(ResolveTeacherIdForUserAction::class)->execute($user?->id);

        return $teacherId !== null && (int) $lessonLog->teacher_id === $teacherId;
    }
}
