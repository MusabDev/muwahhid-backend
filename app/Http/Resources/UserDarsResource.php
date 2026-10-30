<?php

namespace App\Http\Resources;

use App\Models\UserDars;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin UserDars
 */
class UserDarsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'dars_id' => $this->dars_id,
            'status' => $this->status,
            'position_seconds' => $this->position_seconds,
            'progress' => $this->progress,
            'last_played_at' => $this->last_played_at,
            'updated_at' => $this->updated_at,
            'dars' => new DarsResource($this->whenLoaded('dars')),
        ];
    }
}
