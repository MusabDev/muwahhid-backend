<?php

namespace App\Http\Requests\Admin;

use App\Enums\MediaType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DarsRequest extends FormRequest
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
        $required = $this->isMethod('post') ? 'required' : 'sometimes';
        $seriesId = $this->route('darsSeries')?->id ?? $this->route('dars')?->series_id;

        return [
            'number' => [$required, 'integer', 'min:0', Rule::unique('durus', 'number')->where('series_id', $seriesId)->ignore($this->route('dars'))],
            'title_ar' => ['nullable', 'string', 'max:255'],
            'title_bn' => [$required, 'string', 'max:255'],
            'media_type' => [$required, Rule::enum(MediaType::class)],
            'media_url' => [$required, 'url', 'max:2048'],
            'duration_seconds' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
