<?php

namespace App\Domains\Website\Actions;

use App\Domains\Website\Enums\FunnelEventName;
use App\Domains\Website\Models\FunnelEvent;
use Throwable;

class RecordFunnelEventAction
{
    /**
     * Lightweight conversion event. Invalid names or course ids are ignored.
     *
     * @param  array<string, mixed>  $meta
     */
    public function execute(int $courseId, string $name, string $source = 'server', array $meta = []): ?FunnelEvent
    {
        if ($courseId <= 0) {
            return null;
        }

        $event = FunnelEventName::tryFrom($name);
        if ($event === null) {
            return null;
        }

        $source = in_array($source, ['server', 'client', 'webhook'], true) ? $source : 'server';

        try {
            return FunnelEvent::query()->create([
                'course_id' => $courseId,
                'name' => $event,
                'source' => $source,
                'meta' => $meta === [] ? null : $meta,
            ]);
        } catch (Throwable) {
            return null;
        }
    }
}
