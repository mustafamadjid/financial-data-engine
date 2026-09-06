<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['pipeline_run_id', 'filing_id', 'stage', 'job_class', 'queue_name', 'attempt', 'status', 'idempotency_key', 'correlation_id', 'error_type', 'error_code', 'error_message', 'error_context', 'started_at', 'finished_at'])]
class PipelineJobRun extends Model
{
    protected function casts(): array
    {
        return [
            'pipeline_run_id' => 'integer',
            'filing_id' => 'integer',
            'attempt' => 'integer',
            'error_context' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function pipelineRun(): BelongsTo
    {
        return $this->belongsTo(PipelineRun::class);
    }

    public function filing(): BelongsTo
    {
        return $this->belongsTo(Filing::class);
    }
}
