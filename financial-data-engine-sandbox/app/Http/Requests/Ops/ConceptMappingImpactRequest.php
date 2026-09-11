<?php

namespace App\Http\Requests\Ops;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

final class ConceptMappingImpactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'version' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /** @return array{version: int|null, page: int, per_page: int} */
    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'version' => isset($validated['version']) ? (int) $validated['version'] : null,
            'page' => (int) ($validated['page'] ?? 1),
            'per_page' => (int) ($validated['per_page'] ?? 25),
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(response()->json([
            'code' => 'VALIDATION_ERROR',
            'message' => 'The concept mapping impact filters are invalid.',
            'fieldErrors' => $validator->errors()->toArray(),
        ], 422));
    }
}
