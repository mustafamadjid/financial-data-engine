<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['filing_id', 'trigger', 'status', 'correlation_id', 'started_at', 'finished_at'])]
class PipelineRun extends Model
{
    protected function casts(): array
    {
        return [
            'filing_id' => 'integer',
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
