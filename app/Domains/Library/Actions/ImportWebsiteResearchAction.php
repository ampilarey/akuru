<?php

namespace App\Domains\Library\Actions;

use App\Domains\HR\Actions\ReadPublicInstructorProfileAction;
use App\Domains\Library\Enums\LibraryDelivery;
use App\Domains\Library\Enums\LibraryItemStatus;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Media\Actions\CopyPublicMediaToPrivateAction;
use App\Domains\Website\Actions\ListResearchPostsForImportAction;

/**
 * RESEARCH_ARTICLES_PLAN R2: the website's research papers become Digital
 * Library research items.
 *
 * - One item per research post, remembered by `imported_post_id`, so a
 *   second run imports nothing twice.
 * - Open access, read online **and** download (D1's default for open
 *   access), published if the post was live — with its own date — else a
 *   draft.
 * - Title, slug, abstract and body carry over; the citation note becomes
 *   the item's citations.
 * - The PDF, public media on the website, is copied into private media and
 *   its pages made for the reader. The public file stays (rule 9).
 * - A teacher author stays a teacher author (R1); a named outsider stays a
 *   name. A teacher since removed from HR's list is left off and reported.
 * - A slug the library already uses gets `-paper`; the old address still
 *   finds the item, by `imported_post_id`.
 *
 * **The imported papers were never peer-reviewed.** They publish as the
 * institute's already-public papers, and the shelf's *Peer-reviewed* mark
 * stays off them, because that mark is earned only by a reviewer's accept.
 * They are published directly — not through `PublishLibraryItemAction` —
 * for that reason: this is a move, not a new publication.
 */
class ImportWebsiteResearchAction
{
    /**
     * @return list<array{post_id: int, post_slug: string, library_slug: string, status: string, authors: int, pdf: string, pages: int, outcome: string, note: string}>
     */
    public function execute(bool $dryRun = false): array
    {
        $rows = [];
        foreach (app(ListResearchPostsForImportAction::class)->execute() as $post) {
            $rows[] = $this->one($post, $dryRun);
        }

        return $rows;
    }

    /**
     * The library address of the item imported from a post, if any.
     */
    public function slugForPost(int $postId): ?string
    {
        $slug = LibraryItem::query()
            ->where('imported_post_id', $postId)
            ->where('status', LibraryItemStatus::Published->value)
            ->value('slug');

        return $slug !== null ? (string) $slug : null;
    }

    /**
     * @param  array<string, mixed>  $post
     * @return array<string, mixed>
     */
    private function one(array $post, bool $dryRun): array
    {
        $status = $post['is_live'] ? LibraryItemStatus::Published : LibraryItemStatus::Draft;
        $existing = LibraryItem::query()->where('imported_post_id', $post['id'])->first();
        if ($existing !== null) {
            return $this->row($post, (string) $existing->slug, (string) $existing->status?->value, $existing->authors()->count(), $existing->pdf_media_file_id !== null ? 'yes' : 'no', $existing->pages()->count(), 'already imported', '');
        }

        $slug = $post['slug'];
        $note = '';
        if (LibraryItem::query()->where('slug', $slug)->exists()) {
            $slug .= '-paper';
            $note = 'slug taken in the library; imported as '.$slug;
        }

        [$authors, $missing] = $this->authors($post['authors']);
        if ($missing !== []) {
            $note = trim($note.' teacher(s) no longer listed, left off: #'.implode(', #', $missing));
        }

        if ($dryRun) {
            return $this->row($post, $slug, $status->value, count($authors), $post['pdf_media_id'] !== null ? 'will copy' : 'no', 0, 'would import', $note);
        }

        $item = app(SaveLibraryItemAction::class)->execute([
            'title' => $post['title'],
            'slug' => $slug,
            'content_type' => 'research',
            'access_type' => 'free_public',
            'delivery' => LibraryDelivery::Both->value,
            'language' => 'en',
            'abstract' => $post['abstract'],
            'body' => $post['body'],
            'citations' => $post['citation_note'] ?? '',
            'authors' => $authors,
        ]);

        $pdf = 'no';
        if ($post['pdf_media_id'] !== null) {
            $copy = app(CopyPublicMediaToPrivateAction::class)->execute((int) $post['pdf_media_id'], null, ['application/pdf']);
            $pdf = $copy !== null ? 'copied' : 'missing';
            if ($copy !== null) {
                $item->forceFill(['pdf_media_file_id' => $copy['id']])->save();
            }
        }

        $item->forceFill([
            'imported_post_id' => $post['id'],
            'status' => $status,
            'published_at' => $post['published_at'] ?? ($status === LibraryItemStatus::Published ? now() : null),
        ])->save();

        $pages = app(SyncLibraryItemPagesAction::class)->execute($item->refresh());

        return $this->row($post, (string) $item->slug, $status->value, count($authors), $pdf, $pages, 'imported', $note);
    }

    /**
     * @param  list<array{instructor_id: ?int, name: ?string}>  $raw
     * @return array{0: list<array<string, mixed>>, 1: list<int>}
     */
    private function authors(array $raw): array
    {
        $teachers = app(ReadPublicInstructorProfileAction::class);
        $out = [];
        $missing = [];
        foreach ($raw as $author) {
            if ($author['instructor_id'] !== null) {
                if ($teachers->execute((int) $author['instructor_id'], null, false) === null) {
                    $missing[] = (int) $author['instructor_id'];

                    continue;
                }
                $out[] = ['instructor_profile_id' => (int) $author['instructor_id']];
            } elseif ($author['name'] !== null) {
                $out[] = ['name' => $author['name']];
            }
        }

        return [$out, $missing];
    }

    /**
     * @param  array<string, mixed>  $post
     * @return array<string, mixed>
     */
    private function row(array $post, string $slug, string $status, int $authors, string $pdf, int $pages, string $outcome, string $note): array
    {
        return [
            'post_id' => $post['id'],
            'post_slug' => $post['slug'],
            'library_slug' => $slug,
            'status' => $status,
            'authors' => $authors,
            'pdf' => $pdf,
            'pages' => $pages,
            'outcome' => $outcome,
            'note' => $note,
        ];
    }
}
