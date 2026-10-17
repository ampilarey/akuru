<?php

namespace App\Domains\Library\Console;

use App\Domains\Library\Actions\Blog\GrowBlogStudentsUpAction;
use Illuminate\Console\Command;

/**
 * BLOG_PLAN BL7 (D15): once a day, a student blogger who has turned 18
 * becomes a full writer.
 */
class GrowBlogStudentsUpCommand extends Command
{
    protected $signature = 'library:blogs-growing-up';

    protected $description = 'Make the blog of a student who has turned 18 a full writer\'s (same page, posts, followers and earnings)';

    public function handle(GrowBlogStudentsUpAction $grow): int
    {
        $this->info('Blogs moved to a writer: '.$grow->execute());

        return self::SUCCESS;
    }
}
