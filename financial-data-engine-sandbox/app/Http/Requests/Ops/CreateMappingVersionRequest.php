<?php

namespace App\Http\Requests\Ops;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

final class CreateMappingVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'source_concept' => ['required', 'string', 'max:255'],
            'entry_point' => ['nullable', 'string', 'max:255'],
            'canonical_concept' => ['required', 'string', 'max:100', Rule::exists('canonical_concepts', 'code')],
            'allowed_scope' => ['nullable', 'array'],
            'allowed_scope.*' => ['string', 'max:50'],
            'period_type' => ['nullable', 'string', Rule::in(['INSTANT', 'DURATION'])],
            'sign_convention' => ['nullable', 'string', 'max:50'],
            'status' => ['required', 'string', Rule::in(['DRAFT', 'APPROVED', 'REJECTED', 'REVIEW_REQUIRED'])],
            'rationale' => ['required', 'string', 'max:5000'],
            'evidence_ids' => ['nullable', 'array', 'max:100'],
            'evidence_ids.*' => ['string', 'max:128', Rule::exists('evidence_records', 'evidence_id')],
            'expected_version' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'canonical_concept.exists' => 'The selected canonical concept is invalid.',
            'evidence_ids.*.exists' => 'The selected evidence does not exist.',
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(response()->json([
            'code' => 'VALIDATION_ERROR',
            'message' => 'The mapping version payload is invalid.',
            'fieldErrors' => $validator->errors()->toArray(),
        ], 422));
    }
}
