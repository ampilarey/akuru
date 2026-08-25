<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Media\Actions\ReadPrivateMediaAction;
use Illuminate\Contracts\Auth\Authenticatable;

class ServeCatalogMediaAction
{
    /**
     * @return array{id: int, contents: string, mime: string, original_name: string}
     */
    public function execute(int $mediaId, ?Authenticatable $user): array
    {
        abort_unless($user !== null, 403);
        abort_unless(method_exists($user, 'can') && $user->can('courses.manage'), 403);

        $file = app(ReadPrivateMediaAction::class)->execute($mediaId);
        abort_if($file === null, 404);

        return $file;
    }
}
