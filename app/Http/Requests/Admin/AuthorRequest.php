<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AuthorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('slug') && $this->filled('name_translit')) {
            $this->merge(['slug' => Str::slug($this->input('name_translit'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'slug' => [$required, 'string', 'max:255', 'alpha_dash', Rule::unique('authors', 'slug')->ignore($this->route('author'))],
            'name_ar' => [$required, 'string', 'max:255'],
            'name_bn' => [$required, 'string', 'max:255'],
            'name_translit' => [$required, 'string', 'max:255'],
            'about_md' => ['nullable', 'string'],
        ];
    }
}
