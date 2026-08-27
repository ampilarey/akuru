<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\Course;
use App\Domains\Media\Actions\ListPublicMediaFilesAction;
use App\Domains\Settings\Actions\GetSettingAction;

class ComposeCoursePageCtaAction
{
    /**
     * Sticky-bar / lead-magnet signals for a public course page.
     *
     * @return array{whatsapp_url: ?string, syllabus: ?array{id: int, url: string, name: string}}
     */
    public function execute(int $courseId): array
    {
        $course = Course::query()->find($courseId);
        if ($course === null) {
            return ['whatsapp_url' => null, 'syllabus' => null];
        }

        return [
            'whatsapp_url' => $this->whatsappUrl($course),
            'syllabus' => $this->syllabus($course),
        ];
    }

    private function whatsappUrl(Course $course): ?string
    {
        $settings = app(GetSettingAction::class);
        $raw = trim((string) ($course->whatsapp_number ?: ''));
        if ($raw === '') {
            $raw = trim((string) $settings->execute('conversion.whatsapp_number', ''));
        }
        if ($raw === '') {
            $raw = trim((string) $settings->execute('viber', ''));
        }

        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if (strlen($digits) < 7) {
            return null;
        }

        $text = trim((string) $course->title);
        $query = $text !== '' ? '?text='.rawurlencode($text) : '';

        return 'https://wa.me/'.$digits.$query;
    }

    /**
     * @return array{id: int, url: string, name: string}|null
     */
    private function syllabus(Course $course): ?array
    {
        $id = (int) ($course->syllabus_media_file_id ?? 0);
        if ($id <= 0) {
            return null;
        }

        $files = app(ListPublicMediaFilesAction::class)->execute([$id]);
        $file = $files[0] ?? null;
        if ($file === null) {
            return null;
        }

        return [
            'id' => $file['id'],
            'url' => $file['url'],
            'name' => $file['alt'],
        ];
    }
}
