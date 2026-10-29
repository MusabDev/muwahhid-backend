<?php

namespace App\Http\Resources;

use App\Models\DarsSeries;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DarsSeries
 */
class DarsSeriesResource extends JsonResource
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
            'slug' => $this->slug,
            'title_ar' => $this->title_ar,
            'title_bn' => $this->title_bn,
            'title_translit' => $this->title_translit,
            'about_md' => $this->about_md,
            'is_published' => $this->is_published,
            'book_id' => $this->book_id,
            'durus_count' => $this->whenCounted('durus'),
            'total_seconds' => $this->whenHas('total_seconds', fn () => (int) round((float) $this->total_seconds)),
            'media_type' => $this->whenHas('first_media_type'),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'author' => new AuthorResource($this->whenLoaded('author')),
            'book' => new BookResource($this->whenLoaded('book')),
            'durus' => DarsResource::collection($this->whenLoaded('durus')),
        ];
    }
}
