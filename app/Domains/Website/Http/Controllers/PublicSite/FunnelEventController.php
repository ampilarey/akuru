<?php

namespace App\Domains\Website\Http\Controllers\PublicSite;

use App\Domains\Website\Actions\RecordFunnelEventAction;
use App\Domains\Website\Enums\FunnelEventName;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class FunnelEventController extends Controller
{
    public function store(Request $request): Response
    {
        if ($request->filled('website')) {
            return response()->noContent();
        }

        $data = $request->validate([
            'name' => ['required', 'string', Rule::in(FunnelEventName::clientAllowedValues())],
            'course_id' => ['required', 'integer', 'min:1'],
        ]);

        app(RecordFunnelEventAction::class)->execute(
            (int) $data['course_id'],
            $data['name'],
            'client',
        );

        return response()->noContent();
    }
}
