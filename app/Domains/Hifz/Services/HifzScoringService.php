<?php

namespace App\Domains\Hifz\Services;

use App\Enums\Hifz\HifzAttendanceStatus;
use App\Enums\Hifz\HifzLaneResult;
use App\Enums\Hifz\HifzOverallStatus;
use App\Domains\Hifz\Models\HifzSessionRecord;

class HifzScoringService
{
    public function calculateOverallStatus(HifzSessionRecord $record): ?HifzOverallStatus
    {
        if ($record->attendance_status === HifzAttendanceStatus::Absent) {
            return HifzOverallStatus::Absent;
        }

        if ($record->new_result === HifzLaneResult::NotPrepared || $record->new_result === HifzLaneResult::Repeat) {
            return HifzOverallStatus::Weak;
        }

        $mistakes = $record->mistake_count;
        $haraka = $record->haraka_mistakes;

        if ($mistakes === 0) {
            return HifzOverallStatus::Excellent;
        }

        if ($mistakes <= 2 && $haraka === 0) {
            return HifzOverallStatus::Good;
        }

        if ($mistakes <= 5 || $haraka >= 2) {
            return HifzOverallStatus::NeedsRevision;
        }

        return HifzOverallStatus::Weak;
    }

    public function applyToRecord(HifzSessionRecord $record, bool $override = false): HifzSessionRecord
    {
        if (! $override || $record->overall_status === null) {
            $record->overall_status = $this->calculateOverallStatus($record);
        }

        return $record;
    }
}
