<?php

namespace App\Models;

use App\Enums\BookStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A dars in the user's library: listening status (same values as books)
 * and where playback stopped.
 */
#[Fillable(['user_id', 'dars_id', 'status', 'position_seconds', 'progress', 'last_played_at'])]
class UserDars extends Model
{
    protected $table = 'user_durus';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BookStatus::class,
            'position_seconds' => 'float',
            'progress' => 'float',
            'last_played_at' => 'datetime',
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
     * @return BelongsTo<Dars, $this>
     */
    public function dars(): BelongsTo
    {
        return $this->belongsTo(Dars::class);
    }
}
