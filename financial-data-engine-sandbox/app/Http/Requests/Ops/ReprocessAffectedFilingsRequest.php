<?php

namespace App\Http\Requests\Ops;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

final class ReprocessAffectedFilingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'filing_ids' => ['required', 'array', 'min:1', 'max:1000'],
            'filing_ids.*' => ['required', 'string', 'max:128'],
            'expected_mapping_set_version' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:3', 'max:5000'],
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(response()->json([
            'code' => 'VALIDATION_ERROR',
            'message' => 'The mapping reprocess request is invalid.',
            'fieldErrors' => $validator->errors()->toArray(),
        ], 422));
    }
}
