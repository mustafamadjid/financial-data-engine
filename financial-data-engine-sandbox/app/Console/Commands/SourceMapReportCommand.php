<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

final class SourceMapReportCommand extends Command
{
    protected $signature = 'financial-data:source-map-report {path=../DA-1-3/DA-1/data/extracted/source_map.csv}';

    protected $description = 'Report unresolved PARTIAL_SOURCE_IDENTITY source-map records.';

    public function handle(): int
    {
        $path = (string) $this->argument('path');
        if (! is_file($path)) {
            $this->error("Source map not found: {$path}");

            return self::FAILURE;
        }
        $handle = fopen($path, 'rb');
        $header = fgetcsv($handle);
        $partial = [];
        while (($row = fgetcsv($handle)) !== false) {
            if ($row === [] || $row === [null]) {
                continue;
            }
            $record = array_combine($header, $row);
            if (($record['source_map_status'] ?? $record['status'] ?? '') === 'PARTIAL_SOURCE_IDENTITY') {
                $partial[] = $record['filing_id'] ?? $record['issuer_code'] ?? 'unknown';
            }
        }
        fclose($handle);
        sort($partial);
        $this->line('PARTIAL_SOURCE_IDENTITY: '.count($partial));
        foreach ($partial as $identity) {
            $this->line('- '.$identity);
        }

        return self::SUCCESS;
    }
}
