<?php

namespace App\Http\Resources;

use App\Models\TocEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TocEntry
 */
class TocEntryResource extends JsonResource
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
            'parent_id' => $this->parent_id,
            'position' => $this->position,
            'title_ar' => $this->title_ar,
            'title_bn' => $this->title_bn,
            'page_number' => $this->page_number,
            'sentence_id' => $this->sentence_id,
            'children' => TocEntryResource::collection($this->whenLoaded('children')),
        ];
    }
}
