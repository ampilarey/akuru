<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Models\FoundItem;
use App\Domains\Media\Actions\ReadPrivateMediaAction;

/**
 * The photo of a found item that is **still listed**.
 *
 * Exists so Portal can show families a picture without importing Academics'
 * model (rule 3). It is also the narrower of the two reads by design: staff
 * can see the photo of a returned item because that is their record, while for
 * a family a returned item is simply no longer a notice on the board.
 *
 * A picture is the point of lost property — "black water bottle" describes
 * forty of them.
 *
 * @return array{contents: string, mime: string, original_name: string}|null
 */
class ReadListedFoundItemPhotoAction
{
    public function execute(int $foundItemId): ?array
    {
        $mediaId = FoundItem::query()
            ->stillHere()
            ->whereKey($foundItemId)
            ->value('photo_media_id');

        if ($mediaId === null) {
            return null;
        }

        $media = app(ReadPrivateMediaAction::class)->execute((int) $mediaId);

        if ($media === null) {
            return null;
        }

        return [
            'contents' => $media['contents'],
            'mime' => $media['mime'],
            'original_name' => $media['original_name'],
        ];
    }
}
