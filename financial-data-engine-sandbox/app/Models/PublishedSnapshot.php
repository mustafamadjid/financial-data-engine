<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['snapshot_id', 'filing_id', 'revision_number', 'publish_contract_version', 'normalized_dataset_version', 'validation_rule_set_version', 'publish_idempotency_key', 'payload', 'lineage', 'published_at'])]
class PublishedSnapshot extends Model
{
    protected $primaryKey = 'snapshot_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'revision_number' => 'integer',
            'payload' => 'array',
            'lineage' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function filing(): BelongsTo
    {
        return $this->belongsTo(Filing::class, 'filing_id', 'filing_id');
    }
}
