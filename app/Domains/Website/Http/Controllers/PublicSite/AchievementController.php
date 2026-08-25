<?php

namespace App\Domains\Website\Http\Controllers\PublicSite;

use App\Domains\ExamsGrades\Actions\ListPublicAchievementsAction;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

class AchievementController extends Controller
{
    public function index(): View
    {
        return view('public.achievements.index', [
            'achievements' => app(ListPublicAchievementsAction::class)->execute(),
        ]);
    }
}
