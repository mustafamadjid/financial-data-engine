<?php

namespace App\Http\Requests\Ops;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

final class PipelineHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'in:10,25,50'],
        ];
    }

    /** @return array{page: int, per_page: int} */
    public function pagination(): array
    {
        return [
            'page' => (int) $this->validated('page', 1),
            'per_page' => (int) $this->validated('per_page', 25),
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(response()->json([
            'code' => 'VALIDATION_ERROR',
            'message' => 'The requested history page is invalid.',
            'fieldErrors' => $validator->errors()->toArray(),
        ], 422));
    }
}
