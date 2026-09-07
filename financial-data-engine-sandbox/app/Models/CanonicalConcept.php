<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'statement', 'period_type', 'sign_convention', 'allowed_scope', 'required_status', 'formula_dependency', 'description'])]
class CanonicalConcept extends Model
{
    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'allowed_scope' => 'array',
            'formula_dependency' => 'array',
        ];
    }

    public function conceptMappings(): HasMany
    {
        return $this->hasMany(ConceptMapping::class, 'canonical_concept', 'code');
    }

    public function normalizedFacts(): HasMany
    {
        return $this->hasMany(NormalizedFact::class, 'canonical_concept', 'code');
    }
}
