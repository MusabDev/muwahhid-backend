<?php

namespace App\Http\Resources;

use App\Models\Dars;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Dars
 */
class DarsResource extends JsonResource
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
            'series_id' => $this->series_id,
            'number' => $this->number,
            'title_ar' => $this->title_ar,
            'title_bn' => $this->title_bn,
            'media_type' => $this->media_type,
            'media_url' => $this->media_url,
            'duration_seconds' => $this->duration_seconds,
            'subtitle_style' => $this->subtitle_style,
            'subtitle_mode' => $this->subtitle_mode,
            'segments_count' => $this->whenCounted('segments'),
            'series' => new DarsSeriesResource($this->whenLoaded('series')),
            'segments' => DarsSegmentResource::collection($this->whenLoaded('segments')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
