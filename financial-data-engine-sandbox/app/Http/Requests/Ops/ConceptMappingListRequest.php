<?php

namespace App\Http\Requests\Ops;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

final class ConceptMappingListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:128'],
            'status' => ['nullable', 'string', Rule::in(['DRAFT', 'APPROVED', 'REJECTED', 'REVIEW_REQUIRED', 'UNMAPPED'])],
            'entry_point' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', Rule::in([25, 50, 100])],
        ];
    }

    /** @return array<string, mixed> */
    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'search' => isset($validated['search']) ? trim((string) $validated['search']) : null,
            'status' => $validated['status'] ?? null,
            'entry_point' => isset($validated['entry_point']) ? trim((string) $validated['entry_point']) : null,
            'page' => (int) ($validated['page'] ?? 1),
            'per_page' => (int) ($validated['per_page'] ?? 25),
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(response()->json([
            'code' => 'VALIDATION_ERROR',
            'message' => 'The concept mapping filters are invalid.',
            'fieldErrors' => $validator->errors()->toArray(),
        ], 422));
    }
}
