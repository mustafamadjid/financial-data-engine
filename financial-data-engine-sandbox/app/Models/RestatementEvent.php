<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['filing_id', 'superseded_filing_id', 'canonical_concept', 'current_normalized_fact_id', 'prior_normalized_fact_id', 'current_value', 'prior_value', 'rule_code', 'rule_version', 'detected_at'])]
class RestatementEvent extends Model
{
    protected function casts(): array
    {
        return [
            'current_value' => 'decimal:18',
            'prior_value' => 'decimal:18',
            'rule_version' => 'integer',
            'detected_at' => 'datetime',
        ];
    }
}
