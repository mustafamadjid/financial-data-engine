<?php

namespace App\Http\Requests\Ops;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

final class FinancialFactListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'filing_id' => ['nullable', 'string', 'max:128'],
            'search' => ['nullable', 'string', 'max:128'],
            'canonical_concept' => ['nullable', 'string', 'max:100'],
            'scope' => ['nullable', 'string', Rule::in(['CONSOLIDATED', 'PARENT', 'UNKNOWN'])],
            'normalization_status' => ['nullable', 'string', Rule::in(['NORMALIZED', 'UNMAPPED', 'REVIEW_REQUIRED'])],
            'validation_status' => ['nullable', 'string', Rule::in(['PENDING', 'VERIFIED', 'REVIEW_REQUIRED', 'FAILED'])],
            'sort' => ['nullable', 'string', Rule::in(['filing_asc', 'concept_asc', 'period_desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', Rule::in([25, 50, 100])],
        ];
    }

    /** @return array<string, mixed> */
    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'filing_id' => isset($validated['filing_id']) ? trim((string) $validated['filing_id']) : null,
            'search' => isset($validated['search']) ? trim((string) $validated['search']) : null,
            'canonical_concept' => $validated['canonical_concept'] ?? null,
            'scope' => $validated['scope'] ?? null,
            'normalization_status' => $validated['normalization_status'] ?? null,
            'validation_status' => $validated['validation_status'] ?? null,
            'sort' => $validated['sort'] ?? 'filing_asc',
            'per_page' => (int) ($validated['per_page'] ?? 25),
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(response()->json([
            'code' => 'VALIDATION_ERROR',
            'message' => 'The financial fact filters are invalid.',
            'fieldErrors' => $validator->errors()->toArray(),
        ], 422));
    }
}
