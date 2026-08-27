<?php

namespace App\Domains\Website\Actions;

use App\Domains\Media\Contracts\MediaStorageInterface;
use App\Domains\Website\Enums\DailyContentStatus;
use App\Domains\Website\Models\DailyContent;

class StreamDailyShareCardAction
{
    public function execute(DailyContent $row): ?string
    {
        $status = $row->status instanceof DailyContentStatus ? $row->status : DailyContentStatus::tryFrom((string) $row->status);
        if ($status !== DailyContentStatus::Published) {
            return null;
        }

        if (! $row->share_card_path) {
            try {
                app(GenerateShareCardAction::class)->execute($row);
                $row->refresh();
            } catch (\Throwable $e) {
                report($e);

                return null;
            }
        }

        if (! $row->share_card_path) {
            return null;
        }

        $storage = app(MediaStorageInterface::class);
        if (! $storage->exists('public', $row->share_card_path)) {
            return null;
        }

        return $storage->get('public', $row->share_card_path);
    }
}
