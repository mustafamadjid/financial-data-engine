<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['normalized_fact_id', 'filing_id', 'raw_fact_id', 'issuer_code', 'period', 'period_start', 'period_end', 'canonical_concept', 'value', 'currency', 'scope', 'data_type', 'source_concept', 'mapping_rule_id', 'mapping_rule_version', 'normalization_version', 'normalization_status', 'validation_status'])]
class NormalizedFact extends Model
{
    protected $primaryKey = 'normalized_fact_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'value' => 'decimal:18',
            'mapping_rule_version' => 'integer',
            'normalization_version' => 'string',
        ];
    }

    public function filing(): BelongsTo
    {
        return $this->belongsTo(Filing::class, 'filing_id', 'filing_id');
    }

    public function rawFact(): BelongsTo
    {
        return $this->belongsTo(RawFact::class, 'raw_fact_id', 'raw_fact_id');
    }

    public function canonicalConcept(): BelongsTo
    {
        return $this->belongsTo(CanonicalConcept::class, 'canonical_concept', 'code');
    }

    public function mappingRule(): BelongsTo
    {
        return $this->belongsTo(ConceptMapping::class, 'mapping_rule_id', 'mapping_rule_id');
    }
}
