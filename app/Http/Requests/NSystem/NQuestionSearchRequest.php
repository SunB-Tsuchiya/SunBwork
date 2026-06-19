<?php

namespace App\Http\Requests\NSystem;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NQuestionSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $query = preg_replace('/[\s　]+/u', ' ', trim((string) $this->input('q', '')));

        $this->merge([
            'q' => $query ?? '',
            'mode' => $this->input('mode', 'exact'),
        ]);
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'mode' => ['required', Rule::in(['exact', 'all', 'any'])],
            'subject' => ['nullable', Rule::in(['Ko', 'Sa', 'Sh', 'Ri'])],
            'school_id' => ['nullable', 'integer', 'exists:n_schools,id'],
            'category' => ['nullable', Rule::in(['共学', '男子', '女子', '地方'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function searchFilters(): array
    {
        $validated = $this->validated();

        return [
            'q' => $validated['q'] ?? '',
            'mode' => $validated['mode'],
            'subject' => $validated['subject'] ?? null,
            'school_id' => isset($validated['school_id']) ? (int) $validated['school_id'] : null,
            'category' => $validated['category'] ?? null,
            'page' => isset($validated['page']) ? (int) $validated['page'] : 1,
        ];
    }
}
