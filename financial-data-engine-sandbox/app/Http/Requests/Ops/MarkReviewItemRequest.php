<?php

namespace App\Http\Requests\Ops;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

final class MarkReviewItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'entity_type' => ['required', 'string', Rule::in(['normalized_fact', 'validation_result'])],
            'entity_id' => ['required', 'string', 'max:128'],
            'filing_id' => ['required', 'string', 'max:128'],
            'expected_version' => ['required', 'string', 'max:128'],
            'rationale' => ['required', 'string', 'max:5000'],
            'idempotency_key' => ['required', 'string', 'max:128'],
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(response()->json([
            'code' => 'VALIDATION_ERROR',
            'message' => 'The review item payload is invalid.',
            'fieldErrors' => $validator->errors()->toArray(),
        ], 422));
    }
}
