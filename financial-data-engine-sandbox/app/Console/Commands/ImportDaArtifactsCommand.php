<?php

namespace App\Console\Commands;

use App\Application\DaArtifacts\DaArtifactImporter;
use Illuminate\Console\Command;
use Throwable;

final class ImportDaArtifactsCommand extends Command
{
    protected $signature = 'financial-data:import-da {path} {version} {--dry-run}';

    protected $description = 'Validate and import versioned DA-1-3 artifacts.';

    public function handle(DaArtifactImporter $importer): int
    {
        try {
            $result = $importer->import((string) $this->argument('path'), (string) $this->argument('version'), (bool) $this->option('dry-run'));
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf('DA artifacts validated: %d concepts, %d mappings%s.', $result['canonical_count'], $result['mapping_count'], $this->option('dry-run') ? ' (dry-run)' : ''));

        return self::SUCCESS;
    }
}
