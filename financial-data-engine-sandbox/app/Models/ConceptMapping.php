<?php

namespace App\Models;

use App\Domain\FinancialData\Mapping\MappingSeriesKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['mapping_rule_id', 'mapping_series_key', 'supersedes_mapping_rule_id', 'source_concept', 'entry_point', 'canonical_concept', 'allowed_scope', 'period_type', 'sign_convention', 'status', 'rationale', 'reviewer', 'evidence_ids', 'created_by', 'rule_version'])]
class ConceptMapping extends Model
{
    protected $primaryKey = 'mapping_rule_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected static function booted(): void
    {
        static::creating(function (self $mapping): void {
            if (trim((string) $mapping->mapping_series_key) === '') {
                $mapping->mapping_series_key = MappingSeriesKey::from((string) $mapping->source_concept, $mapping->entry_point);
            }

            if (trim((string) $mapping->created_by) === '') {
                $mapping->created_by = 'system';
            }
        });
    }

    protected function casts(): array
    {
        return [
            'allowed_scope' => 'array',
            'evidence_ids' => 'array',
            'rule_version' => 'integer',
        ];
    }

    public function canonicalConcept(): BelongsTo
    {
        return $this->belongsTo(CanonicalConcept::class, 'canonical_concept', 'code');
    }

    public function normalizedFacts(): HasMany
    {
        return $this->hasMany(NormalizedFact::class, 'mapping_rule_id', 'mapping_rule_id');
    }
}
