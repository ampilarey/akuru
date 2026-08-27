<?php

namespace App\Domains\Pronunciation\Actions;

use App\Domains\Pronunciation\Models\AiModelVersion;
use App\Domains\Pronunciation\Models\AiModelVersionEvent;
use Illuminate\Support\Facades\DB;

/**
 * §51.16/§51.18: exactly one active version per model type; every
 * activation and rollback lands in the append-only audit log. Rollback is
 * an activation of an older version — same audited path.
 */
class ActivateAiModelVersionAction
{
    public function execute(int $versionId, ?int $byUserId = null, bool $isRollback = false): AiModelVersion
    {
        return DB::transaction(function () use ($versionId, $byUserId, $isRollback) {
            $version = AiModelVersion::query()->whereKey($versionId)->lockForUpdate()->firstOrFail();

            AiModelVersion::query()
                ->where('model_type', $version->model_type)
                ->whereKeyNot($version->id)
                ->update(['is_active' => false]);
            $version->is_active = true;
            $version->save();

            AiModelVersionEvent::query()->create([
                'ai_model_version_id' => $version->id,
                'action' => $isRollback ? 'rolled_back' : 'activated',
                'by_user_id' => $byUserId,
            ]);

            return $version->refresh();
        });
    }
}
