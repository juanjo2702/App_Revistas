<?php

namespace App\Console\Commands;

use App\Services\Catalog\CatalogSyncService;
use Illuminate\Console\Command;

class SyncCatalogCommand extends Command
{
    protected $signature = 'catalog:sync {--source= : Sync only one OJS source slug} {--journal= : Sync only one journal compound id}';

    protected $description = 'Synchronize the normalized OJS catalog into the local database';

    public function handle(CatalogSyncService $sync): int
    {
        $source = $this->option('source');
        $journal = $this->option('journal');

        if (is_string($journal) && $journal !== '') {
            $summary = $sync->syncJournal($journal);

            $this->info(sprintf(
                'Journal synchronized. Issues: %d, Articles: %d',
                $summary['issues'],
                $summary['articles'],
            ));

            return self::SUCCESS;
        }

        if (is_string($source) && $source !== '') {
            $summary = $sync->syncSource($source);

            $this->info(sprintf(
                'Source synchronized. Journals: %d, Issues: %d, Articles: %d',
                $summary['journals'],
                $summary['issues'],
                $summary['articles'],
            ));

            return self::SUCCESS;
        }

        $summary = $sync->syncAllSources();

        $this->info(sprintf(
            'Catalog synchronized. Sources: %d, Journals: %d, Issues: %d, Articles: %d',
            $summary['sources'],
            $summary['journals'],
            $summary['issues'],
            $summary['articles'],
        ));

        return self::SUCCESS;
    }
}
