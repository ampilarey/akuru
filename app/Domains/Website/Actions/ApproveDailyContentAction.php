<?php

namespace App\Domains\Website\Actions;

use App\Domains\Website\Enums\DailyContentStatus;
use App\Domains\Website\Enums\DailyContentType;
use App\Domains\Website\Jobs\RenderDailyShareCardJob;
use App\Domains\Website\Models\DailyContent;
use Illuminate\Validation\ValidationException;

class ApproveDailyContentAction
{
    public function execute(DailyContent $row, int $approverId, DailyContentStatus $status = DailyContentStatus::Scheduled): DailyContent
    {
        if (! in_array($status, [DailyContentStatus::Scheduled, DailyContentStatus::Published], true)) {
            throw ValidationException::withMessages(['status' => 'Approval can only schedule or publish.']);
        }

        if ($row->created_by === $approverId) {
            throw ValidationException::withMessages([
                'approved_by' => 'Maker–checker: the creator cannot approve this item.',
            ]);
        }

        $this->assertPublishable($row);

        $row->approved_by = $approverId;
        $row->status = $status;
        $row->save();

        $fresh = $row->fresh() ?? $row;
        if ($fresh->status === DailyContentStatus::Published) {
            RenderDailyShareCardJob::dispatch($fresh->id);
        }

        return $fresh;
    }

    public function assertPublishable(DailyContent $row): void
    {
        $type = $row->content_type instanceof DailyContentType ? $row->content_type : DailyContentType::tryFrom((string) $row->content_type);

        if ($type === DailyContentType::Ayah && ! $row->quran_ayah_id) {
            throw ValidationException::withMessages(['quran_ayah_id' => 'An ayah item needs a Quran ayah.']);
        }

        if ($type === DailyContentType::Hadith) {
            $missing = [];
            foreach (['hadith_collection', 'hadith_number', 'hadith_grading', 'grading_source'] as $field) {
                if (trim((string) $row->{$field}) === '') {
                    $missing[] = $field;
                }
            }
            if ($missing !== []) {
                throw ValidationException::withMessages([
                    'hadith_collection' => 'Hadith cannot be published without collection, number, grading, and grading source.',
                ]);
            }
        }

        if (in_array($type, [DailyContentType::Saying, DailyContentType::Reminder], true)) {
            if (trim((string) $row->text_en) === '' || trim((string) $row->text_dv) === '' || trim((string) $row->attribution) === '') {
                throw ValidationException::withMessages([
                    'text_en' => 'Sayings and reminders need English, Dhivehi, and attribution before they can be published.',
                ]);
            }
        }
    }
}
