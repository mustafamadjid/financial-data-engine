<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['debt_record_id', 'filing_id', 'borrower', 'creditor', 'facility', 'outstanding_amount', 'limit_amount', 'currency', 'interest_or_profit_rate', 'maturity_date', 'collateral', 'purpose', 'classification', 'classification_status', 'evidence_ids'])]
class DebtRecord extends Model
{
    protected $primaryKey = 'debt_record_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'outstanding_amount' => 'decimal:18',
            'limit_amount' => 'decimal:18',
            'interest_or_profit_rate' => 'decimal:18',
            'maturity_date' => 'date',
            'evidence_ids' => 'array',
        ];
    }

    public function filing(): BelongsTo
    {
        return $this->belongsTo(Filing::class, 'filing_id', 'filing_id');
    }
}
