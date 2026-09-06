<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['metric_id', 'filing_id', 'issuer_code', 'period', 'metric_code', 'value', 'unit', 'formula_version', 'input_fact_ids', 'calculation_status'])]
class FinancialMetric extends Model
{
    protected $primaryKey = 'metric_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'value' => 'decimal:18',
            'formula_version' => 'integer',
            'input_fact_ids' => 'array',
        ];
    }

    public function filing(): BelongsTo
    {
        return $this->belongsTo(Filing::class, 'filing_id', 'filing_id');
    }
}
