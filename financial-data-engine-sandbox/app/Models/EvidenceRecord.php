<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['evidence_id', 'filing_id', 'source_document', 'source_section', 'page', 'source_concept', 'context_ref', 'text_reference', 'extraction_method'])]
class EvidenceRecord extends Model
{
    protected $primaryKey = 'evidence_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return ['page' => 'integer'];
    }

    public function filing(): BelongsTo
    {
        return $this->belongsTo(Filing::class, 'filing_id', 'filing_id');
    }
}
