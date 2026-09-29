<?php

namespace App\Domains\Library\Actions;

use App\Domains\Library\Enums\LibraryDelivery;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Media\Actions\ReadPrivateMediaAction;

/**
 * R1 (RESEARCH_ARTICLES_PLAN D1): a reader takes the PDF itself, where its
 * author chose "download" or "both".
 *
 * The media id comes from the published item's own row, never the request,
 * and the answer goes through the one access decision
 * (`ResolveLibraryAccessAction`): a free public item to anyone, a free
 * login item to anyone signed in, a paid item only to a reader holding an
 * active grant — which only the bank's webhook, a course link or the office
 * ever creates. A book never reaches here: the protected reader is its copy
 * protection.
 *
 * A download by a signed-in reader is logged as a reading event of kind
 * `download`, so insights count it and the page-rate detector does not.
 */
class DownloadLibraryItemAction
{
    /**
     * @return array{status: 'ok', file: array{id: int, contents: string, mime: string, original_name: string}, filename: string}|array{status: 'missing'|'login'|'locked'}
     */
    public function execute(string $slug, ?int $userId, ?string $sessionId = null, ?string $ip = null, ?string $userAgent = null): array
    {
        $item = LibraryItem::query()->where('slug', $slug)->where('status', 'published')->first();
        $delivery = $item?->delivery instanceof LibraryDelivery ? $item->delivery : LibraryDelivery::Reader;
        if ($item === null
            || $item->pdf_media_file_id === null
            || ! LibraryDelivery::offeredFor($item->content_type?->value)
            || ! $delivery->allowsDownload()) {
            return ['status' => 'missing'];
        }

        $gate = app(ResolveLibraryAccessAction::class)->execute($item, $userId);
        if (! $gate['can_read']) {
            return ['status' => $userId === null ? 'login' : 'locked'];
        }

        $file = app(ReadPrivateMediaAction::class)->execute((int) $item->pdf_media_file_id);
        if ($file === null) {
            return ['status' => 'missing'];
        }

        app(RecordLibraryReadingEventAction::class)->execute($userId, (int) $item->id, 0, $sessionId, $ip, $userAgent, 'download');

        return ['status' => 'ok', 'file' => $file, 'filename' => $item->slug.'.pdf'];
    }
}
