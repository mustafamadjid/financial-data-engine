<?php

namespace App\Console\Commands;

use App\Domain\FinancialData\Pipeline\ReprocessStage;
use App\Services\Pipeline\ReprocessService;
use Illuminate\Console\Command;
use Throwable;

final class ReprocessFilingCommand extends Command
{
    protected $signature = 'financial-data:reprocess {filing} {stage} {--reason=} {--actor=system}';

    protected $description = 'Start an explicit versioned filing reprocess.';

    public function handle(ReprocessService $reprocessService): int
    {
        $stage = ReprocessStage::tryFrom(strtoupper(trim((string) $this->argument('stage'))));
        $reason = trim((string) $this->option('reason'));

        if ($stage === null) {
            $this->error('Stage must be one of: DOWNLOAD, PARSE, NORMALIZE, VALIDATE, PUBLISH.');

            return self::FAILURE;
        }

        if ($reason === '') {
            $this->error('The --reason option is required.');

            return self::FAILURE;
        }

        try {
            $run = $reprocessService->start(
                (string) $this->argument('filing'),
                $stage,
                $reason,
                (string) $this->option('actor'),
            );
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Reprocess run {$run->id} dispatched ({$run->correlation_id}).");

        return self::SUCCESS;
    }
}
