<?php

namespace App\Domains\Portal\Http\Controllers;

use App\Domains\Academics\Actions\ListStudentWorkForGuardianAction;
use App\Domains\Academics\Actions\ReadStudentWorkPhotoAction;
use App\Domains\People\Actions\ListGuardianChildrenAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The family side of E21 — the half that makes photographing paper worth doing.
 *
 * Cross-domain by Action only (rule 3), the photo included. The children are
 * resolved here and their ids passed down, so **the scope comes from who the
 * viewer is and never from the request** — the failure mode the plan names is
 * work reaching the wrong parent, and a guardable id in a URL would be a
 * second way for that to happen.
 */
class PortalStudentWorkController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Portal/Work', [
            'work' => app(ListStudentWorkForGuardianAction::class)->execute($this->childIds($request)),
        ]);
    }

    public function photo(Request $request, int $work, ReadStudentWorkPhotoAction $read): HttpResponse
    {
        $media = $read->execute($work, $this->childIds($request));

        abort_if($media === null, 404);

        return response($media['contents'], 200, [
            'Content-Type' => $media['mime'],
            'Content-Disposition' => 'inline; filename="'.addslashes($media['original_name']).'"',
        ]);
    }

    /** @return list<int> */
    private function childIds(Request $request): array
    {
        return app(ListGuardianChildrenAction::class)
            ->executeForGuardianUserId((int) $request->user()->id)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }
}
