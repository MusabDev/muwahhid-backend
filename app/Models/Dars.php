<?php

namespace App\Models;

use App\Enums\MediaType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['series_id', 'number', 'title_ar', 'title_bn', 'media_type', 'media_url', 'duration_seconds'])]
class Dars extends Model
{
    protected $table = 'durus';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'media_type' => MediaType::class,
            'number' => 'integer',
            'duration_seconds' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<DarsSeries, $this>
     */
    public function series(): BelongsTo
    {
        return $this->belongsTo(DarsSeries::class, 'series_id');
    }

    /**
     * @return HasMany<DarsSegment, $this>
     */
    public function segments(): HasMany
    {
        return $this->hasMany(DarsSegment::class)->orderBy('position');
    }
}
