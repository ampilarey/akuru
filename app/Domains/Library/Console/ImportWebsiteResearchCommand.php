<?php

namespace App\Domains\Library\Console;

use App\Domains\Library\Actions\ImportWebsiteResearchAction;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;

/**
 * RESEARCH_ARTICLES_PLAN R2: move the website's research papers into the
 * Digital Library. Safe to run again — an imported post is never imported
 * twice. `--dry-run` prints what would happen and writes nothing. The
 * printed table is the verification evidence STATUS records (rule 9).
 */
class ImportWebsiteResearchCommand extends Command
{
    use ConfirmableTrait;

    protected $signature = 'library:import-website-research {--dry-run : Show what would be imported, write nothing} {--force : Run in production without asking}';

    protected $description = 'Import the website research posts into the Digital Library';

    public function handle(ImportWebsiteResearchAction $import): int
    {
        $dryRun = (bool) $this->option('dry-run');
        if (! $dryRun && ! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        $rows = $import->execute($dryRun);
        if ($rows === []) {
            $this->info('No website research posts to import.');

            return self::SUCCESS;
        }

        $this->table(
            ['post', 'old slug', 'library slug', 'status', 'authors', 'pdf', 'pages', 'outcome', 'note'],
            array_map(fn (array $row) => array_values($row), $rows),
        );

        $counts = array_count_values(array_column($rows, 'outcome'));
        ksort($counts);
        $this->info(($dryRun ? 'Dry run — nothing written. ' : '').implode(', ', array_map(fn ($outcome, $n) => $n.' '.$outcome, array_keys($counts), $counts)).'.');

        return self::SUCCESS;
    }
}
