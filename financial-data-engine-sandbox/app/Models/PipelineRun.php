<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['filing_id', 'trigger', 'started_from_stage', 'status', 'initiated_by', 'reason', 'dependency_versions', 'correlation_id', 'started_at', 'finished_at'])]
class PipelineRun extends Model
{
    protected function casts(): array
    {
        return [
            'filing_id' => 'string',
            'dependency_versions' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function filing(): BelongsTo
    {
        return $this->belongsTo(Filing::class);
    }

    public function jobRuns(): HasMany
    {
        return $this->hasMany(PipelineJobRun::class);
    }
}
