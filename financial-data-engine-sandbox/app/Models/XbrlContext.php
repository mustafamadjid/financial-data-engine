<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['context_id', 'filing_id', 'source_context_id', 'entity_identifier', 'scope', 'period_type', 'instant_date', 'start_date', 'end_date', 'context_status', 'parser_version', 'parser_config_version'])]
class XbrlContext extends Model
{
    protected $primaryKey = 'context_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'instant_date' => 'date',
            'start_date' => 'date',
            'end_date' => 'date',
            'parser_version' => 'string',
            'parser_config_version' => 'string',
        ];
    }

    public function filing(): BelongsTo
    {
        return $this->belongsTo(Filing::class, 'filing_id', 'filing_id');
    }

    public function dimensions(): HasMany
    {
        return $this->hasMany(XbrlDimension::class, 'context_id', 'context_id');
    }

    public function rawFacts(): HasMany
    {
        return $this->hasMany(RawFact::class, 'context_ref', 'context_id');
    }
}
