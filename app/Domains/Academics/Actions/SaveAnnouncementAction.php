<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Models\Announcement;
use App\Support\Html\HtmlSanitizer;

/**
 * A school notice, written once for the noticeboard (S2 announcements; the
 * portal reads it through `ListAnnouncementsForUserAction`).
 *
 * The three content columns pass through the CMS sanitiser. The React screens
 * render them as text, but the column is authored HTML by history and the
 * mobile scaffold could still render it, so what is stored is what is safe.
 */
class SaveAnnouncementAction
{
    public const TYPES = ['general', 'academic', 'quran', 'event', 'holiday', 'emergency'];

    public const PRIORITIES = ['low', 'medium', 'high', 'urgent'];

    public const AUDIENCES = ['all', 'students', 'teachers', 'parents'];

    /**
     * @param  array<string, mixed>  $data  validated
     */
    public function execute(array $data, int $authorUserId): Announcement
    {
        return Announcement::query()->create([
            'school_id' => app(ResolveDefaultSchoolIdAction::class)->execute() ?? 1,
            'created_by' => $authorUserId,
            'title' => trim((string) $data['title']),
            'title_arabic' => $this->nullable($data['title_arabic'] ?? null),
            'title_dhivehi' => $this->nullable($data['title_dhivehi'] ?? null),
            'content' => $this->clean($data['content']),
            'content_arabic' => $this->clean($data['content_arabic'] ?? null),
            'content_dhivehi' => $this->clean($data['content_dhivehi'] ?? null),
            'type' => $data['type'],
            'priority' => $data['priority'],
            // A blank audience means the whole school, not nobody
            // (AnnouncementAudienceTest); stored as given.
            'target_audience' => array_values(array_filter((array) ($data['target_audience'] ?? []))),
            'target_classes' => array_values(array_map('intval', array_filter((array) ($data['target_classes'] ?? [])))),
            'publish_date' => $data['publish_date'],
            'expiry_date' => $data['expiry_date'] ?? null,
            'is_published' => true,
        ]);
    }

    private function clean(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        return app(HtmlSanitizer::class)->clean($html, HtmlSanitizer::PROFILE_CMS);
    }

    private function nullable(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
