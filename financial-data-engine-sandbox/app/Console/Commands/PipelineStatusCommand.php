<?php

namespace App\Console\Commands;

use App\Models\Filing;
use App\Models\PipelineJobRun;
use App\Models\PipelineRun;
use App\Models\PublishedSnapshot;
use Illuminate\Console\Command;

final class PipelineStatusCommand extends Command
{
    protected $signature = 'financial-data:status {filing}';

    protected $description = 'Display read-only pipeline status for a filing.';

    public function handle(): int
    {
        $filingId = (string) $this->argument('filing');
        $filing = Filing::query()->find($filingId);

        if ($filing === null) {
            $this->error('Filing was not found.');

            return self::FAILURE;
        }

        $run = PipelineRun::query()->where('filing_id', $filingId)->latest('id')->first();
        $job = PipelineJobRun::query()->where('filing_id', $filingId)->latest('id')->first();
        $snapshot = PublishedSnapshot::query()->where('filing_id', $filingId)->latest('published_at')->first();

        $this->line("Filing: {$filing->filing_id}");
        $this->line('Processing stage: '.(string) $filing->processing_stage);
        $this->line('Quality status: '.(string) $filing->quality_status);
        $this->line('Latest run: '.($run?->id ?? 'none').' / '.($run?->status ?? 'none'));
        $this->line('Latest job: '.($job?->stage ?? 'none').' / '.($job?->status ?? 'none'));
        $this->line('Latest error: '.($job?->error_message ?? 'none'));
        $this->line('Published snapshot: '.($snapshot?->snapshot_id ?? 'none'));

        return self::SUCCESS;
    }
}
