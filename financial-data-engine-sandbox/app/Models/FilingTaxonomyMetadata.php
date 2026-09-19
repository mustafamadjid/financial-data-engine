<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['filing_id', 'parser_version', 'parser_config_version', 'contract_version', 'target_namespace', 'imports', 'import_locations', 'linkbase_roles', 'linkbase_references', 'statement_families', 'extracted_at'])]
class FilingTaxonomyMetadata extends Model
{
    protected $table = 'filing_taxonomy_metadata';

    protected $primaryKey = 'filing_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'imports' => 'array',
            'import_locations' => 'array',
            'linkbase_roles' => 'array',
            'linkbase_references' => 'array',
            'statement_families' => 'array',
            'extracted_at' => 'datetime',
        ];
    }

    public function filing(): BelongsTo
    {
        return $this->belongsTo(Filing::class, 'filing_id', 'filing_id');
    }
}
