<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['unit_id', 'filing_id', 'source_unit_id', 'unit_type', 'measure', 'currency'])]
class XbrlUnit extends Model
{
    protected $primaryKey = 'unit_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public function filing(): BelongsTo
    {
        return $this->belongsTo(Filing::class, 'filing_id', 'filing_id');
    }

    public function rawFacts(): HasMany
    {
        return $this->hasMany(RawFact::class, 'unit_ref', 'unit_id');
    }
}
