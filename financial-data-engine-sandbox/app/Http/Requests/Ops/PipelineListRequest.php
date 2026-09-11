<?php

namespace App\Http\Requests\Ops;

use App\Domain\FinancialData\Pipeline\PipelineStage;
use App\Domain\FinancialData\Pipeline\QualityStatus;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

final class PipelineListRequest extends FormRequest
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
            'processing_stage' => ['nullable', 'string', Rule::in(array_column(PipelineStage::cases(), 'value'))],
            'quality_status' => ['nullable', 'string', Rule::in(array_column(QualityStatus::cases(), 'value'))],
            'period' => ['nullable', 'string', 'max:20'],
            'sort' => ['nullable', 'string', Rule::in(['last_processed_desc', 'last_processed_asc', 'issuer_asc'])],
            'per_page' => ['nullable', 'integer', Rule::in([25, 50, 100])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** @return array<string, mixed> */
    public function listFilters(): array
    {
        $validated = $this->validated();

        return [
            'search' => trim((string) ($validated['search'] ?? '')),
            'processing_stage' => $validated['processing_stage'] ?? null,
            'quality_status' => $validated['quality_status'] ?? null,
            'period' => $validated['period'] ?? null,
            'sort' => $validated['sort'] ?? 'last_processed_desc',
            'per_page' => (int) ($validated['per_page'] ?? 25),
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(response()->json([
            'code' => 'VALIDATION_ERROR',
            'message' => 'The given pipeline list parameters are invalid.',
            'fieldErrors' => $validator->errors()->toArray(),
        ], 422));
    }
}
