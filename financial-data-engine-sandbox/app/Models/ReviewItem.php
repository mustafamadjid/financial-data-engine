<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['review_item_id', 'entity_type', 'entity_id', 'filing_id', 'status', 'rationale', 'created_by', 'resolved_by', 'resolution_rationale', 'expected_version', 'active_identity', 'resolved_at'])]
final class ReviewItem extends Model
{
    protected $primaryKey = 'review_item_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    public function filing(): BelongsTo
    {
        return $this->belongsTo(Filing::class, 'filing_id', 'filing_id');
    }
}
