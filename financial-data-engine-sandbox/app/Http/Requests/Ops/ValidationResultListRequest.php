<?php

namespace App\Http\Requests\Ops;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

final class ValidationResultListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'filing_id' => ['required', 'string', 'max:128'],
            'dataset_version' => ['required', 'string', 'max:128'],
            'rule_set_version' => ['required', 'string', 'max:128'],
            'severity' => ['nullable', 'string', Rule::in(['ERROR', 'WARN', 'INFO'])],
            'result' => ['nullable', 'string', Rule::in(['PASS', 'FAIL', 'REVIEW_REQUIRED', 'SKIPPED'])],
            'rule_code' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', Rule::in([25, 50, 100])],
        ];
    }

    /** @return array<string, mixed> */
    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'filing_id' => (string) $validated['filing_id'],
            'dataset_version' => (string) $validated['dataset_version'],
            'rule_set_version' => (string) $validated['rule_set_version'],
            'severity' => $validated['severity'] ?? null,
            'result' => $validated['result'] ?? null,
            'rule_code' => $validated['rule_code'] ?? null,
            'page' => (int) ($validated['page'] ?? 1),
            'per_page' => (int) ($validated['per_page'] ?? 25),
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(response()->json([
            'code' => 'VALIDATION_ERROR',
            'message' => 'A filing, dataset version, and rule-set version are required.',
            'fieldErrors' => $validator->errors()->toArray(),
        ], 422));
    }
}
