<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['rule_code', 'description', 'severity', 'inputs', 'tolerance', 'enabled', 'rule_version'])]
class ValidationRule extends Model
{
    protected function casts(): array
    {
        return [
            'inputs' => 'array',
            'tolerance' => 'decimal:18',
            'enabled' => 'boolean',
            'rule_version' => 'integer',
        ];
    }
}
