<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * One change applied to many books or series at once, picked from the admin list.
 */
class BulkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer', 'distinct'],
            'action' => ['required', Rule::in(['publish', 'unpublish', 'category', 'author', 'delete'])],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'author_id' => ['required_if:action,author', 'integer', 'exists:authors,id'],
        ];
    }
}
