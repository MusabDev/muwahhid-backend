<?php

namespace App\Http\Requests\Admin;

use App\Enums\MediaType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DarsRequest extends FormRequest
{
    private const ARABIC_FONTS = ['amiri', 'noto-naskh-arabic', 'kfgqpc-uthmanic', 'scheherazade-new', 'readex-pro'];

    private const BANGLA_FONTS = ['noto-serif-bengali', 'hind-siliguri', 'kalpurush', 'solaimanlipi', 'ador-noirrit'];

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

            // which languages the subtitles start with, until a viewer picks for themselves; null is the site default
            'subtitle_mode' => ['nullable', Rule::in(['ar', 'bn', 'both'])],
            ...$this->subtitleStyleRules(),
        ];
    }

    /**
     * The dars's default subtitle look; only the fields that differ from the site default are sent.
     * While "separate", each language's cue ("ar", "bn") may set its own placement and look.
     *
     * @return array<string, mixed>
     */
    private function subtitleStyleRules(): array
    {
        $color = ['string', 'regex:/^#[0-9a-fA-F]{6}$/'];
        $cue = [
            'valign' => [Rule::in(['top', 'middle', 'bottom'])],
            'position' => ['numeric', 'between:-45,75'],
            'offsetX' => ['numeric', 'between:-50,50'],
            'align' => [Rule::in(['left', 'center', 'right'])],
            'width' => ['numeric', 'between:40,100'],
            'lineHeight' => ['numeric', 'between:70,160'],
            'bold' => ['boolean'],
            'stroke' => ['boolean'],
            'strokeWidth' => ['numeric', 'between:0.5,6'],
            'strokeColor' => $color,
            'shadow' => ['boolean'],
            'shadowColor' => $color,
            'shadowOpacity' => ['numeric', 'between:0,100'],
            'shadowBlur' => ['numeric', 'between:0,30'],
            'shadowX' => ['numeric', 'between:-10,10'],
            'shadowY' => ['numeric', 'between:-10,10'],
            'shape' => [Rule::in(['box', 'soft', 'lines', 'fade', 'none'])],
            'backgroundColor' => $color,
            'backgroundOpacity' => ['numeric', 'between:0,100'],
            'backgroundBlur' => ['numeric', 'between:0,20'],
            'fadeHeight' => ['numeric', 'between:0,200'],
            'fade' => ['boolean'],
            'transition' => [Rule::in(['fade', 'rise', 'rise-blur', 'blur', 'zoom', 'drop', 'words'])],
            'transitionMs' => ['numeric', 'between:150,1000'],
        ];
        $shared = [
            ...$cue,
            'fontAr' => [Rule::in(self::ARABIC_FONTS)],
            'fontBn' => [Rule::in(self::BANGLA_FONTS)],
            'sizeAr' => ['numeric', 'between:60,200'],
            'sizeBn' => ['numeric', 'between:60,200'],
            'colorAr' => $color,
            'colorBn' => $color,
            'order' => [Rule::in(['ar', 'bn'])],
            'nextLine' => ['boolean'],
            'separate' => ['boolean'],
        ];

        $rules = ['subtitle_style' => ['nullable', 'array:'.implode(',', [...array_keys($shared), 'ar', 'bn'])]];
        foreach ($shared as $key => $rule) {
            $rules["subtitle_style.{$key}"] = $rule;
        }
        foreach (['ar', 'bn'] as $lang) {
            $rules["subtitle_style.{$lang}"] = ['nullable', 'array:'.implode(',', array_keys($cue))];
            foreach ($cue as $key => $rule) {
                $rules["subtitle_style.{$lang}.{$key}"] = ['nullable', ...$rule];
            }
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        // an empty style means "use the site default"
        if ($this->has('subtitle_style') && $this->input('subtitle_style') === []) {
            $this->merge(['subtitle_style' => null]);
        }
    }
}
