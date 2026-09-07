<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['dimension_id', 'context_id', 'axis', 'member', 'typed_value', 'filing_id', 'parser_version', 'parser_config_version'])]
class XbrlDimension extends Model
{
    protected $primaryKey = 'dimension_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'parser_version' => 'string',
            'parser_config_version' => 'string',
        ];
    }

    public function context(): BelongsTo
    {
        return $this->belongsTo(XbrlContext::class, 'context_id', 'context_id');
    }
}
