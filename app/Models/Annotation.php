<?php

namespace App\Models;

use App\Enums\Lang;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['user_id', 'annotatable_type', 'annotatable_id', 'book_id', 'dars_id', 'lang', 'start_offset', 'end_offset', 'quote', 'color', 'note'])]
class Annotation extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'lang' => Lang::class,
            'start_offset' => 'integer',
            'end_offset' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function annotatable(): MorphTo
    {
        return $this->morphTo();
    }
}
