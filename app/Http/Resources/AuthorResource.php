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
            'death_year_ah' => $this->death_year_ah,
            'about_md' => $this->about_md,
            'books_count' => $this->whenCounted('books'),
            'explained_books_count' => $this->whenCounted('explainedBooks'),
            'dars_series_count' => $this->whenCounted('darsSeries'),
            'books' => BookResource::collection($this->whenLoaded('books')),
            'explained_books' => BookResource::collection($this->whenLoaded('explainedBooks')),
            'dars_series' => DarsSeriesResource::collection($this->whenLoaded('darsSeries')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
