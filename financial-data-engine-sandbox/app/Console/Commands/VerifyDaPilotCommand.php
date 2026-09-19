<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

final class VerifyDaPilotCommand extends Command
{
    protected $signature = 'financial-data:verify-da-pilot';

    protected $description = 'Run the compact DA-1-3 pilot parity verification harness.';

    public function handle(): int
    {
        $root = dirname(base_path());
        $script = $root.DIRECTORY_SEPARATOR.'scripts'.DIRECTORY_SEPARATOR.'verify_da_pilot.py';
        $process = new Process(['python', $script], $root);
        $process->run(fn (string $type, string $buffer) => $this->output->write($buffer));

        return $process->isSuccessful() ? self::SUCCESS : self::FAILURE;
    }
}
