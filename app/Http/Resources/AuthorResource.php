<?php

namespace App\Http\Resources;

use App\Models\Author;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Author
 */
class AuthorResource extends JsonResource
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
            'name_ar' => $this->name_ar,
            'name_bn' => $this->name_bn,
            'name_translit' => $this->name_translit,
            'about_md' => $this->about_md,
            'books_count' => $this->whenCounted('books'),
            'dars_series_count' => $this->whenCounted('darsSeries'),
            'books' => BookResource::collection($this->whenLoaded('books')),
            'dars_series' => DarsSeriesResource::collection($this->whenLoaded('darsSeries')),
        ];
    }
}
