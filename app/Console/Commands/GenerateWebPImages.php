<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\Event;
use App\Models\GalleryItem;
use App\Models\Page;
use App\Models\Post;
use App\Services\WebPImageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class GenerateWebPImages extends Command
{
    protected $signature = 'images:generate-webp {--force : Regenerate even if WebP already exists}';

    protected $description = 'Generate WebP versions of all images (courses, posts, events, gallery, pages)';

    public function handle(WebPImageService $service): int
    {
        $this->info('Generating WebP images...');
        $force = $this->option('force');

        $paths = collect();

        // Courses
        Course::whereNotNull('cover_image')->pluck('cover_image')->each(fn ($p) => $paths->push($p));

        // Posts
        Post::whereNotNull('cover_image')->pluck('cover_image')->each(fn ($p) => $paths->push($p));

        // Events
        Event::whereNotNull('cover_image')->pluck('cover_image')->each(fn ($p) => $paths->push($p));

        // Gallery items
        GalleryItem::where('file_type', 'image')->whereNotNull('file_path')->pluck('file_path')->each(fn ($p) => $paths->push($p));

        // Pages
        Page::whereNotNull('cover_image')->pluck('cover_image')->each(fn ($p) => $paths->push($p));

        // Gallery albums cover
        \App\Models\GalleryAlbum::whereNotNull('cover_image')->pluck('cover_image')->each(fn ($p) => $paths->push($p));

        $paths = $paths->unique()->filter(fn ($p) => $service->isConvertible($p));

        if ($force) {
            foreach ($paths as $path) {
                $webpPath = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $path);
                Storage::disk('public')->delete($webpPath);
            }
        }

        $bar = $this->output->createProgressBar($paths->count());
        $bar->start();

        $generated = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($paths as $path) {
            if (! Storage::disk('public')->exists($path)) {
                $skipped++;
                $bar->advance();
                continue;
            }

            $webpPath = $service->getWebPPath($path);
            if ($webpPath) {
                $generated++;
            } else {
                $failed++;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Done. Generated: {$generated}, Skipped: {$skipped}, Failed: {$failed}");

        return 0;
    }
}
