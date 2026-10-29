<?php

namespace App\Http\Resources;

use App\Models\DarsSegment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DarsSegment
 */
class DarsSegmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'position' => $this->position,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'text_ar' => $this->text_ar,
            'text_bn' => $this->text_bn,
        ];
    }
}
