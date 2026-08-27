<?php

namespace App\Domains\Website\Actions;

use App\Domains\Website\Enums\LeadSource;
use App\Domains\Website\Enums\LeadStatus;
use App\Domains\Website\Models\Lead;

class CaptureCourseLeadAction
{
    /**
     * @param  array{name: string, mobile: string, email?: ?string, notes?: ?string}  $data
     */
    public function execute(int $courseId, LeadSource $source, array $data): Lead
    {
        $lead = Lead::query()->create([
            'course_id' => $courseId,
            'name' => trim($data['name']),
            'mobile' => trim($data['mobile']),
            'email' => isset($data['email']) && trim((string) $data['email']) !== '' ? trim((string) $data['email']) : null,
            'source' => $source,
            'status' => LeadStatus::New,
            'notes' => isset($data['notes']) && trim((string) $data['notes']) !== '' ? trim((string) $data['notes']) : null,
        ]);

        if ($source === LeadSource::Syllabus) {
            app(RecordFunnelEventAction::class)->execute($courseId, 'syllabus_download');
        }

        return $lead;
    }
}
