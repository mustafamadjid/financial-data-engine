<?php

namespace App\Http\Requests\Ops;

use App\Domain\FinancialData\Pipeline\ReprocessStage;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

final class ReprocessPipelineRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'stage' => ['required', 'string', Rule::enum(ReprocessStage::class)],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(response()->json([
            'code' => 'VALIDATION_ERROR', 'message' => 'The reprocess request is invalid.', 'fieldErrors' => $validator->errors()->toArray(),
        ], 422));
    }
}
