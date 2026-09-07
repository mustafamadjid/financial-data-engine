<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['artifact_id', 'filing_id', 'artifact_type', 'source_hash', 'storage_path', 'original_filename', 'content_type', 'size_bytes', 'downloaded_at'])]
class FilingArtifact extends Model
{
    protected $primaryKey = 'artifact_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'downloaded_at' => 'datetime',
        ];
    }

    public function filing(): BelongsTo
    {
        return $this->belongsTo(Filing::class, 'filing_id', 'filing_id');
    }
}
