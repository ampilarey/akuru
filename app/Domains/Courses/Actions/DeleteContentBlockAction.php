<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\ContentBlock;

class DeleteContentBlockAction
{
    public function execute(ContentBlock $block): void
    {
        $block->delete();
    }
}
