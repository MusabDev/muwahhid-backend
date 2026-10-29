<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('slug') && $this->filled('name_bn')) {
            $slug = Str::slug($this->input('slug_hint') ?: $this->input('name_bn'));
            $this->merge(['slug' => $slug !== '' ? $slug : 'category-'.Str::lower(Str::random(6))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'slug' => [$required, 'string', 'max:255', 'alpha_dash', Rule::unique('categories', 'slug')->ignore($this->route('category'))],
            'name_bn' => [$required, 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'position' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
