<?php

namespace App\Http\Requests\Ops;

use Illuminate\Foundation\Http\FormRequest;

final class RetryPipelineRequest extends FormRequest
{
    public function rules(): array
    {
        return ['reason' => ['nullable', 'string', 'max:500']];
    }
}
