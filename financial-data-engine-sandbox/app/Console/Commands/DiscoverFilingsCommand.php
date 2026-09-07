<?php

namespace App\Console\Commands;

use App\Domain\FinancialData\Discovery\DiscoveryCriteria;
use App\Jobs\Pipeline\DiscoverFilingsJob;
use Illuminate\Console\Command;

final class DiscoverFilingsCommand extends Command
{
    protected $signature = 'financial-data:discover {sourceAdapter} {discoveryWindow} {--page-size=10}';

    protected $description = 'Dispatch the configured filing discovery job.';

    public function handle(): int
    {
        $criteria = new DiscoveryCriteria(
            sourceAdapter: (string) $this->argument('sourceAdapter'),
            discoveryWindow: (string) $this->argument('discoveryWindow'),
            pageSize: max(1, (int) $this->option('page-size')),
        );

        dispatch(new DiscoverFilingsJob($criteria));
        $this->info('Discovery job dispatched.');

        return self::SUCCESS;
    }
}
