<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['dimension_id', 'context_id', 'axis', 'member', 'typed_value'])]
class XbrlDimension extends Model
{
    protected $primaryKey = 'dimension_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public function context(): BelongsTo
    {
        return $this->belongsTo(XbrlContext::class, 'context_id', 'context_id');
    }
}
