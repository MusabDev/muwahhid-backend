<?php

namespace App\Models;

use App\Support\ArabicNormalizer;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['dars_id', 'position', 'start_time', 'end_time', 'text_ar', 'text_bn'])]
#[Hidden(['text_ar_normalized'])]
class DarsSegment extends Model
{
    protected static function booted(): void
    {
        static::saving(function (DarsSegment $segment) {
            $segment->text_ar_normalized = ArabicNormalizer::normalize($segment->text_ar ?? '');
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_time' => 'float',
            'end_time' => 'float',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Dars, $this>
     */
    public function dars(): BelongsTo
    {
        return $this->belongsTo(Dars::class);
    }

    /**
     * @return MorphMany<Annotation, $this>
     */
    public function annotations(): MorphMany
    {
        return $this->morphMany(Annotation::class, 'annotatable');
    }
}
