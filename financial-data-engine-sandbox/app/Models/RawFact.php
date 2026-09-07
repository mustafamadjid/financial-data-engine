<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['raw_fact_id', 'filing_id', 'source_concept', 'source_namespace', 'raw_value', 'normalized_numeric_value', 'context_ref', 'unit_ref', 'decimals', 'precision', 'is_nil', 'fact_status', 'parser_version', 'parser_config_version'])]
class RawFact extends Model
{
    protected $primaryKey = 'raw_fact_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'normalized_numeric_value' => 'decimal:18',
            'is_nil' => 'boolean',
            'parser_version' => 'string',
            'parser_config_version' => 'string',
        ];
    }

    public function filing(): BelongsTo
    {
        return $this->belongsTo(Filing::class, 'filing_id', 'filing_id');
    }

    public function context(): BelongsTo
    {
        return $this->belongsTo(XbrlContext::class, 'context_ref', 'context_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(XbrlUnit::class, 'unit_ref', 'unit_id');
    }
}
