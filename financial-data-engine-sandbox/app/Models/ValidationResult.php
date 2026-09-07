<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['validation_result_id', 'filing_id', 'normalized_dataset_version', 'validation_rule_set_version', 'normalized_fact_ids', 'rule_code', 'rule_version', 'result', 'severity', 'message', 'expected_value', 'actual_value', 'tolerance', 'checked_at'])]
class ValidationResult extends Model
{
    protected $primaryKey = 'validation_result_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'normalized_fact_ids' => 'array',
            'normalized_dataset_version' => 'string',
            'validation_rule_set_version' => 'string',
            'rule_version' => 'integer',
            'expected_value' => 'decimal:18',
            'actual_value' => 'decimal:18',
            'tolerance' => 'decimal:18',
            'checked_at' => 'datetime',
        ];
    }

    public function filing(): BelongsTo
    {
        return $this->belongsTo(Filing::class, 'filing_id', 'filing_id');
    }
}
