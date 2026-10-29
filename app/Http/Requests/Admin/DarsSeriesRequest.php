<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DarsSeriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('slug') && $this->filled('title_translit')) {
            $this->merge(['slug' => Str::slug($this->input('title_translit'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'slug' => [$required, 'string', 'max:255', 'alpha_dash', Rule::unique('dars_series', 'slug')->ignore($this->route('darsSeries'))],
            'author_id' => [$required, 'integer', 'exists:authors,id'],
            'book_id' => ['nullable', 'integer', 'exists:books,id'],
            'title_ar' => [$required, 'string', 'max:255'],
            'title_bn' => [$required, 'string', 'max:255'],
            'title_translit' => [$required, 'string', 'max:255'],
            'about_md' => ['nullable', 'string'],
            'is_published' => ['sometimes', 'boolean'],
        ];
    }
}
