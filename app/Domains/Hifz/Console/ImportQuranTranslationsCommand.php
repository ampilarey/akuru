<?php

namespace App\Domains\Hifz\Console;

use App\Domains\Hifz\Actions\ImportQuranTranslationsAction;
use Illuminate\Console\Command;

class ImportQuranTranslationsCommand extends Command
{
    protected $signature = 'quran:import-translations
                            {path : JSON file with source_name, language, ayahs[]}
                            {--mushaf= : Optional quran_mushafs id (defaults to the active mushaf)}';

    protected $description = 'Import a licensed or fixture translation onto existing quran_ayahs (never creates parallel Quran tables)';

    public function handle(ImportQuranTranslationsAction $action): int
    {
        $path = $this->argument('path');
        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (! is_array($decoded)) {
            $this->error('Translation file must be a JSON object.');

            return self::FAILURE;
        }

        try {
            $report = $action->execute(
                $decoded,
                $this->option('mushaf') !== null ? (int) $this->option('mushaf') : null,
            );
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Imported %d %s rows from [%s]; skipped %d.',
            $report['imported'],
            $report['language'],
            $report['source_name'],
            $report['skipped'],
        ));

        return self::SUCCESS;
    }
}
