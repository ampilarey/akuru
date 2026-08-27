<?php

namespace App\Domains\Website\Actions;

use App\Domains\Courses\Actions\ComposeCourseConversionSignalsAction;
use App\Domains\Website\Enums\LeadSource;
use App\Domains\Website\Models\ContactInquiry;
use App\Domains\Website\Models\InquiryType;
use Illuminate\Validation\ValidationException;

class JoinCourseWaitlistAction
{
    /**
     * @param  array{name: string, phone: string, email?: ?string, message?: ?string, ip?: ?string, user_agent?: ?string}  $data
     */
    public function execute(int $courseId, array $data): ContactInquiry
    {
        $signals = app(ComposeCourseConversionSignalsAction::class)->execute($courseId);
        if ($signals === null || $signals['seats_tone'] !== 'full') {
            throw ValidationException::withMessages([
                'course' => 'Waiting list is only open when the course is full.',
            ]);
        }

        $type = InquiryType::query()->firstOrCreate(
            ['slug' => 'course-waitlist'],
            [
                'name' => 'Course waitlist',
                'description' => 'Interest form when a public course has no seats left.',
                'requires_phone' => true,
                'requires_subject' => false,
                'is_active' => true,
                'sort_order' => 90,
                'response_time_hours' => 48,
            ],
        );

        $inquiry = ContactInquiry::query()->create([
            'inquiry_type_id' => $type->id,
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'],
            'subject' => 'Waiting list',
            'message' => $data['message'] ?? 'Join waiting list',
            'status' => 'new',
            'priority' => 'medium',
            'ip_address' => $data['ip'] ?? null,
            'user_agent' => $data['user_agent'] ?? null,
            'meta' => [
                'source' => 'waiting_list',
                'course_id' => $courseId,
            ],
        ]);

        app(CaptureCourseLeadAction::class)->execute($courseId, LeadSource::WaitingList, [
            'name' => $data['name'],
            'mobile' => $data['phone'],
            'email' => $data['email'] ?? null,
            'notes' => $data['message'] ?? null,
        ]);

        return $inquiry;
    }
}
